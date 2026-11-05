<?php

namespace Tests\Feature;

use App\Services\GroqService;
use App\Services\GoogleCloudVisionService;
use Illuminate\Http\UploadedFile;
use Tests\Support\CertificationOfOfficersFixtureBuilder;
use Tests\TestCase;

/** **Validates: Requirements 2.1â€“2.3, 2.7, 2.9â€“2.14, 3.4, 3.7** */
final class CertificationOfOfficersPrefillEndpointTest extends TestCase
{
    public function test_valid_fictional_pdf_returns_the_exact_keyed_local_contract_when_ai_services_are_unavailable(): void
    {
        $fixture = CertificationOfOfficersFixtureBuilder::validMultiPageCase();
        foreach ([
            GroqService::class,
            GoogleCloudVisionService::class,
        ] as $service) {
            $this->app->bind($service, static fn (): never => throw new \LogicException("$service must be recoverable when unavailable: the prefill endpoint must keep working."));
        }

        $response = $this->post(route('apply-org.prefill-from-officers'), [
            'request_token' => $fixture['expectedEnvelope']['request_token'],
            'certification_of_officers' => UploadedFile::fake()->createWithContent('fictional-rev-1.pdf', $fixture['pdf']),
        ]);

        $envelope = $fixture['expectedEnvelope'];
        $expected = [
            'success' => $envelope['success'],
            'outcome' => $envelope['outcome'],
            'request_token' => $envelope['request_token'],
            'data' => [
                'dean' => $envelope['data']['dean'],
                'senior_adviser' => $envelope['data']['senior_adviser'],
                'junior_adviser' => $envelope['data']['junior_adviser'],
                'officers' => $envelope['data']['officers'],
                'college' => $envelope['data']['college'],
            ],
            'data_source' => 'fixed',
            'warnings' => [],
        ];

        $response->assertOk();
        $actual = $response->json();
        unset($actual['debug_file_info']);
        self::assertSame($expected, $actual);
    }

    public function test_fictional_malformed_and_non_template_pdfs_return_safe_recovery_envelopes(): void
    {
        foreach (CertificationOfOfficersFixtureBuilder::recoveryCases() as $name => $fixture) {
            $response = $this->post(route('apply-org.prefill-from-officers'), [
                'request_token' => "recovery-$name",
                'certification_of_officers' => UploadedFile::fake()->createWithContent("$name.pdf", $fixture['pdf']),
            ]);

            $response->assertOk();
            self::assertSame(true, $response->json('success'));
            self::assertSame($fixture['expectedOutcome'], $response->json('outcome'));
            self::assertSame("recovery-$name", $response->json('request_token'));
            self::assertSame(
                ['dean', 'senior_adviser', 'junior_adviser', 'officers', 'college'],
                array_keys($response->json('data')),
            );
            self::assertStringNotContainsString('Gemini', implode(' ', $response->json('warnings')));
            self::assertSame($fixture['expectedDataSource'], $response->json('data_source'));
        }
    }

    public function test_gemini_merge_fills_slots_the_fixed_parser_missed_and_reports_merged_source(): void
    {
        $fixture = CertificationOfOfficersFixtureBuilder::aiMergeCase();
        $this->bindFakeGroq(json_encode([
            'dean' => null,
            'senior_adviser' => null,
            'junior_adviser' => null,
            'officers' => ['treasurer' => $fixture['expectedAiOfficer']],
        ]));

        $response = $this->post(route('apply-org.prefill-from-officers'), [
            'request_token' => 'merge-current',
            'certification_of_officers' => UploadedFile::fake()->createWithContent('freeform-merge.pdf', $fixture['pdf']),
        ]);

        $response->assertOk();
        self::assertSame('merged', $response->json('data_source'));
        self::assertSame('completed', $response->json('outcome'));
        self::assertSame($fixture['expectedAiOfficer'], $response->json('data.officers.treasurer'));
        self::assertSame('Dela Aster', $response->json('data.officers.president.last_name'));
    }

    public function test_gemini_merge_rejects_hallucinated_names_and_keeps_the_deterministic_source(): void
    {
        $fixture = CertificationOfOfficersFixtureBuilder::aiMergeCase();
        $this->bindFakeGroq(json_encode([
            'dean' => null,
            'senior_adviser' => null,
            'junior_adviser' => null,
            'officers' => [
                'treasurer' => ['first_name' => 'MADE', 'middle_name' => 'UP', 'last_name' => 'NOSUCH', 'suffix' => '', 'year_level' => '1', 'section' => 'E'],
            ],
        ]));

        $response = $this->post(route('apply-org.prefill-from-officers'), [
            'request_token' => 'hallucination-current',
            'certification_of_officers' => UploadedFile::fake()->createWithContent('freeform-hallucination.pdf', $fixture['pdf']),
        ]);

        $response->assertOk();
        self::assertSame('fixed', $response->json('data_source'));
        self::assertSame($fixture['expectedOutcome'], $response->json('outcome'));
        self::assertNull($response->json('data.officers.treasurer'));
        self::assertSame('Dela Aster', $response->json('data.officers.president.last_name'));
    }

