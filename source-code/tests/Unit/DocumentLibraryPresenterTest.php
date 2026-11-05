<?php

namespace Tests\Unit;

use App\Models\DocumentLibraryEntry;
use App\Models\OrganizationSettings;
use App\Models\ProcessedDocument;
use App\Services\DocumentLibrary\AccreditationYear;
use App\Services\DocumentLibrary\DocumentPage;
use App\Services\DocumentLibrary\FolderBreadcrumb;
use App\Services\DocumentLibrary\FolderResolution;
use App\Services\DocumentLibrary\LibraryQuery;
use App\Services\DocumentLibrary\OrganizationFolderAggregate;
use App\Services\DocumentLibrary\OrganizationFolderIdentity;
use App\Services\DocumentLibrary\OrganizationFolderToken;
use App\Services\DocumentLibrary\ParsedLibraryQuery;
use App\Services\DocumentLibrary\ResolvedOrganizationFolder;
use App\Services\DocumentLibrary\UnspecifiedYear;
use App\Services\DocumentLibrary\YearFolderAggregate;
use App\ViewModels\CurrentDocumentAnalysisPresenter;
use App\ViewModels\DocumentLibraryPresenter;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** **Validates: Requirements 4.1, 4.2, 4.3, 4.4, 4.7, 4.8, 4.9, 7.1, 7.8** */
final class DocumentLibraryPresenterTest extends TestCase
{
    public function test_it_maps_compact_fields_complete_path_actions_and_sanitized_advisory_details(): void
    {
        $entry = $this->entry([
            'has_current_output' => 1,
            'processing_status' => ProcessedDocument::STATUS_COMPLETED,
            'analysis_id' => 91,
            'document_type_name' => 123,
            'document_type' => 'Constitution',
            'summary' => "  Review\0 summary.  ",
            'tags' => json_encode([' policy ', 7, 'policy', ''], JSON_THROW_ON_ERROR),
            'processed_at' => '2026-01-02 15:04:00',
            'key_information' => json_encode([
                'school_year' => '2026-2027',
                'president_name' => '  Alex Reviewer ',
                'focus_areas' => ['Service', 9, 'Research'],
                'file_path' => 'private/secret.pdf',
                'extracted_text' => 'not for presentation',
                'error_message' => 'raw processor failure',
                'malformed' => ['nested' => 'object'],
            ], JSON_THROW_ON_ERROR),
            'storage_disk' => 'secret-disk',
            'file_path' => 'private/current.pdf',
            'source_fingerprint' => str_repeat('a', 64),
        ]);

        $view = app(DocumentLibraryPresenter::class)->entry(
            $entry,
            new LibraryQuery(keyword: 'policy', page: 3),
        );

        self::assertSame('Charter.pdf', $view->originalFilename);
        self::assertSame('Constitution and By-Laws', $view->requirement);
        self::assertSame('Alpha Organization', $view->organizationName);
        self::assertSame('2026-2027', $view->accreditationYear);
        self::assertSame('2026-01-01', $view->submissionDate);
        self::assertSame('Recognition', $view->applicationKind);
        self::assertSame('Under Review', $view->applicationStatus);
        self::assertSame('Completed', $view->processingStatus);
        self::assertSame(
            ['Alpha Organization', 'Accreditation year 2026-2027'],
            $view->folderPath,
        );
        self::assertSame('Alpha Organization / Accreditation year 2026-2027', $view->folderPathText);
        self::assertFalse($view->detailsExpanded);
        self::assertTrue($view->detailsAvailable);

        self::assertNotNull($view->details);
        self::assertSame(CurrentDocumentAnalysisPresenter::ADVISORY, $view->details->advisory);
        self::assertSame('Constitution', $view->details->documentType);
        self::assertSame('Jan 2, 2026, 3:04 PM', $view->details->completionDate);
        self::assertSame('Review summary.', $view->details->summary);
        self::assertSame(['policy'], $view->details->tags);
        self::assertSame(
            ['President Name' => 'Alex Reviewer', 'Focus Areas' => 'Service, Research'],
            collect($view->details->context)->mapWithKeys(
                static fn ($field): array => [$field->label => $field->value],
            )->all(),
        );
        self::assertSame(
            route('osas.apply-org.analyses.source', ['applyOrg' => 7, 'analysis' => 91]),
            $view->viewDocumentUrl,
        );
        self::assertSame(route('osas.apply-org.show', ['applyOrg' => 7]), $view->fullCaseUrl);

        parse_str((string) parse_url($view->openFolderUrl, PHP_URL_QUERY), $openFolderQuery);
        self::assertArrayNotHasKey('q', $openFolderQuery);
        self::assertArrayNotHasKey('page', $openFolderQuery);
        self::assertSame('2026-2027', $openFolderQuery['year_folder']);
        self::assertSame('41', $openFolderQuery['highlight']);
        self::assertSame(
            OrganizationFolderIdentity::linked(12)->key(),
            app(OrganizationFolderToken::class)->verify($openFolderQuery['folder']),
        );

        $serialized = json_encode($view, JSON_THROW_ON_ERROR);
        foreach (['secret-disk', 'private/current.pdf', str_repeat('a', 64), 'not for presentation', 'raw processor failure'] as $secret) {
            self::assertStringNotContainsString($secret, $serialized);
        }
    }

