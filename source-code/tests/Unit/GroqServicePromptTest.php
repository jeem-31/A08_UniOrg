<?php

namespace Tests\Unit;

use App\Services\GroqService;
use Tests\TestCase;

final class GroqServicePromptTest extends TestCase
{
    public function test_service_class_loads_without_parse_errors(): void
    {
        // Guards the tag-prompt block: it lives inside a double-quoted PHP
        // string, so unescaped quotes break the whole class at autoload time
        // (only visible when a queue worker resolves the provider chain).
        self::assertTrue(class_exists(GroqService::class));
    }

    public function test_tag_prompt_carries_quality_conventions(): void
    {
        $source = (string) file_get_contents(
            (string) (new \ReflectionClass(GroqService::class))->getFileName()
        );

        foreach ([
            'exact document type as the first tag',
            'lowercase',
            'generic filler',
            'NEVER invent',
            'no duplicates (case-insensitive)',
        ] as $fragment) {
            self::assertStringContainsString($fragment, $source);
        }
    }
}
