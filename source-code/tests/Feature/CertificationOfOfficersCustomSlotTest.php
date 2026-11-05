<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

/** **Validates: Requirements 2.7, 2.12, 3.1–3.4, 3.7 — Other Position cards** */
final class CertificationOfOfficersCustomSlotTest extends TestCase
{
    public function test_unknown_officer_keys_create_prefilled_custom_cards_after_the_defaults(): void
    {
        $root = dirname(__DIR__, 2);
        $script = $root.DIRECTORY_SEPARATOR.'tests'.DIRECTORY_SEPARATOR.'Support'.DIRECTORY_SEPARATOR.'custom-officer-prefill.js';
        $view = $root.DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'views'.DIRECTORY_SEPARATOR.'auth'.DIRECTORY_SEPARATOR.'apply-org-combined.blade.php';
        $process = proc_open(['node', $script, $view], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root);
        if (! is_resource($process)) {
            self::fail('The custom-officer DOM/state harness could not start Node.js.');
        }
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        self::assertSame(0, proc_close($process), "Custom-officer harness failed:\n$stderr");

        $result = json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);
        foreach ([
            'customCardCreated', 'secondCustomCardCreated', 'dividerCreated', 'addButtonCreated',
            'registeredInState', 'positionNameInputRemoved', 'positionNameCarriedHidden',
            'customNamesApplied', 'secondCardNamesApplied', 'aiKeyLabelDerived',
            'defaultsStillHydrated', 'deanStillHydrated',
            'customTracked', 'customValuesPrefillable',
            'departmentEnabledWhenGlobalEmpty', 'relinkedWithoutRebuild', 'departmentLinkedOnCustomCard',
            'yearLevelFormatted', 'defaultYearLevelFormatted', 'yearLevelFormatIdempotent',
            'collegeMatchedDepartment', 'unmatchedCollegeStaysBlank',
        ] as $assertion) {
            self::assertTrue($result[$assertion], $assertion);
        }
    }
}