    public function test_it_gates_generated_fields_and_maps_snapshot_processing_labels(): void
    {
        $presenter = app(DocumentLibraryPresenter::class);
        $query = new LibraryQuery;
        $statuses = [
            ProcessedDocument::STATUS_PENDING => 'Pending',
            ProcessedDocument::STATUS_PROCESSING => 'Processing',
            ProcessedDocument::STATUS_FAILED => 'Failed',
            ProcessedDocument::STATUS_COMPLETED => 'Completed',
        ];

        foreach ($statuses as $status => $label) {
            $view = $presenter->entry($this->entry([
                'analysis_id' => 90,
                'processing_status' => $status,
                'has_current_output' => 0,
                'summary' => 'stale summary',
                'tags' => json_encode(['stale'], JSON_THROW_ON_ERROR),
                'document_type_name' => 'Stale Type',
                'key_information' => json_encode(['school_year' => '2030-2031'], JSON_THROW_ON_ERROR),
                'error_message' => 'raw failure',
            ]), $query);

            self::assertSame($label, $view->processingStatus);
            self::assertSame('Unspecified Year', $view->accreditationYear);
            self::assertFalse($view->detailsAvailable);
            self::assertNull($view->details);
            self::assertStringNotContainsString('stale', json_encode($view, JSON_THROW_ON_ERROR));
            self::assertStringNotContainsString('raw failure', json_encode($view, JSON_THROW_ON_ERROR));
        }

        $unprocessed = $presenter->entry($this->entry(), $query);
        self::assertSame('Not Processed', $unprocessed->processingStatus);
        self::assertSame(
            route('osas.apply-org.library-documents.source', ['applyOrg' => 7, 'document' => 41]),
            $unprocessed->viewDocumentUrl,
        );
    }

    public function test_application_accreditation_year_wins_over_an_extracted_school_year(): void
    {
        $presenter = app(DocumentLibraryPresenter::class);
        $query = new LibraryQuery;

        $view = $presenter->entry($this->entry([
            'accreditation_year' => '2025-2026',
            'has_current_output' => 0,
            'key_information' => json_encode(['school_year' => '2030-2031'], JSON_THROW_ON_ERROR),
        ]), $query);

        self::assertSame('2025-2026', $view->accreditationYear);
        parse_str((string) parse_url($view->openFolderUrl, PHP_URL_QUERY), $parameters);
        self::assertSame('2025-2026', $parameters['year_folder']);
    }

