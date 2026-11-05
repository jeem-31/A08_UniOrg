<?php

namespace Tests\Unit;

use App\Services\CertificationOfOfficersFixedLayoutParser;
use App\Services\CertificationOfOfficersPdfExtractor;
use PHPUnit\Framework\TestCase;
use Tests\Support\CertificationOfOfficersFixtureBuilder;

/** **Validates: Requirements 2.1, 2.4–2.8, 2.11, 2.12, 2.14** */
final class CertificationOfOfficersFixedLayoutParserTest extends TestCase
{
    public function test_parses_one_officer_per_valid_page_in_physical_source_order(): void
    {
        $fixture = CertificationOfOfficersFixtureBuilder::validMultiPageCase();
        $result = (new CertificationOfOfficersFixedLayoutParser)->parse(
            $this->extractPages($fixture['pdf']),
        );

        self::assertSame('completed', $result['outcome']);
        self::assertSame([], $result['warnings']);
        self::assertSame('College of Imaginary Arts', $result['data']['college']);
        self::assertCount(
            count(CertificationOfOfficersFixtureBuilder::documentedSlots()),
            $result['data']['officers'],
        );
        self::assertSame(
            CertificationOfOfficersFixtureBuilder::documentedSlots(),
            array_keys($result['data']['officers']),
        );
    }

    public function test_visible_text_takes_priority_over_recognized_form_field_values(): void
    {
        $fixture = CertificationOfOfficersFixtureBuilder::formFieldPrecedenceCase();
        $result = (new CertificationOfOfficersFixedLayoutParser)->parse($this->extractPages($fixture['pdf']));

        self::assertSame('completed', $result['outcome']);
        self::assertSame($fixture['expectedOfficer'], $result['data']['officers']['president']);
        self::assertArrayHasKey('president', $result['data']['officers']);
    }

    public function test_complete_visible_representative_role_wins_over_ordinal_only_form_value(): void
    {
        $cases = [
            ['1ST', '1 ST YEAR REPRESENTATIVE', 'rep_1st_year'],
            ['2 ND', '2 ND YEAR REPRESENTATIVE', 'rep_2nd_year'],
            ['3ʳᵈ', '3 RD YEAR REPRESENTATIVE', 'rep_3rd_year'],
            ['4ᵗʰ', '4 TH YEAR REPRESENTATIVE', 'rep_4th_year'],
        ];

        foreach ($cases as [$preferredOrdinal, $visibleRole, $expectedSlot]) {
            $page = $this->page(position: $visibleRole);
            $page['preferred_values']['position'] = $preferredOrdinal;

            $result = (new CertificationOfOfficersFixedLayoutParser)->parse([$page]);

            self::assertSame('completed', $result['outcome']);
            self::assertSame([$expectedSlot], array_keys($result['data']['officers']));
            self::assertSame([], $result['warnings']);
        }
    }

    public function test_requires_the_confirmed_unlabelled_sentence_and_signature_anchors(): void
    {
        $page = $this->page();
        $page['text'] = str_replace(
            'This certifies that',
            'This confirms that',
            $page['text'],
        );

        $result = (new CertificationOfOfficersFixedLayoutParser)->parse([$page]);

        self::assertSame('manual_required', $result['outcome']);
        self::assertSame([], $result['data']['officers']);
        self::assertCount(1, $result['warnings']);
    }

    public function test_requires_form_number_and_revision_as_separate_footer_anchors(): void
    {
        foreach (['LSPU-OSAS-SF-006', 'Rev. 1'] as $anchor) {
            $page = $this->page();
            $page['text'] = str_replace($anchor, '', $page['text']);

            $result = (new CertificationOfOfficersFixedLayoutParser)->parse([$page]);

            self::assertSame('manual_required', $result['outcome'], "Missing $anchor must invalidate the template.");
            self::assertSame([], $result['data']['officers']);
        }
    }

    public function test_normalizes_comma_delimited_student_names_without_inferring_a_suffix(): void
    {
        $page = $this->page('DELA CRUZ, ANA MARIA JR.', '12 z');
        $result = (new CertificationOfOfficersFixedLayoutParser)->parse([$page]);

        self::assertSame('completed', $result['outcome']);
        self::assertSame([
            'first_name' => 'Ana Maria',
            'middle_name' => 'Jr.',
            'last_name' => 'Dela Cruz',
            'suffix' => '',
            'year_level' => '12',
            'section' => 'Z',
        ], $this->officerFields($result['data']['officers']['president']));
    }

    public function test_student_name_normalization_keeps_multi_word_first_names_and_two_token_names_distinct(): void
    {
        $multiWord = (new CertificationOfOfficersFixedLayoutParser)->parse([
            $this->page('VON STONE, LUMA RAE Q.'),
        ]);
        $twoToken = (new CertificationOfOfficersFixedLayoutParser)->parse([
            $this->page('RIVER, SAGE'),
        ]);

        self::assertSame([
            'first_name' => 'Luma Rae',
            'middle_name' => 'Q.',
            'last_name' => 'Von Stone',
            'suffix' => '',
            'year_level' => '2',
            'section' => 'A',
        ], $this->officerFields($multiWord['data']['officers']['president']));
        self::assertSame([
            'first_name' => 'Sage',
            'middle_name' => '',
            'last_name' => 'River',
            'suffix' => '',
            'year_level' => '2',
            'section' => 'A',
        ], $this->officerFields($twoToken['data']['officers']['president']));
    }

