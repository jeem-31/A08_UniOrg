<?php

namespace App\Services\AccountManagement;

use App\Models\AccountManagementAudit;
use Carbon\CarbonImmutable;

/**
 * An immutable, credential-free projection of one persisted audit record.
 *
 * The view model exposes no Eloquent model, so the audit interface has no
 * mutation capability, and it defensively drops any credential-derived key or
 * hashed value that ever reached storage.
 */
final readonly class AuditEntryView
{
    /** @var list<string> */
    private const CREDENTIAL_KEY_FRAGMENTS = [
        'password', 'passwd', 'pwd', 'credential', 'secret', 'token',
        'hash', 'reset', 'salt', 'bcrypt', 'argon', 'remember',
    ];

    /** @var list<string> */
    private const HASH_VALUE_PREFIXES = ['$2y$', '$2a$', '$2b$', '$argon2i$', '$argon2id$'];

    /** @param array<string, mixed> $changes */
    private function __construct(
        public int $id,
        public AccountManagementAction $action,
        public string $actionLabel,
        public CarbonImmutable $occurredAt,
        public ?int $actorId,
        public string $actorDisplay,
        public ?int $affectedAccountId,
        public string $affectedDisplay,
        public ?int $organizationId,
        public ?string $organizationDisplay,
        public array $changes,
    ) {}

    public static function fromRecord(AccountManagementAudit $audit): self
    {
        $action = AccountManagementAction::from((string) $audit->action);
        $occurredAt = $audit->occurred_at instanceof CarbonImmutable
            ? $audit->occurred_at
            : CarbonImmutable::parse((string) $audit->occurred_at);

        return new self(
            (int) $audit->getKey(),
            $action,
            $action->label(),
            $occurredAt,
            $audit->actor_user_id === null ? null : (int) $audit->actor_user_id,
            (string) $audit->actor_display,
            $audit->affected_user_id === null ? null : (int) $audit->affected_user_id,
            (string) $audit->affected_display,
            $audit->organization_id === null ? null : (int) $audit->organization_id,
            $audit->organization_display === null ? null : (string) $audit->organization_display,
            self::credentialFree(is_array($audit->changes) ? $audit->changes : []),
        );
    }

    public function has(string $changeKey): bool
    {
        return array_key_exists($changeKey, $this->changes);
    }

    public function change(string $changeKey): mixed
    {
        return $this->changes[$changeKey] ?? null;
    }

    /** @return array<string, array{before: mixed, after: mixed}> */
    public function profileChanges(): array
    {
        $profile = $this->changes['profile'] ?? [];

        return is_array($profile) ? $profile : [];
    }

    /**
     * @param  array<array-key, mixed>  $payload
     * @return array<string, mixed>
     */
    private static function credentialFree(array $payload): array
    {
        $safe = [];

        foreach ($payload as $key => $value) {
            if (is_string($key) && self::isCredentialKey($key)) {
                continue;
            }

            if (is_array($value)) {
                $safe[$key] = self::credentialFree($value);

                continue;
            }

            if (is_string($value) && self::isHashedValue($value)) {
                continue;
            }

            $safe[$key] = $value;
        }

        return $safe;
    }

    private static function isCredentialKey(string $key): bool
    {
        $normalized = strtolower($key);

        foreach (self::CREDENTIAL_KEY_FRAGMENTS as $fragment) {
            if (str_contains($normalized, $fragment)) {
                return true;
            }
        }

        return false;
    }

    private static function isHashedValue(string $value): bool
    {
        foreach (self::HASH_VALUE_PREFIXES as $prefix) {
            if (str_starts_with($value, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
