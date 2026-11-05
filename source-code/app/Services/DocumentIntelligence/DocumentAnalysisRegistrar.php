<?php

namespace App\Services\DocumentIntelligence;

use App\Models\ApplyOrg;
use App\Models\ProcessedDocument;
use App\Services\DocumentLibrary\DocumentLibrarySourceIndexer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;

final class DocumentAnalysisRegistrar
{
    public function __construct(
        private readonly SupportedDocumentCatalog $catalog,
        private readonly DocumentLibrarySourceIndexer $sourceIndexer,
    ) {}

    /**
     * Build immutable source identity from the exact bytes that were stored.
     */
    public function descriptorFromBytes(
        int $applyOrgId,
        string $field,
        string $slot,
        string $originalFilename,
        string $storageDisk,
        string $storagePath,
        string $unchangedBytes,
    ): StoredDocumentDescriptor {
        $definition = $this->catalog->definition($field);

        $descriptor = new StoredDocumentDescriptor(
            $applyOrgId,
            $field,
            $slot,
            $definition->label,
            $originalFilename,
            $storageDisk,
            $storagePath,
            (string) Str::uuid(),
            hash('sha256', $unchangedBytes),
        );
        $this->validateDescriptor($descriptor);

        return $descriptor;
    }

    /**
     * Reconcile current stored sources inside the caller-owned transaction.
     * Returned commands must be dispatched only after that transaction commits.
     *
     * @param  iterable<StoredDocumentDescriptor>  $descriptors
     * @return list<DocumentAnalysisDispatchCommand>
     */
    public function register(ApplyOrg $application, iterable $descriptors): array
    {
        if (DB::connection()->transactionLevel() < 1) {
            throw new LogicException('Document analyses must be registered inside the caller transaction.');
        }
        if (! $application->exists || $application->getKey() === null) {
            throw new LogicException('The organization application must be persisted before registration.');
        }

        $prepared = $this->prepareDescriptors((int) $application->getKey(), $descriptors);
        $durableApplication = ApplyOrg::query()
            ->whereKey($application->getKey())
            ->lockForUpdate()
            ->first();
        if ($durableApplication === null) {
            throw new LogicException('The organization application association could not be persisted.');
        }

        $commands = [];
        foreach ($prepared as $descriptor) {
            $command = $this->registerDescriptor($durableApplication, $descriptor);
            if ($command !== null) {
                $commands[] = $command;
            }
        }

        $this->sourceIndexer->syncApplication($durableApplication, $prepared);

        return $commands;
    }

    /** @param iterable<StoredDocumentDescriptor> $descriptors @return list<StoredDocumentDescriptor> */
    private function prepareDescriptors(int $applicationId, iterable $descriptors): array
    {
        $prepared = [];
        foreach ($descriptors as $descriptor) {
            if (! $descriptor instanceof StoredDocumentDescriptor) {
                throw new InvalidArgumentException('Registration accepts only stored document descriptors.');
            }
            if ($descriptor->applyOrgId !== $applicationId) {
                throw new InvalidArgumentException('A descriptor belongs to a different organization application.');
            }

            $this->validateDescriptor($descriptor);
            $key = $descriptor->field."\0".$descriptor->slot;
            if (isset($prepared[$key]) && $prepared[$key] != $descriptor) {
                throw new InvalidArgumentException("Conflicting descriptors were provided for [{$descriptor->field}:{$descriptor->slot}].");
            }
            $prepared[$key] = $descriptor;
        }

        return array_values($prepared);
    }

    private function registerDescriptor(
        ApplyOrg $application,
        StoredDocumentDescriptor $descriptor,
    ): ?DocumentAnalysisDispatchCommand {
        $analysis = ProcessedDocument::query()
            ->where('apply_org_id', $descriptor->applyOrgId)
            ->where('document_field', $descriptor->field)
            ->where('document_slot', $descriptor->slot)
            ->lockForUpdate()
            ->first();
        $associationReady = $this->applicationMatchesDescriptor($application, $descriptor);

        if ($analysis === null) {
            $analysis = new ProcessedDocument;
            $cycleId = (string) Str::uuid();
            $this->resetForSource($analysis, $descriptor, $cycleId, $associationReady);
            $analysis->save();

            return $associationReady ? $this->command($analysis, $cycleId) : null;
        }

        if (! $this->sameSourceIdentity($analysis, $descriptor)) {
            $cycleId = (string) Str::uuid();
            $this->resetForSource($analysis, $descriptor, $cycleId, $associationReady);
            $analysis->save();

            return $associationReady ? $this->command($analysis, $cycleId) : null;
        }

        if (! $associationReady) {
            if ($analysis->association_ready_at !== null) {
                $this->resetForSource(
                    $analysis,
                    $descriptor,
                    (string) Str::uuid(),
                    false,
                );
                $analysis->save();
            }

            return null;
        }

        if ($analysis->association_ready_at === null) {
            $cycleId = $analysis->processing_status === ProcessedDocument::STATUS_PENDING
                && is_string($analysis->active_cycle_id)
                && $analysis->active_cycle_id !== ''
                    ? $analysis->active_cycle_id
                    : (string) Str::uuid();
            $analysis->forceFill([
                'original_filename' => $descriptor->originalFilename,
                'file_path' => $descriptor->storagePath,
                'storage_disk' => $descriptor->storageDisk,
                'association_ready_at' => now(),
                'processing_status' => ProcessedDocument::STATUS_PENDING,
                'active_cycle_id' => $cycleId,
                'cycle_kind' => 'initial',
                'cycle_attempt_count' => 0,
            ])->save();

            return $this->command($analysis, $cycleId);
        }

        // Repeated registration of the same durable source is intentionally a no-op.
        return null;
    }

