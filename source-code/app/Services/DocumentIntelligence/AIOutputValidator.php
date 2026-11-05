<?php

namespace App\Services\DocumentIntelligence;

use App\Services\DocumentIntelligence\Contracts\AIOutputValidator as AIOutputValidatorContract;
use App\Services\DocumentIntelligence\Data\CandidateAIOutput;
use App\Services\DocumentIntelligence\Data\ValidatedAIOutput;
use App\Services\DocumentIntelligence\Exceptions\InvalidAIOutputException;
use App\Services\DocumentIntelligence\Outcomes\InvalidAIOutputReason;

final class AIOutputValidator implements AIOutputValidatorContract
{
    private const MINIMUM_TAGS = 1;

    private const MAXIMUM_TAGS = 10;

    public function validate(CandidateAIOutput $candidate): ValidatedAIOutput
    {
        if (trim($candidate->extractedText->text) === '') {
            throw new InvalidAIOutputException(InvalidAIOutputReason::EmptyExtractedText);
        }

        $documentType = trim($candidate->documentType);

        if ($documentType === '') {
            throw new InvalidAIOutputException(InvalidAIOutputReason::MissingDocumentType);
        }

        $summary = trim($candidate->summary);

        if ($summary === '') {
            throw new InvalidAIOutputException(InvalidAIOutputReason::EmptySummary);
        }

        $rawTagCount = count($candidate->tags);

        if ($rawTagCount < self::MINIMUM_TAGS || $rawTagCount > self::MAXIMUM_TAGS) {
            throw new InvalidAIOutputException(InvalidAIOutputReason::InvalidTagCount);
        }

        $documentTypeName = trim($candidate->documentTypeName);
        $detectedTypeTag = $documentTypeName !== '' ? $documentTypeName : $documentType;
        $tags = $this->normalizeTags($candidate->tags, $detectedTypeTag);

        if (count($tags) < self::MINIMUM_TAGS || count($tags) > self::MAXIMUM_TAGS) {
            throw new InvalidAIOutputException(InvalidAIOutputReason::InvalidTagCount);
        }

        return new ValidatedAIOutput(
            $candidate->extractedText,
            $documentType,
            $documentTypeName,
            $summary,
            $this->mapContext($candidate->context),
            $tags,
        );
    }

    /**
     * @param  list<string>  $rawTags
     * @return list<string>
     */
    private function normalizeTags(array $rawTags, string $detectedTypeTag): array
    {
        $normalized = [];
        $seen = [];

        foreach ($rawTags as $tag) {
            if (! is_string($tag)) {
                throw new InvalidAIOutputException(InvalidAIOutputReason::InvalidTagCount);
            }

            $tag = trim($tag);

            if ($tag === '') {
                continue;
            }

            $comparisonKey = mb_strtolower($tag, 'UTF-8');

            if (isset($seen[$comparisonKey])) {
                continue;
            }

            $seen[$comparisonKey] = true;
            $normalized[] = $tag;
        }

        $detectedTypeKey = mb_strtolower($detectedTypeTag, 'UTF-8');

        if (! isset($seen[$detectedTypeKey])) {
            $normalized[] = $detectedTypeTag;
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function mapContext(array $context): array
    {
        $mapped = [];

        foreach ($context as $key => $value) {
            $key = trim((string) $key);

            if ($key === '' || array_key_exists($key, $mapped)) {
                continue;
            }

            [$available, $normalizedValue] = $this->normalizeContextValue($value);

            if ($available) {
                $mapped[$key] = $normalizedValue;
            }
        }

        return $mapped;
    }

    /** @return array{bool, mixed} */
    private function normalizeContextValue(mixed $value): array
    {
        if ($value === null) {
            return [false, null];
        }

        if (is_string($value)) {
            $value = trim($value);

            return [$value !== '', $value];
        }

        if (! is_array($value)) {
            return [true, $value];
        }

        $normalized = [];
        $isList = array_is_list($value);

        foreach ($value as $key => $nestedValue) {
            [$available, $mappedValue] = $this->normalizeContextValue($nestedValue);

            if (! $available) {
                continue;
            }

            if ($isList) {
                $normalized[] = $mappedValue;

                continue;
            }

            $normalizedKey = trim((string) $key);

            if ($normalizedKey !== '' && ! array_key_exists($normalizedKey, $normalized)) {
                $normalized[$normalizedKey] = $mappedValue;
            }
        }

        return [$normalized !== [], $normalized];
    }
}
