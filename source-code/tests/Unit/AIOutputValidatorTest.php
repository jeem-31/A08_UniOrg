<?php

namespace Tests\Unit;

use App\Services\DocumentIntelligence\AIOutputValidator;
use App\Services\DocumentIntelligence\Data\CandidateAIOutput;
use App\Services\DocumentIntelligence\Data\ExtractedDocumentText;
use App\Services\DocumentIntelligence\Exceptions\InvalidAIOutputException;
use App\Services\DocumentIntelligence\Outcomes\InvalidAIOutputReason;
use PHPUnit\Framework\TestCase;

/** **Validates: Requirements 3.2, 3.3, 3.4, 3.5, 3.6, 3.7, 3.8, 3.10, 3.11** */
final class AIOutputValidatorTest extends TestCase
{
    private AIOutputValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validator = new AIOutputValidator;
    }

    public function test_it_normalizes_output_context_and_tags_in_first_seen_order(): void
    {
        $candidate = $this->candidate(
            summary: '  A material summary.  ',
            context: [
                ' organization ' => '  Student Council  ',
                'dates' => [' 2025-01-01 ', '', null],
                'details' => ['amount' => 0, 'rules' => []],
                'unavailable' => '   ',
            ],
            tags: [' Policy ', 'policy', '', ' Student Council ', 'STUDENT COUNCIL'],
        );

        $result = $this->validator->validate($candidate);

        self::assertSame('A material summary.', $result->summary);
        self::assertSame('constitution_bylaws', $result->documentType);
        self::assertSame('Constitution and By-Laws', $result->documentTypeName);
        self::assertSame([
            'organization' => 'Student Council',
            'dates' => ['2025-01-01'],
            'details' => ['amount' => 0],
        ], $result->context);
        self::assertSame(
            ['Policy', 'Student Council', 'Constitution and By-Laws'],
            $result->tags,
        );
    }

    public function test_it_rejects_each_required_blank_output_field(): void
    {
        $cases = [
            [$this->candidate(text: '  '), InvalidAIOutputReason::EmptyExtractedText],
            [$this->candidate(documentType: '  '), InvalidAIOutputReason::MissingDocumentType],
            [$this->candidate(summary: " \n\t "), InvalidAIOutputReason::EmptySummary],
        ];

        foreach ($cases as [$candidate, $expectedReason]) {
            try {
                $this->validator->validate($candidate);
                self::fail("Expected invalid output reason {$expectedReason->value}.");
            } catch (InvalidAIOutputException $failure) {
                self::assertSame($expectedReason, $failure->reason);
            }
        }
    }

    public function test_it_rejects_raw_tag_counts_outside_the_inclusive_bounds(): void
    {
        foreach ([[], array_fill(0, 11, 'duplicate')] as $tags) {
            try {
                $this->validator->validate($this->candidate(tags: $tags));
                self::fail('Expected the raw tag count to be rejected.');
            } catch (InvalidAIOutputException $failure) {
                self::assertSame(InvalidAIOutputReason::InvalidTagCount, $failure->reason);
            }
        }
    }

    public function test_it_accepts_the_inclusive_one_tag_boundary_when_detected_type_is_present(): void
    {
        $result = $this->validator->validate(
            $this->candidate(tags: ['constitution AND by-laws']),
        );

        self::assertSame(['constitution AND by-laws'], $result->tags);
    }

    public function test_it_rechecks_the_final_bound_without_truncating_for_detected_type(): void
    {
        $tags = array_map(static fn (int $number): string => "Tag {$number}", range(1, 10));

        $this->expectExceptionObject(
            new InvalidAIOutputException(InvalidAIOutputReason::InvalidTagCount),
        );

        $this->validator->validate($this->candidate(tags: $tags));
    }

    public function test_ten_normalized_tags_are_valid_when_detected_type_is_present(): void
    {
        $tags = array_map(static fn (int $number): string => "Tag {$number}", range(1, 9));
        $tags[] = 'constitution AND by-laws';

        $result = $this->validator->validate($this->candidate(tags: $tags));

        self::assertCount(10, $result->tags);
        self::assertSame('constitution AND by-laws', $result->tags[9]);
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  list<string>  $tags
     */
    private function candidate(
        string $text = 'Source-derived text',
        string $documentType = 'constitution_bylaws',
        string $documentTypeName = 'Constitution and By-Laws',
        string $summary = 'Material summary.',
        array $context = [],
        array $tags = ['Constitution and By-Laws'],
    ): CandidateAIOutput {
        return new CandidateAIOutput(
            new ExtractedDocumentText($text, 'application/pdf', 'pdf-text'),
            $documentType,
            $documentTypeName,
            $summary,
            $context,
            $tags,
        );
    }
}
