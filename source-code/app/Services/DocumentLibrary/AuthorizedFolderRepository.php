<?php

namespace App\Services\DocumentLibrary;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use stdClass;

final class AuthorizedFolderRepository
{
    public function __construct(
        private OrganizationFolderToken $tokens,
        private string $entriesTable = 'document_library_entries',
        private string $applicationsTable = 'apply_orgs',
        private string $organizationsTable = 'organizations',
        private string $analysesTable = 'processed_documents',
    ) {}

    public function findOrganization(User $user, string $identityKey, ?string $archiveScope = null): ?ResolvedOrganizationFolder
    {
        if ($user->role !== 'osas_admin') {
            return null;
        }

        // Scope-aware: `current` resolves live only, `archive` resolves archive only,
        // null (All) keeps live-first with archived fallback.
        if ($archiveScope === 'archive') {
            if (! $this->archivedAvailable()) {
                return null;
            }
            if (preg_match('/^linked:([1-9]\d*)$/D', $identityKey, $matches) === 1) {
                return $this->findLinkedArchived((int) $matches[1]);
            }
            if (preg_match('/^legacy:[a-f0-9]{64}$/D', $identityKey) !== 1) {
                return null;
            }
            return $this->findLegacyArchived($identityKey);
        }

        if ($archiveScope === 'current') {
            if (preg_match('/^linked:([1-9]\d*)$/D', $identityKey, $matches) === 1) {
                return $this->findLinked((int) $matches[1]);
            }
            if (preg_match('/^legacy:[a-f0-9]{64}$/D', $identityKey) !== 1) {
                return null;
            }
            return $this->findLegacy($identityKey);
        }

        if (preg_match('/^linked:([1-9]\d*)$/D', $identityKey, $matches) === 1) {
            $live = $this->findLinked((int) $matches[1]);
            if ($live !== null) {
                return $live;
            }
            if ($this->archivedAvailable()) {
                return $this->findLinkedArchived((int) $matches[1]);
            }
            return null;
        }

        if (preg_match('/^legacy:[a-f0-9]{64}$/D', $identityKey) !== 1) {
            return null;
        }

        $live = $this->findLegacy($identityKey);
        if ($live !== null) {
            return $live;
        }
        if ($this->archivedAvailable()) {
            return $this->findLegacyArchived($identityKey);
        }
        return null;
    }

    public function containsYear(
        ResolvedOrganizationFolder $folder,
        AccreditationYear|UnspecifiedYear $year,
        ?string $archiveScope = null,
    ): bool {
        // Scope-aware: `current` checks live only, `archive` checks archive only.
        if ($archiveScope !== 'archive') {
            $entries = DB::table($this->entriesTable.' as e')
                ->join($this->applicationsTable.' as a', 'a.id', '=', 'e.apply_org_id')
                ->whereIn('e.apply_org_id', $folder->applicationIds)
                ->select([
                    'e.apply_org_id', 'e.document_field', 'e.document_slot', 'e.storage_disk',
                    'e.file_path', 'e.source_version', 'e.source_fingerprint',
                    'a.accreditation_year',
                ])
                ->orderBy('e.id')
                ->cursor();

            foreach ($entries as $entry) {
                $entryYear = $this->yearFor($entry);
                if ($entryYear->key() === $year->key()) {
                    return true;
                }
            }

            if ($archiveScope === 'current') {
                return false;
            }
        }

        // Fallback to archived when live has no matching year (update-in-place)
        // Note: archived uses archive_id, not live apply_org ids (live id is reused), so we must
        // query by organization identity, not by folder.applicationIds.
        if ($this->archivedAvailable()) {
            $identity = $folder->identity;
            $archQuery = DB::table('archived_document_library_entries as e')
                ->join('apply_org_document_archives as a', 'a.id', '=', 'e.archive_id')
                ->select([
                    'e.archive_id as apply_org_id', 'e.document_field', 'e.document_slot', 'e.storage_disk',
                    'e.file_path', 'e.source_version', 'e.source_fingerprint',
                    'a.accreditation_year', 'a.organization_name', 'a.organization_acronym', 'a.organization_id',
                ])
                ->orderBy('e.id');

            if ($identity->organizationId !== null) {
                $archQuery->where('a.organization_id', $identity->organizationId);
            } else {
                $archQuery->whereNull('a.organization_id');
            }

            $archEntries = $archQuery->cursor();

            foreach ($archEntries as $entry) {
                // For legacy, verify hash matches the folder identity (not just null org)
                if ($identity->organizationId === null) {
                    $candidateKey = OrganizationFolderIdentity::legacy(
                        (string) $entry->organization_name,
                        (string) $entry->organization_acronym,
                    )->key();
                    if (! hash_equals($identity->key(), $candidateKey)) {
                        continue;
                    }
                }
                $entryYear = $this->yearForArchived($entry);
                if ($entryYear->key() === $year->key()) {
                    return true;
                }
            }
        }

        return false;
    }

