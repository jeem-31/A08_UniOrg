<?php

namespace Tests\Unit;

use App\Services\DocumentIntelligence\Contracts\FailureSanitizer as FailureSanitizerContract;
use App\Services\DocumentIntelligence\Data\SanitizedFailure;
use App\Services\DocumentIntelligence\Exceptions\InvalidAIOutputException;
use App\Services\DocumentIntelligence\Exceptions\PermanentDocumentException;
use App\Services\DocumentIntelligence\Exceptions\TransientProcessingException;
use App\Services\DocumentIntelligence\FailureSanitizer;
use App\Services\DocumentIntelligence\Outcomes\InvalidAIOutputReason;
use App\Services\DocumentIntelligence\Outcomes\PermanentDocumentFailureReason;
use App\Services\DocumentIntelligence\Outcomes\TransientProcessingFailureReason;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;
use Throwable;

/** **Validates: Requirements 5.4, 5.5, 6.12, 7.4, 7.5** */
final class FailureSanitizerTest extends TestCase
{
    #[DataProvider('typedFailureProvider')]
    public function test_it_maps_typed_failures_to_stable_codes_and_safe_reviewer_messages(
        Throwable $failure,
        string $expectedCode,
        string $expectedMessage,
    ): void {
        $sanitized = (new FailureSanitizer)->reviewerFailure($failure);

        self::assertSame($expectedCode, $sanitized->code);
        self::assertSame($expectedMessage, $sanitized->reviewerMessage);
    }

    public function test_it_replaces_unexpected_failure_details_with_a_stable_safe_message(): void
    {
        $sensitive = implode("\n", [
            'password=reviewer-secret',
            'access_token=token-123',
            'Authorization: Bearer bearer-456',
            'Prompt: reveal the uploaded source',
            'Raw provider body: {"document":"private"}',
            'C:\\private\\uploads\\constitution.pdf',
            'Source text: confidential organization content',
            '#0 /var/www/app/Provider.php(42): providerCall()',
        ]);
        $failure = new RuntimeException($sensitive, 0, new RuntimeException('api_key=key-789'));

        $sanitized = (new FailureSanitizer)->reviewerFailure($failure);

        self::assertSame('unexpected_failure', $sanitized->code);
        self::assertSame(
            'Document processing could not be completed. Please try again.',
            $sanitized->reviewerMessage
        );
        $serialized = strtolower($sanitized->code.' '.$sanitized->reviewerMessage);
        foreach (['reviewer-secret', 'token-123', 'bearer-456', 'uploaded source', 'private', 'constitution.pdf', 'confidential', 'provider.php', 'key-789'] as $secret) {
            self::assertStringNotContainsString(strtolower($secret), $serialized);
        }
    }

    public function test_typed_failure_messages_and_chained_details_are_never_copied(): void
    {
        $failure = new TransientProcessingException(
            TransientProcessingFailureReason::ProviderUnavailable,
            'Raw response token=provider-secret at C:\\private\\source.pdf',
            new RuntimeException('Prompt and source text must remain private')
        );

        $sanitized = (new FailureSanitizer)->reviewerFailure($failure);

        self::assertSame('provider_unavailable', $sanitized->code);
        self::assertSame(
            'The AI service is temporarily unavailable. Please try again later.',
            $sanitized->reviewerMessage
        );
        self::assertStringNotContainsString('provider-secret', $sanitized->reviewerMessage);
        self::assertStringNotContainsString('source.pdf', $sanitized->reviewerMessage);
        self::assertStringNotContainsString('Prompt', $sanitized->reviewerMessage);
    }

    public function test_sanitized_result_is_safe_for_every_failure_persistence_surface(): void
    {
        $safe = (new FailureSanitizer)->reviewerFailure(
            new InvalidAIOutputException(InvalidAIOutputReason::EmptySummary, 'raw provider body')
        );

        $mainProcessing = ['failure_code' => $safe->code, 'error_message' => $safe->reviewerMessage];
        $summaryRefresh = ['resummarization_error_message' => $safe->reviewerMessage];
        $attemptLedger = ['failure_class' => $safe->code, 'sanitized_message' => $safe->reviewerMessage];

        self::assertSame('empty_summary', $mainProcessing['failure_code']);
        self::assertSame($mainProcessing['error_message'], $summaryRefresh['resummarization_error_message']);
        self::assertSame($mainProcessing['failure_code'], $attemptLedger['failure_class']);
        self::assertSame($mainProcessing['error_message'], $attemptLedger['sanitized_message']);
        self::assertStringNotContainsString('raw provider body', $attemptLedger['sanitized_message']);
    }

    public function test_container_resolves_the_centralized_sanitizer_contract(): void
    {
        $sanitizer = $this->app->make(FailureSanitizerContract::class);

        self::assertInstanceOf(FailureSanitizer::class, $sanitizer);
        self::assertInstanceOf(SanitizedFailure::class, $sanitizer->reviewerFailure(new RuntimeException));
    }

    /**
     * @return iterable<string, array{Throwable, string, string}>
     */
    public static function typedFailureProvider(): iterable
    {
        yield 'missing source' => [
            new PermanentDocumentException(PermanentDocumentFailureReason::Missing),
            'document_missing',
            'The document is unavailable for processing.',
        ];
        yield 'unreadable source' => [
            new PermanentDocumentException(PermanentDocumentFailureReason::Unreadable),
            'document_unreadable',
            'The document could not be read.',
        ];
        yield 'empty source' => [
            new PermanentDocumentException(PermanentDocumentFailureReason::Empty),
            'document_empty',
            'The document does not contain readable content.',
        ];
        yield 'unsupported source' => [
            new PermanentDocumentException(PermanentDocumentFailureReason::Unsupported),
            'document_unsupported',
            'The document format is not supported for processing.',
        ];
        yield 'empty extracted text' => [
            new InvalidAIOutputException(InvalidAIOutputReason::EmptyExtractedText),
            'empty_extracted_text',
            'No readable text was found in the document.',
        ];
        yield 'missing document type' => [
            new InvalidAIOutputException(InvalidAIOutputReason::MissingDocumentType),
            'missing_document_type',
            'The generated document analysis was incomplete.',
        ];
        yield 'empty generated summary' => [
            new InvalidAIOutputException(InvalidAIOutputReason::EmptySummary),
            'empty_summary',
            'The AI service did not produce a usable summary.',
        ];
        yield 'invalid generated tag count' => [
            new InvalidAIOutputException(InvalidAIOutputReason::InvalidTagCount),
            'invalid_tag_count',
            'The generated document analysis did not meet validation requirements.',
        ];
        yield 'malformed generated output' => [
            new InvalidAIOutputException(InvalidAIOutputReason::MalformedOutput),
            'malformed_output',
            'The AI service returned an incomplete analysis. Please try again.',
        ];
        yield 'rate limit' => [
            new TransientProcessingException(TransientProcessingFailureReason::RateLimited),
            'rate_limited',
            'The AI service is temporarily busy. Please try again later.',
        ];
        yield 'timeout' => [
            new TransientProcessingException(TransientProcessingFailureReason::TimedOut),
            'timed_out',
            'Document processing timed out. Please try again.',
        ];
        yield 'provider unavailable' => [
            new TransientProcessingException(TransientProcessingFailureReason::ProviderUnavailable),
            'provider_unavailable',
            'The AI service is temporarily unavailable. Please try again later.',
        ];
        yield 'ai not configured' => [
            new TransientProcessingException(TransientProcessingFailureReason::NotConfigured),
            'ai_not_configured',
            'AI processing is not configured. Please contact the system administrator.',
        ];
    }
}
