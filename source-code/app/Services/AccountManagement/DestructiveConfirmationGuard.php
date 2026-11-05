<?php

namespace App\Services\AccountManagement;

/**
 * The only confirmation boundary mutation services should use. The callback
 * is unreachable for omitted, canceled, expired, mismatched, or reused tokens.
 */
final readonly class DestructiveConfirmationGuard
{
    public function __construct(private DestructiveConfirmationVerifier $verifier) {}

    /** @param callable(): void $mutation */
    public function run(
        ?string $token,
        DestructiveConfirmation $expected,
        callable $mutation,
        bool $canceled = false,
    ): DestructiveConfirmationVerificationResult {
        $result = $this->verifier->verify($token, $expected, $canceled);

        if (! $result->accepted()) {
            return $result;
        }

        $mutation();

        return $result;
    }
}
