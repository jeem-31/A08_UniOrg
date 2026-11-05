<?php

namespace App\Services\DocumentIntelligence;

use App\Services\DocumentIntelligence\Contracts\FailureSanitizer as FailureSanitizerContract;
use App\Services\DocumentIntelligence\Data\SanitizedFailure;
use App\Services\DocumentIntelligence\Exceptions\InvalidAIOutputException;
use App\Services\DocumentIntelligence\Exceptions\PermanentDocumentException;
use App\Services\DocumentIntelligence\Exceptions\TransientProcessingException;
use App\Services\DocumentIntelligence\Outcomes\InvalidAIOutputReason;
use App\Services\DocumentIntelligence\Outcomes\PermanentDocumentFailureReason;
use App\Services\DocumentIntelligence\Outcomes\TransientProcessingFailureReason;
use Throwable;

final class FailureSanitizer implements FailureSanitizerContract
{
    public function reviewerFailure(Throwable $failure): SanitizedFailure
    {
        return match (true) {
            $failure instanceof PermanentDocumentException => $this->permanentDocumentFailure($failure->reason),
            $failure instanceof InvalidAIOutputException => $this->invalidOutputFailure($failure->reason),
            $failure instanceof TransientProcessingException => $this->transientProcessingFailure($failure->reason),
            default => new SanitizedFailure(
                'unexpected_failure',
                'Document processing could not be completed. Please try again.'
            ),
        };
    }

    private function permanentDocumentFailure(PermanentDocumentFailureReason $reason): SanitizedFailure
    {
        $message = match ($reason) {
            PermanentDocumentFailureReason::Missing => 'The document is unavailable for processing.',
            PermanentDocumentFailureReason::Unreadable => 'The document could not be read.',
            PermanentDocumentFailureReason::Empty => 'The document does not contain readable content.',
            PermanentDocumentFailureReason::Unsupported => 'The document format is not supported for processing.',
        };

        return new SanitizedFailure($reason->value, $message);
    }

    private function invalidOutputFailure(InvalidAIOutputReason $reason): SanitizedFailure
    {
        $message = match ($reason) {
            InvalidAIOutputReason::EmptyExtractedText => 'No readable text was found in the document.',
            InvalidAIOutputReason::MissingDocumentType => 'The generated document analysis was incomplete.',
            InvalidAIOutputReason::EmptySummary => 'The AI service did not produce a usable summary.',
            InvalidAIOutputReason::InvalidTagCount => 'The generated document analysis did not meet validation requirements.',
            InvalidAIOutputReason::MalformedOutput => 'The AI service returned an incomplete analysis. Please try again.',
        };

        return new SanitizedFailure($reason->value, $message);
    }

    private function transientProcessingFailure(TransientProcessingFailureReason $reason): SanitizedFailure
    {
        $message = match ($reason) {
            TransientProcessingFailureReason::RateLimited => 'The AI service is temporarily busy. Please try again later.',
            TransientProcessingFailureReason::TimedOut => 'Document processing timed out. Please try again.',
            TransientProcessingFailureReason::ProviderUnavailable => 'The AI service is temporarily unavailable. Please try again later.',
            TransientProcessingFailureReason::NotConfigured => 'AI processing is not configured. Please contact the system administrator.',
        };

        return new SanitizedFailure($reason->value, $message);
    }
}
