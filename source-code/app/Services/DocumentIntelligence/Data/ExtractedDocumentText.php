<?php

namespace App\Services\DocumentIntelligence\Data;

final readonly class ExtractedDocumentText
{
    public function __construct(
        public string $text,
        public ?string $mimeType = null,
        public ?string $extractionMethod = null,
    ) {}
}
