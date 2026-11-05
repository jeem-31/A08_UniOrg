<?php

namespace Tests\Unit;

use App\ViewModels\DocumentLibrary\BreadcrumbView;
use App\ViewModels\DocumentLibrary\DocumentEntryView;
use App\ViewModels\DocumentLibrary\FolderView;
use App\ViewModels\DocumentLibrary\LibraryPageView;
use App\ViewModels\DocumentLibrary\PaginationView;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/** **Validates: Requirements 1.2, 2.5, 2.9, 3.1, 3.2, 3.4, 4.1, 4.2, 4.3, 4.5, 4.6, 4.10, 4.11, 5.7, 6.16** */
final class DocumentLibraryBladeComponentsTest extends TestCase
{
    public function test_it_renders_folder_first_hierarchy_with_breadcrumbs_back_action_and_counts(): void
    {
        $library = $this->page(
            level: 'organization',
            heading: 'Alpha Organization',
            breadcrumbs: [
                new BreadcrumbView('Document Library', '/osas/smart-search', false),
                new BreadcrumbView('Alpha Organization', null, true),
            ],
            parentUrl: '/osas/smart-search?department%5B0%5D=CCS',
            folders: [
                new FolderView('accreditation_year', '2025-2026', 'Accreditation year 2025-2026', 7, '/osas/smart-search?folder=signed&year_folder=2025-2026'),
            ],
            pagination: $this->pagination(total: 7),
        );

        $html = $this->renderPage($library);

        self::assertStringContainsString('Document Library breadcrumb', $html);
        self::assertStringContainsString('aria-current="page"', $html);
        self::assertStringContainsString('Back to parent folder', $html);
        self::assertStringContainsString('Alpha Organization', $html);
        self::assertStringContainsString('Accreditation year folder', $html);
        self::assertStringContainsString('7 documents', $html);
        self::assertStringContainsString('aria-labelledby="document-library-folder-1-label document-library-folder-1-type document-library-folder-1-count"', $html);
        self::assertStringNotContainsString('Semantic search across AI-verified', $html);
    }

    public function test_it_renders_organization_logo_on_folder_cards_when_available(): void
    {
        $library = $this->page(
            level: 'root',
            heading: 'Document Library',
            breadcrumbs: [new BreadcrumbView('Document Library', null, true)],
            folders: [
                new FolderView('organization', 'linked:1', 'Alpha Society', 5, '/osas/smart-search?folder=signed', 'https://example.test/alpha-logo.png'),
                new FolderView('organization', 'legacy:abc', 'Beta Guild', 3, '/osas/smart-search?folder=signed2'),
            ],
            pagination: $this->pagination(total: 8),
        );

        $html = $this->renderPage($library);

        self::assertStringContainsString('<img src="https://example.test/alpha-logo.png"', $html);
        self::assertStringContainsString('object-contain', $html);
        self::assertStringContainsString('ph-folder', $html);
        self::assertStringNotContainsString('src="https://example.test/beta-logo.png"', $html);
    }

    public function test_it_renders_compact_document_search_result_row_with_folder_directory(): void
    {
        $document = new DocumentEntryView(
            id: 41,
            originalFilename: 'Constitution & By-Laws.pdf',
            requirement: 'Constitution and By-Laws',
            organizationName: 'Alpha Organization',
            accreditationYear: '2025-2026',
            submissionDate: '2026-01-01',
            applicationKind: 'Recognition',
            applicationStatus: 'Approved',
            processingStatus: 'Completed',
            folderPath: ['Alpha Organization', 'Accreditation year 2025-2026'],
            folderPathText: 'Alpha Organization / Accreditation year 2025-2026',
            openFolderUrl: '/osas/smart-search?folder=signed&amp;year_folder=2025-2026&amp;highlight=41',
            detailsExpanded: false,
            detailsAvailable: false,
            details: null,
            viewDocumentAvailable: true,
            viewDocumentUrl: '/osas/apply-org/7/analyses/91/source',
            fullCaseAvailable: false,
            fullCaseUrl: null,
        );

        $library = $this->page(
            level: 'search',
            heading: 'Search results',
            breadcrumbs: [new BreadcrumbView('Document Library', '/osas/smart-search', false)],
            documents: [$document],
            pagination: $this->pagination(total: 30, first: 26, last: 30, current: 2, lastPage: 2),
        );

        $html = $this->renderPage($library);

        foreach (['Constitution &amp; By-Laws.pdf', 'Constitution and By-Laws', 'Alpha Organization', '2026-01-01', 'Recognition'] as $value) {
            self::assertStringContainsString($value, $html);
        }
        self::assertStringNotContainsString('Alpha Organization / Accreditation year 2025-2026', $html);
        self::assertStringContainsString('aria-label="Open Folder containing Constitution &amp; By-Laws.pdf"', $html);
        self::assertStringContainsString('Open Folder', $html);
        self::assertStringContainsString('View Document', $html);
        self::assertStringContainsString('Showing 26–30 of 30', $html);
        self::assertMatchesRegularExpression('/30 documents found\.\s*Showing 26 through 30\./', $html);
        self::assertStringNotContainsString('Approved', $html);
        self::assertStringNotContainsString('Completed', $html);
        self::assertStringNotContainsString('Full Case', $html);
        self::assertStringNotContainsString('<details', $html);
        self::assertStringNotContainsString('storage_disk', $html);
        self::assertStringNotContainsString('extracted_text', $html);
    }

