<?php

namespace App\Services\DocumentIntelligence;

/**
 * Scalar identifiers that may be dispatched by the caller only after its
 * surrounding application transaction commits successfully.
 */
final readonly class DocumentAnalysisDispatchCommand
{
    public function __construct(
        public int $analysisId,
        public string $sourceVersion,
        public string $cycleId,
    ) {}
}
