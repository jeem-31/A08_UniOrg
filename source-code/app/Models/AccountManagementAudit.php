<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

/**
 * Append-only, credential-free record of a completed OSAS account-management action.
 *
 * Completed records are intentionally created through createCompleted() rather than
 * mass assignment. Database triggers provide the final update/delete protection.
 */
class AccountManagementAudit extends Model
{
    public const ACTION_ACCOUNT_CREATED = 'account.created';

    public const ACTION_PROFILE_UPDATED = 'profile.updated';

    public const ACTION_ROLE_CHANGED = 'role.changed';

    public const ACTION_ACCOUNT_DEACTIVATED = 'account.deactivated';

    public const ACTION_ACCOUNT_REACTIVATED = 'account.reactivated';

    public const ACTION_ACCOUNT_EXPIRED = 'account.expired';

    public const ACTION_ACCOUNT_DELETED = 'account.deleted';

    public const ACTION_DEAN_ASSIGNED = 'dean.assigned';

    public const ACTION_DEAN_REPLACED = 'dean.replaced';

    public const ACTION_ADVISER_APPOINTED = 'adviser.appointed';

    public const ACTION_OFFICER_APPOINTED = 'officer.appointed';

    /** @var list<string> */
    private const ACTIONS = [
        self::ACTION_ACCOUNT_CREATED,
        self::ACTION_PROFILE_UPDATED,
        self::ACTION_ROLE_CHANGED,
        self::ACTION_ACCOUNT_DEACTIVATED,
        self::ACTION_ACCOUNT_REACTIVATED,
        self::ACTION_ACCOUNT_EXPIRED,
        self::ACTION_ACCOUNT_DELETED,
        self::ACTION_DEAN_ASSIGNED,
        self::ACTION_DEAN_REPLACED,
        self::ACTION_ADVISER_APPOINTED,
        self::ACTION_OFFICER_APPOINTED,
    ];

    /** @var list<string> */
    private const CREDENTIAL_KEY_FRAGMENTS = ['password', 'credential', 'token', 'secret', 'hash', 'reset'];

    public $timestamps = false;

    /** @var array<int, string> */
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'occurred_at' => 'immutable_datetime',
        ];
    }

    /**
     * Persist one completed action from typed related records and a credential-free change payload.
     *
     * @param  array<array-key, mixed>  $changes
     */
    public static function createCompleted(
        string $action,
        ?User $actor,
        string $actorDisplay,
        ?User $affectedUser,
        string $affectedDisplay,
        ?Organization $organization,
        ?string $organizationDisplay,
        array $changes,
        ?DateTimeInterface $occurredAt = null,
    ): self {
        if (! in_array($action, self::ACTIONS, true)) {
            throw new InvalidArgumentException('Invalid account-management audit action.');
        }

        self::assertDisplay($actorDisplay, 'actor display');
        self::assertDisplay($affectedDisplay, 'affected display');
        if ($organizationDisplay !== null) {
            self::assertDisplay($organizationDisplay, 'organization display');
        }
        self::assertCredentialFreeChanges($changes);

        $audit = new self;
        $audit->forceFill([
            'action' => $action,
            'actor_user_id' => $actor?->getKey(),
            'actor_display' => $actorDisplay,
            'affected_user_id' => $affectedUser?->getKey(),
            'affected_display' => $affectedDisplay,
            'organization_id' => $organization?->getKey(),
            'organization_display' => $organizationDisplay,
            'changes' => $changes,
            'occurred_at' => $occurredAt ?? now(),
        ]);
        $audit->saveOrFail();

        return $audit;
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function affectedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'affected_user_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    private static function assertDisplay(string $display, string $field): void
    {
        if (trim($display) === '' || mb_strlen($display) > 255) {
            throw new InvalidArgumentException("Invalid {$field}.");
        }
    }

    /** @param array<array-key, mixed> $changes */
    private static function assertCredentialFreeChanges(array $changes): void
    {
        foreach ($changes as $key => $value) {
            if (is_string($key) && self::isCredentialKey($key)) {
                throw new InvalidArgumentException('Credential data cannot be recorded in an audit entry.');
            }

            if (is_array($value)) {
                self::assertCredentialFreeChanges($value);
            }
        }
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
}