    public function test_it_maps_an_explicit_page_without_exposing_query_models_or_entry_models(): void
    {
        $query = new LibraryQuery(keyword: 'charter', page: 2);
        $documentPage = new DocumentPage(
            entries: [$this->entry()],
            entryIds: [41],
            total: 30,
            firstResult: 26,
            lastResult: 30,
            currentPage: 2,
            perPage: 25,
            lastPage: 2,
            query: $query,
            pathParameters: ['folder' => 'trusted', 'year_folder' => '2026-2027'],
            canonicalUrl: 'https://example.test/library?page=2',
            previousPageUrl: 'https://example.test/library?page=1',
            nextPageUrl: null,
            firstPageUrl: 'https://example.test/library?page=1',
            lastPageUrl: 'https://example.test/library?page=2',
        );
        $identity = OrganizationFolderIdentity::linked(12);
        $path = new FolderResolution(
            level: 'year',
            breadcrumbs: [
                new FolderBreadcrumb('Document Library', '/root', false),
                new FolderBreadcrumb('Alpha Organization', '/organization', false),
                new FolderBreadcrumb('2026-2027', null, true),
            ],
            organization: new ResolvedOrganizationFolder(
                $identity,
                'Alpha Organization',
                [7],
                'trusted',
            ),
            year: AccreditationYear::parse('2026-2027'),
            parentUrl: '/organization',
            rootUrl: '/root',
            error: null,
        );

        $view = app(DocumentLibraryPresenter::class)->page($documentPage, $path);

        self::assertSame('2026-2027', $view->heading);
        self::assertSame(['Document Library', 'Alpha Organization', '2026-2027'], array_map(
            static fn ($breadcrumb): string => $breadcrumb->label,
            $view->breadcrumbs,
        ));
        self::assertSame('/organization', $view->parentUrl);
        self::assertSame('/root', $view->rootUrl);
        self::assertCount(1, $view->documents);
        self::assertTrue($view->detailsCollapsedByDefault);
        self::assertSame(['q' => 'charter', 'page' => 2], $view->queryParameters);
        self::assertSame(30, $view->pagination->total);
        self::assertSame(26, $view->pagination->firstResult);
        self::assertSame(30, $view->pagination->lastResult);
        self::assertSame('https://example.test/library?page=2', $view->canonicalUrl);
        self::assertNotInstanceOf(DocumentLibraryEntry::class, $view->documents[0]);
    }

    public function test_it_presents_safe_empty_validation_and_canonical_recovery_actions(): void
    {
        $query = new LibraryQuery(
            keyword: ' charter ',
            departments: ['Engineering'],
            page: 3,
        );
        $page = $this->documentPage([], [], $query, 0);
        $parsed = new ParsedLibraryQuery(
            query: $query,
            rejectedInputs: [
                'department' => ['A selected department filter is unavailable.'],
                'sort' => ['The selected sort option is unavailable.'],
            ],
            canonicalParameters: $query->toCanonicalParameters(),
        );

        $view = app(DocumentLibraryPresenter::class)->page($page, null, $parsed);

        self::assertSame('no_results', $view->emptyState?->kind);
        self::assertSame(0, $view->pagination->total);
        self::assertSame('charter', $view->queryParameters['q']);
        self::assertSame(3, $view->queryParameters['page']);
        self::assertSame($parsed->rejectedInputs, $view->validationMessages);

        $actions = collect($view->emptyState?->actions ?? [])->keyBy('label');
        self::assertStringContainsString('#document-library-search', $actions['Edit keyword']->url);
        self::assertStringNotContainsString('q=', $actions['Clear keyword']->url);
        self::assertStringContainsString('department%5B0%5D=Engineering', $actions['Clear keyword']->url);
        self::assertStringContainsString('q=charter', $actions['Clear filters']->url);
        self::assertStringNotContainsString('department', $actions['Clear filters']->url);
        self::assertStringNotContainsString('page=', $actions['Clear keyword and filters']->url);

        $emptyLibrary = app(DocumentLibraryPresenter::class)->page(
            $this->documentPage([], [], new LibraryQuery, 0),
        );
        self::assertSame('empty_library', $emptyLibrary->emptyState?->kind);
        self::assertSame('The Document Library contains zero authorized documents.', $emptyLibrary->emptyState?->message);
    }

    public function test_it_presents_folder_hierarchies_with_canonical_navigation_and_counts(): void
    {
        Schema::create('organization_settings', function (Blueprint $table): void {
            $table->temporary();
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->string('logo_url')->nullable();
            $table->timestamps();
        });
        OrganizationSettings::create([
            'organization_id' => 7,
            'logo_url' => 'https://example.test/alpha-logo.png',
        ]);

        try {
            $this->assertFolderHierarchies();
        } finally {
            Schema::dropIfExists('organization_settings');
        }
    }

