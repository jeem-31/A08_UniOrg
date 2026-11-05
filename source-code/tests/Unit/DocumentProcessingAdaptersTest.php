<?php

namespace Tests\Unit;

use App\Services\DocumentIntelligence\Contracts\DocumentIntelligenceProvider;
use App\Services\DocumentIntelligence\Contracts\SourceDocumentReader;
use App\Services\DocumentIntelligence\Contracts\SourceTextExtractor;
use App\Services\DocumentIntelligence\Data\ExtractedDocumentText;
use App\Services\DocumentIntelligence\Exceptions\PermanentDocumentException;
use App\Services\DocumentIntelligence\Exceptions\TransientProcessingException;
use App\Services\DocumentIntelligence\GoogleCloudSourceTextExtractor;
use App\Services\DocumentIntelligence\GoogleDocumentIntelligenceProvider;
use App\Services\DocumentIntelligence\LocalTemporaryDocumentSource;
use App\Services\DocumentIntelligence\Outcomes\PermanentDocumentFailureReason;
use App\Services\DocumentIntelligence\Outcomes\TransientProcessingFailureReason;
use App\Services\DocumentIntelligence\StorageSourceDocumentReader;
use App\Services\DocumentIntelligence\StoredDocumentDescriptor;
use App\Services\GeminiService;
use App\Services\GoogleCloudNLPService;
use App\Services\GoogleCloudVisionService;
use App\Services\GroqService;
use App\Services\OcrService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

/** **Validates: Requirements 3.1–3.4, 5.1, 5.3, 8.1, 8.2** */
final class DocumentProcessingAdaptersTest extends TestCase
{
    private string $diskRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->diskRoot = sys_get_temp_dir().DIRECTORY_SEPARATOR.'document-adapter-'.bin2hex(random_bytes(6));
        mkdir($this->diskRoot, 0700, true);
        config()->set('filesystems.disks.adapter-test', [
            'driver' => 'local',
            'root' => $this->diskRoot,
            'throw' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Storage::forgetDisk('adapter-test');
        $this->removeDirectory($this->diskRoot);
        parent::tearDown();
    }

    public function test_storage_reader_uses_a_disposable_copy_without_changing_source(): void
    {
        Storage::disk('adapter-test')->put('documents/source.pdf', 'unchanged-source-bytes');
        $reader = new StorageSourceDocumentReader;
        $source = $reader->open($this->descriptor('source.pdf', 'documents/source.pdf'));

        self::assertInstanceOf(LocalTemporaryDocumentSource::class, $source);
        self::assertNotSame($this->diskRoot.DIRECTORY_SEPARATOR.'documents'.DIRECTORY_SEPARATOR.'source.pdf', $source->localPath());
        self::assertSame('unchanged-source-bytes', stream_get_contents($stream = $source->readStream()));
        fclose($stream);

        $temporaryPath = $source->localPath();
        $source->cleanup();

        self::assertFileDoesNotExist($temporaryPath);
        self::assertSame('unchanged-source-bytes', Storage::disk('adapter-test')->get('documents/source.pdf'));
    }

    public function test_storage_reader_returns_permanent_source_failures(): void
    {
        $reader = new StorageSourceDocumentReader;

        try {
            $reader->open($this->descriptor('missing.pdf', 'documents/missing.pdf'));
            self::fail('A missing source must fail.');
        } catch (PermanentDocumentException $failure) {
            self::assertSame(PermanentDocumentFailureReason::Missing, $failure->reason);
        }

        $this->expectException(PermanentDocumentException::class);
        $reader->open($this->descriptor('unsupported.exe', 'documents/unsupported.exe'));
    }

    public function test_docx_extractor_returns_source_text_without_mutating_copy(): void
    {
        $path = $this->diskRoot.DIRECTORY_SEPARATOR.'source.docx';
        $zip = new ZipArchive;
        self::assertTrue($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE));
        $zip->addFromString('word/document.xml', '<document><p>Organization purpose and material content</p></document>');
        $zip->close();

        $source = new LocalTemporaryDocumentSource($this->descriptor('source.docx', 'source.docx'), $path);
        $extractor = new GoogleCloudSourceTextExtractor($this->disabledVision(), new OcrService);
        $result = $extractor->extract($source);

        self::assertStringContainsString('Organization purpose and material content', $result->text);
        self::assertSame('application/vnd.openxmlformats-officedocument.wordprocessingml.document', $result->mimeType);
        self::assertSame('local_document_extraction', $result->extractionMethod);
        self::assertFileExists($path);
    }

