<?php

namespace App\Services\DocumentLibrary;

use App\Models\Department;
use App\Models\DocumentLibraryEntry;
use App\Models\User;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

final class DocumentLibraryQueryService
{
    public const DOCUMENTS_PER_PAGE = 25;

    private const RELATION_ALIAS = 'authorized_entries';

    private const FILTER_YEARS_BACK = 10;

    private const FILTER_YEARS_END = 2036;

    public function __construct(
        private readonly CurrentLibraryOutputScope $currentOutput,
        private readonly OrganizationFolderToken $folderTokens,
        private readonly LibraryUrlBuilder $urls,
    ) {}

    /**
     * Aggregate the authorized filtered ID set into canonical organization folders.
     *
     * SQL first reduces the set to one row per application and display identity. The
     * small result is then merged by the PHP Unicode-aware legacy identity contract,
     * which keeps grouping identical on every supported database dialect.
     *
     * @return list<OrganizationFolderAggregate>
     */
    public function organizationFolders(User $user, LibraryQuery $query): array
    {
        if ($query->archiveScope === 'current') {
            return $this->organizationFoldersForLive($user, $query);
        }
        if ($query->archiveScope === 'archive') {
            return $this->organizationFoldersForArchived($user, $query);
        }

        // All (null) — union live + archived so SOCCS appears with combined counts
        // but year folders already handle per-year split. Here we sum document counts
        // per canonical identity across both stores.
        $live = $this->organizationFoldersForLive($user, $query);
        $archived = $this->organizationFoldersForArchived($user, $query);

        if ($archived === []) {
            return $live;
        }
        if ($live === []) {
            return $archived;
        }

        /** @var array<string, OrganizationFolderAggregate> $merged */
        $merged = [];
        foreach ($live as $folder) {
            $merged[$folder->identity->key()] = $folder;
        }
        foreach ($archived as $folder) {
            $key = $folder->identity->key();
            if (isset($merged[$key])) {
                $existing = $merged[$key];
                // Keep earliest live label but sum counts
                $merged[$key] = new OrganizationFolderAggregate(
                    identity: $existing->identity,
                    displayName: $existing->displayName,
                    acronym: $existing->acronym,
                    label: $existing->label,
                    token: $existing->token,
                    documentCount: $existing->documentCount + $folder->documentCount,
                );
            } else {
                $merged[$key] = $folder;
            }
        }

        $folders = array_values($merged);
        usort($folders, function (
            OrganizationFolderAggregate $left,
            OrganizationFolderAggregate $right,
        ): int {
            $byName = strcmp(
                $this->organizationSortKey($left->displayName),
                $this->organizationSortKey($right->displayName),
            );
            return $byName !== 0
                ? $byName
                : strcmp($left->identity->key(), $right->identity->key());
        });

        return $folders;
    }

    /**
     * @return list<OrganizationFolderAggregate>
     */
    private function organizationFoldersForLive(User $user, LibraryQuery $query): array
    {
        return $this->collectOrganizationFolders(
            $this->filteredEntriesForLive($user, $query),
        );
    }

