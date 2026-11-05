<?php

namespace App\Services\DocumentIntelligence\Exceptions;

use RuntimeException;

final class LifecycleConflictException extends RuntimeException
{
    public static function retryRequiresFailed(int $analysisId, string $status): self
    {
        return new self(
            "Analysis [{$analysisId}] cannot start a retry while its status is [{$status}]."
        );
    }

    public static function resummarizationRequiresCompleted(int $analysisId, string $status): self
    {
        return new self(
            "Analysis [{$analysisId}] cannot start re-summarization while its status is [{$status}]."
        );
    }

    public static function resummarizationAlreadyActive(int $analysisId): self
    {
        return new self("Analysis [{$analysisId}] already has an active re-summarization cycle.");
    }

    public static function resummarizationCycleAlreadyUsed(int $analysisId, string $cycleId): self
    {
        return new self(
            "Analysis [{$analysisId}] cannot reuse re-summarization cycle [{$cycleId}]."
        );
    }
}
