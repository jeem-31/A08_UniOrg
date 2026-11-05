<?php

namespace App\Services\DocumentIntelligence;

use App\Models\ApplyOrg;
use App\Models\ProcessedDocument;

final readonly class CurrentDocumentAnalysisLocator
{
    public function __construct(private SupportedDocumentCatalog $catalog) {}

    /**
     * @param  iterable<ProcessedDocument>  $analyses
     * @return list<ProcessedDocument>
     */
    public function forApplication(ApplyOrg $application, iterable $analyses): array
    {
        $candidates = [];
        foreach ($analyses as $analysis) {
            if ($analysis instanceof ProcessedDocument) {
                $candidates[] = $analysis;
            }
        }

        return array_values(array_filter(
            $candidates,
            fn (ProcessedDocument $analysis): bool => $this->isCurrent($application, $analysis),
        ));
    }

    public function isCurrent(ApplyOrg $application, ProcessedDocument $analysis): bool
    {
        foreach ($this->catalog->sourcesFor($application, (string) $analysis->storage_disk) as $source) {
            if ($this->matches($application, $analysis, $source)) {
                return true;
            }
        }

        return false;
    }

    private function matches(
        ApplyOrg $application,
        ProcessedDocument $analysis,
        StoredDocumentSource $source,
    ): bool {
        if ((int) $analysis->apply_org_id !== (int) $application->getKey()
            || $analysis->association_ready_at === null
            || $source->field !== $analysis->document_field
            || $source->slot !== $analysis->document_slot
            || ! hash_equals($this->path($source->storagePath), $this->path((string) $analysis->file_path))
            || ! hash_equals($source->storageDisk, (string) $analysis->storage_disk)) {
            return false;
        }

        if ($source->sourceVersion !== null
            && ! hash_equals($source->sourceVersion, (string) $analysis->source_version)) {
            return false;
        }

        return $source->sourceFingerprint === null
            || hash_equals(
                strtolower($source->sourceFingerprint),
                strtolower((string) $analysis->source_fingerprint),
            );
    }

    private function path(string $path): string
    {
        return ltrim(str_replace('\\', '/', $path), '/');
    }
}
