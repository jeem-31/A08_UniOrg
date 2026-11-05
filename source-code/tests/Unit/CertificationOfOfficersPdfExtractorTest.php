<?php

namespace Tests\Unit;

use App\Services\CertificationOfOfficersPdfExtractor;
use PHPUnit\Framework\TestCase;
use Tests\Support\CertificationOfOfficersFixtureBuilder;

/** **Validates: Requirements 2.1, 2.3, 2.12, 2.14** */
final class CertificationOfOfficersPdfExtractorTest extends TestCase
{
    public function test_reads_selectable_text_for_every_physical_page_in_document_order(): void
    {
        $fixture = CertificationOfOfficersFixtureBuilder::validMultiPageCase();
        $pages = $this->extractTemporaryFixture($fixture['pdf']);

        self::assertSame(array_keys($fixture['pages']), array_column($pages, 'index'));
        self::assertCount(count($fixture['pages']), $pages);

        foreach ($fixture['pages'] as $index => $expectedPage) {
            self::assertStringContainsString(
                'elected/appointed '.$expectedPage['text_values']['position'].' of',
                $pages[$index]['text'],
            );
            self::assertSame([], $pages[$index]['form_fields']);
            self::assertSame([], $pages[$index]['preferred_values']);
        }
    }

    public function test_recognized_non_empty_acroform_values_are_page_local_and_preferred_over_text(): void
    {
        $fixture = CertificationOfOfficersFixtureBuilder::formFieldPrecedenceCase();
        $pages = $this->extractTemporaryFixture($fixture['pdf']);

        self::assertCount(1, $pages);
        self::assertStringContainsString('This certifies that TEXT, TAY Z.,', $pages[0]['text']);
        self::assertSame($fixture['expectedPreferredValues'], $pages[0]['form_fields']);
        self::assertSame($fixture['expectedPreferredValues'], $pages[0]['preferred_values']);
    }

    public function test_reader_has_no_external_rendering_or_ai_service_dependency(): void
    {
        $fixture = CertificationOfOfficersFixtureBuilder::validMultiPageCase();
        self::assertCount(count($fixture['pages']), $this->extractTemporaryFixture($fixture['pdf']));

        $source = file_get_contents(dirname(__DIR__, 2).'/app/Services/CertificationOfOfficersPdfExtractor.php');
        self::assertIsString($source);
        self::assertStringContainsString('Smalot\\PdfParser\\Parser', $source);
        self::assertDoesNotMatchRegularExpression(
            '/\\b(?:GeminiService|GoogleCloudVisionService|Tesseract|DocumentProcessorController|PdfToImage|Spatie)\\b/i',
            $source,
        );
    }

    /** @return list<array<string, mixed>> */
    private function extractTemporaryFixture(string $pdf): array
    {
        $path = tempnam(sys_get_temp_dir(), 'fictional-certification-');
        self::assertNotFalse($path);
        file_put_contents($path, $pdf);

        try {
            return (new CertificationOfOfficersPdfExtractor)->extract($path);
        } finally {
            @unlink($path);
        }
    }
}
