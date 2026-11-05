<?php

namespace App\ViewModels;

use App\Models\DocumentLibraryEntry;
use App\Models\OrganizationSettings;
use App\Models\ProcessedDocument;
use Illuminate\Database\Eloquent\Model;
use App\Services\DocumentLibrary\AccreditationYear;
use App\Services\DocumentLibrary\DocumentPage;
use App\Services\DocumentLibrary\FolderResolution;
use App\Services\DocumentLibrary\LibraryQuery;
use App\Services\DocumentLibrary\LibraryUrlBuilder;
use App\Services\DocumentLibrary\OrganizationFolderAggregate;
use App\Services\DocumentLibrary\OrganizationFolderIdentity;
use App\Services\DocumentLibrary\OrganizationFolderToken;
use App\Services\DocumentLibrary\ParsedLibraryQuery;
use App\Services\DocumentLibrary\UnspecifiedYear;
use App\Services\DocumentLibrary\YearFolderAggregate;
use App\ViewModels\DocumentLibrary\AdvisoryDetailsView;
use App\ViewModels\DocumentLibrary\BreadcrumbView;
use App\ViewModels\DocumentLibrary\DocumentEntryView;
use App\ViewModels\DocumentLibrary\FolderView;
use App\ViewModels\DocumentLibrary\GeneratedContextFieldView;
use App\ViewModels\DocumentLibrary\LibraryEmptyStateView;
use App\ViewModels\DocumentLibrary\LibraryFailureView;
use App\ViewModels\DocumentLibrary\LibraryPageView;
use App\ViewModels\DocumentLibrary\PaginationView;
use App\ViewModels\DocumentLibrary\RecoveryActionView;
use DateTimeImmutable;
use DateTimeInterface;
use Throwable;