    private function archivedAvailable(): bool
    {
        try {
            return Schema::hasTable('archived_document_library_entries')
                && Schema::hasTable('apply_org_document_archives')
                && Schema::hasTable('archived_processed_documents');
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function findLinked(int $organizationId): ?ResolvedOrganizationFolder
    {
        $rows = DB::table($this->entriesTable.' as e')
            ->join($this->applicationsTable.' as a', 'a.id', '=', 'e.apply_org_id')
            ->join($this->organizationsTable.' as o', 'o.id', '=', 'a.organization_id')
            ->where('a.organization_id', $organizationId)
            ->where('a.status', 'Approved')
            ->select(['a.id as application_id', 'o.name', 'a.organization_acronym'])
            ->distinct()
            ->orderBy('a.id')
            ->get();

        if ($rows->isEmpty()) {
            return null;
        }

        $identity = OrganizationFolderIdentity::linked($organizationId);

        return new ResolvedOrganizationFolder(
            $identity,
            $this->displayLabel((string) $rows->first()->name, $rows->first()->organization_acronym),
            $rows->pluck('application_id')->map(static fn ($id): int => (int) $id)->all(),
            $this->tokens->issue($identity),
        );
    }

    private function findLegacy(string $identityKey): ?ResolvedOrganizationFolder
    {
        $rows = DB::table($this->entriesTable.' as e')
            ->join($this->applicationsTable.' as a', 'a.id', '=', 'e.apply_org_id')
            ->whereNull('a.organization_id')
            ->where('a.status', 'Approved')
            ->select(['a.id as application_id', 'a.organization_name', 'a.organization_acronym'])
            ->distinct()
            ->orderBy('a.id')
            ->get();
        $matching = $rows->filter(function (stdClass $row) use ($identityKey): bool {
            return hash_equals($identityKey, OrganizationFolderIdentity::legacy(
                (string) $row->organization_name,
                (string) $row->organization_acronym,
            )->key());
        })->values();

        if ($matching->isEmpty()) {
            return null;
        }

        $first = $matching->first();
        $identity = OrganizationFolderIdentity::legacy(
            (string) $first->organization_name,
            (string) $first->organization_acronym,
        );

        return new ResolvedOrganizationFolder(
            $identity,
            $this->displayLabel((string) $first->organization_name, $first->organization_acronym),
            $matching->pluck('application_id')->map(static fn ($id): int => (int) $id)->all(),
            $this->tokens->issue($identity),
        );
    }

    private function yearFor(stdClass $entry): AccreditationYear|UnspecifiedYear
    {
        if (is_string($entry->accreditation_year ?? null)) {
            $applicationYear = AccreditationYear::parse(trim($entry->accreditation_year));

            if ($applicationYear !== null) {
                return $applicationYear;
            }
        }

        $analysis = DB::table($this->analysesTable)
            ->where('apply_org_id', $entry->apply_org_id)
            ->where('document_field', $entry->document_field)
            ->where('document_slot', $entry->document_slot)
            ->first();

        if ($analysis === null
            || $analysis->processing_status !== 'completed'
            || $entry->source_version === null
            || $analysis->source_version === null
            || $analysis->output_source_version === null
            || ! hash_equals((string) $entry->source_version, (string) $analysis->source_version)
            || ! hash_equals((string) $analysis->source_version, (string) $analysis->output_source_version)
            || ! hash_equals((string) $entry->storage_disk, (string) $analysis->storage_disk)
            || ! hash_equals($this->normalizePath((string) $entry->file_path), $this->normalizePath((string) $analysis->file_path))
            || ($entry->source_fingerprint !== null
                && ($analysis->source_fingerprint === null
                    || ! hash_equals((string) $entry->source_fingerprint, (string) $analysis->source_fingerprint)))) {
            return UnspecifiedYear::Value;
        }

        $information = $analysis->key_information;
        if (is_string($information)) {
            $information = json_decode($information, true);
        }

        return AccreditationYear::fromCurrentOutput(
            is_array($information) && is_string($information['school_year'] ?? null)
                ? $information['school_year']
                : null,
        );
    }

    private function findLinkedArchived(int $organizationId): ?ResolvedOrganizationFolder
    {
        $rows = DB::table('archived_document_library_entries as e')
            ->join('apply_org_document_archives as a', 'a.id', '=', 'e.archive_id')
            ->join('organizations as o', 'o.id', '=', 'a.organization_id')
            ->where('a.organization_id', $organizationId)
            ->where('a.status', 'Approved')
            ->select(['a.id as application_id', 'o.name', 'a.organization_acronym'])
            ->distinct()
            ->orderBy('a.id')
            ->get();

        if ($rows->isEmpty()) {
            return null;
        }

        $identity = OrganizationFolderIdentity::linked($organizationId);

        return new ResolvedOrganizationFolder(
            $identity,
            $this->displayLabel((string) $rows->first()->name, $rows->first()->organization_acronym),
            $rows->pluck('application_id')->map(static fn ($id): int => (int) $id)->all(),
            $this->tokens->issue($identity),
        );
    }

    private function findLegacyArchived(string $identityKey): ?ResolvedOrganizationFolder
    {
        $rows = DB::table('archived_document_library_entries as e')
            ->join('apply_org_document_archives as a', 'a.id', '=', 'e.archive_id')
            ->whereNull('a.organization_id')
            ->where('a.status', 'Approved')
            ->select(['a.id as application_id', 'a.organization_name', 'a.organization_acronym'])
            ->distinct()
            ->orderBy('a.id')
            ->get();
        $matching = $rows->filter(function (stdClass $row) use ($identityKey): bool {
            return hash_equals($identityKey, OrganizationFolderIdentity::legacy(
                (string) $row->organization_name,
                (string) $row->organization_acronym,
            )->key());
        })->values();

        if ($matching->isEmpty()) {
            return null;
        }

        $first = $matching->first();
        $identity = OrganizationFolderIdentity::legacy(
            (string) $first->organization_name,
            (string) $first->organization_acronym,
        );

        return new ResolvedOrganizationFolder(
            $identity,
            $this->displayLabel((string) $first->organization_name, $first->organization_acronym),
            $matching->pluck('application_id')->map(static fn ($id): int => (int) $id)->all(),
            $this->tokens->issue($identity),
        );
    }

    private function yearForArchived(stdClass $entry): AccreditationYear|UnspecifiedYear
    {
        if (is_string($entry->accreditation_year ?? null)) {
            $applicationYear = AccreditationYear::parse(trim($entry->accreditation_year));
            if ($applicationYear !== null) {
                return $applicationYear;
            }
        }

        $analysis = DB::table('archived_processed_documents')
            ->where('archive_id', $entry->apply_org_id)
            ->where('document_field', $entry->document_field)
            ->where('document_slot', $entry->document_slot)
            ->first();

        if ($analysis === null
            || $analysis->processing_status !== 'completed'
            || $entry->source_version === null
            || $analysis->source_version === null
            || $analysis->output_source_version === null
            || ! hash_equals((string) $entry->source_version, (string) $analysis->source_version)
            || ! hash_equals((string) $analysis->source_version, (string) $analysis->output_source_version)
            || ! hash_equals((string) $entry->storage_disk, (string) $analysis->storage_disk)
            || ! hash_equals($this->normalizePath((string) $entry->file_path), $this->normalizePath((string) $analysis->file_path))
            || ($entry->source_fingerprint !== null
                && ($analysis->source_fingerprint === null
                    || ! hash_equals((string) $entry->source_fingerprint, (string) $analysis->source_fingerprint)))) {
            return UnspecifiedYear::Value;
        }

        $information = $analysis->key_information;
        if (is_string($information)) {
            $information = json_decode($information, true);
        } elseif (is_array($information) && isset($information['school_year'])) {
            // already decoded via cast
        }

        return AccreditationYear::fromCurrentOutput(
            is_array($information) && is_string($information['school_year'] ?? null)
                ? $information['school_year']
                : null,
        );
    }

    private function displayLabel(string $name, mixed $acronym): string
    {
        $collapsed = preg_replace('/[\p{Z}\s]+/u', ' ', (string) $name);

        return trim($collapsed ?? (string) $name);
    }

    private function normalizePath(string $path): string
    {
        return ltrim(str_replace('\\', '/', trim($path)), '/');
    }
}