    private function assertFolderHierarchies(): void
    {
        $presenter = app(DocumentLibraryPresenter::class);
        $tokens = app(OrganizationFolderToken::class);
        $query = new LibraryQuery(departments: ['Engineering'], page: 4);
        $identity = OrganizationFolderIdentity::linked(7);
        $token = $tokens->issue($identity);
        $rootUrl = url('/osas/smart-search?department%5B0%5D=Engineering&page=4');
        $root = new FolderResolution(
            level: 'root',
            breadcrumbs: [new FolderBreadcrumb('Document Library', null, true)],
            organization: null,
            year: null,
            parentUrl: null,
            rootUrl: $rootUrl,
            error: null,
        );

        $rootPage = $presenter->hierarchyPage([
            new OrganizationFolderAggregate(
                identity: $identity,
                displayName: 'Alpha Organization',
                acronym: 'AO',
                label: 'Alpha Organization',
                token: $token,
                documentCount: 12,
            ),
        ], $root, $query);

        self::assertSame('root', $rootPage->level);
        self::assertSame(12, $rootPage->pagination->total);
        self::assertCount(1, $rootPage->folders);
        self::assertSame('organization', $rootPage->folders[0]->type);
        self::assertSame('Alpha Organization', $rootPage->folders[0]->label);
        self::assertSame(12, $rootPage->folders[0]->documentCount);
        self::assertStringContainsString('folder=', $rootPage->folders[0]->url);
        self::assertStringNotContainsString('page=', $rootPage->folders[0]->url);
        self::assertSame('https://example.test/alpha-logo.png', $rootPage->folders[0]->logoUrl);

        $noSettings = $presenter->hierarchyPage([
            new OrganizationFolderAggregate(
                identity: OrganizationFolderIdentity::linked(999999),
                displayName: 'Missing Settings Org',
                acronym: 'MS',
                label: 'Missing Settings Org',
                token: $tokens->issue(OrganizationFolderIdentity::linked(999999)),
                documentCount: 1,
            ),
        ], $root, new LibraryQuery);
        self::assertNull($noSettings->folders[0]->logoUrl);

        $organization = new ResolvedOrganizationFolder(
            identity: $identity,
            label: 'Alpha Organization',
            applicationIds: [10],
            token: $token,
        );
        $organizationPath = new FolderResolution(
            level: 'organization',
            breadcrumbs: [
                new FolderBreadcrumb('Document Library', $rootUrl, false),
                new FolderBreadcrumb('Alpha Organization', null, true),
            ],
            organization: $organization,
            year: null,
            parentUrl: $rootUrl,
            rootUrl: $rootUrl,
            error: null,
        );
        $yearPage = $presenter->hierarchyPage(
            [new YearFolderAggregate(UnspecifiedYear::Value, 3)],
            $organizationPath,
            new LibraryQuery,
        );

        self::assertSame('organization', $yearPage->level);
        self::assertSame('accreditation_year', $yearPage->folders[0]->type);
        self::assertSame('Accreditation year Unspecified Year', $yearPage->folders[0]->label);
        self::assertStringContainsString('year_folder=unspecified', $yearPage->folders[0]->url);
        self::assertSame($rootUrl, $yearPage->parentUrl);
    }

    public function test_it_preserves_last_successful_view_for_retrieval_and_navigation_failures(): void
    {
        $query = new LibraryQuery(keyword: 'charter', page: 2);
        $successful = app(DocumentLibraryPresenter::class)->page(
            $this->documentPage([$this->entry()], [41], $query, 30),
        );
        $retryUrl = 'https://example.test/osas/smart-search?q=charter&page=2';

        $failed = app(DocumentLibraryPresenter::class)->retrievalFailure(
            $query,
            $retryUrl,
            $successful,
        );

        self::assertSame('retrieval_failure', $failed->failure?->kind);
        self::assertSame($retryUrl, $failed->failure?->retryUrl);
        self::assertSame($successful->canonicalUrl, $failed->canonicalUrl);
        self::assertSame($successful->queryParameters, $failed->queryParameters);
        self::assertSame($successful->pagination, $failed->pagination);
        self::assertSame($successful->documents, $failed->documents);

        $navigation = app(DocumentLibraryPresenter::class)->navigationFailure($successful, $retryUrl);
        self::assertSame('navigation_failure', $navigation->failure?->kind);
        self::assertSame($successful->breadcrumbs, $navigation->breadcrumbs);

        $notFound = app(DocumentLibraryPresenter::class)->notFound($query);
        self::assertSame('not_found', $notFound->failure?->kind);
        self::assertSame($query->toCanonicalParameters(), $notFound->queryParameters);
        self::assertStringContainsString('q=charter', $notFound->rootUrl);
        self::assertStringContainsString('page=2', $notFound->rootUrl);
        self::assertStringNotContainsString('database', json_encode($notFound, JSON_THROW_ON_ERROR));
    }