final readonly class DocumentLibraryPresenter
{
    private const SENSITIVE_CONTEXT_KEYS = [
        'disk', 'storage_disk', 'path', 'file_path', 'source_version',
        'source_fingerprint', 'fingerprint', 'extracted_text', 'error',
        'error_message', 'failure_code',
    ];

    private const CONTEXT_DISPLAY_LABELS = [
        'organization' => 'Organization',
        'school_year' => 'School Year',
        'dates' => 'Key Dates',
        'details' => 'Key Information',
    ];

    public function __construct(
        private OrganizationFolderToken $folderTokens,
        private LibraryUrlBuilder $urls,
    ) {}

    public function page(
        DocumentPage $page,
        ?FolderResolution $path = null,
        ?ParsedLibraryQuery $parsedQuery = null,
    ): LibraryPageView {
        if ($path !== null && ! $path->found()) {
            return $this->notFound($page->query);
        }

        return new LibraryPageView(
            heading: $path?->heading() ?? 'Document Library',
            breadcrumbs: $this->breadcrumbs($path),
            parentUrl: $path?->parentUrl,
            rootUrl: $path?->rootUrl ?? $this->urls->build($page->query),
            documents: $this->documentViews($page),
            queryParameters: $page->query->toCanonicalParameters(),
            pagination: $this->pagination($page),
            canonicalUrl: $page->canonicalUrl,
            validationMessages: $this->validationMessages($parsedQuery),
            emptyState: $this->emptyState($page, $path),
            level: $page->query->keyword !== null ? 'search' : ($path?->level ?? 'search'),
        );
    }

    /**
     * Present a root or organization hierarchy without manufacturing document rows.
     *
     * @param list<OrganizationFolderAggregate>|list<YearFolderAggregate> $folders
     */
    public function hierarchyPage(
        array $folders,
        FolderResolution $path,
        LibraryQuery $query,
        ?ParsedLibraryQuery $parsedQuery = null,
    ): LibraryPageView {
        if (! $path->found() || ! in_array($path->level, ['root', 'organization'], true)) {
            return $this->notFound($query);
        }

        $logos = $this->organizationLogos($folders);
        $folderViews = array_map(
            fn (OrganizationFolderAggregate|YearFolderAggregate $folder): FolderView => $this->folder(
                $folder,
                $path,
                $query,
                $logos,
            ),
            $folders,
        );
        $pathParameters = $path->organization === null
            ? []
            : ['folder' => $path->organization->token];
        $canonicalUrl = $this->urls->build($query, $pathParameters);
        $documentCount = array_sum(array_map(
            static fn (OrganizationFolderAggregate|YearFolderAggregate $folder): int => $folder->documentCount,
            $folders,
        ));

        return new LibraryPageView(
            heading: $path->heading(),
            breadcrumbs: $this->breadcrumbs($path),
            parentUrl: $path->parentUrl,
            rootUrl: $path->rootUrl,
            documents: [],
            queryParameters: $query->toCanonicalParameters(),
            pagination: new PaginationView(
                $documentCount,
                0,
                0,
                1,
                1,
                25,
                null,
                null,
                $canonicalUrl,
                $canonicalUrl,
            ),
            canonicalUrl: $canonicalUrl,
            validationMessages: $this->validationMessages($parsedQuery),
            emptyState: $this->folderEmptyState($folders, $path, $query),
            level: $path->level,
            folders: $folderViews,
        );
    }

    public function notFound(LibraryQuery $query): LibraryPageView
    {
        $rootUrl = $this->urls->build($query);

        return $this->failurePage(
            query: $query,
            canonicalUrl: $rootUrl,
            rootUrl: $rootUrl,
            failure: new LibraryFailureView(
                kind: 'not_found',
                title: 'Document library item not found',
                message: 'The requested library item is unavailable or no longer exists.',
                actions: [new RecoveryActionView('Open Document Library', $rootUrl)],
            ),
        );
    }

    public function retrievalFailure(
        LibraryQuery $query,
        string $retryUrl,
        ?LibraryPageView $lastSuccessfulPage = null,
    ): LibraryPageView {
        $rootUrl = $lastSuccessfulPage?->rootUrl ?? $this->urls->build($query);
        $failure = new LibraryFailureView(
            kind: 'retrieval_failure',
            title: 'Documents could not be loaded',
            message: 'The requested library view could not be loaded. Your current view has been kept.',
            actions: [
                new RecoveryActionView('Retry', $retryUrl),
                new RecoveryActionView('Open Document Library', $rootUrl),
            ],
            retryUrl: $retryUrl,
        );

        return $lastSuccessfulPage === null
            ? $this->failurePage($query, $retryUrl, $rootUrl, $failure)
            : $this->copyWithFailure($lastSuccessfulPage, $failure);
    }

    public function navigationFailure(
        LibraryPageView $lastSuccessfulPage,
        string $retryUrl,
    ): LibraryPageView {
        return $this->copyWithFailure($lastSuccessfulPage, new LibraryFailureView(
            kind: 'navigation_failure',
            title: 'Folder could not be opened',
            message: 'The requested folder could not be loaded. Your current location has been kept.',
            actions: [
                new RecoveryActionView('Retry', $retryUrl),
                new RecoveryActionView('Open Document Library', $lastSuccessfulPage->rootUrl),
            ],
            retryUrl: $retryUrl,
        ));
    }

    public function entry(Model $entry, LibraryQuery $query): DocumentEntryView
    {
        $id = $this->positiveId($entry->getAttribute('id'));
        $applicationId = $this->positiveId($entry->getAttribute('application_id'));
        $analysisId = $this->positiveId($entry->getAttribute('analysis_id'));
        $organizationName = $this->firstText(
            $entry->getAttribute('linked_organization_name'),
            $entry->getAttribute('submitted_organization_name'),
        ) ?? 'Unknown Organization';
        $organizationLabel = $organizationName;
        $identity = $this->organizationIdentity($entry);
        $folderToken = $this->folderTokens->issue($identity);
        $hasCurrentOutput = $this->hasCurrentOutput($entry);
        $rawContext = $hasCurrentOutput
            ? $this->decodedArray($entry->getAttribute('key_information'))
            : null;
        $applicationYear = AccreditationYear::parse(
            $this->safeText($entry->getAttribute('accreditation_year')),
        );
        $year = $applicationYear ?? $this->yearFromContext($rawContext);
        $folderPath = [$organizationLabel, 'Accreditation year '.$year->label()];
        $openFolderUrl = $this->urls->build($query->clearAll()->withPage(1), [
            'folder' => $folderToken,
            'year_folder' => $year->key(),
            'highlight' => (string) $id,
        ]);
        $details = $hasCurrentOutput ? $this->details($entry, $rawContext) : null;
        $sourceMarkedAvailable = $this->availableUnlessMarked($entry, 'source_available');
        $fullCaseMarkedAvailable = $this->availableUnlessMarked($entry, 'full_case_available');
        $viewDocumentUrl = $sourceMarkedAvailable
            ? $this->sourceUrl($applicationId, $analysisId, $id)
            : null;
        $fullCaseUrl = $applicationId !== null && $fullCaseMarkedAvailable
            ? route('osas.apply-org.show', ['applyOrg' => $applicationId])
            : null;

        return new DocumentEntryView(
            id: $id ?? 0,
            originalFilename: $this->safeText($entry->getAttribute('original_filename')) ?? 'Unnamed document',
            requirement: $this->safeText($entry->getAttribute('requirement_label')) ?? 'Document',
            organizationName: $organizationName,
            accreditationYear: $year->label(),
            submissionDate: $this->date($entry->getAttribute('application_submitted_at'), 'Y-m-d'),
            applicationKind: $this->applicationKind($entry),
            applicationStatus: $this->statusLabel($entry->getAttribute('application_status')),
            processingStatus: $this->processingLabel($analysisId, $entry->getAttribute('processing_status')),
            folderPath: $folderPath,
            folderPathText: implode(' / ', $folderPath),
            openFolderUrl: $openFolderUrl,
            detailsExpanded: false,
            detailsAvailable: $details !== null,
            details: $details,
            viewDocumentAvailable: $viewDocumentUrl !== null,
            viewDocumentUrl: $viewDocumentUrl,
            fullCaseAvailable: $fullCaseUrl !== null,
            fullCaseUrl: $fullCaseUrl,
            viewDocumentUnavailableMessage: $viewDocumentUrl === null
                ? 'View Document is unavailable for this entry.'
                : null,
            fullCaseUnavailableMessage: $fullCaseUrl === null
                ? 'Full Case is unavailable for this entry.'
                : null,
        );
    }

    /** @return list<BreadcrumbView> */
    private function breadcrumbs(?FolderResolution $path): array
    {
        if ($path === null) {
            return [new BreadcrumbView('Document Library', null, true)];
        }

        return array_map(
            static fn ($breadcrumb): BreadcrumbView => new BreadcrumbView(
                $breadcrumb->label,
                $breadcrumb->url,
                $breadcrumb->current,
            ),
            $path->breadcrumbs,
        );
    }

    /**
     * @param array<int, ?string> $logos
     */
    private function folder(
        OrganizationFolderAggregate|YearFolderAggregate $folder,
        FolderResolution $path,
        LibraryQuery $query,
        array $logos = [],
    ): FolderView {
        $query = $query->withPage(1);

        if ($folder instanceof OrganizationFolderAggregate && $path->level === 'root') {
            $organizationId = $folder->identity->organizationId;

            return new FolderView(
                type: 'organization',
                key: $folder->identity->key(),
                label: $folder->label,
                documentCount: $folder->documentCount,
                url: $this->urls->build($query, ['folder' => $folder->token]),
                logoUrl: $organizationId !== null ? ($logos[$organizationId] ?? null) : null,
            );
        }

        if ($folder instanceof YearFolderAggregate
            && $path->level === 'organization'
            && $path->organization !== null) {
            return new FolderView(
                type: 'accreditation_year',
                key: $folder->key(),
                label: 'Accreditation year '.$folder->label(),
                documentCount: $folder->documentCount,
                url: $this->urls->build($query, [
                    'folder' => $path->organization->token,
                    'year_folder' => $folder->key(),
                ]),
            );
        }

        throw new \InvalidArgumentException('Folder aggregate does not match the resolved hierarchy level.');
    }

    /**
     * Resolve linked organization logos with a single settings lookup.
     *
     * @param  list<OrganizationFolderAggregate>|list<YearFolderAggregate>  $folders
     * @return array<int, ?string>
     */
    private function organizationLogos(array $folders): array
    {
        $organizationIds = [];
        foreach ($folders as $folder) {
            if ($folder instanceof OrganizationFolderAggregate
                && $folder->identity->organizationId !== null) {
                $organizationIds[] = $folder->identity->organizationId;
            }
        }

        $organizationIds = array_values(array_unique($organizationIds));
        if ($organizationIds === []) {
            return [];
        }

        $logos = [];
        foreach (OrganizationSettings::query()
            ->whereIn('organization_id', $organizationIds)
            ->get() as $settings) {
            $logos[(int) $settings->organization_id] = $settings->getRawOriginal('logo_url')
                ? $settings->logo_url
                : null;
        }

        return $logos;
    }

    /**
     * @param list<OrganizationFolderAggregate>|list<YearFolderAggregate> $folders
     */
    private function folderEmptyState(
        array $folders,
        FolderResolution $path,
        LibraryQuery $query,
    ): ?LibraryEmptyStateView {
        if ($folders !== []) {
            return null;
        }

        $actions = [];
        if ($query->hasFilters()) {
            $pathParameters = $path->organization === null
                ? []
                : ['folder' => $path->organization->token];
            $actions[] = new RecoveryActionView(
                'Clear filters',
                $this->urls->build($this->withoutFilters($query), $pathParameters),
            );
        }
        if ($path->parentUrl !== null) {
            $actions[] = new RecoveryActionView('Open parent level', $path->parentUrl);
        }

        if ($query->hasFilters()) {
            return new LibraryEmptyStateView(
                kind: 'no_results',
                title: 'No documents found',
                message: 'No documents match the current filters.',
                actions: $actions,
            );
        }

        return $path->level === 'root'
            ? new LibraryEmptyStateView(
                kind: 'empty_library',
                title: 'No organization documents available',
                message: 'The Document Library contains zero authorized documents.',
                actions: [],
            )
            : new LibraryEmptyStateView(
                kind: 'empty_folder',
                title: 'No documents in this folder',
                message: 'No organization documents are available in this folder.',
                actions: $actions,
            );
    }

    /** @return list<DocumentEntryView> */
    private function documentViews(DocumentPage $page): array
    {
        $byId = [];
        foreach ($page->entries as $entry) {
            $id = $this->positiveId($entry->getAttribute('id'));
            if ($id !== null) {
                $byId[$id] = $entry;
            }
        }

        $ids = $page->entryIds !== [] ? $page->entryIds : array_keys($byId);
        $views = [];
        foreach ($ids as $id) {
            $entry = $byId[$id] ?? null;
            if (! $entry instanceof Model) {
                $views[] = $this->unavailableEntry((int) $id);
                continue;
            }

            try {
                $views[] = $this->entry($entry, $page->query);
            } catch (Throwable) {
                $views[] = $this->unavailableEntry((int) $id, $entry);
            }
        }

        return $views;
    }

    private function unavailableEntry(int $id, ?Model $entry = null): DocumentEntryView
    {
        $organizationName = $entry === null
            ? 'Unavailable organization'
            : ($this->firstText(
                $entry->getAttribute('linked_organization_name'),
                $entry->getAttribute('submitted_organization_name'),
            ) ?? 'Unavailable organization');
        $folderPath = [$organizationName, 'Accreditation year '.UnspecifiedYear::Value->label()];

        return new DocumentEntryView(
            id: max(0, $id),
            originalFilename: $entry === null
                ? "Document #{$id}"
                : ($this->safeText($entry->getAttribute('original_filename')) ?? "Document #{$id}"),
            requirement: $entry === null
                ? 'Document'
                : ($this->safeText($entry->getAttribute('requirement_label')) ?? 'Document'),
            organizationName: $organizationName,
            accreditationYear: UnspecifiedYear::Value->label(),
            submissionDate: $entry === null
                ? null
                : $this->date($entry->getAttribute('application_submitted_at'), 'Y-m-d'),
            applicationKind: $entry === null ? 'Unknown' : $this->applicationKind($entry),
            applicationStatus: $entry === null
                ? 'Unknown'
                : $this->statusLabel($entry->getAttribute('application_status')),
            processingStatus: 'Unavailable',
            folderPath: $folderPath,
            folderPathText: implode(' / ', $folderPath),
            openFolderUrl: null,
            detailsExpanded: false,
            detailsAvailable: false,
            details: null,
            viewDocumentAvailable: false,
            viewDocumentUrl: null,
            fullCaseAvailable: false,
            fullCaseUrl: null,
            entryAvailable: false,
            unavailableMessage: 'This document entry is temporarily unavailable.',
            viewDocumentUnavailableMessage: 'View Document is unavailable for this entry.',
            fullCaseUnavailableMessage: 'Full Case is unavailable for this entry.',
        );
    }

    private function pagination(DocumentPage $page): PaginationView
    {
        return new PaginationView(
            total: $page->total,
            firstResult: $page->firstResult,
            lastResult: $page->lastResult,
            currentPage: $page->currentPage,
            lastPage: $page->lastPage,
            perPage: $page->perPage,
            previousUrl: $page->previousPageUrl,
            nextUrl: $page->nextPageUrl,
            firstUrl: $page->firstPageUrl,
            lastUrl: $page->lastPageUrl,
        );
    }

    private function emptyState(
        DocumentPage $page,
        ?FolderResolution $path,
    ): ?LibraryEmptyStateView {
        if ($page->total !== 0) {
            return null;
        }

        $query = $page->query;
        $actions = [];
        if ($query->keyword !== null) {
            $actions[] = new RecoveryActionView(
                'Edit keyword',
                $page->canonicalUrl.'#document-library-search',
            );
            $actions[] = new RecoveryActionView(
                'Clear keyword',
                $this->urls->build($query->withoutKeyword(), $page->pathParameters),
            );
        }
        if ($query->hasFilters()) {
            $actions[] = new RecoveryActionView(
                'Clear filters',
                $this->urls->build($this->withoutFilters($query), $page->pathParameters),
            );
        }
        if ($query->keyword !== null || $query->hasFilters()) {
            $actions[] = new RecoveryActionView(
                'Clear keyword and filters',
                $this->urls->build($query->clearAll(), $page->pathParameters),
            );
        }
        if ($path?->parentUrl !== null) {
            $actions[] = new RecoveryActionView('Open parent level', $path->parentUrl);
        }

        if ($query->keyword !== null || $query->hasFilters()) {
            return new LibraryEmptyStateView(
                kind: 'no_results',
                title: 'No documents found',
                message: 'No documents match the current keyword and filters.',
                actions: $actions,
            );
        }
        if ($path !== null && $path->level !== 'root') {
            return new LibraryEmptyStateView(
                kind: 'empty_folder',
                title: 'No documents in this folder',
                message: 'No organization documents are available in this folder.',
                actions: $actions,
            );
        }

        return new LibraryEmptyStateView(
            kind: 'empty_library',
            title: 'No organization documents available',
            message: 'The Document Library contains zero authorized documents.',
            actions: [],
        );
    }

    /** @return array<string, list<string>> */
    private function validationMessages(?ParsedLibraryQuery $parsedQuery): array
    {
        if ($parsedQuery === null) {
            return [];
        }

        $messages = [];
        foreach ($parsedQuery->rejectedInputs as $field => $fieldMessages) {
            if (! is_string($field)) {
                continue;
            }
            foreach ($fieldMessages as $message) {
                $message = $this->safeText($message);
                if ($message !== null) {
                    $messages[$field][] = $message;
                }
            }
        }

        return $messages;
    }

    private function withoutFilters(LibraryQuery $query): LibraryQuery
    {
        return new LibraryQuery(keyword: $query->keyword, sort: $query->sort);
    }

    private function failurePage(
        LibraryQuery $query,
        string $canonicalUrl,
        string $rootUrl,
        LibraryFailureView $failure,
    ): LibraryPageView {
        return new LibraryPageView(
            heading: 'Document Library',
            breadcrumbs: [new BreadcrumbView('Document Library', null, true)],
            parentUrl: null,
            rootUrl: $rootUrl,
            documents: [],
            queryParameters: $query->toCanonicalParameters(),
            pagination: new PaginationView(0, 0, 0, $query->page, 1, 25, null, null, $rootUrl, $rootUrl),
            canonicalUrl: $canonicalUrl,
            failure: $failure,
        );
    }

    private function copyWithFailure(
        LibraryPageView $page,
        LibraryFailureView $failure,
    ): LibraryPageView {
        return new LibraryPageView(
            heading: $page->heading,
            breadcrumbs: $page->breadcrumbs,
            parentUrl: $page->parentUrl,
            rootUrl: $page->rootUrl,
            documents: $page->documents,
            queryParameters: $page->queryParameters,
            pagination: $page->pagination,
            canonicalUrl: $page->canonicalUrl,
            detailsCollapsedByDefault: $page->detailsCollapsedByDefault,
            validationMessages: $page->validationMessages,
            emptyState: $page->emptyState,
            failure: $failure,
            level: $page->level,
            folders: $page->folders,
        );
    }

    private function availableUnlessMarked(Model $entry, string $attribute): bool
    {
        $attributes = $entry->getAttributes();
        if (! array_key_exists($attribute, $attributes)) {
            return true;
        }

        return in_array($attributes[$attribute], [1, '1', true], true);
    }

    /** @param array<mixed>|null $rawContext */
    private function details(Model $entry, ?array $rawContext): AdvisoryDetailsView
    {
        return new AdvisoryDetailsView(
            advisory: CurrentDocumentAnalysisPresenter::ADVISORY,
            documentType: $this->firstText(
                $entry->getAttribute('document_type_name'),
                $entry->getAttribute('document_type'),
            ),
            completionDate: $this->date($entry->getAttribute('processed_at'), 'M j, Y, g:i A'),
            summary: $this->safeText($entry->getAttribute('summary')),
            tags: $this->tags($entry->getAttribute('tags')),
            context: $this->context($rawContext),
        );
    }

    private function organizationIdentity(Model $entry): OrganizationFolderIdentity
    {
        $linkedId = $this->positiveId($entry->getAttribute('linked_organization_id'));
        if ($linkedId !== null) {
            return OrganizationFolderIdentity::linked($linkedId);
        }

        return OrganizationFolderIdentity::legacy(
            $this->safeText($entry->getAttribute('submitted_organization_name')) ?? '',
            $this->safeText($entry->getAttribute('submitted_organization_acronym')) ?? '',
        );
    }

    /** @param array<mixed>|null $context */
    private function yearFromContext(?array $context): AccreditationYear|UnspecifiedYear
    {
        if ($context === null || ! array_key_exists('school_year', $context)) {
            return UnspecifiedYear::Value;
        }

        return AccreditationYear::fromCurrentOutput(
            $this->safeText($context['school_year']),
        );
    }

    private function hasCurrentOutput(Model $entry): bool
    {
        return in_array($entry->getAttribute('has_current_output'), [1, '1', true], true);
    }

    private function applicationKind(Model $entry): string
    {
        $applicationType = $this->safeText($entry->getAttribute('application_type'));
        $requestType = $this->safeText($entry->getAttribute('request_type'));

        return match (true) {
            $applicationType === 'apply' => 'Recognition',
            $applicationType === 'renew' => 'Renewal',
            $applicationType === null && $requestType === 'apply' => 'Recognition',
            $applicationType === null && $requestType === 'request' => 'Renewal',
            default => 'Unknown',
        };
    }

    private function processingLabel(?int $analysisId, mixed $status): string
    {
        if ($analysisId === null) {
            return 'Not Processed';
        }

        return match ($status) {
            ProcessedDocument::STATUS_PENDING => 'Pending',
            ProcessedDocument::STATUS_PROCESSING => 'Processing',
            ProcessedDocument::STATUS_COMPLETED => 'Completed',
            ProcessedDocument::STATUS_FAILED => 'Failed',
            default => 'Not Processed',
        };
    }

    private function statusLabel(mixed $status): string
    {
        $status = $this->safeText($status);
        if ($status === null) {
            return 'Unknown';
        }

        return mb_convert_case(str_replace(['_', '-'], ' ', $status), MB_CASE_TITLE, 'UTF-8');
    }

    /** @return list<string> */
    private function tags(mixed $value): array
    {
        $decoded = $this->decodedArray($value);
        if ($decoded === null || ! array_is_list($decoded)) {
            return [];
        }

        $tags = [];
        foreach ($decoded as $tag) {
            $tag = $this->safeText($tag);
            if ($tag !== null && ! in_array($tag, $tags, true)) {
                $tags[] = $tag;
            }
        }

        return $tags;
    }

    /**
     * @param array<mixed>|null $values
     * @return list<GeneratedContextFieldView>
     */
    private function context(?array $values): array
    {
        if ($values === null || array_is_list($values)) {
            return [];
        }

        $fields = [];
        foreach ($values as $key => $value) {
            if (! is_string($key)) {
                continue;
            }

            $normalizedKey = $this->normalizedContextKey($key);
            if ($normalizedKey === 'school_year'
                || in_array($normalizedKey, self::SENSITIVE_CONTEXT_KEYS, true)
                || $normalizedKey === 'entities'
                || str_starts_with($normalizedKey, 'entities_')) {
                continue;
            }

            $label = $this->contextLabel($key);
            $displayValue = $this->contextValue($value);
            if ($normalizedKey === 'organization' && $displayValue !== null) {
                $displayValue = $this->organizationDisplayName($displayValue);
            }
            if ($label !== null && $displayValue !== null) {
                $fields[] = new GeneratedContextFieldView($label, $displayValue);
            }
        }

        return $fields;
    }

    private function organizationDisplayName(string $value): string
    {
        $trimmed = trim($value);
        $letters = preg_replace('/[^A-Za-z]/', '', $trimmed) ?? '';

        if (strlen($letters) <= 3 || $letters !== mb_strtoupper($letters, 'UTF-8')) {
            return $trimmed;
        }

        $smallWords = ['of', 'and', 'the', 'or', 'for', 'in', 'on', 'with', 'by', 'at', 'a', 'an'];
        $words = preg_split('/\s+/', $trimmed) ?: [];

        foreach ($words as $index => $word) {
            if (preg_match('/^[A-Z0-9&\'.-]+$/', $word) && strlen($word) >= 2) {
                continue;
            }

            $lower = mb_strtolower($word, 'UTF-8');
            $words[$index] = in_array($lower, $smallWords, true) && $index !== 0
                ? $lower
                : mb_strtoupper(mb_substr($lower, 0, 1, 'UTF-8'), 'UTF-8').mb_substr($lower, 1, null, 'UTF-8');
        }

        return implode(' ', $words);
    }

    private function contextValue(mixed $value): ?string
    {
        if (is_string($value)) {
            return $this->safeText($value);
        }
        if (is_int($value) || is_float($value)) {
            return is_float($value) && ! is_finite($value) ? null : (string) $value;
        }
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }
        if (! is_array($value) || ! array_is_list($value)) {
            return null;
        }

        $items = [];
        foreach ($value as $item) {
            $item = is_string($item) ? $this->safeText($item) : null;
            if ($item !== null) {
                $items[] = $item;
            }
        }

        return $items === [] ? null : implode(', ', $items);
    }

    private function contextLabel(string $key): ?string
    {
        $normalized = $this->normalizedContextKey($key);
        if (isset(self::CONTEXT_DISPLAY_LABELS[$normalized])) {
            return self::CONTEXT_DISPLAY_LABELS[$normalized];
        }

        $key = $this->safeText(str_replace(['_', '-'], ' ', $key));

        return $key === null ? null : mb_convert_case($key, MB_CASE_TITLE, 'UTF-8');
    }

    private function normalizedContextKey(string $key): string
    {
        $key = mb_strtolower(trim($key), 'UTF-8');
        $key = preg_replace('/[\s-]+/u', '_', $key);

        return $key ?? '';
    }

    /** @return array<mixed>|null */
    private function decodedArray(mixed $value): ?array
    {
        if (is_array($value)) {
            return $value;
        }
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            $decoded = json_decode($value, true, 16, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    private function sourceUrl(?int $applicationId, ?int $analysisId, ?int $entryId): ?string
    {
        if ($applicationId === null) {
            return null;
        }
        if ($analysisId !== null) {
            return route('osas.apply-org.analyses.source', [
                'applyOrg' => $applicationId,
                'analysis' => $analysisId,
            ]);
        }
        if ($entryId === null) {
            return null;
        }

        return route('osas.apply-org.library-documents.source', [
            'applyOrg' => $applicationId,
            'document' => $entryId,
        ]);
    }

    private function date(mixed $value, string $format): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format($format);
        }
        $value = $this->safeText($value);
        if ($value === null) {
            return null;
        }

        try {
            return (new DateTimeImmutable($value))->format($format);
        } catch (Throwable) {
            return null;
        }
    }

    private function positiveId(mixed $value): ?int
    {
        if (! is_int($value) && ! (is_string($value) && ctype_digit($value))) {
            return null;
        }
        $value = (int) $value;

        return $value > 0 ? $value : null;
    }

    private function firstText(mixed ...$values): ?string
    {
        foreach ($values as $value) {
            $value = $this->safeText($value);
            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    private function safeText(mixed $value): ?string
    {
        if (! is_string($value) || ! mb_check_encoding($value, 'UTF-8')) {
            return null;
        }

        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);
        $value = $value === null ? '' : trim($value);

        return $value === '' ? null : $value;
    }
}