    public function test_it_renders_canonical_search_filter_sort_clear_and_validation_controls(): void
    {
        $queryParameters = [
            'q' => 'policy',
            'department' => ['Engineering', 'Arts'],
            'year_from' => '2025',
            'requirement' => ['Constitution and By-Laws'],
            'document_type' => ['Constitution'],
            'application_kind' => ['Recognition'],
            'application_status' => ['Approved'],
            'submitted_from' => '2025-01-01',
            'submitted_to' => '2025-12-31',
            'sort' => 'filename_desc',
            'page' => 3,
        ];
        $canonicalUrl = route('osas.smart-search', array_merge($queryParameters, [
            'folder' => 'signed-folder',
            'year_folder' => '2025-2026',
        ]));
        $library = $this->page(
            level: 'search',
            heading: 'Search results',
            breadcrumbs: [new BreadcrumbView('Document Library', '/osas/smart-search', false)],
            queryParameters: $queryParameters,
            validationMessages: [
                'department' => ['A selected department filter is unavailable.'],
                'sort' => ['The selected sort option is unavailable.'],
            ],
            canonicalUrl: $canonicalUrl,
        );
        $options = [
            'department' => ['Engineering', 'Arts'],
            'year' => ['2025'],
            'requirement' => ['Constitution and By-Laws'],
            'document_type' => ['Constitution'],
            'application_kind' => ['Recognition'],
            'application_status' => ['Approved'],
        ];

        $html = $this->renderPage($library, $options);
        $decoded = html_entity_decode($html, ENT_QUOTES | ENT_HTML5);

        foreach (['Colleges', 'Accreditation Year'] as $label) {
            self::assertStringContainsString($label, $html);
        }
        foreach (['department', 'year_from', 'sort'] as $name) {
            self::assertStringContainsString('name="'.$name.'"', $html);
        }
        self::assertStringContainsString('value="policy"', $html);
        self::assertStringContainsString('<option value="Engineering" selected>Engineering</option>', $html);
        self::assertStringContainsString('<option value="filename_desc" selected>Filename Z–A</option>', $html);
        self::assertStringContainsString('id="document-library-filter-heading"', $html);
        self::assertStringContainsString('id="document-library-filter-panel"', $html);
        self::assertStringContainsString('Active filters', $html);
        self::assertStringContainsString('Colleges: Engineering', $html);
        self::assertStringContainsString('Some library query values could not be used', $html);
        self::assertStringContainsString('A selected department filter is unavailable.', $html);
        self::assertStringContainsString('The selected sort option is unavailable.', $html);
        self::assertStringNotContainsString('name="page"', $html);

        self::assertMatchesRegularExpression('/href="([^"]+)"[^>]*>Clear All<\/a>/', $decoded);
        preg_match('/href="([^"]+)"[^>]*>Clear All<\/a>/', $decoded, $clearAllMatch);
        parse_str((string) parse_url($clearAllMatch[1], PHP_URL_QUERY), $clearAllQuery);
        self::assertSame('filename_desc', $clearAllQuery['sort']);
        self::assertSame('signed-folder', $clearAllQuery['folder']);
        self::assertSame('2025-2026', $clearAllQuery['year_folder']);
        foreach (['q', 'department', 'year_from', 'submitted_from', 'submitted_to', 'page'] as $removed) {
            self::assertArrayNotHasKey($removed, $clearAllQuery);
        }

        preg_match('/href="([^"]+)"[^>]*aria-label="Clear search keyword policy"/', $decoded, $clearKeywordMatch);
        parse_str((string) parse_url($clearKeywordMatch[1], PHP_URL_QUERY), $clearKeywordQuery);
        self::assertArrayNotHasKey('q', $clearKeywordQuery);
        self::assertArrayNotHasKey('page', $clearKeywordQuery);
        self::assertSame(['Engineering', 'Arts'], $clearKeywordQuery['department']);
        self::assertSame('filename_desc', $clearKeywordQuery['sort']);
    }

