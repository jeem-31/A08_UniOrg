<?php

namespace Tests\Unit;

use App\Services\DocumentIntelligence\Contracts\AIOutputValidator;
use App\Services\DocumentIntelligence\Contracts\DocumentIntelligenceProvider;
use App\Services\DocumentIntelligence\Contracts\FailureSanitizer;
use App\Services\DocumentIntelligence\Contracts\SourceDocumentReader;
use App\Services\DocumentIntelligence\Contracts\SourceTextExtractor;
use App\Services\DocumentIntelligence\Data\CandidateAIOutput;
use App\Services\DocumentIntelligence\Data\CandidateSummary;
use App\Services\DocumentIntelligence\Data\ExtractedDocumentText;
use App\Services\DocumentIntelligence\Data\SanitizedFailure;
use App\Services\DocumentIntelligence\Data\ValidatedAIOutput;
use App\Services\DocumentIntelligence\Exceptions\InvalidAIOutputException;
use App\Services\DocumentIntelligence\Exceptions\PermanentDocumentException;
use App\Services\DocumentIntelligence\Exceptions\TransientProcessingException;
use App\Services\DocumentIntelligence\Outcomes\InvalidAIOutputReason;
use App\Services\DocumentIntelligence\Outcomes\PermanentDocumentFailureReason;
use App\Services\DocumentIntelligence\Outcomes\StaleCycleOutcome;
use App\Services\DocumentIntelligence\Outcomes\TransientProcessingFailureReason;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/** **Validates: Requirements 2.3, 2.4, 2.5, 3.1, 3.2, 3.3, 3.4, 5.1, 5.3** */
final class DocumentIntelligenceContractsTest extends TestCase
{
    public function test_processing_boundaries_are_provider_neutral_interfaces(): void
    {
        foreach ([SourceDocumentReader::class, SourceTextExtractor::class, DocumentIntelligenceProvider::class, AIOutputValidator::class, FailureSanitizer::class] as $contract) {
            self::assertTrue((new ReflectionClass($contract))->isInterface());
        }

        self::assertSame(['open'], get_class_methods(SourceDocumentReader::class));
        self::assertSame(['extract'], get_class_methods(SourceTextExtractor::class));
        self::assertSame(['analyze', 'summarize'], get_class_methods(DocumentIntelligenceProvider::class));
        self::assertSame(['validate'], get_class_methods(AIOutputValidator::class));
        self::assertSame(['reviewerFailure'], get_class_methods(FailureSanitizer::class));
    }

    public function test_candidate_and_validated_outputs_retain_structured_generated_data(): void
    {
        $text = new ExtractedDocumentText('Source-derived text', 'application/pdf', 'pdf-text');
        $candidate = new CandidateAIOutput($text, 'constitution', 'Constitution', 'Purpose and material content.', ['school_year' => '2025-2026'], ['constitution']);
        $validated = new ValidatedAIOutput($candidate->extractedText, $candidate->documentType, $candidate->documentTypeName, $candidate->summary, $candidate->context, $candidate->tags);

        self::assertTrue((new ReflectionClass(CandidateAIOutput::class))->isReadOnly());
        self::assertTrue((new ReflectionClass(ValidatedAIOutput::class))->isReadOnly());
        self::assertSame('Source-derived text', $validated->extractedText->text);
        self::assertSame(['school_year' => '2025-2026'], $validated->context);
        self::assertSame(['constitution'], $validated->tags);
        self::assertSame('A clearer summary', (new CandidateSummary('A clearer summary'))->summary);
        self::assertSame('document_unreadable', (new SanitizedFailure('document_unreadable', 'The document could not be read.'))->code);
    }

    public function test_failures_and_stale_cycles_have_explicit_typed_outcomes(): void
    {
        $permanent = new PermanentDocumentException(PermanentDocumentFailureReason::Unsupported);
        $transient = new TransientProcessingException(TransientProcessingFailureReason::RateLimited);
        $invalid = new InvalidAIOutputException(InvalidAIOutputReason::EmptySummary);
        $stale = new StaleCycleOutcome(42, 'source-v2', 'cycle-v1');

        self::assertSame(PermanentDocumentFailureReason::Unsupported, $permanent->reason);
        self::assertSame(TransientProcessingFailureReason::RateLimited, $transient->reason);
        self::assertSame(InvalidAIOutputReason::EmptySummary, $invalid->reason);
        self::assertSame(42, $stale->analysisId);
        self::assertSame('source_or_cycle_mismatch', $stale->reason);
    }
}