    public function test_intelligence_adapter_maps_provider_and_nlp_output(): void
    {
        $provider = new GoogleDocumentIntelligenceProvider(
            $this->groqReturning([
                'document_type' => 'Plan of Activities',
                'organization_name' => 'Computing Society',
                'school_year' => '2025-2026',
                'key_dates' => ['2025-09-01'],
                'summary' => 'The organization proposes its annual activities.',
                'key_information' => ['amount' => '1000'],
                'tags' => ['Plan of Activities', 'Annual Program'],
            ]),
            $this->nlpReturningOrganizations(),
        );

        $candidate = $provider->analyze(new ExtractedDocumentText('source-derived text'));

        self::assertSame('plan_of_activities', $candidate->documentType);
        self::assertSame('The organization proposes its annual activities.', $candidate->summary);
        self::assertSame(['Plan of Activities', 'Annual Program'], $candidate->tags);
        self::assertSame('Computing Society', $candidate->context['organization']);
        self::assertSame(['CCS Council'], $candidate->context['entities']['organizations']);
    }

    public function test_intelligence_adapter_classifies_rate_limits_as_transient(): void
    {
        $groq = new class extends GroqService
        {
            public function isConfigured()
            {
                return true;
            }

            public function summarizeDocument($text, $documentType = null)
            {
                throw new RuntimeException('HTTP 429 rate limit');
            }
        };

        $provider = new GoogleDocumentIntelligenceProvider($groq, $this->disabledNlp());

        try {
            $provider->analyze(new ExtractedDocumentText('readable source text'));
            self::fail('A rate limit must be surfaced as transient.');
        } catch (TransientProcessingException $failure) {
            self::assertSame(TransientProcessingFailureReason::RateLimited, $failure->reason);
        }
    }

    public function test_gemini_makes_one_request_and_never_uses_raw_response_as_summary(): void
    {
        putenv('GOOGLE_GEMINI_API_KEY=adapter-test-key');
        Http::fakeSequence()
            ->push(['error' => ['message' => 'busy']], 429)
            ->push(['candidates' => [['content' => ['parts' => [['text' => 'not valid json']]]]]], 200);

        try {
            (new GeminiService)->generate('prompt');
            self::fail('The failed provider request must not be retried internally.');
        } catch (RuntimeException) {
            Http::assertSentCount(1);
        }

        $this->expectException(\UnexpectedValueException::class);
        (new GeminiService)->summarizeDocument('source text');
    }

    public function test_contracts_resolve_to_concrete_adapters(): void
    {
        self::assertInstanceOf(StorageSourceDocumentReader::class, app(SourceDocumentReader::class));
        self::assertInstanceOf(GoogleCloudSourceTextExtractor::class, app(SourceTextExtractor::class));
        self::assertInstanceOf(GoogleDocumentIntelligenceProvider::class, app(DocumentIntelligenceProvider::class));
    }

    private function descriptor(string $name, string $path): StoredDocumentDescriptor
    {
        return new StoredDocumentDescriptor(1, 'application_form', 'primary', 'Application Form', $name, 'adapter-test', $path, 'version', hash('sha256', $path));
    }

    private function disabledVision(): GoogleCloudVisionService
    {
        return new class extends GoogleCloudVisionService
        {
            public function __construct() {}

            public function isEnabled(): bool
            {
                return false;
            }
        };
    }

    private function groqReturning(array $analysis): GroqService
    {
        return new class($analysis) extends GroqService
        {
            public function __construct(private readonly array $analysis) {}

            public function isConfigured()
            {
                return true;
            }

            public function summarizeDocument($text, $documentType = null)
            {
                return $this->analysis;
            }
        };
    }

    private function disabledNlp(): GoogleCloudNLPService
    {
        return new class extends GoogleCloudNLPService
        {
            public function __construct() {}

            public function isEnabled(): bool
            {
                return false;
            }
        };
    }

    private function nlpReturningOrganizations(): GoogleCloudNLPService
    {
        return new class extends GoogleCloudNLPService
        {
            public function __construct() {}

            public function isEnabled(): bool
            {
                return true;
            }

            public function extractEntities(string $text): array
            {
                return ['success' => true, 'entities' => [[
                    'name' => 'CCS Council',
                    'type' => 3,
                    'salience' => 0.9,
                ]]];
            }
        };
    }

    private function removeDirectory(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $entry) {
            $child = $path.DIRECTORY_SEPARATOR.$entry;
            is_dir($child) ? $this->removeDirectory($child) : @unlink($child);
        }

        @rmdir($path);
    }
}
