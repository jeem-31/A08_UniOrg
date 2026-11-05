<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class CertificationOfOfficersBugConditionExplorationTest extends TestCase
{
    /**
     * Property 1 — Bug Condition: deterministic fixed-layout PDF extraction.
     *
     * Validates: Requirements 1.1–1.13, 2.1, 2.3–2.10, 2.13
     *
     * This deliberately asserts the post-fix contract against the unfixed path.
     * All names and PDF bytes below are synthetic, seeded, and non-personal.
     */
    public function test_seeded_synthetic_rev_1_pdf_requires_the_direct_complete_keyed_endpoint_contract(): void
    {
        $case = $this->seededCase();
        $response = $this->withoutGeminiCredentials(fn () => $this->post(
            route('apply-org.prefill-from-officers'),
            [
                'request_token' => $case['expectedEnvelope']['request_token'],
                'certification_of_officers' => UploadedFile::fake()->createWithContent('synthetic-rev-1.pdf', $case['pdf']),
            ]
        ));

        $actual = $response->json();
        unset($actual['debug_file_info']);
        self::assertSame(
            $case['expectedEnvelope'],
            $actual,
            'COUNTEREXAMPLE endpoint: a valid synthetic nine-page Rev. 1 PDF must bypass Gemini and return its complete, ordered, slot-keyed local result.'
        );
    }

    /**
     * Property 1/4 — execute only the current browser prefill helper in a tiny DOM.
     * The original exploration assertion remains unchanged and requires keyed data,
     * year/section, and current-file-safe automatic application.
     */
    public function test_seeded_browser_helper_timeline_requires_complete_keyed_current_file_safe_application(): void
    {
        $case = $this->seededCase();
        $actual = $this->runLegacyHelperExplorer($case['expectedEnvelope']['data']);

        self::assertSame(
            $case['expectedBrowserResult'],
            $actual,
            'COUNTEREXAMPLE browser helper: keyed officers, year/section, PDF A → B/removal, and manual edits must remain current-file-safe.'
        );
    }

    /**
     * Property 1/5 — an unsupported role on the Certification (e.g. an
     * "ASSISTANT SECRETARY" that is not part of the default list) must be
     * captured as a custom_1 slot with its editable position_name, never
     * silently dropped.
     */
    public function test_seeded_synthetic_pdf_with_unknown_role_captures_custom_slot(): void
    {
        $source = 'LIMA, MAYA K.';
        $role = 'ASSISTANT SECRETARY';
        [$lastName, $given] = array_map('trim', explode(',', $source, 2));
        $parts = preg_split('/\s+/', $given);
        $expectedOfficers = [
            'custom_1' => [
                'first_name' => \App\Helpers\NameParser::toTitleCase($parts[0]),
                'middle_name' => $parts[1],
                'last_name' => \App\Helpers\NameParser::toTitleCase($lastName),
                'suffix' => '',
                'year_level' => '2',
                'section' => 'A',
                'course' => 'Bachelor of Synthetic Studies',
                'position_name' => 'Assistant Secretary',
            ],
        ];

        $expectedEnvelope = [
            'success' => true,
            'outcome' => 'partial',
            'request_token' => 'seed-32440-custom-a',
            'data' => [
                'dean' => ['first_name' => 'Dana', 'middle_name' => 'L.', 'last_name' => 'Faculty', 'suffix' => 'EdD'],
                'senior_adviser' => ['first_name' => 'Arlo', 'middle_name' => 'P.', 'last_name' => 'Guide', 'suffix' => 'MA'],
                'junior_adviser' => ['first_name' => 'Beth', 'middle_name' => 'Q.', 'last_name' => 'Mentor', 'suffix' => 'PhD'],
                'officers' => $expectedOfficers,
                'college' => 'College of Imaginary Arts',
            ],
            'data_source' => 'fixed',
            'warnings' => ['Certification page 1 captured "ASSISTANT SECRETARY" as an additional position; review it before submitting.'],
        ];

        $response = $this->withoutGeminiCredentials(fn () => $this->post(
            route('apply-org.prefill-from-officers'),
            [
                'request_token' => $expectedEnvelope['request_token'],
                'certification_of_officers' => UploadedFile::fake()->createWithContent(
                    'synthetic-custom.pdf',
                    $this->syntheticPdf([
                        ['position' => 'custom_1', 'source' => $source, 'year_section' => '2A'],
                    ], $role),
                ),
            ]
        ));

        $actual = $response->json();
        unset($actual['debug_file_info']);
        self::assertSame($expectedEnvelope, $actual);
    }

    private function seededCase(): array
    {
        mt_srand(32440);
        $officers = [
            ['position' => 'president', 'source' => 'NORTH, ALICE M.', 'year_section' => '2A'],
            ['position' => 'secretary', 'source' => 'SOUTH, BEN R.', 'year_section' => '3B'],
            ['position' => 'business_manager', 'source' => 'WEST, DEV T.', 'year_section' => '1D'],
            ['position' => 'business_manager_2', 'source' => 'NOVA, ELI U.', 'year_section' => '2E'],
            ['position' => 'pio', 'source' => 'ORBIT, FAY V.', 'year_section' => '3F'],
            ['position' => 'pio_2', 'source' => 'QUARK, GUS W.', 'year_section' => '4G'],
            ['position' => 'event_coordinator', 'source' => 'PULSE, HAL X.', 'year_section' => '1H'],
            ['position' => 'event_coordinator_2', 'source' => 'VECTOR, IVA Y.', 'year_section' => '2I'],
        ];
        $customPositionNames = [
            'business_manager_2' => 'Business Manager',
            'pio_2' => 'Public Information Officer',
            'event_coordinator_2' => 'Event Coordinator',
        ];
        $expectedOfficers = [];
        $customCount = 0;
        foreach ($officers as $officer) {
            $key = isset($customPositionNames[$officer['position']])
                ? 'custom_'.(++$customCount)
                : $officer['position'];
            [$lastName, $given] = array_map('trim', explode(',', $officer['source'], 2));
            $parts = preg_split('/\s+/', $given);
            preg_match('/^(\d+)([A-Za-z]+)$/', $officer['year_section'], $yearSection);
            $expectedOfficers[$key] = [
                'first_name' => \App\Helpers\NameParser::toTitleCase($parts[0]),
                'middle_name' => $parts[1],
                'last_name' => \App\Helpers\NameParser::toTitleCase($lastName),
                'suffix' => '', 'year_level' => $yearSection[1], 'section' => $yearSection[2],
                'course' => 'Bachelor of Synthetic Studies',
            ];
            if (isset($customPositionNames[$officer['position']])) {
                $expectedOfficers[$key]['position_name'] = $customPositionNames[$officer['position']];
            }
        }

        $data = [
            'dean' => ['first_name' => 'Dana', 'middle_name' => 'L.', 'last_name' => 'Faculty', 'suffix' => 'EdD'],
            'senior_adviser' => ['first_name' => 'Arlo', 'middle_name' => 'P.', 'last_name' => 'Guide', 'suffix' => 'MA'],
            'junior_adviser' => ['first_name' => 'Beth', 'middle_name' => 'Q.', 'last_name' => 'Mentor', 'suffix' => 'PhD'],
            'officers' => $expectedOfficers,
            'college' => 'College of Imaginary Arts',
        ];
        $envelope = [
            'success' => true,
            'outcome' => 'partial',
            'request_token' => 'seed-32440-current-b',
            'data' => $data,
            'data_source' => 'fixed',
            'warnings' => [
                'Certification page 4 captured "BUSINESS MANAGER" as an additional position; review it before submitting.',
                'Certification page 6 captured "PUBLIC INFORMATION OFFICER" as an additional position; review it before submitting.',
                'Certification page 8 captured "EVENT COORDINATOR" as an additional position; review it before submitting.',
            ],
        ];

        return [
            'pdf' => $this->syntheticPdf($officers),
            'expectedEnvelope' => $envelope,
            'expectedBrowserResult' => [
                'keyedError' => null,
                // The endpoint envelope keeps the raw year_level; the browser
                // displays the formatted ordinal label ("2nd Year"). Course
                // is excluded: the legacy helper only reads the 6 fields below.
                'nameOnly' => array_merge(
                    array_diff_key($expectedOfficers['president'], ['course' => true]),
                    ['year_level' => $this->ordinalYearLabel((int) $expectedOfficers['president']['year_level'])]
                ),
                'timeline' => ['fetchCalls' => 1, 'cachedFirstName' => 'BETA', 'afterRemovalCache' => null, 'manualFirstName' => 'MANUAL FIRST'],
            ],
        ];
    }

    private function ordinalYearLabel(int $year): string
    {
        $suffix = match (true) {
            $year % 100 >= 11 && $year % 100 <= 13 => 'th',
            $year % 10 === 1 => 'st',
            $year % 10 === 2 => 'nd',
            $year % 10 === 3 => 'rd',
            default => 'th',
        };

        return "{$year}{$suffix} Year";
    }

    private function syntheticPdf(array $officers, ?string $roleOverride = null): string
    {
        $objects = [1 => '<< /Type /Catalog /Pages 2 0 R >>'];
        $pageIds = [];
        $fontId = 3 + (count($officers) * 2);
        foreach ($officers as $index => $officer) {
            $pageId = 3 + ($index * 2);
            $contentId = $pageId + 1;
            $pageIds[] = "$pageId 0 R";
            $stream = $this->pdfTextStream($this->fixedLayoutPageLines($officer, $roleOverride));
            $objects[$pageId] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 $fontId 0 R >> >> /Contents $contentId 0 R >>";
            $objects[$contentId] = '<< /Length '.strlen($stream)." >>\nstream\n$stream\nendstream";
        }
        $objects[2] = '<< /Type /Pages /Kids ['.implode(' ', $pageIds).'] /Count '.count($pageIds).' >>';
        $objects[$fontId] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
        ksort($objects);

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        foreach ($objects as $id => $object) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "$id 0 obj\n$object\nendobj\n";
        }
        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 ".(max(array_keys($objects)) + 1)."\n0000000000 65535 f \n";
        for ($id = 1; $id <= max(array_keys($objects)); $id++) {
            $pdf .= sprintf('%010d 00000 n ', $offsets[$id] ?? 0)."\n";
        }

        return $pdf."trailer\n<< /Size ".(max(array_keys($objects)) + 1)." /Root 1 0 R >>\nstartxref\n$xrefOffset\n%%EOF\n";
    }

    private function fixedLayoutPageLines(array $officer, ?string $roleOverride = null): array
    {
        $role = $roleOverride ?? [
            'president' => 'PRESIDENT', 'secretary' => 'SECRETARY',
            'business_manager' => 'BUSINESS MANAGER', 'business_manager_2' => 'BUSINESS MANAGER',
            'pio' => 'PUBLIC INFORMATION OFFICER', 'pio_2' => 'PUBLIC INFORMATION OFFICER',
            'event_coordinator' => 'EVENT COORDINATOR', 'event_coordinator_2' => 'EVENT COORDINATOR',
        ][$officer['position']];

        return [
            'CERTIFICATION',
            "This certifies that {$officer['source']}, a student taking up Bachelor of Synthetic Studies, {$officer['year_section']} from the College of Imaginary Arts and the elected/appointed $role of the Fictional Orbit Society.",
            'ARLO P. GUIDE, MA', 'Organization Adviser', 'BETH Q. MENTOR, PhD', 'Organization Adviser',
            'DANA L. FACULTY, EdD', 'Dean/Assoc. Dean, College of Imaginary Arts', 'Noted:',
            'IGNORED NOTED-BY PERSON', 'Director/Chairperson', 'LSPU-OSAS-SF-006', 'Rev. 1',
        ];
    }

    private function pdfTextStream(array $lines): string
    {
        $encoded = array_map(fn (string $line) => str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $line), $lines);

        return "BT\n/F1 10 Tf\n14 TL\n50 740 Td\n(".implode(") Tj\nT*\n(", $encoded).") Tj\nET";
    }

    private function runLegacyHelperExplorer(array $data): array
    {
        $script = base_path('tests/Support/explore-legacy-officers-prefill.js');
        $view = resource_path('views/auth/apply-org-combined.blade.php');
        $payload = base64_encode(json_encode($data, JSON_THROW_ON_ERROR));
        $process = proc_open(['node', $script, $view, $payload], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path());
        if (! is_resource($process)) {
            self::fail('The isolated browser-helper exploration could not start Node.js.');
        }
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        $exitCode = proc_close($process);
        self::assertSame(0, $exitCode, "Browser-helper explorer failed:\n$stderr");

        return json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);
    }

    private function withoutGeminiCredentials(callable $callback)
    {
        $key = 'GOOGLE_GEMINI_API_KEY';
        $originalEnvironment = getenv($key);
        $originalEnvValue = $_ENV[$key] ?? null;
        $originalServerValue = $_SERVER[$key] ?? null;
        putenv("$key=");
        unset($_ENV[$key], $_SERVER[$key]);
        try {
            return $callback();
        } finally {
            $originalEnvironment === false ? putenv($key) : putenv("$key=$originalEnvironment");
            if ($originalEnvValue === null) {
                unset($_ENV[$key]);
            } else {
                $_ENV[$key] = $originalEnvValue;
            }
            if ($originalServerValue === null) {
                unset($_SERVER[$key]);
            } else {
                $_SERVER[$key] = $originalServerValue;
            }
        }
    }
}
