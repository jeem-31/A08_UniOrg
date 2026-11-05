<?php

namespace App\Services\AccountManagement;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Encryption\Encrypter;
use InvalidArgumentException;
use JsonException;

/**
 * Issues opaque server-backed confirmations and consumes them before a caller
 * can enter mutation logic. Tokens are bound to every immutable action fact.
 */
final readonly class DestructiveConfirmationVerifier
{
    private const FORMAT_VERSION = 1;

    private const MAX_TOKEN_LENGTH = 65_536;

    private const CACHE_PREFIX = 'account-management:destructive-confirmation:';

    public function __construct(
        private Encrypter $encrypter,
        private Repository $cache,
        private int $ttlSeconds = 300,
    ) {
        if ($ttlSeconds < 1 || $ttlSeconds > 900) {
            throw new InvalidArgumentException('Destructive confirmation TTL must be between one second and fifteen minutes.');
        }
    }

    public function issue(DestructiveConfirmation $confirmation): string
    {
        $issuedAt = now()->getTimestamp();
        $nonce = bin2hex(random_bytes(32));
        $payload = [
            'format' => self::FORMAT_VERSION,
            'issued_at' => $issuedAt,
            'expires_at' => $issuedAt + $this->ttlSeconds,
            'nonce' => $nonce,
            'confirmation' => $confirmation->toPayload(),
        ];

        $this->cache->put($this->cacheKey($nonce), $this->digest($payload), $this->ttlSeconds);

        return $this->encrypter->encryptString(json_encode($payload, JSON_THROW_ON_ERROR));
    }

    public function verify(
        ?string $token,
        DestructiveConfirmation $expected,
        bool $canceled = false,
    ): DestructiveConfirmationVerificationResult {
        if ($canceled) {
            $this->revoke($token);

            return new CanceledDestructiveConfirmation;
        }

        if ($token === null || $token === '') {
            return new MissingDestructiveConfirmation;
        }

        $payload = $this->decode($token);
        if ($payload === null) {
            return new MismatchedDestructiveConfirmation;
        }

        if ($payload['expires_at'] <= now()->getTimestamp()) {
            $this->cache->forget($this->cacheKey($payload['nonce']));

            return new ExpiredDestructiveConfirmation;
        }

        $receipt = $this->cache->pull($this->cacheKey($payload['nonce']));
        if (! is_string($receipt)) {
            return new ConsumedDestructiveConfirmation;
        }

        if (! hash_equals($receipt, $this->digest($payload))
            || ! hash_equals(
                $this->canonicalJson($payload['confirmation']->toPayload()),
                $this->canonicalJson($expected->toPayload()),
            )) {
            return new MismatchedDestructiveConfirmation;
        }

        return new DestructiveConfirmationAccepted;
    }

    private function revoke(?string $token): void
    {
        $payload = $token === null || $token === '' ? null : $this->decode($token);
        if ($payload !== null) {
            $this->cache->forget($this->cacheKey($payload['nonce']));
        }
    }

    /** @return array{format: int, issued_at: int, expires_at: int, nonce: string, confirmation: DestructiveConfirmation}|null */
    private function decode(string $token): ?array
    {
        if (strlen($token) > self::MAX_TOKEN_LENGTH) {
            return null;
        }

        try {
            $payload = json_decode($this->encrypter->decryptString($token), true, 32, JSON_THROW_ON_ERROR);
        } catch (DecryptException|JsonException) {
            return null;
        }

        if (! is_array($payload) || array_keys($payload) !== [
            'format', 'issued_at', 'expires_at', 'nonce', 'confirmation',
        ] || ($payload['format'] ?? null) !== self::FORMAT_VERSION
            || ! is_int($payload['issued_at'] ?? null) || ! is_int($payload['expires_at'] ?? null)
            || ! is_string($payload['nonce'] ?? null) || ! ctype_xdigit($payload['nonce'])
            || strlen($payload['nonce']) !== 64 || ! is_array($payload['confirmation'] ?? null)
            || $payload['expires_at'] <= $payload['issued_at']
            || $this->ttlSeconds < $payload['expires_at'] - $payload['issued_at']) {
            return null;
        }

        try {
            $payload['confirmation'] = DestructiveConfirmation::fromPayload($payload['confirmation']);
        } catch (InvalidArgumentException) {
            return null;
        }

        return $payload;
    }

    /** @param array{format: int, issued_at: int, expires_at: int, nonce: string, confirmation: DestructiveConfirmation|array<string, mixed>} $payload */
    private function digest(array $payload): string
    {
        $confirmation = $payload['confirmation'] instanceof DestructiveConfirmation
            ? $payload['confirmation']->toPayload()
            : $payload['confirmation'];

        return hash('sha256', $this->canonicalJson([
            'format' => $payload['format'],
            'issued_at' => $payload['issued_at'],
            'expires_at' => $payload['expires_at'],
            'nonce' => $payload['nonce'],
            'confirmation' => $confirmation,
        ]));
    }

    private function cacheKey(string $nonce): string
    {
        return self::CACHE_PREFIX.hash('sha256', $nonce);
    }

    /** @param array<string, mixed> $payload */
    private function canonicalJson(array $payload): string
    {
        return json_encode($payload, JSON_THROW_ON_ERROR);
    }
}
