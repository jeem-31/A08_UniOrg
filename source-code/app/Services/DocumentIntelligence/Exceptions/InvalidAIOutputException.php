<?php

namespace App\Services\DocumentIntelligence\Exceptions;

use App\Services\DocumentIntelligence\Outcomes\InvalidAIOutputReason;
use RuntimeException;
use Throwable;

final class InvalidAIOutputException extends RuntimeException
{
    public function __construct(
        public readonly InvalidAIOutputReason $reason,
        string $message = '',
        ?Throwable $previous = null,
    ) {
        parent::__construct($message !== '' ? $message : $reason->value, 0, $previous);
    }
}
