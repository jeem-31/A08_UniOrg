<?php

namespace App\Services\DocumentLibrary;

use App\Models\ApplyOrg;
use App\Models\DocumentLibraryEntry;
use App\Services\DocumentIntelligence\StoredDocumentDescriptor;
use App\Services\DocumentIntelligence\StoredDocumentSource;
use App\Services\DocumentIntelligence\SupportedDocumentCatalog;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

final readonly class DocumentLibrarySourceIndexer
{
    public function __construct(
        private SupportedDocumentCatalog $catalog,
    ) {}

    /**
     * Reconcile one application's derived entries with its current catalog sources.
     * Descriptors supplied by a source-write workflow override catalog values with
     * the exact identity captured from the stored bytes.
     *
     * @param  iterable<StoredDocumentDescriptor>|null  $descriptors
     * @return int Number of current source slots indexed.
     */
    public function syncApplication(ApplyOrg $application, ?iterable $descriptors = null): int
    {
        $applicationId = (int) $application->getKey();
        if ($applicationId < 1) {
            throw new LogicException('A persisted application is required for document library indexing.');
        }

        $catalogSources = $this->uniqueSources($this->catalog->sourcesFor($application));
        $descriptorSources = $descriptors === null
            ? []
            : $this->currentDescriptorSources(
                $application,
                $this->descriptorSources($applicationId, $descriptors),
            );

        DB::transaction(function () use ($applicationId, $catalogSources, $descriptorSources): void {
            $existing = DocumentLibraryEntry::query()
                ->where('apply_org_id', $applicationId)
                ->get()
                ->keyBy(fn (DocumentLibraryEntry $entry): string => $entry->document_field."\0".$entry->document_slot);

            $sources = array_map(
                fn (StoredDocumentSource $source): StoredDocumentSource => $this->sourceWithCurrentIdentity(
                    $source,
                    $descriptorSources[$this->logicalKey($source)] ?? null,
                    $existing->get($this->logicalKey($source)),
                ),
                $catalogSources,
            );

            if ($sources !== []) {
                $timestamp = now();
                DocumentLibraryEntry::query()->upsert(
                    array_map(fn (StoredDocumentSource $source): array => $this->row(
                        $applicationId,
                        $source,
                        $timestamp,
                    ), $sources),
                    ['apply_org_id', 'document_field', 'document_slot'],
                    [
                        'requirement_label',
                        'original_filename',
                        'storage_disk',
                        'file_path',
                        'source_version',
                        'source_fingerprint',
                        'updated_at',
                    ],
                );
            }

            $this->removeMissingSlots($applicationId, $sources);
        });

        return count($catalogSources);
    }

    /**
     * Remove projection rows that no longer correspond to a current catalog slot.
     *
     * @param  list<StoredDocumentSource>  $sources
     */
    public function removeMissingSlots(int $applicationId, array $sources): int
    {
        $currentSlots = [];
        foreach ($sources as $source) {
            $currentSlots[$this->logicalKey($source)] = true;
        }

        $staleIds = DocumentLibraryEntry::query()
            ->where('apply_org_id', $applicationId)
            ->get(['id', 'document_field', 'document_slot'])
            ->filter(fn (DocumentLibraryEntry $entry): bool => ! isset(
                $currentSlots[$entry->document_field."\0".$entry->document_slot]
            ))
            ->modelKeys();

        if ($staleIds === []) {
            return 0;
        }

        return DocumentLibraryEntry::query()->whereKey($staleIds)->delete();
    }

    /**
     * @param  iterable<StoredDocumentDescriptor>  $descriptors
     * @return array<string, StoredDocumentSource>
     */
    private function descriptorSources(int $applicationId, iterable $descriptors): array
    {
        $sources = [];

        foreach ($descriptors as $descriptor) {
            if (! $descriptor instanceof StoredDocumentDescriptor) {
                throw new InvalidArgumentException('Document library synchronization accepts only stored document descriptors.');
            }
            if ($descriptor->applyOrgId !== $applicationId) {
                throw new InvalidArgumentException('A descriptor belongs to a different organization application.');
            }

            $definition = $this->catalog->definition($descriptor->field);
            if ($definition->label !== $descriptor->label) {
                throw new InvalidArgumentException("The descriptor label does not match [{$descriptor->field}].");
            }

            $source = new StoredDocumentSource(
                $descriptor->field,
                $descriptor->slot,
                $descriptor->label,
                $descriptor->originalFilename,
                $descriptor->storageDisk,
                $descriptor->storagePath,
                $descriptor->sourceVersion,
                strtolower($descriptor->sourceFingerprint),
            );
            $key = $this->logicalKey($source);
            if (isset($sources[$key]) && $sources[$key] != $source) {
                throw new InvalidArgumentException("Conflicting descriptors were provided for [{$descriptor->field}:{$descriptor->slot}].");
            }
            $sources[$key] = $source;
        }

        return $sources;
    }

    /**
     * Keep descriptor identity only when the application's current source matches
     * under the descriptor's disk (important for legacy scalar path columns).
     *
     * @param  array<string, StoredDocumentSource>  $descriptorSources
     * @return array<string, StoredDocumentSource>
     */
    private function currentDescriptorSources(ApplyOrg $application, array $descriptorSources): array
    {
        $current = [];

        foreach ($descriptorSources as $key => $descriptorSource) {
            $catalogSources = $this->uniqueSources(
                $this->catalog->sourcesFor($application, $descriptorSource->storageDisk),
            );
            foreach ($catalogSources as $catalogSource) {
                if ($this->logicalKey($catalogSource) === $key
                    && $this->sameStoredSource($catalogSource, $descriptorSource)) {
                    $current[$key] = $descriptorSource;
                    break;
                }
            }
        }

        return $current;
    }

    private function sourceWithCurrentIdentity(
        StoredDocumentSource $catalogSource,
        ?StoredDocumentSource $descriptorSource,
        ?DocumentLibraryEntry $existing,
    ): StoredDocumentSource {
        if ($descriptorSource !== null) {
            return $descriptorSource;
        }

        if ($existing !== null
            && $catalogSource->sourceVersion === null
            && $catalogSource->sourceFingerprint === null
            && hash_equals($this->path((string) $existing->file_path), $this->path($catalogSource->storagePath))
            && hash_equals((string) $existing->storage_disk, $catalogSource->storageDisk)) {
            return new StoredDocumentSource(
                $catalogSource->field,
                $catalogSource->slot,
                $catalogSource->label,
                (string) $existing->original_filename,
                $catalogSource->storageDisk,
                $catalogSource->storagePath,
                $existing->source_version,
                $existing->source_fingerprint,
            );
        }

        return $catalogSource;
    }

    private function sameStoredSource(StoredDocumentSource $catalogSource, StoredDocumentSource $descriptorSource): bool
    {
        if ($catalogSource->field !== $descriptorSource->field
            || $catalogSource->slot !== $descriptorSource->slot
            || ! hash_equals($this->path($catalogSource->storagePath), $this->path($descriptorSource->storagePath))
            || ! hash_equals($catalogSource->storageDisk, $descriptorSource->storageDisk)) {
            return false;
        }

        return ($catalogSource->sourceVersion === null
                || hash_equals($catalogSource->sourceVersion, (string) $descriptorSource->sourceVersion))
            && ($catalogSource->sourceFingerprint === null
                || hash_equals(
                    strtolower($catalogSource->sourceFingerprint),
                    strtolower((string) $descriptorSource->sourceFingerprint),
                ));
    }

    /** @return array<string, mixed> */
    private function row(int $applicationId, StoredDocumentSource $source, mixed $timestamp): array
    {
        return [
            'apply_org_id' => $applicationId,
            'document_field' => $source->field,
            'document_slot' => $source->slot,
            'requirement_label' => $source->label,
            'original_filename' => $source->originalFilename,
            'storage_disk' => $source->storageDisk,
            'file_path' => $source->storagePath,
            'source_version' => $source->sourceVersion,
            'source_fingerprint' => $source->sourceFingerprint,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ];
    }

    private function path(string $path): string
    {
        return ltrim(str_replace('\\', '/', trim($path)), '/');
    }

    /**
     * @param  list<StoredDocumentSource>  $sources
     * @return list<StoredDocumentSource>
     */
    private function uniqueSources(array $sources): array
    {
        $seen = [];

        foreach ($sources as $source) {
            $key = $this->logicalKey($source);
            if (isset($seen[$key])) {
                throw new LogicException(
                    "The catalog returned duplicate document slot [{$source->field}:{$source->slot}]."
                );
            }

            $seen[$key] = true;
        }

        return $sources;
    }

    private function logicalKey(StoredDocumentSource $source): string
    {
        return $source->field."\0".$source->slot;
    }
}
