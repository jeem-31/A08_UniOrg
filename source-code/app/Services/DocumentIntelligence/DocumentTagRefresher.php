<?php

namespace App\Services\DocumentIntelligence;

use App\Models\ProcessedDocument;
use App\Services\DocumentIntelligence\Contracts\AIOutputValidator;
use App\Services\DocumentIntelligence\Contracts\DocumentIntelligenceProvider;
use App\Services\DocumentIntelligence\Contracts\SourceDocumentReader;
use App\Services\DocumentIntelligence\Contracts\SourceTextExtractor;
use App\Services\DocumentIntelligence\Data\ExtractedDocumentText;
use App\Services\DocumentIntelligence\Data\ReadOnlyDocumentSource;
use App\Services\DocumentIntelligence\Exceptions\InvalidAIOutputException;
use App\Services\DocumentIntelligence\Exceptions\PermanentDocumentException;
use App\Services\DocumentIntelligence\Outcomes\PermanentDocumentFailureReason;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Refreshes only the auto-tags of a completed analysis.
 *
 * Unlike re-summarization (summary-only) and full processing, this never
 * touches the summary or any other output column, so concurrent
 * summary refreshes cannot be clobbered: each writer only dirties its
 * own column. Returns null when the analysis is not eligible or when
 * the previous tags were retained (invalid AI output keeps old tags).
 */
final class DocumentTagRefresher
{
    public function __construct(
        private readonly SupportedDocumentCatalog $catalog,
        private readonly SourceDocumentReader $sourceReader,
        private readonly SourceTextExtractor $textExtractor,
        private readonly DocumentIntelligenceProvider $intelligenceProvider,
        private readonly AIOutputValidator $outputValidator,
    ) {}

    public function refresh(int $analysisId, string $sourceVersion): ?ProcessedDocument
    {
        $analysis = ProcessedDocument::query()
            ->whereKey($analysisId)
            ->where('source_version', $sourceVersion)
            ->where('output_source_version', $sourceVersion)
            ->where('processing_status', ProcessedDocument::STATUS_COMPLETED)
            ->first();

        if ($analysis === null || ! $this->catalog->supports((string) $analysis->document_field)) {
            return null;
        }

        $source = null;

        try {
            $text = $this->retainedText($analysis);
            if ($text === null) {
                $source = $this->sourceReader->open($this->descriptor($analysis));
                $text = $this->textExtractor->extract($source);
            }

            if (trim($text->text) === '') {
                throw new PermanentDocumentException(PermanentDocumentFailureReason::Empty);
            }

            $output = $this->outputValidator->validate(
                $this->intelligenceProvider->analyze($text)
            );

            $analysis->forceFill(['tags' => $output->tags])->save();

            return $analysis->fresh() ?? $analysis;
        } catch (InvalidAIOutputException $failure) {
            Log::warning('Document re-tagging kept previous tags: invalid AI output.', [
                'analysis_id' => $analysisId,
                'message' => $failure->getMessage(),
            ]);

            return $analysis->fresh() ?? $analysis;
        } catch (PermanentDocumentException $failure) {
            Log::warning('Document re-tagging kept previous tags: no text to tag from.', [
                'analysis_id' => $analysisId,
                'message' => $failure->getMessage(),
            ]);

            return $analysis->fresh() ?? $analysis;
        } finally {
            $this->cleanup($source);
        }
    }

    private function descriptor(ProcessedDocument $analysis): StoredDocumentDescriptor
    {
        return new StoredDocumentDescriptor(
            (int) $analysis->apply_org_id,
            (string) $analysis->document_field,
            (string) $analysis->document_slot,
            $this->catalog->definition((string) $analysis->document_field)->label,
            (string) $analysis->original_filename,
            (string) $analysis->storage_disk,
            (string) $analysis->file_path,
            (string) $analysis->source_version,
            (string) $analysis->source_fingerprint,
        );
    }

    private function retainedText(ProcessedDocument $analysis): ?ExtractedDocumentText
    {
        $text = trim((string) $analysis->extracted_text);

        return $text === '' ? null : new ExtractedDocumentText(
            $text,
            extractionMethod: 'retained-current-source',
        );
    }

    private function cleanup(?ReadOnlyDocumentSource $source): void
    {
        if ($source === null || ! method_exists($source, 'cleanup')) {
            return;
        }

        try {
            $source->cleanup();
        } catch (Throwable) {
            // Cleanup is best-effort and never changes the stored source.
        }
    }
}
