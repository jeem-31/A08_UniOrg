<?php

namespace App\Services\AccountManagement;

use InvalidArgumentException;

/** An opaque, already-validated-request token for a destructive command. */
final readonly class DestructiveConfirmationToken
{
    private function __construct(private string $value) {}

    public static function fromRequest(string $value): self
    {
        if (trim($value) === '' || strlen($value) > 8192) {
            throw new InvalidArgumentException('Invalid destructive confirmation token.');
        }

        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }
}