    public function test_it_renders_beautified_filter_labels_while_preserving_option_values(): void
    {
        $library = $this->page(
            level: 'search',
            heading: 'Search results',
            breadcrumbs: [new BreadcrumbView('Document Library', '/osas/smart-search', false)],
            queryParameters: [
                'department' => ['College of Engineering'],
                'year_from' => '2025',
            ],
            canonicalUrl: '/osas/smart-search?department%5B0%5D=College%20of%20Engineering&year_from=2025',
        );
        $options = [
            'department' => ['College of Engineering'],
            'year' => ['2025'],
        ];
        $labels = [
            'department' => ['College of Engineering' => 'COE'],
        ];

        $html = $this->renderPage($library, $options, $labels);
        $decoded = html_entity_decode($html, ENT_QUOTES | ENT_HTML5);

        self::assertStringContainsString('value="College of Engineering"', $html);
        self::assertStringContainsString('>COE</option>', $html);
        self::assertStringContainsString('value="2025" selected', $html);
        self::assertStringContainsString('Colleges: COE', $decoded);
        self::assertStringContainsString('Accreditation Year: 2025', $decoded);
    }

    public function test_it_marks_unavailable_entry_actions_without_exposing_links(): void
    {
        $document = new DocumentEntryView(
            id: 99,
            originalFilename: 'Unavailable.pdf',
            requirement: 'Application Form',
            organizationName: 'Beta Guild',
            accreditationYear: 'Unspecified Year',
            submissionDate: null,
            applicationKind: 'Renewal',
            applicationStatus: 'Pending',
            processingStatus: 'Not Processed',
            folderPath: ['Beta Guild', 'Accreditation year Unspecified Year'],
            folderPathText: 'Beta Guild / Accreditation year Unspecified Year',
            openFolderUrl: null,
            detailsExpanded: false,
            detailsAvailable: false,
            details: null,
            viewDocumentAvailable: false,
            viewDocumentUrl: null,
            fullCaseAvailable: false,
            fullCaseUrl: null,
            entryAvailable: true,
            viewDocumentUnavailableMessage: 'View Document is unavailable for this entry.',
            fullCaseUnavailableMessage: 'Full Case is unavailable for this entry.',
        );

        $html = Blade::render(
            '<x-document-library.document-entry :document="$document" />',
            ['document' => $document],
        );

        self::assertStringContainsString('View Document unavailable', $html);
        self::assertStringContainsString('role="link"', $html);
        self::assertStringContainsString('aria-disabled="true"', $html);
        self::assertStringContainsString('aria-describedby="document-99-view-unavailable-message"', $html);
        self::assertStringNotContainsString('Full Case', $html);
        self::assertStringNotContainsString('<details', $html);
        self::assertStringNotContainsString('target="_blank"', $html);
    }

