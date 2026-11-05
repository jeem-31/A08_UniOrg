<?php

namespace App\Services\DocumentIntelligence\Outcomes;

final readonly class StaleCycleOutcome
{
    public function __construct(
        public int $analysisId,
        public string $sourceVersion,
        public string $cycleId,
        public string $reason = 'source_or_cycle_mismatch',
    ) {}
}