    private function resetForSource(
        ProcessedDocument $analysis,
        StoredDocumentDescriptor $descriptor,
        string $cycleId,
        bool $associationReady,
    ): void {
        $analysis->forceFill([
            'apply_org_id' => $descriptor->applyOrgId,
            'document_field' => $descriptor->field,
            'document_slot' => $descriptor->slot,
            'original_filename' => $descriptor->originalFilename,
            'file_path' => $descriptor->storagePath,
            'storage_disk' => $descriptor->storageDisk,
            'source_version' => $descriptor->sourceVersion,
            'source_fingerprint' => strtolower($descriptor->sourceFingerprint),
            'association_ready_at' => $associationReady ? now() : null,
            'active_cycle_id' => $cycleId,
            'cycle_kind' => 'initial',
            'cycle_attempt_count' => 0,
            'processing_started_at' => null,
            'processing_duration_ms' => null,
            'processing_status' => ProcessedDocument::STATUS_PENDING,
            'failure_code' => null,
            'error_message' => null,
            'processed_at' => null,
            'output_source_version' => null,
            'document_type' => null,
            'document_type_name' => null,
            'summary' => null,
            'tags' => null,
            'key_information' => null,
            'extracted_text' => null,
            'confidence_score' => null,
            'summary_version' => 0,
            'summary_processed_at' => null,
            'resummarization_status' => 'idle',
            'resummarization_cycle_id' => null,
            'resummarization_error_message' => null,
            'last_resummarization_changed' => null,
        ]);
    }

    private function command(
        ProcessedDocument $analysis,
        string $cycleId,
    ): DocumentAnalysisDispatchCommand {
        return new DocumentAnalysisDispatchCommand(
            (int) $analysis->getKey(),
            (string) $analysis->source_version,
            $cycleId,
        );
    }

    private function validateDescriptor(StoredDocumentDescriptor $descriptor): void
    {
        $definition = $this->catalog->definition($descriptor->field);
        if ($definition->label !== $descriptor->label) {
            throw new InvalidArgumentException("The descriptor label does not match [{$descriptor->field}].");
        }
        if ($descriptor->slot === ''
            || (! $definition->isMultiFile() && $descriptor->slot !== SupportedDocumentCatalog::PRIMARY_SLOT)) {
            throw new InvalidArgumentException("Invalid document slot for [{$descriptor->field}].");
        }
        foreach ([
            'original filename' => $descriptor->originalFilename,
            'storage disk' => $descriptor->storageDisk,
            'storage path' => $descriptor->storagePath,
        ] as $name => $value) {
            if (trim($value) === '') {
                throw new InvalidArgumentException("The descriptor {$name} is required.");
            }
        }
        if (! Str::isUuid($descriptor->sourceVersion)) {
            throw new InvalidArgumentException('The source version must be a UUID.');
        }
        if (! preg_match('/^[a-f0-9]{64}$/i', $descriptor->sourceFingerprint)) {
            throw new InvalidArgumentException('The source fingerprint must be a SHA-256 digest.');
        }
    }

    private function sameSourceIdentity(
        ProcessedDocument $analysis,
        StoredDocumentDescriptor $descriptor,
    ): bool {
        return hash_equals((string) $analysis->source_version, $descriptor->sourceVersion)
            && hash_equals(
                strtolower((string) $analysis->source_fingerprint),
                strtolower($descriptor->sourceFingerprint),
            );
    }

    private function applicationMatchesDescriptor(
        ApplyOrg $application,
        StoredDocumentDescriptor $descriptor,
    ): bool {
        foreach ($this->catalog->sourcesFor($application, $descriptor->storageDisk) as $source) {
            if ($source->field !== $descriptor->field
                || $source->slot !== $descriptor->slot
                || ! hash_equals($this->path($source->storagePath), $this->path($descriptor->storagePath))
                || ! hash_equals($source->storageDisk, $descriptor->storageDisk)) {
                continue;
            }
            if ($source->sourceVersion !== null
                && ! hash_equals($source->sourceVersion, $descriptor->sourceVersion)) {
                continue;
            }
            if ($source->sourceFingerprint !== null
                && ! hash_equals(
                    strtolower($source->sourceFingerprint),
                    strtolower($descriptor->sourceFingerprint),
                )) {
                continue;
            }

            return true;
        }

        return false;
    }

    private function path(string $path): string
    {
        return ltrim(str_replace('\\', '/', trim($path)), '/');
    }
}
