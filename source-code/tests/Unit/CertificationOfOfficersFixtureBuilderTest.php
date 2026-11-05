<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Tests\Support\CertificationOfOfficersFixtureBuilder;

/** **Validates: Requirements 2.1–2.14, 3.5, 3.6** */
final class CertificationOfOfficersFixtureBuilderTest extends TestCase
{
    public function test_complete_case_is_deterministic_ordered_and_covers_every_documented_slot(): void
    {
        $first = CertificationOfOfficersFixtureBuilder::validMultiPageCase();
        $second = CertificationOfOfficersFixtureBuilder::validMultiPageCase();

        self::assertSame($first, $second);
        self::assertStringStartsWith('%PDF-1.4', $first['pdf']);
        $documentedSlots = CertificationOfOfficersFixtureBuilder::documentedSlots();
        self::assertCount(count($documentedSlots), $first['pages']);
        self::assertSame(array_keys($first['pages']), array_column($first['pages'], 'index'));
        self::assertSame($documentedSlots, array_keys($first['expectedEnvelope']['data']['officers']));
        self::assertSame('AIDEN P. NORTH, MA', $first['pages'][0]['certifiers']['senior_adviser']);
        self::assertSame($first['pages'][0]['certifiers'], $first['pages'][array_key_last($first['pages'])]['certifiers']);
    }

    public function test_precedence_and_recovery_cases_are_explicit_and_fictional(): void
    {
        $precedence = CertificationOfOfficersFixtureBuilder::formFieldPrecedenceCase();
        self::assertNotSame($precedence['page']['text_values']['student_name'], $precedence['page']['fields']['student_name']);
        self::assertSame($precedence['page']['fields'], $precedence['expectedPreferredValues']);
        self::assertStringContainsString('/AcroForm', $precedence['pdf']);

        foreach (CertificationOfOfficersFixtureBuilder::recoveryCases() as $case) {
            self::assertContains($case['expectedOutcome'], ['manual_required', 'partial']);
            self::assertNotSame('', $case['pdf']);
        }
    }
}