    public function test_it_associates_and_focuses_errors_and_exposes_document_disclosure_semantics(): void
    {
        $document = new DocumentEntryView(
            id: 7,
            originalFilename: 'A very long complete filename for accessibility.pdf',
            requirement: 'Constitution and By-Laws',
            organizationName: 'Alpha Organization',
            accreditationYear: '2025-2026',
            submissionDate: '2026-01-01',
            applicationKind: 'Recognition',
            applicationStatus: 'Approved',
            processingStatus: 'Completed',
            folderPath: ['Alpha Organization', 'Accreditation year 2025-2026'],
            folderPathText: 'Alpha Organization / Accreditation year 2025-2026',
            openFolderUrl: null,
            detailsExpanded: false,
            detailsAvailable: false,
            details: null,
            viewDocumentAvailable: true,
            viewDocumentUrl: '/source',
            fullCaseAvailable: false,
            fullCaseUrl: null,
        );
        $library = $this->page(
            level: 'search',
            heading: 'Search results',
            breadcrumbs: [new BreadcrumbView('Document Library', '/osas/smart-search', false)],
            documents: [$document],
            pagination: $this->pagination(total: 1, first: 1, last: 1),
            validationMessages: ['department' => ['A selected department filter is unavailable.']],
        );

        $html = $this->renderPage($library, ['department' => ['Engineering']]);

        self::assertMatchesRegularExpression('/id="document-library-validation-summary"[\s\S]*?tabindex="-1"[\s\S]*?autofocus/', $html);
        self::assertStringContainsString('href="#document-library-filter-department"', $html);
        self::assertStringContainsString('aria-invalid="true"', $html);
        self::assertStringContainsString('aria-describedby="department-filter-error"', $html);
        self::assertStringContainsString('aria-describedby="document-library-validation-summary"', $html);
        self::assertStringContainsString('aria-labelledby="document-7-filename"', $html);
        self::assertStringContainsString('aria-label="View Document: A very long complete filename for accessibility.pdf (opens a preview)"', $html);
        self::assertStringContainsString('A very long complete filename for accessibility.pdf', $html);
        self::assertStringContainsString('sm:flex-row', $html);
        self::assertStringNotContainsString('<details', $html);
        self::assertMatchesRegularExpression('/1 document found\.\s*Showing the only match\./', $html);

        $failure = new \App\ViewModels\DocumentLibrary\LibraryFailureView(
            kind: 'retrieval_failure',
            title: 'Documents could not be loaded',
            message: 'The requested library view could not be loaded.',
            actions: [new \App\ViewModels\DocumentLibrary\RecoveryActionView('Retry', '/retry')],
            retryUrl: '/retry',
        );
        $failureHtml = Blade::render(
            '<x-document-library.feedback :state="$failure" tone="error" id="retrieval-error" :should-focus="true" />',
            ['failure' => $failure],
        );

        self::assertStringContainsString('id="retrieval-error"', $failureHtml);
        self::assertStringContainsString('role="alert"', $failureHtml);
        self::assertStringContainsString('aria-labelledby="retrieval-error-title"', $failureHtml);
        self::assertStringContainsString('aria-describedby="retrieval-error-message"', $failureHtml);
        self::assertStringContainsString('tabindex="-1" autofocus', $failureHtml);
        self::assertStringContainsString('Retry', $failureHtml);
    }

    /**
     * @param list<BreadcrumbView> $breadcrumbs
     * @param list<DocumentEntryView> $documents
     * @param list<FolderView> $folders
     */
    private function page(
        string $level,
        string $heading,
        array $breadcrumbs,
        ?string $parentUrl = null,
        array $documents = [],
        array $folders = [],
        ?PaginationView $pagination = null,
        array $queryParameters = [],
        array $validationMessages = [],
        string $canonicalUrl = '/osas/smart-search',
    ): LibraryPageView {
        return new LibraryPageView(
            heading: $heading,
            breadcrumbs: $breadcrumbs,
            parentUrl: $parentUrl,
            rootUrl: '/osas/smart-search',
            documents: $documents,
            queryParameters: $queryParameters,
            pagination: $pagination ?? $this->pagination(),
            canonicalUrl: $canonicalUrl,
            validationMessages: $validationMessages,
            level: $level,
            folders: $folders,
        );
    }

    private function pagination(
        int $total = 0,
        int $first = 0,
        int $last = 0,
        int $current = 1,
        int $lastPage = 1,
    ): PaginationView {
        return new PaginationView(
            total: $total,
            firstResult: $first,
            lastResult: $last,
            currentPage: $current,
            lastPage: $lastPage,
            perPage: 25,
            previousUrl: $current > 1 ? '/osas/smart-search?page='.($current - 1) : null,
            nextUrl: $current < $lastPage ? '/osas/smart-search?page='.($current + 1) : null,
            firstUrl: '/osas/smart-search?page=1',
            lastUrl: '/osas/smart-search?page='.$lastPage,
        );
    }

    /**
     * @param array<string, list<string>> $filterOptions
     * @param array<string, array<string, string>> $filterLabels
     */
    private function renderPage(
        LibraryPageView $library,
        array $filterOptions = [],
        array $filterLabels = [],
    ): string {
        return Blade::render(
            '<x-document-library.page :library="$library" :filter-options="$filterOptions" :filter-labels="$filterLabels" />',
            ['library' => $library, 'filterOptions' => $filterOptions, 'filterLabels' => $filterLabels],
        );
    }
}
