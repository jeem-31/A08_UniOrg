<?php

namespace App\Services\DocumentIntelligence\Data;

final readonly class SanitizedFailure
{
    public function __construct(
        public string $code,
        public string $reviewerMessage,
    ) {}
}
