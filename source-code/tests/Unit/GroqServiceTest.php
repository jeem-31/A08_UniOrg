<?php

namespace Tests\Unit;

use App\Services\GroqService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class GroqServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        putenv('GROQ_API_KEY=gsk_test-key');
        config()->set('services.groq', ['api_key' => 'gsk_test-key']);
    }

    protected function tearDown(): void
    {
        putenv('GROQ_API_KEY');
        parent::tearDown();
    }

    public function test_it_sends_openai_style_chat_completion_with_bearer_token(): void
    {
        Http::fake([
            'api.groq.com/*' => Http::response([
                'choices' => [['message' => ['content' => 'Analysis result']]],
            ], 200),
        ]);

        $service = new GroqService;
        $result = $service->generate('Analyze this.', 2048);

        self::assertSame('Analysis result', $result);
        Http::assertSent(function (Request $request) {
            $body = $request->data();

            return $request->hasHeader('Authorization', 'Bearer gsk_test-key')
                && $body['model'] === 'llama-3.3-70b-versatile'
                && $body['max_tokens'] === 2048
                && ($body['response_format'] ?? null) === null
                && $body['messages'][0]['content'] === 'Analyze this.';
        });
    }

    public function test_json_mode_requests_a_json_object_response(): void
    {
        Http::fake([
            'api.groq.com/*' => Http::response([
                'choices' => [['message' => ['content' => '{"ok":true}']]],
            ], 200),
        ]);

        $service = new GroqService;
        $service->generate('Return JSON.', 4096, true);

        Http::assertSent(fn (Request $request) => $request->data()['response_format'] === ['type' => 'json_object']);
    }

    public function test_rate_limit_response_throws_runtime_exception_with_status_and_body(): void
    {
        Http::fake([
            'api.groq.com/*' => Http::response([
                'error' => ['message' => 'You exceeded your current quota, please check your plan and billing details.'],
            ], 429),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Groq API request failed with status 429');

        (new GroqService)->generate('Analyze this.');
    }

    public function test_empty_content_throws_unexpected_format_exception(): void
    {
        Http::fake([
            'api.groq.com/*' => Http::response([
                'choices' => [['message' => ['content' => '']]],
            ], 200),
        ]);

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('Unexpected Groq API response format');

        (new GroqService)->generate('Analyze this.');
    }

    public function test_not_configured_throws_when_key_missing(): void
    {
        putenv('GROQ_API_KEY');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Groq AI is not configured.');

        (new GroqService)->generate('Analyze this.');
    }

    public function test_summarize_document_parses_json_response(): void
    {
        $payload = json_encode([
            'document_type' => 'Certification of Officers',
            'organization_name' => 'Junior Philippine Computer Society',
            'school_year' => '2025-2026',
            'key_dates' => ['January 15, 2026'],
            'summary' => 'A useful summary.',
            'key_information' => ['officers' => [['name' => 'Juan Dela Cruz', 'position' => 'President']]],
            'tags' => ['Certification of Officers', 'Officers'],
        ]);

        Http::fake([
            'api.groq.com/*' => Http::response([
                'choices' => [['message' => ['content' => $payload]]],
            ], 200),
        ]);

        $result = (new GroqService)->summarizeDocument('CERTIFICATION OF OFFICERS...');

        self::assertSame('Certification of Officers', $result['document_type']);
        self::assertSame('Junior Philippine Computer Society', $result['organization_name']);
        self::assertSame('A useful summary.', $result['summary']);
        self::assertCount(1, $result['key_information']['officers']);
    }

    public function test_classify_document_returns_trimmed_category_label(): void
    {
        Http::fake([
            'api.groq.com/*' => Http::response([
                'choices' => [['message' => ['content' => '```json' . PHP_EOL . 'Certification of Officers' . PHP_EOL . '```']]],
            ], 200),
        ]);

        $result = (new GroqService)->classifyDocument('President: Juan Dela Cruz');

        self::assertSame('Certification of Officers', $result);
    }
}
