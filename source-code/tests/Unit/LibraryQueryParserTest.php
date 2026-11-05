<?php

namespace Tests\Unit;

use App\Services\DocumentLibrary\AuthorizedFilterOptions;
use App\Services\DocumentLibrary\LibraryQueryParser;
use App\Services\DocumentLibrary\LibrarySort;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/** **Validates: Requirements 5.8, 6.1, 6.5, 6.8, 6.9, 6.17, 9.8** */
final class LibraryQueryParserTest extends TestCase
{
    private LibraryQueryParser $parser;
    private AuthorizedFilterOptions $options;

    protected function setUp(): void
    {
        $this->parser = new LibraryQueryParser();
        $this->options = new AuthorizedFilterOptions(
            departments: ['CCS', 'CAS'],
            years: ['2024', '2025', '2026'],
            requirements: ['Application Form', 'List of Members'],
            documentTypes: ['Form', 'Report'],
            applicationKinds: ['Recognition', 'Renewal'],
            applicationStatuses: ['Pending', 'Approved'],
        );
    }

    public function test_it_parses_all_supported_values_and_builds_canonical_parameters(): void
    {
        $parsed = $this->parse([
            'q' => '  annual report  ',
            'department' => ['CCS', 'CAS'],
            'year_from' => '2024',
            'requirement' => ['Application Form'],
            'document_type' => ['Report'],
            'application_kind' => ['Recognition'],
            'application_status' => ['Approved'],
            'submitted_from' => '2024-01-02',
            'submitted_to' => '2024-12-31',
            'sort' => 'filename_asc',
            'page' => '3',
        ]);

        self::assertFalse($parsed->hasRejectedInputs());
        self::assertSame('annual report', $parsed->query->keyword);
        self::assertSame(['CCS', 'CAS'], $parsed->query->departments);
        self::assertSame(2024, $parsed->query->yearFrom);
        self::assertSame(['Application Form'], $parsed->query->requirements);
        self::assertSame(['Report'], $parsed->query->documentTypes);
        self::assertSame(['Recognition'], $parsed->query->applicationKinds);
        self::assertSame(['Approved'], $parsed->query->applicationStatuses);
        self::assertSame('2024-01-02', $parsed->query->submittedFrom?->format('Y-m-d'));
        self::assertSame('2024-12-31', $parsed->query->submittedTo?->format('Y-m-d'));
        self::assertSame(LibrarySort::FilenameAscending, $parsed->query->sort);
        self::assertSame(3, $parsed->query->page);
        self::assertSame([
            'q' => 'annual report',
            'department' => ['CCS', 'CAS'],
            'requirement' => ['Application Form'],
            'document_type' => ['Report'],
            'application_kind' => ['Recognition'],
            'application_status' => ['Approved'],
            'year_from' => '2024',
            'submitted_from' => '2024-01-02',
            'submitted_to' => '2024-12-31',
            'sort' => 'filename_asc',
            'page' => 3,
        ], $parsed->canonicalParameters);
    }

    public function test_whitespace_keyword_and_defaults_are_omitted_from_canonical_parameters(): void
    {
        $parsed = $this->parse([
            'q' => " \t\u{2003}\n ",
            'sort' => 'newest',
            'page' => '1',
            'submitted_from' => '',
            'submitted_to' => '',
        ]);

        self::assertNull($parsed->query->keyword);
        self::assertSame(LibrarySort::NewestSubmission, $parsed->query->sort);
        self::assertSame(1, $parsed->query->page);
        self::assertSame([], $parsed->canonicalParameters);
        self::assertSame([], $parsed->rejectedInputs);
    }

    public function test_it_preserves_valid_values_while_rejecting_each_unavailable_filter_value(): void
    {
        $parsed = $this->parse([
            'department' => ['CCS', 'Private Department', 'CAS', 'Another Private Department'],
            'year_from' => 'not-authorized',
        ]);

        self::assertSame(['CCS', 'CAS'], $parsed->query->departments);
        self::assertNull($parsed->query->yearFrom);
        self::assertCount(2, $parsed->messagesFor('department'));
        self::assertCount(1, $parsed->messagesFor('year_from'));
        self::assertSame([
            'department' => ['CCS', 'CAS'],
        ], $parsed->canonicalParameters);

        $messages = implode(' ', array_merge(...array_values($parsed->rejectedInputs)));
        self::assertStringNotContainsString('Private Department', $messages);
        self::assertStringNotContainsString('not-authorized', $messages);
        self::assertStringNotContainsString('CCS', $messages);
    }

