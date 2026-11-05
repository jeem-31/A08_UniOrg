<?php

namespace App\Services\DocumentIntelligence\Exceptions;

use App\Services\DocumentIntelligence\Outcomes\TransientProcessingFailureReason;
use RuntimeException;
use Throwable;

final class TransientProcessingException extends RuntimeException
{
    public function __construct(
        public readonly TransientProcessingFailureReason $reason,
        string $message = '',
        ?Throwable $previous = null,
    ) {
        parent::__construct($message !== '' ? $message : $reason->value, 0, $previous);
    }
}
