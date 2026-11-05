<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Database-free observation of Property 2 — Preservation.
 *
 * **Validates: Requirements 2.12, 3.1, 3.2, 3.4, 3.7**
 *
 * The test runs only a synthetic Node/DOM harness. It intentionally does not
 * bootstrap Laravel, submit a form, create files, or access a database.
 */
final class CertificationOfOfficersPreservationTest extends TestCase
{
    #[DataProvider('seededManualFlowCases')]
    public function test_seeded_manual_flows_remain_editable_nonblocking_and_navigation_safe(array $case): void
    {
        $result = $this->runPreservationHarness($case);

        self::assertSame($case['expected_fetches'], $result['fetchCalls'], $case['name']);
        self::assertSame($case['expected_outcome'], $result['outcome'], $case['name']);
        self::assertSame($case['manual'], $result['afterRecovery']['manual'], $case['name']);
        self::assertTrue($result['afterRecovery']['editable'], 'Manual Dean and officer destinations must remain enabled.');
        self::assertFalse($result['afterRecovery']['unrelatedCreated'], 'Recovery must not create unrelated destinations.');
        self::assertTrue($result['validationBlockedNavigation'], 'Required-field validation must remain active.');
        self::assertTrue($result['validationLeftManualFieldsEditable'], 'Validation must not lock manual destinations.');
        self::assertTrue($result['navigationAdvanced'], 'A valid manual step must remain navigable.');
        self::assertTrue($result['submissionAvailable'], 'The manual submit action must remain available.');
        self::assertTrue($result['ordinaryUploadAccepted'], 'Existing PDF/JPG/JPEG/PNG upload acceptance must remain available.');
    }

    public static function seededManualFlowCases(): iterable
    {
        mt_srand(32440);
        $seed = (string) mt_rand(100, 999);
        $manual = [
            'dean' => ['first_name' => "Dean{$seed}", 'middle_name' => 'Manual', 'last_name' => 'Supervisor', 'suffix' => 'III'],
            'officer' => ['first_name' => "Officer{$seed}", 'middle_name' => 'Manual', 'last_name' => 'President', 'suffix' => ''],
        ];
        foreach ([
            ['no-certification-file', false, '', 'manual', 0],
            ['ordinary-accepted-jpeg-manual-path', true, 'image/jpeg', 'manual', 0],
            ['unreadable-non-template-pdf-recovery', true, 'application/pdf', 'recovery', 1],
        ] as [$name, $upload, $mimeType, $outcome, $fetches]) {
            yield $name => [[
                'name' => $name, 'upload' => $upload, 'fileName' => "synthetic-{$seed}".($mimeType === 'application/pdf' ? '.pdf' : '.jpg'),
                'mimeType' => $mimeType, 'manual' => $manual, 'validationValue' => "manual-{$seed}",
                'expected_outcome' => $outcome, 'expected_fetches' => $fetches,
            ]];
        }
    }

    private function runPreservationHarness(array $case): array
    {
        $root = dirname(__DIR__, 2);
        $script = $root.DIRECTORY_SEPARATOR.'tests'.DIRECTORY_SEPARATOR.'Support'.DIRECTORY_SEPARATOR.'observe-officers-preservation.js';
        $view = $root.DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'views'.DIRECTORY_SEPARATOR.'auth'.DIRECTORY_SEPARATOR.'apply-org-combined.blade.php';
        $payload = base64_encode(json_encode($case, JSON_THROW_ON_ERROR));
        $process = proc_open(['node', $script, $view, $payload], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root);
        if (! is_resource($process)) {
            self::fail('The preservation DOM/state harness could not start Node.js.');
        }
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        $exitCode = proc_close($process);
        self::assertSame(0, $exitCode, "Preservation DOM/state harness failed:\n$stderr");

        return json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);
    }
}
