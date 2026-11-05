<?php

namespace Tests\Unit;

use App\Services\DocumentLibrary\AccreditationYear;
use App\Services\DocumentLibrary\LibraryFilterCategory;
use App\Services\DocumentLibrary\LibraryQuery;
use App\Services\DocumentLibrary\LibrarySort;
use App\Services\DocumentLibrary\OrganizationFolderIdentity;
use App\Services\DocumentLibrary\UnspecifiedYear;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/** **Validates: Requirements 2.1, 2.2, 2.3, 2.4, 2.6, 2.7, 2.8, 5.8, 6.7, 6.9, 6.10, 6.12** */
final class DocumentLibraryValueObjectsTest extends TestCase
{
    #[DataProvider('validYearProvider')]
    public function test_accreditation_year_accepts_only_exact_consecutive_years(string $input, int $start): void
    {
        $year = AccreditationYear::parse($input);

        self::assertNotNull($year);
        self::assertSame($start, $year->startingYear);
        self::assertSame($start + 1, $year->endingYear);
        self::assertSame($input, $year->key());
        self::assertSame($input, (string) $year);
    }

    /** @return iterable<string, array{string, int}> */
    public static function validYearProvider(): iterable
    {
        yield 'ordinary' => ['2024-2025', 2024];
        yield 'leading zeroes remain exact' => ['0000-0001', 0];
        yield 'upper four-digit boundary' => ['9998-9999', 9998];
    }

    #[DataProvider('invalidYearProvider')]
    public function test_invalid_or_absent_years_are_unspecified(?string $input): void
    {
        self::assertNull(AccreditationYear::parse($input));
        self::assertSame(UnspecifiedYear::Value, AccreditationYear::fromCurrentOutput($input));
        self::assertSame('unspecified', UnspecifiedYear::Value->key());
        self::assertSame('Unspecified Year', UnspecifiedYear::Value->label());
    }

    /** @return iterable<string, array{?string}> */
    public static function invalidYearProvider(): iterable
    {
        yield 'absent' => [null];
        yield 'empty' => [''];
        yield 'leading whitespace' => [' 2024-2025'];
        yield 'trailing whitespace' => ['2024-2025 '];
        yield 'one digit short' => ['024-2025'];
        yield 'one digit long' => ['02024-2025'];
        yield 'en dash' => ['2024–2025'];
        yield 'non consecutive' => ['2024-2026'];
        yield 'reversed' => ['2025-2024'];
        yield 'embedded valid value' => ['year 2024-2025'];
        yield 'newline suffix' => ["2024-2025\n"];
    }

    public function test_linked_organization_identity_uses_only_the_exact_id(): void
    {
        $first = OrganizationFolderIdentity::linked(42);
        $same = OrganizationFolderIdentity::linked(42);
        $other = OrganizationFolderIdentity::linked(43);

        self::assertSame('linked:42', $first->key());
        self::assertTrue($first->isLinked());
        self::assertTrue($first->equals($same));
        self::assertFalse($first->equals($other));
        self::assertNull($first->normalizedName);
    }

    public function test_legacy_identity_collapses_unicode_whitespace_and_applies_unicode_case_folding(): void
    {
        $first = OrganizationFolderIdentity::legacy(
            "  Straße\u{00A0}\u{2003}Scholars  ",
            "  Σ\u{2009}Club ",
        );
        $same = OrganizationFolderIdentity::legacy('STRASSE scholars', 'σ club');
        $differentAcronym = OrganizationFolderIdentity::legacy('STRASSE scholars', 'σ society');

        self::assertFalse($first->isLinked());
        self::assertSame('strasse scholars', $first->normalizedName);
        self::assertSame('σ club', $first->normalizedAcronym);
        self::assertTrue($first->equals($same));
        self::assertFalse($first->equals($differentAcronym));
        self::assertMatchesRegularExpression('/^legacy:[a-f0-9]{64}$/', $first->key());
    }

    public function test_query_normalizes_keyword_and_deduplicates_valid_filter_sets(): void
    {
        $query = new LibraryQuery(
            keyword: "\u{2003} annual report \u{00A0}",
            departments: ['CCS', 'CCS', 'CAS'],
        );

        self::assertSame('annual report', $query->keyword);
        self::assertSame(['CCS', 'CAS'], $query->departments);
        self::assertNull((new LibraryQuery(keyword: "\t\u{2003}\n"))->keyword);
    }

