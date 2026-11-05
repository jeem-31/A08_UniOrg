<?php

namespace App\ViewModels;

use App\Models\ApplyOrg;
use App\Models\ProcessedDocument;
use App\Services\DocumentIntelligence\CurrentDocumentAnalysisLocator;
use App\Services\DocumentIntelligence\SupportedDocumentCatalog;
use LogicException;

final readonly class CurrentDocumentAnalysisPresenter
{
    public const ADVISORY = '';

    public function __construct(
        private SupportedDocumentCatalog $catalog,
        private CurrentDocumentAnalysisLocator $locator,
    ) {}

    /** @return list<array<string, mixed>> */
    public function forApplication(ApplyOrg $application): array
    {
        if (! $application->relationLoaded('processedDocuments')) {
            throw new LogicException('Current analyses must be eager loaded before presentation.');
        }

        return array_map(
            fn (ProcessedDocument $analysis): array => $this->present($application, $analysis),
            $this->locator->forApplication($application, $application->processedDocuments),
        );
    }

    /** @return array<string, mixed> */
    public function aggregate(ApplyOrg $application): array
    {
        $presented = $this->forApplication($application);
        $counts = array_fill_keys(['pending', 'processing', 'completed', 'failed'], 0);

        foreach ($presented as $document) {
            $status = $document['status'];
            if (array_key_exists($status, $counts)) {
                $counts[$status]++;
            }
        }

        $total = count($presented);
        $available = count(array_filter(
            $presented,
            fn (array $document): bool => $document['output_available'],
        ));

        return [
            'total' => $total,
            'counts' => $counts,
            'output_available' => $available,
            'status' => $this->aggregateStatus($counts, $total, $available),
            'label' => $this->aggregateLabel($counts, $total, $available),
        ];
    }

    /** @return array<string, mixed> */
    private function present(ApplyOrg $application, ProcessedDocument $analysis): array
    {
        $output = $analysis->outputForCurrentSource();
        $outputAvailable = $output !== null;
        $refreshStatus = $outputAvailable ? ($analysis->resummarization_status ?: 'idle') : null;
        $refreshActive = $refreshStatus === 'processing';
        $refreshFailed = $refreshStatus === 'failed';
        $failed = $analysis->processing_status === ProcessedDocument::STATUS_FAILED;

        return [
            'analysis_id' => (int) $analysis->getKey(),
            'document_field' => (string) $analysis->document_field,
            'document_slot' => (string) $analysis->document_slot,
            'requirement_label' => $this->catalog->definition((string) $analysis->document_field)->label,
            'original_filename' => (string) $analysis->original_filename,
            'status' => (string) $analysis->processing_status,
            'status_label' => ucfirst((string) $analysis->processing_status),
            'output_available' => $outputAvailable,
            'failure_text' => $failed
                ? ($analysis->error_message ?: 'Document processing failed. Please try again.')
                : null,
            'advisory_output' => $outputAvailable ? [
                'ai_generated' => true,
                'advisory' => self::ADVISORY,
                'summary' => $output['summary'],
                'context' => is_array($output['key_information']) ? $output['key_information'] : [],
            ] : null,
            'details' => $outputAvailable ? [
                'requirement_name' => $this->catalog->definition((string) $analysis->document_field)->label,
                'original_filename' => (string) $analysis->original_filename,
                'detected_document_type' => $output['document_type_name'] ?: $output['document_type'],
                'status' => (string) $analysis->processing_status,
                'tags' => is_array($output['tags']) ? $output['tags'] : [],
                'processed_at' => $analysis->processed_at?->toIso8601String(),
                'processing_duration_ms' => $analysis->processing_duration_ms,
                'ai_generated' => true,
                'advisory' => self::ADVISORY,
            ] : null,
            'summary_refresh' => $outputAvailable ? [
                'status' => $refreshStatus,
                'in_progress' => $refreshActive,
                'failure_text' => $refreshFailed ? $analysis->resummarization_error_message : null,
                'last_result_changed' => $analysis->last_resummarization_changed,
                'summary_version' => (int) $analysis->summary_version,
                'summary_processed_at' => $analysis->summary_processed_at?->toIso8601String(),
            ] : null,
            'allowed_actions' => [
                'view_document' => true,
                'view_summary' => $outputAvailable,
                'view_details' => $outputAvailable,
                'retry' => $failed,
                'resummarize' => $outputAvailable && ! $refreshActive,
                'retag' => $outputAvailable,
            ],
            'urls' => [
                'status' => route('osas.apply-org.analyses.status', [$application, $analysis]),
                'source' => route('osas.apply-org.analyses.source', [$application, $analysis]),
                'summary' => route('osas.apply-org.analyses.summary', [$application, $analysis]),
                'details' => route('osas.apply-org.analyses.details', [$application, $analysis]),
                'retry' => route('osas.apply-org.analyses.retry', [$application, $analysis]),
                'resummarize' => route('osas.apply-org.analyses.resummarize', [$application, $analysis]),
                'retag' => route('osas.apply-org.analyses.retag', [$application, $analysis]),
            ],
        ];
    }

    /** @param array<string, int> $counts */
    private function aggregateStatus(array $counts, int $total, int $available): string
    {
        if ($total === 0) {
            return 'none';
        }
        if ($counts['processing'] > 0) {
            return 'processing';
        }
        if ($counts['pending'] > 0) {
            return 'pending';
        }
        if ($counts['failed'] > 0) {
            return $counts['failed'] === $total ? 'failed' : 'mixed';
        }

        return $available === $total ? 'completed' : 'mixed';
    }

    /** @param array<string, int> $counts */
    private function aggregateLabel(array $counts, int $total, int $available): string
    {
        return match ($this->aggregateStatus($counts, $total, $available)) {
            'none' => 'No Documents',
            'processing' => "Processing ({$counts['processing']})",
            'pending' => "Pending ({$counts['pending']})",
            'failed' => "Failed ({$counts['failed']})",
            'completed' => "Completed ({$available}/{$total})",
            default => "Mixed ({$available}/{$total} complete)",
        };
    }
}
