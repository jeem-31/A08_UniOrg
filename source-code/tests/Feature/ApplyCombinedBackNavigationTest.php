<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

/** **Validates: "Back to Main Menu" navigation and page-load reset in the combined apply view** */
final class ApplyCombinedBackNavigationTest extends TestCase
{
    public function test_back_to_main_menu_restores_landing_state_without_undefined_function(): void
    {
        $root = dirname(__DIR__, 2);
        $script = $root.DIRECTORY_SEPARATOR.'tests'.DIRECTORY_SEPARATOR.'Support'.DIRECTORY_SEPARATOR.'choose-back-navigation.js';
        $view = $root.DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'views'.DIRECTORY_SEPARATOR.'auth'.DIRECTORY_SEPARATOR.'apply-org-combined.blade.php';
        $process = proc_open(['node', $script, $view], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root);
        if (! is_resource($process)) {
            self::fail('The choose-back-navigation DOM harness could not start Node.js.');
        }
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        self::assertSame(0, proc_close($process), "Choose-back-navigation harness failed:\n$stderr");

        $result = json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);
        foreach (array_keys($result) as $assertion) {
            self::assertTrue($result[$assertion], $assertion);
        }
    }
}
