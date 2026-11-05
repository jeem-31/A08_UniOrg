<?php

namespace Tests\Unit;

use App\Services\CertificationOfOfficersPrefillMerger;
use PHPUnit\Framework\TestCase;

final class CertificationOfOfficersPrefillMergerTest extends TestCase
{
    private const SOURCE = <<<TEXT
CERTIFICATION OF OFFICERS
Fictional Orbit Society - SY 2026-2027.
This certifies that EMBER, ELLIS Q., a student taking up Bachelor of Synthetic Studies, 1E, is the elected TREASURER of the Fictional Orbit Society.
This certifies that DELA ASTER, AVERY SKY M., a student taking up Bachelor of Synthetic Studies, 1A, is the elected PRESIDENT of the Fictional Orbit Society.
CASEY R. WEST, EdD
Dean/Assoc. Dean, College of Imaginary Arts
Signed by the Organization Adviser.
TEXT;

    public function test_fixed_value_wins_per_slot_when_both_sources_have_it(): void
    {
        $fixed = $this->data(
            ['president' => ['first_name' => 'Avery', 'middle_name' => 'Sky', 'last_name' => 'DELA ASTER', 'suffix' => '', 'year_level' => '1', 'section' => 'A']],
            null
        );
        $ai = $this->data(
            ['president' => ['first_name' => 'AVERY', 'middle_name' => 'SKY', 'last_name' => 'DELA ASTER', 'suffix' => '', 'year_level' => '9', 'section' => 'Z']],
            null
        );

        $result = (new CertificationOfOfficersPrefillMerger)->merge($fixed, $ai, self::SOURCE);

        self::assertSame('fixed', $result['source']);
        self::assertSame('1', $result['data']['officers']['president']['year_level']);
        self::assertSame('A', $result['data']['officers']['president']['section']);
    }

    public function test_ai_fills_slots_the_fixed_parser_missed_and_marks_merged(): void
    {
        $fixed = $this->data(
            ['president' => ['first_name' => 'Avery', 'middle_name' => 'Sky', 'last_name' => 'DELA ASTER', 'suffix' => '', 'year_level' => '1', 'section' => 'A']],
            null
        );
        $ai = $this->data(
            ['treasurer' => ['first_name' => 'Ellis', 'middle_name' => 'Q.', 'last_name' => 'EMBER', 'suffix' => '', 'year_level' => '1', 'section' => 'E']],
            null
        );

        $result = (new CertificationOfOfficersPrefillMerger)->merge($fixed, $ai, self::SOURCE);

        self::assertSame('merged', $result['source']);
        self::assertSame('DELA ASTER', $result['data']['officers']['president']['last_name']);
        self::assertSame('EMBER', $result['data']['officers']['treasurer']['last_name']);
        self::assertSame('E', $result['data']['officers']['treasurer']['section']);
    }

    public function test_ai_only_when_fixed_parser_found_nothing(): void
    {
        $fixed = $this->data([], null);
        $ai = $this->data(
            ['treasurer' => ['first_name' => 'Ellis', 'middle_name' => 'Q.', 'last_name' => 'EMBER', 'suffix' => '', 'year_level' => '1', 'section' => 'E']],
            null
        );

        $result = (new CertificationOfOfficersPrefillMerger)->merge($fixed, $ai, self::SOURCE);

        self::assertSame('ai', $result['source']);
        self::assertSame('EMBER', $result['data']['officers']['treasurer']['last_name']);
    }

    public function test_source_text_uses_last_first_order_and_still_passes(): void
    {
        $ai = $this->data(
            ['treasurer' => ['first_name' => 'Ellis', 'middle_name' => 'Q.', 'last_name' => 'EMBER', 'suffix' => '', 'year_level' => '1', 'section' => 'E']],
            null
        );

        $result = (new CertificationOfOfficersPrefillMerger)->merge($this->data([], null), $ai, self::SOURCE);

        self::assertSame('ai', $result['source']);
        self::assertSame('EMBER', $result['data']['officers']['treasurer']['last_name']);
    }

