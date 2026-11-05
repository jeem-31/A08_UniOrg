<?php

namespace App\Services\DocumentIntelligence;

final readonly class SupportedDocumentDefinition
{
    public function __construct(
        public string $field,
        public string $label,
        public DocumentCardinality $cardinality,
    ) {}

    public function isMultiFile(): bool
    {
        return $this->cardinality === DocumentCardinality::MultiFile;
    }
}
