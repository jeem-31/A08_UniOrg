<?php

namespace App\Services\AccountManagement;

use App\Models\AccountManagementAudit;
use App\Models\Organization;
use App\Models\User;
use RuntimeException;

/**
 * Appends exactly one durable, credential-free record for a completed action.
 *
 * The writer never begins its own transaction: it must run inside the caller's
 * mutation transaction so state and audit commit or roll back together, and a
 * rejected command records nothing.
 */
final readonly class AccountManagementAuditWriter
{
    public function record(CompletedAction $action): AccountManagementAudit
    {
        $connection = (new AccountManagementAudit)->getConnection();

        if ($connection->transactionLevel() < 1) {
            throw new RuntimeException('A completed action may only be appended inside the caller transaction.');
        }

        return AccountManagementAudit::createCompleted(
            $action->action->value,
            $this->user($action->actor->id),
            $action->actor->display,
            $this->user($action->affected->id),
            $action->affected->display,
            $this->organization($action->organization?->id),
            $action->organization?->display,
            $action->changes->toPayload(),
            $action->occurredAt,
        );
    }

    private function user(?int $id): ?User
    {
        return $id === null ? null : User::query()->find($id);
    }

    private function organization(?int $id): ?Organization
    {
        return $id === null ? null : Organization::query()->find($id);
    }
}