    public function test_hallucinated_last_name_is_rejected(): void
    {
        $ai = $this->data(
            ['treasurer' => ['first_name' => 'Fake', 'middle_name' => '', 'last_name' => 'PERSON', 'suffix' => '', 'year_level' => '1', 'section' => 'E']],
            null
        );

        $result = (new CertificationOfOfficersPrefillMerger)->merge($this->data([], null), $ai, self::SOURCE);

        self::assertSame('fixed', $result['source']);
        self::assertArrayNotHasKey('treasurer', $result['data']['officers']);
    }

    public function test_hallucinated_first_name_is_rejected_when_last_name_is_real(): void
    {
        $ai = $this->data(
            ['treasurer' => ['first_name' => 'Madeup', 'middle_name' => '', 'last_name' => 'EMBER', 'suffix' => '', 'year_level' => '1', 'section' => 'E']],
            null
        );

        $result = (new CertificationOfOfficersPrefillMerger)->merge($this->data([], null), $ai, self::SOURCE);

        self::assertSame('fixed', $result['source']);
        self::assertArrayNotHasKey('treasurer', $result['data']['officers']);
    }

    public function test_ai_dean_fills_gap_when_fixed_dean_missing(): void
    {
        $fixed = $this->data(
            ['president' => ['first_name' => 'Avery', 'middle_name' => 'Sky', 'last_name' => 'DELA ASTER', 'suffix' => '', 'year_level' => '1', 'section' => 'A']],
            null
        );
        $ai = $this->data([], ['first_name' => 'Casey', 'middle_name' => 'R.', 'last_name' => 'West']);

        $result = (new CertificationOfOfficersPrefillMerger)->merge($fixed, $ai, self::SOURCE);

        self::assertSame('merged', $result['source']);
        self::assertSame('West', $result['data']['dean']['last_name']);
    }

    public function test_ai_dean_ignored_when_name_not_in_source(): void
    {
        $ai = $this->data([], ['first_name' => 'Nobody', 'middle_name' => 'R.', 'last_name' => 'Real']);

        $result = (new CertificationOfOfficersPrefillMerger)->merge($this->data([], null), $ai, self::SOURCE);

        self::assertSame('fixed', $result['source']);
        self::assertNull($result['data']['dean']);
    }

    public function test_empty_ai_payload_leaves_fixed_source(): void
    {
        $fixed = $this->data(
            ['president' => ['first_name' => 'Avery', 'middle_name' => 'Sky', 'last_name' => 'DELA ASTER', 'suffix' => '', 'year_level' => '1', 'section' => 'A']],
            null
        );

        $result = (new CertificationOfOfficersPrefillMerger)->merge($fixed, [], self::SOURCE);

        self::assertSame('fixed', $result['source']);
        self::assertCount(1, $result['data']['officers']);
    }

    public function test_lenient_rule_accepts_when_first_name_is_real_even_if_middle_initial_is_absent(): void
    {
        // The guard requires every last-name token plus at least one
        // first/middle token; a single missing middle initial does not
        // invalidate a name whose first and last names are both present.
        $ai = $this->data(
            ['treasurer' => ['first_name' => 'Ellis', 'middle_name' => 'Z.', 'last_name' => 'EMBER', 'suffix' => '', 'year_level' => '1', 'section' => 'E']],
            null
        );

        $result = (new CertificationOfOfficersPrefillMerger)->merge($this->data([], null), $ai, self::SOURCE);

        self::assertSame('ai', $result['source']);
        self::assertSame('EMBER', $result['data']['officers']['treasurer']['last_name']);
    }

    /**
     * @param  array<string,array<string,string>>  $officers
     */
    private function data(array $officers, ?array $dean): array
    {
        return [
            'dean'           => $dean,
            'senior_adviser' => null,
            'junior_adviser' => null,
            'officers'       => $officers,
        ];
    }
}