    /**
     * @return list<OrganizationFolderAggregate>
     */
    private function organizationFoldersForArchived(User $user, LibraryQuery $query): array
    {
        if (! $this->archivedTablesExist()) {
            return [];
        }
        try {
            return $this->collectOrganizationFolders(
                $this->filteredEntriesForArchived($user, $query),
            );
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * @param Builder $filtered already filtered (live or archived)
     * @return list<OrganizationFolderAggregate>
     */
    private function collectOrganizationFolders(Builder $filtered): array
    {
        $alias = self::RELATION_ALIAS;
        $rows = (clone $filtered)
            ->reorder()
            ->select([
                "{$alias}.application_id",
                "{$alias}.linked_organization_id",
                "{$alias}.linked_organization_name",
                "{$alias}.linked_organization_acronym",
                "{$alias}.submitted_organization_name",
                "{$alias}.submitted_organization_acronym",
            ])
            ->selectRaw("COUNT(DISTINCT {$alias}.id) as document_count")
            ->groupBy([
                "{$alias}.application_id",
                "{$alias}.linked_organization_id",
                "{$alias}.linked_organization_name",
                "{$alias}.linked_organization_acronym",
                "{$alias}.submitted_organization_name",
                "{$alias}.submitted_organization_acronym",
            ])
            ->toBase()
            ->get();

        /** @var array<string, array{identity: OrganizationFolderIdentity, application_id: int, name: string, acronym: ?string, count: int}> $grouped */
        $grouped = [];

        foreach ($rows as $row) {
            $linkedId = $row->linked_organization_id === null
                ? null
                : (int) $row->linked_organization_id;
            $identity = $linkedId === null
                ? OrganizationFolderIdentity::legacy(
                    (string) $row->submitted_organization_name,
                    (string) $row->submitted_organization_acronym,
                )
                : OrganizationFolderIdentity::linked($linkedId);
            $key = $identity->key();
            $applicationId = (int) $row->application_id;
            $name = $linkedId === null
                ? (string) $row->submitted_organization_name
                : $this->firstNonEmpty(
                    $row->linked_organization_name,
                    $row->submitted_organization_name,
                );
            $acronym = $linkedId === null
                ? $this->nullableTrimmed($row->submitted_organization_acronym)
                : $this->nullableTrimmed(
                    $this->firstNonEmpty(
                        $row->linked_organization_acronym,
                        $row->submitted_organization_acronym,
                    ),
                );

            if (! isset($grouped[$key])) {
                $grouped[$key] = [
                    'identity' => $identity,
                    'application_id' => $applicationId,
                    'name' => trim($name),
                    'acronym' => $acronym,
                    'count' => 0,
                ];
            } elseif ($applicationId < $grouped[$key]['application_id']) {
                $grouped[$key]['application_id'] = $applicationId;
                $grouped[$key]['name'] = trim($name);
                $grouped[$key]['acronym'] = $acronym;
            }

            $grouped[$key]['count'] += (int) $row->document_count;
        }

        $folders = array_map(function (array $folder): OrganizationFolderAggregate {
            $label = $folder['name'];
            return new OrganizationFolderAggregate(
                identity: $folder['identity'],
                displayName: $folder['name'],
                acronym: $folder['acronym'],
                label: $label,
                token: $this->folderTokens->issue($folder['identity']),
                documentCount: $folder['count'],
            );
        }, array_values($grouped));

        usort($folders, function (
            OrganizationFolderAggregate $left,
            OrganizationFolderAggregate $right,
        ): int {
            $byName = strcmp(
                $this->organizationSortKey($left->displayName),
                $this->organizationSortKey($right->displayName),
            );
            return $byName !== 0
                ? $byName
                : strcmp($left->identity->key(), $right->identity->key());
        });

        return $folders;
    }

    /**
     * Aggregate one trusted organization scope into current-output-derived years.
     *
     * When archiveScope is null (All documents) we union live and archived so
     * per-year folders like 2025-2026 and 2026-2027 appear individually instead
     * of merging into the current year. Mirrors the update-in-place archiving
     * where the same apply_org id is reused but old docs survive in archived_*.
     *
     * @return list<YearFolderAggregate>
     */
    public function yearFolders(
        User $user,
        LibraryQuery $query,
        ResolvedOrganizationFolder $organization,
    ): array {
        if ($organization->applicationIds === []) {
            // Live ids empty but archived may still have years for this org (update-in-place).
            // Fall through to archived check below.
            if ($query->archiveScope !== 'archive' && $query->archiveScope !== null) {
                return [];
            }
        }

        // Scoped queries: delegate to source-specific helpers.
        if ($query->archiveScope === 'current') {
            return $this->yearFoldersForLive($user, $query, $organization);
        }
        if ($query->archiveScope === 'archive') {
            return $this->yearFoldersForArchived($user, $query, $organization);
        }

        // archiveScope null (All) — union live + archived by year_key.
        $live = $this->yearFoldersForLive($user, $query, $organization);
        $archived = $this->yearFoldersForArchived($user, $query, $organization);

        if ($archived === []) {
            return $live;
        }
        if ($live === []) {
            return $archived;
        }

        /** @var array<string, YearFolderAggregate> $merged */
        $merged = [];
        foreach (array_merge($live, $archived) as $folder) {
            $key = $folder->key();
            if (isset($merged[$key])) {
                $merged[$key] = new YearFolderAggregate(
                    $merged[$key]->year,
                    $merged[$key]->documentCount + $folder->documentCount,
                );
            } else {
                $merged[$key] = $folder;
            }
        }

        $folders = array_values($merged);
        usort($folders, static function (
            YearFolderAggregate $left,
            YearFolderAggregate $right,
        ): int {
            if ($left->year instanceof UnspecifiedYear) {
                return $right->year instanceof UnspecifiedYear ? 0 : 1;
            }
            if ($right->year instanceof UnspecifiedYear) {
                return -1;
            }

            return $right->year->startingYear <=> $left->year->startingYear;
        });

        return $folders;
    }

    /**
     * @return list<YearFolderAggregate>
     */
    private function yearFoldersForLive(
        User $user,
        LibraryQuery $query,
        ResolvedOrganizationFolder $organization,
    ): array {
        if ($organization->applicationIds === []) {
            return [];
        }
        // Bypass filteredEntries fallback so live is queried explicitly.
        $entries = $this->filteredEntriesForLive($user, $query)
            ->whereIn(self::RELATION_ALIAS.'.application_id', $organization->applicationIds)
            ->reorder();
        return $this->collectYearFolders($entries);
    }

    /**
     * @return list<YearFolderAggregate>
     */
    private function yearFoldersForArchived(
        User $user,
        LibraryQuery $query,
        ResolvedOrganizationFolder $organization,
    ): array {
        $archivedIds = $this->archivedApplicationIdsForOrganization($organization);
        if ($archivedIds === []) {
            return [];
        }
        if (! $this->archivedTablesExist()) {
            return [];
        }
        try {
            $entries = $this->filteredEntriesForArchived($user, $query)
                ->whereIn(self::RELATION_ALIAS.'.application_id', $archivedIds)
                ->reorder();
            return $this->collectYearFolders($entries);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * @param Builder $entries already filtered & scoped to organization
     * @return list<YearFolderAggregate>
     */
    private function collectYearFolders(Builder $entries): array
    {
        $year = $this->yearExpression($entries->getQuery());
        $validYear = $this->validYearPredicate($entries->getQuery(), $year);
        $bucket = "CASE WHEN {$validYear} THEN {$year} ELSE NULL END";
        $rows = $entries
            ->select([])
            ->selectRaw("{$bucket} as year_key")
            ->selectRaw('COUNT(DISTINCT '.self::RELATION_ALIAS.'.id) as document_count')
            ->groupByRaw($bucket)
            ->toBase()
            ->get();

        $folders = $rows->map(static function (object $row): YearFolderAggregate {
            $year = AccreditationYear::parse(is_string($row->year_key) ? $row->year_key : null)
                ?? UnspecifiedYear::Value;
            return new YearFolderAggregate($year, (int) $row->document_count);
        })->all();

        usort($folders, static function (
            YearFolderAggregate $left,
            YearFolderAggregate $right,
        ): int {
            if ($left->year instanceof UnspecifiedYear) {
                return $right->year instanceof UnspecifiedYear ? 0 : 1;
            }
            if ($right->year instanceof UnspecifiedYear) {
                return -1;
            }
            return $right->year->startingYear <=> $left->year->startingYear;
        });

        return $folders;
    }

    /**
     * Explicit live/archived filtered builders for union logic (archiveScope null).
     * These bypass the fallback in filteredEntries() so live and archived can be
     * queried separately and merged by year key.
     */
    private function filteredEntriesForLive(User $user, LibraryQuery $query): Builder
    {
        // Force current scope to avoid the All fallback; keep other filters.
        $liveQuery = $query->archiveScope === null ? $query : $query->withArchiveScope('current');
        // Apply filters without the All->fallback path: directly use authorizedEntries.
        $entries = $this->applyFilters($this->authorizedEntries($user), $liveQuery);
        return $this->applyKeyword($entries, $liveQuery->keyword);
    }

    private function filteredEntriesForArchived(User $user, LibraryQuery $query): Builder
    {
        if (! $this->archivedTablesExist()) {
            // Return empty builder that yields no rows.
            return $this->authorizedEntries($user)->whereRaw('1 = 0');
        }
        // For All (null) keep the query unfiltered so archived current-year docs
        // stay visible alongside older periods; explicit `archive` keeps its
        // non-current predicate via applyArchiveScopeFilter.
        $archQuery = $query;
        try {
            $entries = $this->applyFilters($this->archivedAuthorizedEntries($user), $archQuery);
            return $this->applyKeyword($entries, $archQuery->keyword);
        } catch (\Throwable $e) {
            return $this->authorizedEntries($user)->whereRaw('1 = 0');
        }
    }

    /**
     * Resolve archived applicationIds for the same organization identity.
     * Used so All scope can show 2025-2026 archived alongside 2026-2027 live
     * even though the live apply_org id was reused update-in-place.
     *
     * @return list<int>
     */
    private function archivedApplicationIdsForOrganization(ResolvedOrganizationFolder $organization): array
    {
        if (! $this->archivedTablesExist()) {
            return [];
        }
        try {
            $identity = $organization->identity;
            $organizationId = $identity->organizationId;
            if ($organizationId !== null) {
                return DB::table('archived_document_library_entries as e')
                    ->join('apply_org_document_archives as a', 'a.id', '=', 'e.archive_id')
                    ->where('a.organization_id', $organizationId)
                    ->where('a.status', 'Approved')
                    ->distinct()
                    ->pluck('a.id')
                    ->map(static fn (mixed $id): int => (int) $id)
                    ->all();
            }
            // Legacy: hash-based identity (no organization_id)
            $rows = DB::table('archived_document_library_entries as e')
                ->join('apply_org_document_archives as a', 'a.id', '=', 'e.archive_id')
                ->whereNull('a.organization_id')
                ->where('a.status', 'Approved')
                ->select(['a.id as archive_id', 'a.organization_name', 'a.organization_acronym'])
                ->distinct()
                ->get();
            $key = $identity->key();
            $matching = [];
            foreach ($rows as $row) {
                $candidate = \App\Services\DocumentLibrary\OrganizationFolderIdentity::legacy(
                    (string) $row->organization_name,
                    (string) $row->organization_acronym,
                )->key();
                if (hash_equals($key, $candidate)) {
                    $matching[] = (int) $row->archive_id;
                }
            }
            return array_values(array_unique($matching));
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Return cross-folder results while retaining an optional active folder path in
     * every canonical pagination link.
     */
    public function searchPage(
        LibraryQuery $query,
        User $user,
        ?FolderResolution $path = null,
    ): DocumentPage {
        return $this->paginatedDocuments($user, $query, $path, false);
    }

    /**
     * Return documents from one resolved accreditation-year folder.
     */
    public function folderPage(
        LibraryQuery $query,
        FolderResolution $path,
        User $user,
    ): DocumentPage {
        if (! $path->found() || $path->level !== 'year' || $path->organization === null || $path->year === null) {
            throw new InvalidArgumentException('A resolved accreditation-year folder is required.');
        }

        return $this->paginatedDocuments($user, $query, $path, true);
    }

    private function paginatedDocuments(
        User $user,
        LibraryQuery $query,
        ?FolderResolution $path,
        bool $scopeToFolder,
    ): DocumentPage {
        $connection = DB::connection();
        $outerTransactionLevel = $connection->transactionLevel();

        // MySQL applies this statement to the next transaction. PostgreSQL applies
        // its equivalent as the first statement inside the transaction below.
        if ($outerTransactionLevel === 0 && $connection->getDriverName() === 'mysql') {
            $connection->statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        }

        return $connection->transaction(function () use (
            $connection,
            $outerTransactionLevel,
            $user,
            $query,
            $path,
            $scopeToFolder,
        ): DocumentPage {
            if ($outerTransactionLevel === 0 && $connection->getDriverName() === 'pgsql') {
                $connection->statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            }

            $entries = $this->filteredEntries($user, $query);
            $usedArchivedForFolder = false;
            if ($scopeToFolder) {
                // For archive scope we must use archived ids, not live ids from path.
                if ($query->archiveScope === 'archive') {
                    $archIds = $path?->organization ? $this->archivedApplicationIdsForOrganization($path->organization) : [];
                    if ($archIds === []) {
                        // No archived docs for this org -> empty page
                        $matching = $this->matchingEntryRows($entries->whereRaw('1 = 0'));
                        $total = 0;
                        $entryIds = [];
                        return $this->makeDocumentPage($query, $path, $entryIds, [], $total);
                    }
                    $this->scopeToFolderWithIds($entries, $path, $archIds);
                } else {
                    $this->scopeToFolder($entries, $path);
                }
            }

            // The first query shape contains only IDs and sort values. Counting and
            // page slicing therefore cannot hydrate entry metadata accidentally.
            $matching = $this->matchingEntryRows($entries);
            $total = (int) (clone $matching)->count();
            $entryIds = $this->orderedPageIds($matching, $query);

            // When All scope folder is empty on live (archived year like 2025-2026),
            // fall back to archived source with correct archived ids.
            if ($total === 0 && $scopeToFolder && $query->archiveScope === null && $path?->organization !== null && $this->archivedTablesExist()) {
                try {
                    $archIds = $this->archivedApplicationIdsForOrganization($path->organization);
                    if ($archIds !== []) {
                        $archEntries = $this->filteredEntriesForArchived($user, $query);
                        $this->scopeToFolderWithIds($archEntries, $path, $archIds);
                        $archMatching = $this->matchingEntryRows($archEntries);
                        $archTotal = (int) (clone $archMatching)->count();
                        if ($archTotal > 0) {
                            $entries = $archEntries;
                            $matching = $archMatching;
                            $total = $archTotal;
                            $entryIds = $this->orderedPageIds($matching, $query);
                            $usedArchivedForFolder = true;
                        }
                    }
                } catch (\Throwable $e) {
                    // keep live empty result
                }
            }

            // Full metadata is loaded only after page selection and for at most 25 IDs.
            // When live is empty we fall back to archived (see filteredEntries), so hydrate from
            // whichever source contains the page IDs. Try live first, then fill missing from archive.
            $byId = collect();
            if ($entryIds !== []) {
                $liveById = $this->authorizedEntries($user)
                    ->whereIn(self::RELATION_ALIAS.'.id', $entryIds)
                    ->get()
                    ->keyBy(static fn (DocumentLibraryEntry $entry): int => (int) $entry->id);
                $byId = $liveById;
                // If some IDs are missing from live (archived fallback), fetch from archive.
                $missingIds = array_values(array_filter($entryIds, static fn (int $id): bool => ! $byId->has($id)));
                if ($missingIds !== [] && $this->archivedTablesExist()) {
                    try {
                        $archById = $this->archivedAuthorizedEntries($user)
                            ->whereIn(self::RELATION_ALIAS.'.id', $missingIds)
                            ->get()
                            ->keyBy(static fn (\Illuminate\Database\Eloquent\Model $entry): int => (int) $entry->getAttribute('id'));
                        foreach ($archById as $id => $archEntry) {
                            $byId->put($id, $archEntry);
                        }
                    } catch (\Throwable $e) {
                        // Archived tables may not exist in test SQLite; ignore
                    }
                }
            }
            $hydrated = [];
            foreach ($entryIds as $entryId) {
                $entry = $byId->get($entryId);
                if ($entry instanceof \Illuminate\Database\Eloquent\Model) {
                    $hydrated[] = $entry;
                }
            }

            return $this->makeDocumentPage($query, $path, $entryIds, $hydrated, $total);
        }, 1);
    }

    private function matchingEntryRows(Builder $entries): QueryBuilder
    {
        $alias = self::RELATION_ALIAS;
        $idsAndSortValues = $entries
            ->reorder()
            ->select([
                "{$alias}.id as entry_id",
                "{$alias}.application_submitted_at as sort_submission_date",
                "{$alias}.original_filename as sort_filename",
            ])
            ->distinct();

        return $idsAndSortValues->getQuery()->getConnection()->query()
            ->fromSub($idsAndSortValues->toBase(), 'matching_documents');
    }

    /** @return list<int> */
    private function orderedPageIds(QueryBuilder $matching, LibraryQuery $query): array
    {
        $ordered = clone $matching;

        match ($query->sort) {
            LibrarySort::NewestSubmission => $ordered->orderByDesc('sort_submission_date'),
            LibrarySort::OldestSubmission => $ordered->orderBy('sort_submission_date'),
            LibrarySort::FilenameAscending => $ordered->orderByRaw("LOWER(COALESCE(sort_filename, '')) ASC"),
            LibrarySort::FilenameDescending => $ordered->orderByRaw("LOWER(COALESCE(sort_filename, '')) DESC"),
        };

        return $ordered
            ->orderByDesc('entry_id')
            ->offset(($query->page - 1) * self::DOCUMENTS_PER_PAGE)
            ->limit(self::DOCUMENTS_PER_PAGE)
            ->get(['entry_id'])
            ->map(static fn (object $row): int => (int) $row->entry_id)
            ->all();
    }

    private function scopeToFolder(Builder $entries, ?FolderResolution $path): void
    {
        $this->scopeToFolderWithIds($entries, $path, $path?->organization?->applicationIds ?? []);
    }

    private function scopeToFolderWithIds(Builder $entries, ?FolderResolution $path, array $applicationIds): void
    {
        if ($path?->organization === null || $path->year === null) {
            throw new InvalidArgumentException('A resolved accreditation-year folder is required.');
        }

        $entries->whereIn(
            self::RELATION_ALIAS.'.application_id',
            $applicationIds,
        );
        $year = $this->yearExpression($entries->getQuery());
        $validYear = $this->validYearPredicate($entries->getQuery(), $year);

        if ($path->year instanceof AccreditationYear) {
            $entries->where(self::RELATION_ALIAS.'.has_current_output', 1)
                ->whereRaw("{$validYear}")
                ->whereRaw("{$year} = ?", [$path->year->key()]);

            return;
        }

        $entries->where(function (Builder $unspecified) use ($year, $validYear): void {
            $unspecified->where(self::RELATION_ALIAS.'.has_current_output', '<>', 1)
                ->orWhereNull(DB::raw($year))
                ->orWhereRaw("NOT ({$validYear})");
        });
    }

    /**
     * @param  list<int>  $entryIds
     * @param  list<DocumentLibraryEntry>  $entries
     */
    private function makeDocumentPage(
        LibraryQuery $query,
        ?FolderResolution $path,
        array $entryIds,
        array $entries,
        int $total,
    ): DocumentPage {
        $pathParameters = $this->pathParameters($path);
        $shown = count($entryIds);
        $first = $shown === 0 ? 0 : (($query->page - 1) * self::DOCUMENTS_PER_PAGE) + 1;
        $last = $shown === 0 ? 0 : $first + $shown - 1;
        $lastPage = max(1, (int) ceil($total / self::DOCUMENTS_PER_PAGE));
        $pageUrl = fn (int $page): string => $this->urls->build(
            $query->withPage($page),
            $pathParameters,
        );

        return new DocumentPage(
            entries: $entries,
            entryIds: $entryIds,
            total: $total,
            firstResult: $first,
            lastResult: $last,
            currentPage: $query->page,
            perPage: self::DOCUMENTS_PER_PAGE,
            lastPage: $lastPage,
            query: $query,
            pathParameters: $pathParameters,
            canonicalUrl: $pageUrl($query->page),
            previousPageUrl: $query->page > 1 ? $pageUrl($query->page - 1) : null,
            nextPageUrl: $last < $total ? $pageUrl($query->page + 1) : null,
            firstPageUrl: $pageUrl(1),
            lastPageUrl: $pageUrl($lastPage),
        );
    }

    /** @return array<string, string> */
    private function pathParameters(?FolderResolution $path): array
    {
        if ($path === null || ! $path->found() || $path->organization === null) {
            return [];
        }

        $parameters = ['folder' => $path->organization->token];
        if ($path->level === 'year' && $path->year !== null) {
            $parameters['year_folder'] = $path->year->key();
        }

        return $parameters;
    }

    private function firstNonEmpty(mixed $preferred, mixed $fallback): string
    {
        $preferred = is_string($preferred) ? trim($preferred) : '';

        return $preferred !== '' ? $preferred : (is_string($fallback) ? $fallback : '');
    }

    private function nullableTrimmed(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : '';

        return $value === '' ? null : $value;
    }

    private function organizationSortKey(string $name): string
    {
        return mb_convert_case($name, MB_CASE_FOLD, 'UTF-8');
    }

    /**
     * Build the sole discovery relation for the library.
     *
     * Authorization is deliberately applied before options, filtering, aggregation,
     * search, or hydration so future narrower OSAS scoping has one enforcement point.
     */
    public function authorizedEntries(User $user): Builder
    {
        $projected = DocumentLibraryEntry::query();
        $this->currentOutput->apply($projected);
        $projected
            ->join('apply_orgs as library_application', 'library_application.id', '=', 'document_library_entries.apply_org_id')
            ->leftJoin('organizations as library_organization', 'library_organization.id', '=', 'library_application.organization_id')
            ->leftJoin('departments as library_department', 'library_department.id', '=', 'library_application.department_id')
            ->addSelect([
                'library_application.id as application_id',
                'library_application.organization_id as linked_organization_id',
                'library_application.organization_name as submitted_organization_name',
                'library_application.organization_acronym as submitted_organization_acronym',
                'library_application.application_type as application_type',
                'library_application.request_type as request_type',
                'library_application.status as application_status',
                'library_application.accreditation_year as accreditation_year',
                'library_application.created_at as application_submitted_at',
                'library_organization.name as linked_organization_name',
                'library_organization.org_acronym as linked_organization_acronym',
                'library_department.department_name as department_name',
            ])
            ->where('library_application.status', 'Approved');

        $query = DocumentLibraryEntry::query()
            ->fromSub($projected->toBase(), self::RELATION_ALIAS)
            ->select(self::RELATION_ALIAS.'.*');

        return $user->role === 'osas_admin' ? $query : $query->whereRaw('1 = 0');
    }

    public function authorizedFilterOptions(User $user, ?string $archiveScope = null): AuthorizedFilterOptions
    {
        if ($user->role !== 'osas_admin') {
            return new AuthorizedFilterOptions;
        }

        // Scope-aware options so `current` never leaks archived years/types.
        // null (All) keeps the union behavior; explicit scopes use one source.
        if ($archiveScope === 'current') {
            $liveEntries = $this->authorizedEntries($user);
            return $this->filterOptionsFrom($liveEntries);
        }

        if ($archiveScope === 'archive') {
            if (! $this->archivedTablesExist()) {
                return $this->filterOptionsFrom($this->authorizedEntries($user)->whereRaw('1 = 0'));
            }
            try {
                return $this->filterOptionsFrom($this->archivedAuthorizedEntries($user));
            } catch (\Throwable $e) {
                return $this->filterOptionsFrom($this->authorizedEntries($user)->whereRaw('1 = 0'));
            }
        }

        // Union live + archived when both exist so filter dropdowns show
        // 2025-2026 archived alongside 2026-2027 live instead of hiding one.
        $liveEntries = $this->authorizedEntries($user);
        $hasLive = false;
        try {
            $hasLive = (clone $liveEntries)->select(self::RELATION_ALIAS.'.id')->limit(1)->exists();
        } catch (\Throwable $e) {
            $hasLive = false;
        }

        $hasArchived = $this->archivedTablesExist();
        $archivedEntries = null;
        $hasArchData = false;
        if ($hasArchived) {
            try {
                $archivedEntries = $this->archivedAuthorizedEntries($user);
                $hasArchData = (clone $archivedEntries)->select(self::RELATION_ALIAS.'.id')->limit(1)->exists();
            } catch (\Throwable $e) {
                $hasArchData = false;
            }
        }

        // If only one source has data, use fallback (original behavior).
        // If both have data, merge distinct values for each category.
        if ($hasLive && $hasArchData) {
            // Departments are global (departments table), same for both.
            $departments = $this->allDepartments();
            $requirements = array_values(array_unique(array_merge(
                $this->distinctStrings($liveEntries, 'requirement_label'),
                $this->distinctStrings($archivedEntries, 'requirement_label'),
            )));
            sort($requirements, SORT_FLAG_CASE | SORT_STRING);
            $statuses = array_values(array_unique(array_merge(
                $this->distinctStrings($liveEntries, 'application_status'),
                $this->distinctStrings($archivedEntries, 'application_status'),
            )));
            sort($statuses, SORT_FLAG_CASE | SORT_STRING);
            $kinds = array_values(array_unique(array_merge(
                $this->applicationKindOptions($liveEntries),
                $this->applicationKindOptions($archivedEntries),
            )));
            sort($kinds, SORT_FLAG_CASE | SORT_STRING);
            [$liveYears, $liveTypes] = $this->currentOutputOptions($liveEntries);
            [$archYears, $archTypes] = $this->currentOutputOptions($archivedEntries);
            $years = array_values(array_unique(array_merge($liveYears, $archYears)));
            // Years are numeric strings; keep same descending sort as currentOutputOptions.
            rsort($years, SORT_NUMERIC);
            $documentTypes = array_values(array_unique(array_merge($liveTypes, $archTypes)));
            sort($documentTypes, SORT_FLAG_CASE | SORT_STRING);
        } else {
            $entries = $hasLive ? $liveEntries : ($hasArchData ? $archivedEntries : $liveEntries);
            if (! $hasLive && $hasArchData) {
                $entries = $archivedEntries;
            }
            $departments = $this->allDepartments();
            $requirements = $this->distinctStrings($entries, 'requirement_label');
            $statuses = $this->distinctStrings($entries, 'application_status');
            $kinds = $this->applicationKindOptions($entries);
            [$years, $documentTypes] = $this->currentOutputOptions($entries);
        }

        return new AuthorizedFilterOptions(
            departments: $departments,
            years: $years,
            requirements: $requirements,
            documentTypes: $documentTypes,
            applicationKinds: $kinds,
            applicationStatuses: $statuses,
            labels: [
                'department' => $this->departmentLabels(),
            ],
        );
    }

    private function filterOptionsFrom(Builder $entries): AuthorizedFilterOptions
    {
        [$years, $documentTypes] = $this->currentOutputOptions($entries);

        return new AuthorizedFilterOptions(
            departments: $this->allDepartments(),
            years: $years,
            requirements: $this->distinctStrings($entries, 'requirement_label'),
            documentTypes: $documentTypes,
            applicationKinds: $this->applicationKindOptions($entries),
            applicationStatuses: $this->distinctStrings($entries, 'application_status'),
            labels: [
                'department' => $this->departmentLabels(),
            ],
        );
    }

    /**
     * Apply the filter categories. Values are ORed by whereIn/OR branches
     * inside a category; each category, year bound, and date bound is ANDed.
     */
    public function applyFilters(Builder $entries, LibraryQuery $query): Builder
    {
        $alias = self::RELATION_ALIAS;

        $this->whereInWhenSelected($entries, "{$alias}.department_name", $query->departments);
        $this->applyYearFilter($entries, $query->yearFrom);
        $this->applyArchiveScopeFilter($entries, $query->archiveScope);
        $this->whereInWhenSelected($entries, "{$alias}.requirement_label", $query->requirements);

        if ($query->documentTypes !== []) {
            $entries->where("{$alias}.has_current_output", 1)
                ->whereIn(DB::raw($this->documentTypeExpression()), $query->documentTypes);
        }

        if ($query->applicationKinds !== []) {
            $entries->whereIn(DB::raw($this->applicationKindExpression()), $query->applicationKinds);
        }

        $this->whereInWhenSelected($entries, "{$alias}.application_status", $query->applicationStatuses);
        $this->applyDateRange($entries, $query->submittedFrom, $query->submittedTo);

        return $entries;
    }

    /**
     * Apply literal, case-insensitive keyword containment to every permitted field.
     *
     * AI columns are the already-gated projection from CurrentLibraryOutputScope, so
     * stale and incomplete output is null here. The explicit escape character keeps
     * %, _, and the escape character itself literal on PostgreSQL, MySQL, and SQLite.
     */
    public function applyKeyword(Builder $entries, ?string $keyword): Builder
    {
        $keyword = $this->normalizeKeyword($keyword);
        if ($keyword === null) {
            return $entries;
        }

        $pattern = '%'.$this->escapeLikeLiteral($this->lower($keyword)).'%';
        $columns = [
            self::RELATION_ALIAS.'.tags',
            self::RELATION_ALIAS.'.summary',
            self::RELATION_ALIAS.'.extracted_text',
            self::RELATION_ALIAS.'.linked_organization_name',
            self::RELATION_ALIAS.'.linked_organization_acronym',
            self::RELATION_ALIAS.'.submitted_organization_name',
            self::RELATION_ALIAS.'.submitted_organization_acronym',
            self::RELATION_ALIAS.'.original_filename',
            self::RELATION_ALIAS.'.requirement_label',
        ];

        $entries->where(function (Builder $matches) use ($columns, $pattern): void {
            foreach ($columns as $index => $column) {
                $method = $index === 0 ? 'whereRaw' : 'orWhereRaw';
                $matches->{$method}(
                    $this->caseInsensitiveTextExpression($matches->getQuery(), $column)
                        ." LIKE ? ESCAPE '!'",
                    [$pattern],
                );
            }
        });

        return $entries->distinct();
    }

     public function archivedAuthorizedEntries(User $user): Builder
    {
        // Archived entries per accreditation period (hard-deleted sources) — full copy including AI output
        $projected = \App\Models\ArchivedDocumentLibraryEntry::query()
            ->join('apply_org_document_archives as archive_application', 'archive_application.id', '=', 'archived_document_library_entries.archive_id')
            ->leftJoin('organizations as library_organization', 'library_organization.id', '=', 'archive_application.organization_id')
            ->leftJoin('departments as library_department', 'library_department.id', '=', 'archive_application.department_id')
            // Join archived processed docs for AI columns (left join one per field/slot)
            ->leftJoin('archived_processed_documents as library_analysis', function ($join) {
                $join->on('library_analysis.archive_id', '=', 'archived_document_library_entries.archive_id')
                    ->on('library_analysis.document_field', '=', 'archived_document_library_entries.document_field')
                    ->on('library_analysis.document_slot', '=', 'archived_document_library_entries.document_slot');
            })
            ->addSelect([
                'archived_document_library_entries.*',
                DB::raw("CASE WHEN library_analysis.processing_status = 'completed' THEN 1 ELSE 0 END as has_current_output"),
                'library_analysis.document_type as document_type',
                'library_analysis.document_type_name as document_type_name',
                'library_analysis.summary as summary',
                'library_analysis.tags as tags',
                'library_analysis.key_information as key_information',
                'library_analysis.extracted_text as extracted_text',
                'library_analysis.confidence_score as confidence_score',
                'library_analysis.id as analysis_id',
                'library_analysis.processing_status as processing_status',
                'library_analysis.processed_at as processed_at',
                'archive_application.id as application_id',
                'archive_application.organization_id as linked_organization_id',
                'archive_application.organization_name as submitted_organization_name',
                'archive_application.organization_acronym as submitted_organization_acronym',
                'archive_application.application_type as application_type',
                'archive_application.request_type as request_type',
                'archive_application.status as application_status',
                'archive_application.accreditation_year as accreditation_year',
                'archive_application.created_at as application_submitted_at',
                'library_organization.name as linked_organization_name',
                'library_organization.org_acronym as linked_organization_acronym',
                'library_department.department_name as department_name',
            ]);

        $query = \App\Models\ArchivedDocumentLibraryEntry::query()
            ->fromSub($projected->toBase(), self::RELATION_ALIAS)
            ->select(self::RELATION_ALIAS.'.*');

        return $user->role === 'osas_admin' ? $query : $query->whereRaw('1 = 0');
    }

    public function filteredEntries(User $user, LibraryQuery $query): Builder
    {
        if ($query->archiveScope === 'current') {
            $entries = $this->applyFilters($this->authorizedEntries($user), $query);
            return $this->applyKeyword($entries, $query->keyword);
        }
        if ($query->archiveScope === 'archive') {
            $entries = $this->applyFilters($this->archivedAuthorizedEntries($user), $query);
            return $this->applyKeyword($entries, $query->keyword);
        }
        // all (null): update in place keeps accreditation_year empty on live, live docs cleared to archive_lib
        // Fallback to archived when live is empty so Document Library still shows 2025-2026 archived 30
        // Use count() check with non-json column to avoid PostgreSQL "could not identify equality operator for type json"
        // that occurs with exists() on `select distinct authorized_entries.*` (json columns).
        $live = $this->applyFilters($this->authorizedEntries($user), $query);
        $live = $this->applyKeyword($live, $query->keyword);
        if ($this->archivedTablesExist()) {
            try {
                $hasLive = (clone $live)->select(self::RELATION_ALIAS.'.id')->limit(1)->exists();
                if (! $hasLive) {
                    $arch = $this->applyFilters($this->archivedAuthorizedEntries($user), $query);
                    return $this->applyKeyword($arch, $query->keyword);
                }
            } catch (\Throwable $e) {
            }
        }
        return $live;
    }

    /** @param list<string> $values */
    private function whereInWhenSelected(Builder $query, string $column, array $values): void
    {
        if ($values !== []) {
            $query->whereIn($column, $values);
        }
    }

    /**
     * Keep only current AI output whose accreditation year's starting year equals
     * the requested year. The validity predicate keeps malformed stored values
     * from ever being treated as a concrete year.
     */
    private function applyYearFilter(Builder $entries, ?int $year): void
    {
        if ($year === null) {
            return;
        }

        $yearExpression = $this->yearExpression($entries->getQuery());
        $validYear = $this->validYearPredicate($entries->getQuery(), $yearExpression);
        $startingYear = $this->startingYearExpression($entries->getQuery(), $yearExpression);

        $entries->where(function (Builder $filter) use ($year, $startingYear, $validYear): void {
            $filter->where(self::RELATION_ALIAS.'.has_current_output', 1)
                ->whereRaw("{$startingYear} = ?", [$year])
                ->whereRaw("({$validYear})");
        });
    }

    /**
     * Filter by current vs archived accreditation period.
     * current = accreditation_year equals Organization::currentAccreditationPeriod school year
     * archive = accreditation_year not equal (or invalid/unspecified) — retains per-period filtering
     */
    private function applyArchiveScopeFilter(Builder $entries, ?string $scope): void
    {
        if ($scope === null) {
            return;
        }
        $period = \App\Models\Organization::currentAccreditationPeriod();
        $currentYear = $period ? \App\Models\Organization::accreditationSchoolYear($period->started_at, $period->expires_at) : null;

        $yearExpression = $this->yearExpression($entries->getQuery());
        $validYear = $this->validYearPredicate($entries->getQuery(), $yearExpression);

        if ($scope === 'current') {
            if ($currentYear === null) {
                $entries->whereRaw('1 = 0');
                return;
            }
            $entries->where(self::RELATION_ALIAS.'.has_current_output', 1)
                ->whereRaw("({$validYear})")
                ->whereRaw("{$yearExpression} = ?", [$currentYear]);
            return;
        }
        // archive: year invalid/unspecified OR valid but not current year (still per-period)
        if ($scope === 'archive') {
            if ($currentYear === null) {
                // No current period — everything is archive
                return;
            }
            $entries->where(function (Builder $q) use ($yearExpression, $validYear, $currentYear): void {
                $q->where(self::RELATION_ALIAS.'.has_current_output', '<>', 1)
                  ->orWhereNull(DB::raw($yearExpression))
                  ->orWhereRaw("NOT ({$validYear})")
                  ->orWhereRaw("{$yearExpression} <> ?", [$currentYear]);
            });
        }
    }

    private function applyDateRange(
        Builder $entries,
        ?DateTimeImmutable $from,
        ?DateTimeImmutable $to,
    ): void {
        if ($from !== null) {
            $entries->where(
                self::RELATION_ALIAS.'.application_submitted_at',
                '>=',
                $from->format('Y-m-d 00:00:00'),
            );
        }
        if ($to !== null) {
            $entries->where(
                self::RELATION_ALIAS.'.application_submitted_at',
                '<',
                $to->modify('+1 day')->format('Y-m-d 00:00:00'),
            );
        }
    }

    /** @return list<string> */
    private function distinctStrings(Builder $entries, string $column): array
    {
        $values = (clone $entries)
            ->whereNotNull($column)
            ->where($column, '<>', '')
            ->select($column)
            ->distinct()
            ->pluck($column)
            ->map(static fn (mixed $value): string => (string) $value)
            ->all();

        return $this->sortStrings($values);
    }

    /** @return list<string> */
    private function allDepartments(): array
    {
        return Department::query()
            ->where('department_name', '!=', 'Non-Academic')
            ->orderBy('department_name')
            ->pluck('department_name')
            ->map(static fn (mixed $value): string => trim((string) $value))
            ->filter(static fn (string $name): bool => $name !== '')
            ->values()
            ->all();
    }

    /** @return array<string, string> */
    private function departmentLabels(): array
    {
        return Department::query()
            ->pluck('department_acronym', 'department_name')
            ->map(static fn (mixed $value): string => trim((string) $value))
            ->filter(static fn (string $acronym): bool => $acronym !== '')
            ->all();
    }

    /** @return list<string> */
    private function applicationKindOptions(Builder $entries): array
    {
        return $this->distinctExpressionStrings(
            $entries,
            $this->applicationKindExpression(),
        );
    }

    /** @return array{list<string>, list<string>} */
    private function currentOutputOptions(Builder $entries): array
    {
        $year = $this->yearExpression($entries->getQuery());
        $startingYear = $this->startingYearExpression($entries->getQuery(), $year);
        $years = (clone $entries)
            ->where(self::RELATION_ALIAS.'.has_current_output', 1)
            ->select([])
            ->selectRaw("{$startingYear} as option_year")
            ->distinct()
            ->pluck('option_year')
            ->map(static fn (mixed $value): ?int => is_numeric($value) ? (int) $value : null)
            ->filter()
            ->all();

        $latestStartingYear = $years === [] ? self::FILTER_YEARS_END : max(array_merge([self::FILTER_YEARS_END], $years));
        for ($start = now()->year - self::FILTER_YEARS_BACK; $start <= $latestStartingYear; $start++) {
            $years[] = $start;
        }

        $years = array_values(array_unique($years));
        rsort($years);
        $yearValues = array_map(static fn (int $value): string => (string) $value, $years);

        $documentTypes = (clone $entries)
            ->where(self::RELATION_ALIAS.'.has_current_output', 1);

        return [
            $yearValues,
            $this->distinctExpressionStrings($documentTypes, $this->documentTypeExpression()),
        ];
    }

    private function applicationKindExpression(): string
    {
        $alias = self::RELATION_ALIAS;

        return "CASE WHEN {$alias}.application_type = 'apply' THEN 'Recognition' "
            ."WHEN {$alias}.application_type = 'renew' THEN 'Renewal' "
            ."WHEN ({$alias}.application_type IS NULL OR {$alias}.application_type = '') "
            ."AND {$alias}.request_type = 'apply' THEN 'Recognition' "
            ."WHEN ({$alias}.application_type IS NULL OR {$alias}.application_type = '') "
            ."AND {$alias}.request_type = 'request' THEN 'Renewal' ELSE NULL END";
    }

    private function documentTypeExpression(): string
    {
        $alias = self::RELATION_ALIAS;

        return "COALESCE(NULLIF(TRIM({$alias}.document_type_name), ''), "
            ."NULLIF(TRIM({$alias}.document_type), ''))";
    }

    private function yearExpression(QueryBuilder $query): string
    {
        $column = self::RELATION_ALIAS.'.key_information';
        $applicationYear = self::RELATION_ALIAS.'.accreditation_year';

        $analysisYear = match ($query->getConnection()->getDriverName()) {
            'pgsql' => "({$column}->>'school_year')",
            'mysql' => "JSON_UNQUOTE(JSON_EXTRACT({$column}, '$.school_year'))",
            default => "CASE WHEN json_valid({$column}) THEN json_extract({$column}, '$.school_year') ELSE NULL END",
        };

        $trimmedApplicationYear = "TRIM({$applicationYear})";
        $validApplicationYear = "{$applicationYear} IS NOT NULL "
            ."AND {$trimmedApplicationYear} <> '' "
            .'AND '.$this->validAccreditationYearPredicate($query, $trimmedApplicationYear);

        return "COALESCE(CASE WHEN {$validApplicationYear} THEN {$trimmedApplicationYear} END, {$analysisYear})";
    }

    private function startingYearExpression(QueryBuilder $query, string $year): string
    {
        return match ($query->getConnection()->getDriverName()) {
            'pgsql' => "CASE WHEN {$year} ~ '^[0-9]{4}' "
                ."THEN CAST(SUBSTRING({$year} FROM 1 FOR 4) AS INTEGER) ELSE NULL END",
            'mysql' => "CASE WHEN {$year} REGEXP '^[0-9]{4}' "
                ."THEN CAST(SUBSTRING({$year}, 1, 4) AS UNSIGNED) ELSE NULL END",
            default => "CASE WHEN {$year} GLOB '[0-9][0-9][0-9][0-9]*' "
                ."THEN CAST(SUBSTR({$year}, 1, 4) AS INTEGER) ELSE NULL END",
        };
    }

    private function validYearPredicate(QueryBuilder $query, string $year): string
    {
        $current = self::RELATION_ALIAS.'.has_current_output = 1';

        return "{$current} AND ".$this->validAccreditationYearPredicate($query, $year);
    }

    private function validAccreditationYearPredicate(QueryBuilder $query, string $year): string
    {
        return match ($query->getConnection()->getDriverName()) {
            'pgsql' => "{$year} ~ '^[0-9]{4}-[0-9]{4}$' "
                ."AND CAST(SUBSTRING({$year} FROM 6 FOR 4) AS INTEGER) "
                ."= CAST(SUBSTRING({$year} FROM 1 FOR 4) AS INTEGER) + 1",
            'mysql' => "{$year} REGEXP '^[0-9]{4}-[0-9]{4}$' "
                ."AND CAST(SUBSTRING({$year}, 6, 4) AS UNSIGNED) "
                ."= CAST(SUBSTRING({$year}, 1, 4) AS UNSIGNED) + 1",
            default => "{$year} GLOB '[0-9][0-9][0-9][0-9]-[0-9][0-9][0-9][0-9]' "
                ."AND CAST(SUBSTR({$year}, 6, 4) AS INTEGER) "
                ."= CAST(SUBSTR({$year}, 1, 4) AS INTEGER) + 1",
        };
    }

    private function caseInsensitiveTextExpression(QueryBuilder $query, string $column): string
    {
        $cast = $query->getConnection()->getDriverName() === 'mysql'
            ? "CAST({$column} AS CHAR)"
            : "CAST({$column} AS TEXT)";

        return "LOWER(COALESCE({$cast}, ''))";
    }

    private function normalizeKeyword(?string $keyword): ?string
    {
        if ($keyword === null) {
            return null;
        }

        $trimmed = preg_replace('/^\s+|\s+$/u', '', $keyword);

        return $trimmed === null || $trimmed === '' ? null : $trimmed;
    }

    private function lower(string $value): string
    {
        return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    }

    private function escapeLikeLiteral(string $value): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
    }

    /** @return list<string> */
    private function distinctExpressionStrings(Builder $entries, string $expression): array
    {
        $values = (clone $entries)
            ->select([])
            ->selectRaw("{$expression} as option_value")
            ->whereRaw("{$expression} IS NOT NULL")
            ->distinct()
            ->pluck('option_value')
            ->map(static fn (mixed $value): string => (string) $value)
            ->all();

        return $this->sortStrings($values);
    }

    /** @param list<string> $values
     * @return list<string>
     */
    private function sortStrings(array $values): array
    {
        usort($values, static fn (string $left, string $right): int => strcasecmp($left, $right));

        return array_values(array_unique($values));
    }

    private function archivedTablesExist(): bool
    {
        try {
            return Schema::hasTable('archived_document_library_entries')
                && Schema::hasTable('apply_org_document_archives')
                && Schema::hasTable('archived_processed_documents');
        } catch (\Throwable $e) {
            return false;
        }
    }
}
