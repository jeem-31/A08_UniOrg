<?php

namespace App\ViewModels\DocumentLibrary;

final readonly class AdvisoryDetailsView
{
    /**
     * @param list<string> $tags
     * @param list<GeneratedContextFieldView> $context
     */
    public function __construct(
        public string $advisory,
        public ?string $documentType,
        public ?string $completionDate,
        public ?string $summary,
        public array $tags,
        public array $context,
    ) {}
}
