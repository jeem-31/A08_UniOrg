<?php

namespace App\Services\DocumentLibrary;

use App\Models\ApplyOrg;
use App\Models\DocumentLibraryEntry;
use App\Services\DocumentIntelligence\StoredDocumentSource;
use App\Services\DocumentIntelligence\SupportedDocumentCatalog;

final readonly class CurrentDocumentLibraryEntryLocator
{
    public function __construct(private SupportedDocumentCatalog $catalog) {}

    public function find(ApplyOrg $application, DocumentLibraryEntry $entry): ?StoredDocumentSource
    {
        $applicationId = (int) $application->getKey();
        $entryId = (int) $entry->getKey();
        if ($applicationId < 1 || $entryId < 1) {
            return null;
        }

        $currentApplication = ApplyOrg::query()->useWritePdo()->find($applicationId);
        $currentEntry = DocumentLibraryEntry::query()->useWritePdo()->find($entryId);
        if ($currentApplication === null || $currentEntry === null
            || (int) $currentEntry->apply_org_id !== $applicationId
            || ! $this->catalog->supports((string) $currentEntry->document_field)) {
            return null;
        }

        $sources = array_values(array_filter(
            $this->catalog->sourcesFor($currentApplication),
            fn (StoredDocumentSource $source): bool => $source->field === $currentEntry->document_field
                && $source->slot === $currentEntry->document_slot,
        ));

        return count($sources) === 1 && $this->matches($sources[0], $currentEntry)
            ? $sources[0]
            : null;
    }

    private function matches(StoredDocumentSource $source, DocumentLibraryEntry $entry): bool
    {
        if (! hash_equals($source->storageDisk, (string) $entry->storage_disk)
            || ! hash_equals($this->path($source->storagePath), $this->path((string) $entry->file_path))) {
            return false;
        }
        if ($source->sourceVersion !== null
            && ! hash_equals($source->sourceVersion, (string) $entry->source_version)) {
            return false;
        }

        return $source->sourceFingerprint === null || hash_equals(
            strtolower($source->sourceFingerprint),
            strtolower((string) $entry->source_fingerprint),
        );
    }

    private function path(string $path): string
    {
        return ltrim(str_replace('\\', '/', $path), '/');
    }
}
