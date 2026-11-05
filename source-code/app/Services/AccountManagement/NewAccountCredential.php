<?php

namespace App\Services\AccountManagement;

use InvalidArgumentException;

/** Write-only credential input; it is never serializable or usable as a result value. */
final readonly class NewAccountCredential
{
    private function __construct(private string $plainText) {}

    public static function fromValidated(string $plainText): self
    {
        if ($plainText === '' || strlen($plainText) > 4096) {
            throw new InvalidArgumentException('Invalid account credential input.');
        }

        return new self($plainText);
    }

    public function plainText(): string
    {
        return $this->plainText;
    }
}
