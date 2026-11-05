<?php

namespace App\Services\AccountManagement;

use App\Models\Organization;
use App\Models\User;
use InvalidArgumentException;

/**
 * Immutable, credential-free identity snapshot recorded with a completed action.
 *
 * The display value is captured at completion time so an audit entry stays
 * understandable after the referenced record is removed.
 */
final readonly class AuditIdentity
{
    private const MAX_DISPLAY_LENGTH = 255;

    private function __construct(public ?int $id, public string $display) {}

    public static function of(?int $id, string $display): self
    {
        if ($id !== null && $id < 1) {
            throw new InvalidArgumentException('An audit identity must reference a positive record identifier.');
        }

        $normalized = trim($display);

        if ($normalized === '' || mb_strlen($normalized) > self::MAX_DISPLAY_LENGTH) {
            throw new InvalidArgumentException('An audit identity display must be present and at most 255 characters.');
        }

        return new self($id, $normalized);
    }

    public static function fromUser(User $user): self
    {
        $key = $user->getKey();
        $display = trim((string) $user->full_name);

        if ($display === '') {
            $display = trim((string) $user->email);
        }

        return self::of(
            $key === null ? null : (int) $key,
            $display === '' ? 'Removed account' : $display,
        );
    }

    public static function fromOrganization(Organization $organization): self
    {
        $key = $organization->getKey();
        $display = trim((string) $organization->name);

        return self::of(
            $key === null ? null : (int) $key,
            $display === '' ? 'Removed organization' : $display,
        );
    }

    /** @return array{id: ?int, display: string} */
    public function toPayload(): array
    {
        return ['id' => $this->id, 'display' => $this->display];
    }
}
