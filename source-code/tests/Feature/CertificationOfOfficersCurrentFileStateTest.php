<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

/** **Validates: Requirements 2.7, 2.12, 2.13, 3.1–3.4, 3.7** */
final class CertificationOfOfficersCurrentFileStateTest extends TestCase
{
    public function test_current_file_tokens_ownership_and_manual_edits_are_preserved_in_the_synthetic_dom(): void
    {
        $root = dirname(__DIR__, 2);
        $script = $root.DIRECTORY_SEPARATOR.'tests'.DIRECTORY_SEPARATOR.'Support'.DIRECTORY_SEPARATOR.'current-officers-prefill-state.js';
        $view = $root.DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'views'.DIRECTORY_SEPARATOR.'auth'.DIRECTORY_SEPARATOR.'apply-org-combined.blade.php';
        $process = proc_open(['node', $script, $view], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root);
        if (! is_resource($process)) {
            self::fail('The current-file DOM/state harness could not start Node.js.');
        }
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        self::assertSame(0, proc_close($process), "Current-file DOM/state harness failed:\n$stderr");

        $result = json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('1', $result['requestAToken']);
        self::assertSame('2', $result['requestBToken']);
        foreach ([
            'abortedA', 'staleAWasIgnored', 'deanHydrated', 'deanHydratedWhenResponseLandsOnStepSix',
            'deanDidNotFillIdOrEmail',
            'adviserHydrated', 'adviserDestinationIdsPresent', 'generatedDestinationsStartBlank',
            'requesterValuesRetained', 'requesterHelperValuesRetained',
            'officerHydrated', 'allSupportedOfficersHydrated',
            'didNotFillUnrelated', 'exactGeneratedKeys', 'stepFiveRevisitRetainedDean',
            'stepSixRevisitPreservedManualIntent', 'removalClearedOnlyUnchangedAutomatic',
            'allInputsRemainEditable', 'failedToastShown',
        ] as $assertion) {
            self::assertTrue($result[$assertion], $assertion);
        }
        self::assertSame('manual', $result['statusAfterRemoval']);
    }
}