    public function test_query_transitions_are_immutable_and_isolated(): void
    {
        $from = new DateTimeImmutable('2024-01-01');
        $to = new DateTimeImmutable('2024-12-31');
        $original = new LibraryQuery(
            keyword: 'minutes',
            departments: ['CCS'],
            requirements: ['Application Form'],
            documentTypes: ['Form'],
            applicationKinds: ['Recognition'],
            applicationStatuses: ['Approved'],
            yearFrom: 2024,
            submittedFrom: $from,
            submittedTo: $to,
            sort: LibrarySort::FilenameAscending,
            page: 4,
        );

        $changedSort = $original->withSort(LibrarySort::OldestSubmission);
        self::assertSame(1, $changedSort->page);
        self::assertSame('minutes', $changedSort->keyword);
        self::assertSame(['CCS'], $changedSort->departments);
        self::assertSame(2024, $changedSort->yearFrom);
        self::assertSame($from, $changedSort->submittedFrom);
        self::assertSame(4, $original->page);
        self::assertSame(LibrarySort::FilenameAscending, $original->sort);

        $page = $original->withPage(2);
        self::assertSame(2, $page->page);
        self::assertSame(LibrarySort::FilenameAscending, $page->sort);
        self::assertSame($original->filters(), $page->filters());

        $ranged = $original->withYear(2022);
        self::assertSame(2022, $ranged->yearFrom);
        self::assertSame(1, $ranged->page);
        self::assertSame(2024, $original->yearFrom);
    }

    public function test_filter_keyword_date_and_clear_transitions_reset_only_required_state(): void
    {
        $query = new LibraryQuery(
            keyword: 'report',
            departments: ['CCS'],
            sort: LibrarySort::FilenameDescending,
            page: 5,
        );

        $added = $query->withAddedFilterValue(LibraryFilterCategory::ApplicationStatus, 'Approved');
        self::assertSame(['Approved'], $added->applicationStatuses);
        self::assertSame(['CCS'], $added->departments);
        self::assertSame('report', $added->keyword);
        self::assertSame(1, $added->page);

        $removed = $added->withoutFilterValue(LibraryFilterCategory::ApplicationStatus, 'Approved');
        self::assertSame([], $removed->applicationStatuses);
        self::assertSame(['CCS'], $removed->departments);

        $withoutKeyword = $query->withoutKeyword();
        self::assertNull($withoutKeyword->keyword);
        self::assertSame(['CCS'], $withoutKeyword->departments);
        self::assertSame(LibrarySort::FilenameDescending, $withoutKeyword->sort);
        self::assertSame(1, $withoutKeyword->page);

        $cleared = $query->clearAll();
        self::assertNull($cleared->keyword);
        self::assertFalse($cleared->hasFilters());
        self::assertSame(LibrarySort::FilenameDescending, $cleared->sort);
        self::assertSame(1, $cleared->page);
    }

    public function test_every_supported_sort_is_total_with_newest_entry_id_as_final_tie_breaker(): void
    {
        self::assertSame([
            'newest', 'oldest', 'filename_asc', 'filename_desc',
        ], array_column(LibrarySort::cases(), 'value'));

        foreach (LibrarySort::cases() as $sort) {
            $definition = $sort->orderDefinition();
            self::assertCount(2, $definition);
            self::assertSame([
                'field' => 'entry_id',
                'direction' => 'desc',
                'case_insensitive' => false,
            ], $definition[1]);
        }

        self::assertSame(LibrarySort::NewestSubmission, LibrarySort::default());
        self::assertNull(LibrarySort::tryFrom('unsupported'));
    }

    public function test_invalid_query_invariants_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new LibraryQuery(page: 0);
    }

    public function test_reversed_date_range_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new LibraryQuery(
            submittedFrom: new DateTimeImmutable('2025-01-02'),
            submittedTo: new DateTimeImmutable('2025-01-01'),
        );
    }

    public function test_value_objects_are_language_level_readonly(): void
    {
        self::assertTrue((new ReflectionClass(AccreditationYear::class))->isReadOnly());
        self::assertTrue((new ReflectionClass(OrganizationFolderIdentity::class))->isReadOnly());
        self::assertTrue((new ReflectionClass(LibraryQuery::class))->isReadOnly());
    }
}