    public function test_gemini_ai_only_recovery_when_the_fixed_parser_finds_nothing(): void
    {
        $fixture = CertificationOfOfficersFixtureBuilder::aiOnlyCase();
        $this->bindFakeGroq(json_encode([
            'dean' => null,
            'senior_adviser' => null,
            'junior_adviser' => null,
            'officers' => ['treasurer' => $fixture['expectedAiOfficer']],
        ]));

        $response = $this->post(route('apply-org.prefill-from-officers'), [
            'request_token' => 'ai-only-current',
            'certification_of_officers' => UploadedFile::fake()->createWithContent('freeform-ai-only.pdf', $fixture['pdf']),
        ]);

        $response->assertOk();
        self::assertSame('ai', $response->json('data_source'));
        self::assertSame('completed', $response->json('outcome'));
        self::assertSame($fixture['expectedAiOfficer'], $response->json('data.officers.treasurer'));
    }

    public function test_complete_deterministic_result_skips_the_gemini_call(): void
    {
        $fixture = CertificationOfOfficersFixtureBuilder::validMultiPageCase();
        $fake = $this->bindFakeGroq(json_encode([
            'dean' => ['first_name' => 'Hallucinated', 'middle_name' => '', 'last_name' => 'Dean', 'suffix' => ''],
            'senior_adviser' => null,
            'junior_adviser' => null,
            'officers' => [],
        ]));

        $response = $this->post(route('apply-org.prefill-from-officers'), [
            'request_token' => 'skip-ai-current',
            'certification_of_officers' => UploadedFile::fake()->createWithContent('complete.pdf', $fixture['pdf']),
        ]);

        $response->assertOk();
        self::assertSame(0, $fake->callCount, 'A complete deterministic result must skip the Gemini call entirely.');
        self::assertSame('fixed', $response->json('data_source'));
        self::assertSame('completed', $response->json('outcome'));
    }

    public function test_non_certification_document_with_position_words_is_rejected(): void
    {
        // A Plan of Activities mentions coordinators/presidents but carries
        // none of the LSPU-OSAS-SF-006 Rev. 1 anchors. The loose text fallback
        // must not fabricate officers from it.
        $pdf = self::textPdf([
            'PLAN OF ACTIVITIES',
            'First Semester 2026-2027',
            'Event Coordinator: Juan Cruz',
            'President: Maria Santos',
            'Monthly meeting of all organization officers.',
        ]);

        $response = $this->post(route('apply-org.prefill-from-officers'), [
            'request_token' => 'wrong-template-current',
            'certification_of_officers' => UploadedFile::fake()->createWithContent('plan-of-activities.pdf', $pdf),
        ]);

        $response->assertOk();
        self::assertTrue($response->json('success'));
        self::assertSame('manual_required', $response->json('outcome'));
        self::assertSame([], $response->json('data.officers'));
        self::assertSame('none', $response->json('data_source'));
        self::assertStringContainsString(
            'template',
            strtolower(implode(' ', $response->json('warnings') ?? []))
        );
    }

    /**
     * Minimal single-page text PDF (one BT/ET block per line) for fixtures.
     *
     * @param list<string> $lines
     */
    private static function textPdf(array $lines): string
    {
        $content = '';
        $y = 700;
        foreach ($lines as $line) {
            $escaped = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $line);
            $content .= "BT /F1 12 Tf 50 {$y} Td ({$escaped}) Tj ET\n";
            $y -= 20;
        }

        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            3 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
            5 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            4 => '<< /Length '.strlen($content).' >> stream'."\n".$content.'endstream',
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $num => $body) {
            $offsets[$num] = strlen($pdf);
            $pdf .= "{$num} 0 obj {$body} endobj\n";
        }
        $xrefPos = strlen($pdf);
        $count = max(array_keys($objects)) + 1;
        $pdf .= "xref\n0 {$count}\n0000000000 65535 f \n";
        for ($i = 1; $i < $count; $i++) {
            $pdf .= sprintf('%010d 00000 n ', $offsets[$i] ?? 0)."\n";
        }
        $pdf .= "trailer\n<< /Size {$count} /Root 1 0 R >>\nstartxref\n{$xrefPos}\n%%EOF";

        return $pdf;
    }

    private function bindFakeGroq(string $jsonResponse): GroqService
    {
        $fake = new class($jsonResponse) extends GroqService
        {
            public string $json;
            public int $callCount = 0;

            public function __construct(string $json)
            {
                $this->json = $json;
            }

            public function isConfigured(): bool
            {
                return true;
            }

            public function generate($prompt, $maxTokens = 1024, $jsonMode = false): string
            {
                $this->callCount++;

                return $this->json;
            }
        };

        $this->app->instance(GroqService::class, $fake);

        return $fake;
    }
}
