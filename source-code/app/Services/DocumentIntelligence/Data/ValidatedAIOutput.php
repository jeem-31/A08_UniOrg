<?php

namespace App\Services\DocumentIntelligence\Data;

final readonly class ValidatedAIOutput
{
    /**
     * @param  array<string, mixed>  $context
     * @param  list<string>  $tags
     */
    public function __construct(
        public ExtractedDocumentText $extractedText,
        public string $documentType,
        public string $documentTypeName,
        public string $summary,
        public array $context,
        public array $tags,
    ) {}
}