    public function test_it_isolates_missing_rows_and_marks_only_explicitly_unavailable_actions(): void
    {
        $available = $this->entry();
        $page = $this->documentPage([$available], [41, 99], new LibraryQuery, 2);

        $view = app(DocumentLibraryPresenter::class)->page($page);

        self::assertCount(2, $view->documents);
        self::assertTrue($view->documents[0]->entryAvailable);
        self::assertTrue($view->documents[0]->viewDocumentAvailable);
        self::assertTrue($view->documents[0]->fullCaseAvailable);
        self::assertFalse($view->documents[1]->entryAvailable);
        self::assertSame(99, $view->documents[1]->id);
        self::assertSame('This document entry is temporarily unavailable.', $view->documents[1]->unavailableMessage);
        self::assertFalse($view->documents[1]->viewDocumentAvailable);
        self::assertFalse($view->documents[1]->fullCaseAvailable);

        $unavailable = app(DocumentLibraryPresenter::class)->entry($this->entry([
            'source_available' => 0,
            'full_case_available' => false,
            'file_path' => 'private/hidden.pdf',
            'storage_disk' => 'private-disk',
        ]), new LibraryQuery);
        self::assertTrue($unavailable->entryAvailable);
        self::assertFalse($unavailable->viewDocumentAvailable);
        self::assertSame('View Document is unavailable for this entry.', $unavailable->viewDocumentUnavailableMessage);
        self::assertFalse($unavailable->fullCaseAvailable);
        self::assertSame('Full Case is unavailable for this entry.', $unavailable->fullCaseUnavailableMessage);
        self::assertStringNotContainsString('private/hidden.pdf', json_encode($unavailable, JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString('private-disk', json_encode($unavailable, JSON_THROW_ON_ERROR));
    }

    /**
     * @param list<DocumentLibraryEntry> $entries
     * @param list<int> $entryIds
     */
    private function documentPage(
        array $entries,
        array $entryIds,
        LibraryQuery $query,
        int $total,
    ): DocumentPage {
        $shown = count($entryIds);
        $first = $shown === 0 ? 0 : (($query->page - 1) * 25) + 1;
        $last = $shown === 0 ? 0 : $first + $shown - 1;
        $canonical = 'https://example.test/osas/smart-search'.(
            $query->toCanonicalParameters() === []
                ? ''
                : '?'.http_build_query($query->toCanonicalParameters())
        );

        return new DocumentPage(
            entries: $entries,
            entryIds: $entryIds,
            total: $total,
            firstResult: $first,
            lastResult: $last,
            currentPage: $query->page,
            perPage: 25,
            lastPage: max(1, (int) ceil($total / 25)),
            query: $query,
            pathParameters: [],
            canonicalUrl: $canonical,
            previousPageUrl: null,
            nextPageUrl: null,
            firstPageUrl: 'https://example.test/osas/smart-search',
            lastPageUrl: $canonical,
        );
    }

    /** @param array<string, mixed> $overrides */
    private function entry(array $overrides = []): DocumentLibraryEntry
    {
        return (new DocumentLibraryEntry)->forceFill(array_merge([
            'id' => 41,
            'application_id' => 7,
            'analysis_id' => null,
            'linked_organization_id' => 12,
            'linked_organization_name' => 'Alpha Organization',
            'linked_organization_acronym' => 'AO',
            'submitted_organization_name' => 'Submitted Alpha',
            'submitted_organization_acronym' => 'SA',
            'original_filename' => 'Charter.pdf',
            'requirement_label' => 'Constitution and By-Laws',
            'application_submitted_at' => '2026-01-01 08:00:00',
            'application_type' => 'apply',
            'request_type' => 'apply',
            'application_status' => 'under_review',
            'processing_status' => null,
            'has_current_output' => 0,
            'key_information' => null,
        ], $overrides));
    }
}
