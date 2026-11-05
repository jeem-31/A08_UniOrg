<?php

namespace App\Services\DocumentIntelligence\Exceptions;

use App\Services\DocumentIntelligence\Outcomes\PermanentDocumentFailureReason;
use RuntimeException;
use Throwable;

final class PermanentDocumentException extends RuntimeException
{
    public function __construct(
        public readonly PermanentDocumentFailureReason $reason,
        string $message = '',
        ?Throwable $previous = null,
    ) {
        parent::__construct($message !== '' ? $message : $reason->value, 0, $previous);
    }
}