    public function test_accepts_conventional_name_order_before_the_fixed_sentence_comma_only(): void
    {
        $result = (new CertificationOfOfficersFixedLayoutParser)->parse([
            $this->page('A. SAGE RIVER M.'),
        ]);
        $malformed = (new CertificationOfOfficersFixedLayoutParser)->parse([
            $this->page('MONONYM'),
        ]);

        self::assertSame('completed', $result['outcome']);
        self::assertSame([
            'first_name' => 'A. Sage',
            'middle_name' => 'River',
            'last_name' => 'M.',
            'suffix' => '',
            'year_level' => '2',
            'section' => 'A',
        ], $this->officerFields($result['data']['officers']['president']));
        self::assertSame('manual_required', $malformed['outcome']);
        self::assertSame([], $malformed['data']['officers']);
    }

    public function test_reads_name_lines_before_certifier_roles_and_preserves_credentials(): void
    {
        $page = $this->page(
            advisers: ['MIA ROSE V. VILLARICA, DIT, LPT', 'RILEY M. DELA VEGA, PhD'],
            dean: 'CASEY WEST, EdD',
        );
        $result = (new CertificationOfOfficersFixedLayoutParser)->parse([$page]);

        self::assertSame([
            'first_name' => 'Mia Rose',
            'middle_name' => 'V.',
            'last_name' => 'Villarica',
            'suffix' => 'DIT, LPT',
        ], $result['data']['senior_adviser']);
        self::assertSame('Dela Vega', $result['data']['junior_adviser']['last_name']);
        self::assertSame('PhD', $result['data']['junior_adviser']['suffix']);
        self::assertSame([
            'first_name' => 'Casey',
            'middle_name' => '',
            'last_name' => 'West',
            'suffix' => 'EdD',
        ], $result['data']['dean']);
    }

    public function test_invalid_year_section_is_recoverable_partial_with_blank_destinations(): void
    {
        $result = (new CertificationOfOfficersFixedLayoutParser)->parse([$this->page(yearSection: 'SECOND YEAR')]);

        self::assertSame('partial', $result['outcome']);
        self::assertSame('', $result['data']['officers']['president']['year_level']);
        self::assertSame('', $result['data']['officers']['president']['section']);
        self::assertStringContainsString('malformed year-section', $result['warnings'][0]);
    }

    public function test_uses_only_the_unlabelled_sentence_and_ignores_noted_content(): void
    {
        $page = $this->page();
        self::assertStringNotContainsString('STUDENT NAME:', $page['text']);
        self::assertStringNotContainsString('POSITION:', $page['text']);

        $result = (new CertificationOfOfficersFixedLayoutParser)->parse([$page]);
        $serialized = json_encode($result, JSON_THROW_ON_ERROR);

        self::assertArrayNotHasKey('course', $result['data']);
        self::assertArrayNotHasKey('organization', $result['data']);
        self::assertStringNotContainsString('NOTED-BY', $serialized);
        // The degree program is legitimate sentence content carried per officer.
        self::assertSame('Bachelor of Synthetic Studies', $result['data']['officers']['president']['course']);
        self::assertStringNotContainsString('Fictional Orbit Society', $serialized);
    }

    public function test_empty_or_non_template_content_requires_manual_entry_and_mixed_pages_are_partial(): void
    {
        $parser = new CertificationOfOfficersFixedLayoutParser;
        $empty = $parser->parse([]);
        $nonTemplate = $parser->parse([['index' => 0, 'text' => 'fictional unrelated page']]);
        $mixed = $parser->parse([$this->page(), ['index' => 1, 'text' => 'fictional unrelated page']]);

        self::assertSame('manual_required', $empty['outcome']);
        self::assertSame('manual_required', $nonTemplate['outcome']);
        self::assertSame('partial', $mixed['outcome']);
        self::assertCount(1, $mixed['data']['officers']);
        self::assertCount(1, $mixed['warnings']);
    }

    /** @return array{index:int,text:string,preferred_values:array<string,string>} */
    private function page(
        string $student = 'RIVER, SAGE M.',
        string $yearSection = '2A',
        string $position = 'PRESIDENT',
        array $advisers = ['AIDEN P. NORTH, MA', 'BLAIR Q. SOUTH, PhD'],
        string $dean = 'CASEY R. WEST, EdD',
    ): array {
        return [
            'index' => 0,
            'text' => implode("\n", [
                'CERTIFICATION',
                "This certifies that $student, a student taking up Bachelor of Synthetic Studies, $yearSection from the College of Imaginary Arts and the elected/appointed $position of the Fictional Orbit Society.",
                $advisers[0],
                'Organization Adviser',
                $advisers[1],
                'Organization Adviser',
                $dean,
                'Dean/Assoc. Dean, College of Imaginary Arts',
                'Noted:',
                'NOTED-BY FICTIONAL PERSON',
                'Director/Chairperson',
                'LSPU-OSAS-SF-006',
                'Rev. 1',
            ]),
            'preferred_values' => [],
        ];
    }

    /** @return list<array<string,mixed>> */
    private function extractPages(string $pdf): array
    {
        return (new CertificationOfOfficersPdfExtractor)->extractBytes($pdf);
    }

    /** @param array<string,int|string> $officer @return array<string,string> */
    private function officerFields(array $officer): array
    {
        $fields = ['first_name', 'middle_name', 'last_name', 'suffix', 'year_level', 'section'];

        return array_intersect_key($officer, array_flip($fields));
    }
}
