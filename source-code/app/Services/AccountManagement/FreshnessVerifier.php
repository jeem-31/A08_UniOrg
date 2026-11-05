<?php

namespace App\Services\AccountManagement;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Encryption\Encrypter;
use InvalidArgumentException;
use JsonException;

/**
 * Issues opaque, time-limited form snapshots and compares them with records
 * reloaded under the caller's transaction locks. This class never writes state.
 */
final readonly class FreshnessVerifier
{
    private const FORMAT_VERSION = 1;

    private const MAX_TOKEN_LENGTH = 65_536;

    public function __construct(
        private Encrypter $encrypter,
        private int $ttlSeconds = 300,
    ) {
        if ($ttlSeconds < 1 || $ttlSeconds > 900) {
            throw new InvalidArgumentException('Freshness snapshot TTL must be between one second and fifteen minutes.');
        }
    }

    public function issue(FreshnessSnapshot $snapshot): string
    {
        $issuedAt = now()->getTimestamp();

        return $this->encrypter->encryptString(json_encode([
            'format' => self::FORMAT_VERSION,
            'issued_at' => $issuedAt,
            'expires_at' => $issuedAt + $this->ttlSeconds,
            'snapshot' => $snapshot->toPayload(),
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * The current snapshot must be built only after all relevant rows are locked.
     */
    public function verify(?string $token, FreshnessSnapshot $current): FreshnessVerificationResult
    {
        $issued = $this->open($token);

        if ($issued instanceof StaleSubmission) {
            return $issued;
        }

        return $this->compare($issued, $current);
    }

    /**
     * Recovers the signed snapshot a submitted form was displayed with, so a
     * typed command can carry immutable issued state instead of a raw token.
     */
    public function open(?string $token): FreshnessSnapshot|StaleSubmission
    {
        return $this->decode($token);
    }

    /**
     * Compares an issued snapshot with the counterpart rebuilt after locking.
     */
    public function compare(FreshnessSnapshot $issued, FreshnessSnapshot $current): FreshnessVerificationResult
    {
        return hash_equals(
            $this->canonicalJson($issued->toPayload()),
            $this->canonicalJson($current->toPayload()),
        ) ? new FreshnessAccepted : new StaleSubmission(StaleSubmission::MISMATCH);
    }

    private function decode(?string $token): FreshnessSnapshot|StaleSubmission
    {
        if ($token === null || $token === '' || strlen($token) > self::MAX_TOKEN_LENGTH) {
            return new StaleSubmission(StaleSubmission::INVALID);
        }

        try {
            $payload = json_decode($this->encrypter->decryptString($token), true, 32, JSON_THROW_ON_ERROR);
        } catch (DecryptException|JsonException) {
            return new StaleSubmission(StaleSubmission::INVALID);
        }

        if (! is_array($payload)
            || ($payload['format'] ?? null) !== self::FORMAT_VERSION
            || ! is_int($payload['issued_at'] ?? null)
            || ! is_int($payload['expires_at'] ?? null)
            || ! is_array($payload['snapshot'] ?? null)) {
            return new StaleSubmission(StaleSubmission::INVALID);
        }

        if ($payload['expires_at'] < now()->getTimestamp()) {
            return new StaleSubmission(StaleSubmission::EXPIRED);
        }

        if ($payload['expires_at'] <= $payload['issued_at']
            || $this->ttlSeconds < $payload['expires_at'] - $payload['issued_at']) {
            return new StaleSubmission(StaleSubmission::INVALID);
        }

        try {
            return FreshnessSnapshot::fromPayload($payload['snapshot']);
        } catch (InvalidArgumentException) {
            return new StaleSubmission(StaleSubmission::INVALID);
        }
    }

    /** @param array<string, mixed> $payload */
    private function canonicalJson(array $payload): string
    {
        return json_encode($payload, JSON_THROW_ON_ERROR);
    }
}
