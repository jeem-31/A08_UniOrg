<?php

namespace App\Services\AccountManagement;

use InvalidArgumentException;

/** Base type for an identifier that has already been scoped by a command. */
abstract readonly class PositiveIdentifier
{
    public function __construct(public int $value)
    {
        if ($value < 1) {
            throw new InvalidArgumentException('Identifiers must be positive integers.');
        }
    }
}