    public function test_invalid_dates_sort_and_page_are_field_addressable_and_defaults_remain_canonical(): void
    {
        $parsed = $this->parse([
            'q' => 'minutes',
            'department' => ['CCS'],
            'submitted_from' => '2024-02-30',
            'submitted_to' => ['2024-12-31'],
            'sort' => 'secret-sort',
            'page' => '0',
        ]);

        self::assertSame('minutes', $parsed->query->keyword);
        self::assertSame(['CCS'], $parsed->query->departments);
        self::assertNull($parsed->query->submittedFrom);
        self::assertNull($parsed->query->submittedTo);
        self::assertSame(LibrarySort::NewestSubmission, $parsed->query->sort);
        self::assertSame(1, $parsed->query->page);
        self::assertSame(['q' => 'minutes', 'department' => ['CCS']], $parsed->canonicalParameters);
        self::assertSame([
            'submitted_from', 'submitted_to', 'sort', 'page',
        ], array_keys($parsed->rejectedInputs));
    }

    public function test_reversed_range_preserves_start_and_rejects_only_conflicting_end(): void
    {
        $parsed = $this->parse([
            'submitted_from' => '2025-01-02',
            'submitted_to' => '2025-01-01',
            'sort' => 'oldest',
            'page' => '2',
        ]);

        self::assertSame('2025-01-02', $parsed->query->submittedFrom?->format('Y-m-d'));
        self::assertNull($parsed->query->submittedTo);
        self::assertSame(LibrarySort::OldestSubmission, $parsed->query->sort);
        self::assertSame(2, $parsed->query->page);
        self::assertSame([
            'submitted_from' => '2025-01-02',
            'sort' => 'oldest',
            'page' => 2,
        ], $parsed->canonicalParameters);
        self::assertCount(1, $parsed->messagesFor('submitted_to'));
    }

    public function test_malformed_year_endpoint_is_field_addressable_and_defaults_remain_canonical(): void
    {
        $parsed = $this->parse([
            'year_from' => ['2024'],
            'sort' => 'newest',
            'page' => '1',
        ]);

        self::assertNull($parsed->query->yearFrom);
        self::assertSame(LibrarySort::NewestSubmission, $parsed->query->sort);
        self::assertSame(1, $parsed->query->page);
        self::assertSame([], $parsed->canonicalParameters);
        self::assertSame([
            'year_from',
        ], array_keys($parsed->rejectedInputs));
    }

    public function test_unlisted_year_pair_value_is_rejected_and_legacy_year_to_is_ignored(): void
    {
        $parsed = $this->parse([
            'year_from' => '2024-2025',
            'year_to' => '2026-2027',
        ]);

        self::assertNull($parsed->query->yearFrom);
        self::assertCount(1, $parsed->messagesFor('year_from'));
        self::assertSame([], $parsed->canonicalParameters);
        self::assertArrayNotHasKey('year_to', $parsed->rejectedInputs);
    }

    public function test_non_scalar_keyword_and_malformed_filter_elements_are_safely_rejected(): void
    {
        $parsed = $this->parse([
            'q' => ['private keyword'],
            'department' => ['CCS', ['private department'], 7, 'CAS'],
        ]);

        self::assertNull($parsed->query->keyword);
        self::assertSame(['CCS', 'CAS'], $parsed->query->departments);
        self::assertCount(1, $parsed->messagesFor('q'));
        self::assertCount(2, $parsed->messagesFor('department'));
        self::assertSame(['department' => ['CCS', 'CAS']], $parsed->canonicalParameters);
    }

    public function test_authorized_options_are_immutable_deduplicated_exact_sets(): void
    {
        $options = new AuthorizedFilterOptions(departments: ['CCS', 'CCS', 'CAS']);

        self::assertSame(['CCS', 'CAS'], $options->all()['department']);
        self::assertTrue((new ReflectionClass(AuthorizedFilterOptions::class))->isReadOnly());
        self::assertTrue((new ReflectionClass($this->parse([])))->isReadOnly());
    }

    /** @param array<string, mixed> $query */
    private function parse(array $query): \App\Services\DocumentLibrary\ParsedLibraryQuery
    {
        return $this->parser->parse(Request::create('/osas/smart-search', 'GET', $query), $this->options);
    }
}
