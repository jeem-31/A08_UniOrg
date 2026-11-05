<?php

namespace Tests\Unit;

use App\Models\ProcessedDocument;
use App\Models\User;
use App\Services\DocumentLibrary\DocumentLibraryQueryService;
use App\Services\DocumentLibrary\FolderResolution;
use App\Services\DocumentLibrary\LibraryQuery;
use App\Services\DocumentLibrary\LibrarySort;
use App\Services\DocumentLibrary\OrganizationFolderIdentity;
use App\Services\DocumentLibrary\OrganizationFolderToken;
use App\Services\DocumentLibrary\ResolvedOrganizationFolder;
use App\Services\DocumentLibrary\UnspecifiedYear;
use Carbon\Carbon;
use DateTimeImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** **Validates: Requirements 5.1-5.6, 5.8, 6.1-6.5, 6.9-6.16, 7.1-7.3, 8.1-8.3, 11.2-11.4** */
final class DocumentLibraryQueryServiceTest extends TestCase
{
    private string $originalConnection;

    private bool $sqliteReady = false;

    private DocumentLibraryQueryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        if (! extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('The pdo_sqlite extension is required for library query tests.');
        }

        $this->originalConnection = DB::getDefaultConnection();
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        $this->sqliteReady = true;
        $this->createSchema();
        $this->seedEntries();
        $this->service = app(DocumentLibraryQueryService::class);
    }

    protected function tearDown(): void
    {
        if ($this->sqliteReady) {
            DB::purge('sqlite');
            DB::setDefaultConnection($this->originalConnection);
        }
        parent::tearDown();
    }

    /** **Validates: Requirements 1.2, 2.10, 2.12** */
    public function test_root_folders_merge_canonical_identities_order_names_and_count_filtered_distinct_ids(): void
    {
        DB::table('organizations')->insert([
            'id' => 2,
            'name' => 'alpha Association',
            'org_acronym' => 'AA',
        ]);
        $this->application(5, 2, 1, 'Submitted Alpha', 'SA', 'apply', 'apply', 'Approved', '2025-03-01 00:00:00');
        $this->application(6, null, 2, " legacy\u{00A0}  GUILD ", ' lg ', 'renew', 'request', 'Approved', '2025-04-01 00:00:00');
        $this->entry(5, 5, 'Application Form');
        $this->entry(6, 5, 'Application Form');
        $this->entry(7, 6, 'Application Form');

        $folders = $this->service->organizationFolders($this->osasUser(), new LibraryQuery);

        self::assertSame(
            ['alpha Association', 'Legacy Guild', 'Linked Society'],
            array_map(static fn ($folder): string => $folder->label, $folders),
        );
        self::assertSame(
            [2, 2, 3],
            array_map(static fn ($folder): int => $folder->documentCount, $folders),
        );
        foreach ($folders as $folder) {
            self::assertSame(
                $folder->identity->key(),
                app(OrganizationFolderToken::class)->verify($folder->token),
            );
        }

        $filtered = $this->service->organizationFolders(
            $this->osasUser(),
            new LibraryQuery(keyword: 'SharedHit'),
        );
        self::assertCount(1, $filtered);
        self::assertSame('linked:1', $filtered[0]->identity->key());
        self::assertSame(1, $filtered[0]->documentCount);
        self::assertSame([], $this->service->organizationFolders(
            new User(['role' => 'student']),
            new LibraryQuery,
        ));
    }

    public function test_unapproved_applications_are_excluded_from_the_library(): void
    {
        $this->application(9, 1, 1, 'Pending Society', 'PS', 'apply', 'apply', 'Pending', '2026-06-01 00:00:00');
        $this->entry(9, 9, 'Application Form');
        $this->analysis(109, 9, ProcessedDocument::STATUS_COMPLETED, 'v9', '2026-2027', 'report', 'Report');

        $folders = $this->service->organizationFolders($this->osasUser(), new LibraryQuery);
        self::assertNotContains('Pending Society', array_map(
            static fn ($folder): string => $folder->label,
            $folders,
        ));

        self::assertNotContains(9, $this->searchIds(''));

        self::assertNotContains('Pending', $this->service->authorizedFilterOptions($this->osasUser())->all()['application_status']);
    }

    /** **Validates: Requirements 2.5, 2.9, 2.11, 2.12** */
    public function test_year_folders_use_current_output_order_newest_first_and_count_filtered_distinct_ids(): void
    {
        $this->application(5, 1, 1, 'Linked Society', 'LS', 'apply', 'apply', 'Approved', '2026-01-01 00:00:00');

        foreach ([5 => 'constitution_bylaws', 6 => 'plan_of_activities'] as $id => $field) {
            DB::table('document_library_entries')->insert([
                'id' => $id,
                'apply_org_id' => 5,
                'document_field' => $field,
                'document_slot' => 'primary',
                'requirement_label' => 'Application Form',
                'original_filename' => "document-{$id}.pdf",
                'storage_disk' => 'private',
                'file_path' => "documents/{$id}.pdf",
                'source_version' => "v{$id}",
                'source_fingerprint' => str_repeat((string) $id, 64),
            ]);
            DB::table('processed_documents')->insert([
                'id' => 100 + $id,
                'apply_org_id' => 5,
                'document_field' => $field,
                'document_slot' => 'primary',
                'storage_disk' => 'private',
                'file_path' => "documents/{$id}.pdf",
                'source_version' => "v{$id}",
                'source_fingerprint' => str_repeat((string) $id, 64),
                'association_ready_at' => '2026-01-01 00:00:00',
                'output_source_version' => "v{$id}",
                'processing_status' => ProcessedDocument::STATUS_COMPLETED,
                'key_information' => json_encode(['school_year' => '2026-2027'], JSON_THROW_ON_ERROR),
            ]);
        }

        $identity = OrganizationFolderIdentity::linked(1);
        $organization = new ResolvedOrganizationFolder(
            $identity,
            'Linked Society',
            [1, 3, 4, 5],
            app(OrganizationFolderToken::class)->issue($identity),
        );
        $folders = $this->service->yearFolders($this->osasUser(), new LibraryQuery, $organization);

        self::assertSame(
            ['2026-2027', '2024-2025', 'unspecified'],
            array_map(static fn ($folder): string => $folder->key(), $folders),
        );
        self::assertSame(
            [2, 1, 2],
            array_map(static fn ($folder): int => $folder->documentCount, $folders),
        );
        self::assertSame('Unspecified Year', $folders[2]->label());

        $filtered = $this->service->yearFolders(
            $this->osasUser(),
            new LibraryQuery(keyword: 'SharedHit'),
            $organization,
        );
        self::assertSame(['2024-2025'], array_map(static fn ($folder): string => $folder->key(), $filtered));
        self::assertSame(1, $filtered[0]->documentCount);
    }

    public function test_discovery_and_options_are_denied_outside_the_osas_admin_role(): void
    {
        foreach (['student', 'admin', 'super_admin'] as $role) {
            $user = new User(['role' => $role]);
            self::assertSame(0, $this->service->authorizedEntries($user)->count());
            self::assertSame([
                'department' => [],
                'year' => [],
                'requirement' => [],
                'document_type' => [],
                'application_kind' => [],
                'application_status' => [],
            ], $this->service->authorizedFilterOptions($user)->all());
        }
    }

    public function test_options_cover_all_departments_and_current_ai_output_only(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 8, 10));
        try {
            DB::table('departments')->insert([
                ['id' => 3, 'department_name' => 'College of Nursing', 'department_acronym' => 'CON'],
                ['id' => 11, 'department_name' => 'Non-Academic', 'department_acronym' => ''],
            ]);

            $options = $this->service->authorizedFilterOptions($this->osasUser());
            $all = $options->all();

            self::assertSame(['CAS', 'CCS', 'College of Nursing'], $all['department']);
            self::assertNotContains('Non-Academic', $all['department']);
            self::assertSame(
                array_map('strval', range(2036, 2016)),
                $all['year'],
            );
            self::assertSame(['Application Form', 'List of Members'], $all['requirement']);
            self::assertSame(['Minutes', 'Report'], $all['document_type']);
            self::assertSame(['Recognition', 'Renewal'], $all['application_kind']);
            self::assertSame(['Approved'], $all['application_status']);
            self::assertNotContains('Secret Stale Type', $all['document_type']);
            self::assertContains('2036', $all['year']);
            self::assertNotContains('2040', $all['year']);

            self::assertSame(
                ['CCS' => 'CCS', 'CAS' => 'CAS', 'College of Nursing' => 'CON'],
                $options->labels()['department'],
            );
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_current_output_with_malformed_school_year_is_excluded_from_year_options(): void
    {
        $this->application(7, 1, 1, 'Linked Society', 'LS', 'apply', 'apply', 'Approved', '2025-01-01 00:00:00');
        $this->entry(7, 7, 'Application Form');
        $this->analysis(107, 7, ProcessedDocument::STATUS_COMPLETED, 'v7', 'N/A', 'report', 'Report');

        $all = $this->service->authorizedFilterOptions($this->osasUser())->all();

        self::assertNotContains('N/A', $all['year']);
        self::assertNotContains('0', $all['year']);
        self::assertSame(array_map('strval', range(2036, now()->year - 10)), $all['year']);
    }

    public function test_application_accreditation_year_groups_form_documents_without_an_extracted_year(): void
    {
        $this->application(20, 1, 1, 'Year Bound Society', 'YB', 'apply', 'apply', 'Approved', '2026-02-01 00:00:00', '2025-2026');
        $this->entry(20, 20, 'Application Form');
        DB::table('processed_documents')->insert([
            'id' => 220,
            'apply_org_id' => 20,
            'document_field' => 'application_form',
            'document_slot' => 'primary',
            'storage_disk' => 'private',
            'file_path' => 'documents/20.pdf',
            'source_version' => 'v20',
            'source_fingerprint' => str_repeat('20', 64),
            'association_ready_at' => '2026-02-01 00:00:00',
            'output_source_version' => 'v20',
            'processing_status' => ProcessedDocument::STATUS_COMPLETED,
            'key_information' => json_encode([], JSON_THROW_ON_ERROR),
        ]);

        $identity = OrganizationFolderIdentity::linked(1);
        $organization = new ResolvedOrganizationFolder(
            $identity,
            'Year Bound Society',
            [20],
            app(OrganizationFolderToken::class)->issue($identity),
        );
        $folders = $this->service->yearFolders($this->osasUser(), new LibraryQuery, $organization);

        self::assertSame(
            ['2025-2026'],
            array_map(static fn ($folder): string => $folder->key(), $folders),
        );
        self::assertSame(1, $folders[0]->documentCount);
    }

    public function test_application_accreditation_year_overrides_an_extracted_school_year(): void
    {
        $this->application(21, 1, 1, 'Preference Society', 'PS', 'apply', 'apply', 'Approved', '2026-02-01 00:00:00', '2024-2025');
        $this->entry(21, 21, 'Application Form');
        $this->analysis(221, 21, ProcessedDocument::STATUS_COMPLETED, 'v21', '2026-2027', 'report', 'Report');

        $identity = OrganizationFolderIdentity::linked(1);
        $organization = new ResolvedOrganizationFolder(
            $identity,
            'Preference Society',
            [21],
            app(OrganizationFolderToken::class)->issue($identity),
        );
        $folders = $this->service->yearFolders($this->osasUser(), new LibraryQuery, $organization);

        self::assertSame(
            ['2024-2025'],
            array_map(static fn ($folder): string => $folder->key(), $folders),
        );
        self::assertSame(1, $folders[0]->documentCount);
    }

    public function test_invalid_application_accreditation_year_falls_back_to_extracted_or_unspecified(): void
    {
        $this->application(22, 1, 1, 'Malformed Society', 'MS', 'apply', 'apply', 'Approved', '2026-02-01 00:00:00', 'N/A');
        $this->entry(22, 22, 'Application Form');
        $this->analysis(222, 22, ProcessedDocument::STATUS_COMPLETED, 'v22', '2025-2026', 'minutes', 'Minutes');

        $this->application(23, 1, 1, 'Malformed Society', 'MS', 'apply', 'apply', 'Approved', '2026-02-01 00:00:00', 'N/A');
        $this->entry(23, 23, 'Application Form');
        DB::table('processed_documents')->insert([
            'id' => 223,
            'apply_org_id' => 23,
            'document_field' => 'application_form',
            'document_slot' => 'primary',
            'storage_disk' => 'private',
            'file_path' => 'documents/23.pdf',
            'source_version' => 'v23',
            'source_fingerprint' => str_repeat('23', 64),
            'association_ready_at' => '2026-02-01 00:00:00',
            'output_source_version' => 'v23',
            'processing_status' => ProcessedDocument::STATUS_COMPLETED,
            'key_information' => json_encode([], JSON_THROW_ON_ERROR),
        ]);

        $identity = OrganizationFolderIdentity::linked(1);
        $organization = new ResolvedOrganizationFolder(
            $identity,
            'Malformed Society',
            [22, 23],
            app(OrganizationFolderToken::class)->issue($identity),
        );
        $folders = $this->service->yearFolders($this->osasUser(), new LibraryQuery, $organization);

        self::assertSame(
            ['2025-2026', 'unspecified'],
            array_map(static fn ($folder): string => $folder->key(), $folders),
        );
        self::assertSame(2, array_sum(array_map(
            static fn ($folder): int => $folder->documentCount,
            $folders,
        )));
    }

    public function test_all_categories_and_dates_are_anded_while_values_within_categories_are_ored(): void
    {
        $query = new LibraryQuery(
            departments: ['CCS', 'CAS'],
            requirements: ['Application Form', 'List of Members'],
            documentTypes: ['Report', 'Minutes'],
            applicationKinds: ['Recognition', 'Renewal'],
            applicationStatuses: ['Approved', 'Pending'],
            submittedFrom: new DateTimeImmutable('2025-01-01'),
            submittedTo: new DateTimeImmutable('2025-12-31'),
        );

        self::assertSame(
            [1, 2],
            $this->service->filteredEntries($this->osasUser(), $query)
                ->orderBy('authorized_entries.id')->pluck('id')->all(),
        );

        $narrowed = new LibraryQuery(
            departments: ['CCS'],
            yearFrom: 2024,
            requirements: ['Application Form'],
            documentTypes: ['Report'],
            applicationKinds: ['Recognition'],
            applicationStatuses: ['Approved'],
            submittedFrom: new DateTimeImmutable('2025-02-03'),
            submittedTo: new DateTimeImmutable('2025-02-03'),
        );

        self::assertSame([1], $this->service->filteredEntries($this->osasUser(), $narrowed)->pluck('id')->all());
    }

    public function test_year_filter_keeps_only_current_output_with_matching_starting_year(): void
    {
        $year2024 = new LibraryQuery(yearFrom: 2024);
        self::assertSame(
            [1],
            $this->service->filteredEntries($this->osasUser(), $year2024)
                ->orderBy('authorized_entries.id')->pluck('id')->all(),
        );

        $year2025 = new LibraryQuery(yearFrom: 2025);
        self::assertSame(
            [2],
            $this->service->filteredEntries($this->osasUser(), $year2025)
                ->orderBy('authorized_entries.id')->pluck('id')->all(),
        );

        $year2026 = new LibraryQuery(yearFrom: 2026);
        self::assertSame([], $this->service->filteredEntries($this->osasUser(), $year2026)->pluck('id')->all());

        $staleType = new LibraryQuery(documentTypes: ['Secret Stale Type']);
        self::assertSame([], $this->service->filteredEntries($this->osasUser(), $staleType)->pluck('id')->all());
    }

    public function test_literal_case_insensitive_search_covers_all_permitted_fields_once(): void
    {
        $cases = [
            'sharedhit' => [1],
            'SUMMARY NEEDLE' => [1],
            'extracted needle' => [1],
            'linked society' => [1, 3, 4],
            'LS' => [1, 3, 4],
            'old submitted name' => [1],
            'legacy guild' => [2],
            'lg' => [2],
            'budget 100%_READY!.pdf' => [1],
            'list of mem' => [2],
        ];

        foreach ($cases as $keyword => $expectedIds) {
            self::assertSame(
                $expectedIds,
                $this->searchIds($keyword),
                "Unexpected result IDs for keyword [{$keyword}].",
            );
        }
    }

    public function test_search_escapes_wildcards_ignores_whitespace_and_excludes_non_current_ai_output(): void
    {
        self::assertSame([1], $this->searchIds('%'));
        self::assertSame([1], $this->searchIds('_'));
        self::assertSame([1], $this->searchIds('!'));
        self::assertSame([], $this->searchIds('Stale AI Needle'));
        self::assertSame([], $this->searchIds('Incomplete AI Needle'));
        self::assertSame([1, 2, 3, 4], $this->searchIds(" \t\n "));
    }

    /** **Validates: Requirements 6.9, 6.10, 6.11, 11.3, 11.4** */
    public function test_every_sort_uses_entry_id_descending_for_equal_primary_values(): void
    {
        $this->seedPageFixtures(2, 'Sort Fixture', 'same-name.pdf');

        foreach (LibrarySort::cases() as $sort) {
            $page = $this->service->searchPage(
                new LibraryQuery(requirements: ['Sort Fixture'], sort: $sort),
                $this->osasUser(),
            );

            self::assertSame([6, 5], $page->entryIds, "Unexpected tie order for {$sort->value}.");
            self::assertSame($page->entryIds, array_map(
                static fn ($entry): int => (int) $entry->id,
                $page->entries,
            ));
        }
    }

    /** **Validates: Requirements 6.13, 6.14, 6.15, 6.16, 11.2, 11.3** */
    public function test_page_ids_are_selected_before_bounded_hydration_with_stable_ranges_and_canonical_state(): void
    {
        $this->seedPageFixtures(30, 'Page Fixture', 'same-name.pdf');
        $identity = OrganizationFolderIdentity::linked(1);
        $organization = new ResolvedOrganizationFolder(
            $identity,
            'Linked Society',
            range(5, 34),
            app(OrganizationFolderToken::class)->issue($identity),
        );
        $path = new FolderResolution(
            'year',
            [],
            $organization,
            UnspecifiedYear::Value,
            null,
            url('/osas/smart-search'),
            null,
        );

        DB::flushQueryLog();
        DB::enableQueryLog();
        $first = $this->service->folderPage(
            new LibraryQuery(requirements: ['Page Fixture']),
            $path,
            $this->osasUser(),
        );
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        self::assertSame(range(34, 10), $first->entryIds);
        self::assertCount(25, $first->entries);
        self::assertSame(30, $first->total);
        self::assertSame(1, $first->firstResult);
        self::assertSame(25, $first->lastResult);
        self::assertSame(2, $first->lastPage);
        self::assertNull($first->previousPageUrl);
        self::assertNotNull($first->nextPageUrl);
        // When live is empty we fall back to archived (adds one exists check), so allow 3 or 4 queries
        self::assertContains(count($queries), [3, 4]);
        $hasExistsFallback = count($queries) === 4;
        $offset = $hasExistsFallback ? 1 : 0;
        self::assertStringContainsString('count(*) as aggregate', strtolower($queries[$offset]['query']));
        self::assertStringContainsString('limit 25', strtolower($queries[$offset + 1]['query']));
        self::assertStringContainsString('where "authorized_entries"."id" in (', strtolower($queries[$offset + 2]['query']));

        $secondQuery = new LibraryQuery(requirements: ['Page Fixture'], page: 2);
        $second = $this->service->folderPage($secondQuery, $path, $this->osasUser());
        self::assertSame(range(9, 5), $second->entryIds);
        self::assertCount(5, $second->entries);
        self::assertSame(30, $second->total);
        self::assertSame(26, $second->firstResult);
        self::assertSame(30, $second->lastResult);
        self::assertNotNull($second->previousPageUrl);
        self::assertNull($second->nextPageUrl);
        self::assertSame([], array_values(array_intersect($first->entryIds, $second->entryIds)));
        self::assertSame(range(34, 5), array_merge($first->entryIds, $second->entryIds));

        parse_str((string) parse_url($second->canonicalUrl, PHP_URL_QUERY), $canonical);
        self::assertSame(['Page Fixture'], $canonical['requirement']);
        self::assertSame('2', (string) $canonical['page']);
        self::assertSame($organization->token, $canonical['folder']);
        self::assertSame('unspecified', $canonical['year_folder']);

        $repeated = $this->service->folderPage($secondQuery, $path, $this->osasUser());
        self::assertSame($second->entryIds, $repeated->entryIds);
        self::assertSame($second->total, $repeated->total);
        self::assertSame([$second->firstResult, $second->lastResult], [$repeated->firstResult, $repeated->lastResult]);
    }

    /** **Validates: Requirements 6.13, 6.16** */
    public function test_empty_and_out_of_range_pages_report_zero_display_boundaries(): void
    {
        $page = $this->service->searchPage(
            new LibraryQuery(keyword: 'does not exist', page: 3),
            $this->osasUser(),
        );

        self::assertSame([], $page->entryIds);
        self::assertSame([], $page->entries);
        self::assertSame(0, $page->total);
        self::assertSame(0, $page->firstResult);
        self::assertSame(0, $page->lastResult);
        self::assertSame(1, $page->lastPage);
        self::assertNotNull($page->previousPageUrl);
        self::assertNull($page->nextPageUrl);
    }

    /** @return list<int> */
    private function searchIds(?string $keyword): array
    {
        return $this->service->filteredEntries($this->osasUser(), new LibraryQuery(keyword: $keyword))
            ->orderBy('authorized_entries.id')
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
    }

    private function seedPageFixtures(int $count, string $requirement, string $filename): void
    {
        for ($offset = 0; $offset < $count; $offset++) {
            $id = 5 + $offset;
            $this->application(
                $id,
                1,
                1,
                'Linked Society',
                'LS',
                'apply',
                'apply',
                'Approved',
                '2027-01-01 00:00:00',
            );
            $this->entry($id, $id, $requirement);
            DB::table('document_library_entries')->where('id', $id)->update([
                'original_filename' => $filename,
            ]);
        }
    }

    private function seedEntries(): void
    {
        DB::table('departments')->insert([
            ['id' => 1, 'department_name' => 'CCS', 'department_acronym' => 'CCS'],
            ['id' => 2, 'department_name' => 'CAS', 'department_acronym' => 'CAS'],
        ]);
        DB::table('organizations')->insert([
            ['id' => 1, 'name' => 'Linked Society', 'org_acronym' => 'LS'],
        ]);

        $this->application(1, 1, 1, 'Old Submitted Name', 'OSN', 'apply', 'request', 'Approved', '2025-02-03 17:15:00');
        $this->application(2, null, 2, 'Legacy Guild', 'LG', 'renew', 'request', 'Approved', '2025-11-20 08:00:00');
        $this->application(3, 1, 1, 'Linked Society', 'LS', 'apply', 'apply', 'Approved', '2024-06-01 00:00:00');
        $this->application(4, 1, 1, 'Linked Society', 'LS', null, 'request', 'Approved', '2026-01-01 00:00:00');

        $this->entry(1, 1, 'Application Form');
        $this->entry(2, 2, 'List of Members');
        $this->entry(3, 3, 'Application Form');
        $this->entry(4, 4, 'Application Form');

        $this->analysis(101, 1, ProcessedDocument::STATUS_COMPLETED, 'v1', '2024-2025', 'report', 'Report');
        $this->analysis(102, 2, ProcessedDocument::STATUS_COMPLETED, 'v2', '2025-2026', 'minutes', 'Minutes');
        $this->analysis(103, 3, ProcessedDocument::STATUS_COMPLETED, 'stale-v0', '2030-2031', 'secret', 'Secret Stale Type');
        $this->analysis(104, 4, ProcessedDocument::STATUS_PROCESSING, 'v4', '2040-2041', 'secret', 'Processing Secret');
    }

    private function application(
        int $id,
        ?int $organizationId,
        int $departmentId,
        string $name,
        string $acronym,
        ?string $applicationType,
        string $requestType,
        string $status,
        string $createdAt,
        ?string $accreditationYear = null,
    ): void {
        DB::table('apply_orgs')->insert([
            'id' => $id,
            'organization_id' => $organizationId,
            'department_id' => $departmentId,
            'organization_name' => $name,
            'organization_acronym' => $acronym,
            'application_type' => $applicationType,
            'request_type' => $requestType,
            'status' => $status,
            'accreditation_year' => $accreditationYear,
            'created_at' => $createdAt,
        ]);
    }

    private function entry(int $id, int $applicationId, string $requirement): void
    {
        DB::table('document_library_entries')->insert([
            'id' => $id,
            'apply_org_id' => $applicationId,
            'document_field' => 'application_form',
            'document_slot' => 'primary',
            'requirement_label' => $requirement,
            'original_filename' => $id === 1 ? 'Budget 100%_ready!.pdf' : "document-{$id}.pdf",
            'storage_disk' => 'private',
            'file_path' => "documents/{$id}.pdf",
            'source_version' => "v{$id}",
            'source_fingerprint' => str_repeat((string) $id, 64),
        ]);
    }

    private function analysis(
        int $id,
        int $applicationId,
        string $status,
        string $outputVersion,
        string $year,
        string $type,
        string $typeName,
    ): void {
        DB::table('processed_documents')->insert([
            'id' => $id,
            'apply_org_id' => $applicationId,
            'document_field' => 'application_form',
            'document_slot' => 'primary',
            'storage_disk' => 'private',
            'file_path' => "documents/{$applicationId}.pdf",
            'source_version' => "v{$applicationId}",
            'source_fingerprint' => str_repeat((string) $applicationId, 64),
            'association_ready_at' => '2025-01-01 00:00:00',
            'output_source_version' => $outputVersion,
            'processing_status' => $status,
            'document_type' => $type,
            'document_type_name' => $typeName,
            'summary' => match ($applicationId) {
                1 => 'SharedHit summary needle',
                3 => 'Stale AI Needle',
                4 => 'Incomplete AI Needle',
                default => "Summary {$applicationId}",
            },
            'tags' => json_encode(
                $applicationId === 1 ? ['SharedHit tag needle'] : ["tag-{$applicationId}"],
                JSON_THROW_ON_ERROR,
            ),
            'extracted_text' => $applicationId === 1 ? 'SharedHit extracted needle' : "Extracted {$applicationId}",
            'key_information' => json_encode(['school_year' => $year], JSON_THROW_ON_ERROR),
        ]);
    }

    private function osasUser(): User
    {
        return new User(['role' => 'osas_admin']);
    }

    private function createSchema(): void
    {
        Schema::create('departments', function (Blueprint $table): void {
            $table->id();
            $table->string('department_name');
            $table->string('department_acronym', 20)->nullable();
        });
        Schema::create('organizations', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('org_acronym')->nullable();
        });
        Schema::create('apply_orgs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->string('organization_name')->nullable();
            $table->string('organization_acronym')->nullable();
            $table->string('application_type')->nullable();
            $table->string('request_type')->nullable();
            $table->string('status');
            $table->string('accreditation_year')->nullable();
            $table->timestamp('created_at')->nullable();
        });
        Schema::create('document_library_entries', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('apply_org_id');
            $table->string('document_field');
            $table->string('document_slot');
            $table->string('requirement_label');
            $table->string('original_filename');
            $table->string('storage_disk');
            $table->string('file_path');
            $table->string('source_version')->nullable();
            $table->string('source_fingerprint', 64)->nullable();
        });
        Schema::create('processed_documents', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('apply_org_id');
            $table->string('document_field');
            $table->string('document_slot');
            $table->string('storage_disk');
            $table->string('file_path');
            $table->string('source_version')->nullable();
            $table->string('source_fingerprint', 64)->nullable();
            $table->timestamp('association_ready_at')->nullable();
            $table->string('output_source_version')->nullable();
            $table->string('processing_status');
            $table->timestamp('processed_at')->nullable();
            $table->string('document_type')->nullable();
            $table->string('document_type_name')->nullable();
            $table->text('summary')->nullable();
            $table->text('tags')->nullable();
            $table->text('key_information')->nullable();
            $table->longText('extracted_text')->nullable();
            $table->decimal('confidence_score', 5, 2)->nullable();
            $table->unique(['apply_org_id', 'document_field', 'document_slot']);
        });
    }
}
