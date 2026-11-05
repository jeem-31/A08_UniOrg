<?php

namespace App\Services\AccountManagement;

use App\Models\OrganizationUser;
use InvalidArgumentException;

/**
 * One current Organization_Assignment of the requested target, projected without
 * pivot internals or any other account's membership data.
 */
final readonly class ManagedAccountAssignmentSummary
{
    private function __construct(
        public int $assignmentId,
        public int $userId,
        public ?int $organizationId,
        public ?string $organizationName,
        public string $role,
        public string $membershipStatus,
    ) {}

    /** @throws InvalidArgumentException when the pivot row does not belong to the target. */
    public static function fromAssignment(OrganizationUser $assignment, int $targetUserId): self
    {
        $userId = (int) $assignment->getAttribute('user_id');

        if ($userId !== $targetUserId) {
            throw new InvalidArgumentException('An assignment summary must belong to the requested account.');
        }

        $organizationId = $assignment->getAttribute('organization_id');
        $organization = $assignment->relationLoaded('organization') ? $assignment->organization : null;

        if ($organization !== null && (int) $organization->getKey() !== (int) $organizationId) {
            throw new InvalidArgumentException('An assignment summary must describe its own organization.');
        }

        $name = $organization === null ? null : trim((string) $organization->name);

        return new self(
            (int) $assignment->getKey(),
            $userId,
            $organizationId === null ? null : (int) $organizationId,
            $name === '' ? null : $name,
            (string) $assignment->getAttribute('role'),
            (string) $assignment->getAttribute('membership_status'),
        );
    }

    /**
     * Human-readable position label for display.
     * Strips the " 2" / " 2nd" slot suffix so users always see
     * "Business Manager", "Public Information Officer", "Event Coordinator"
     * regardless of which slot they occupy.
     */
    public function positionLabel(): string
    {
        return OrganizationUser::formatDisplayLabel($this->role);
    }
}
