<?php

namespace Tests\Unit\AccountManagement;

use App\Services\AccountManagement\FreshnessAccepted;
use App\Services\AccountManagement\FreshnessRecord;
use App\Services\AccountManagement\FreshnessSlot;
use App\Services\AccountManagement\FreshnessSnapshot;
use App\Services\AccountManagement\FreshnessVerifier;
use App\Services\AccountManagement\StaleSubmission;
use Carbon\Carbon;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/** **Validates: Requirements 8.4, 8.5, 8.6** */
final class FreshnessVerifierTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_accepts_the_same_locked_snapshot(): void
    {
        $snapshot = $this->snapshot();

        self::assertInstanceOf(FreshnessAccepted::class, $this->verifier()->verify(
            $this->verifier()->issue($snapshot),
            $snapshot,
        ));
    }

    public function test_it_rejects_post_display_changes_to_every_watched_record_category(): void
    {
        $verifier = $this->verifier();
        $token = $verifier->issue($this->snapshot());

        $changedSnapshots = [
            'user' => $this->snapshot(targetUserVersion: 8),
            'organization' => $this->snapshot(organizationVersion: 12),
            'slot pivot' => $this->snapshot(slotPivotVersion: 6),
            'conflicting pivot' => $this->snapshot(conflictPivotVersion: 3),
        ];

        foreach ($changedSnapshots as $changed) {
            $result = $verifier->verify($token, $changed);

            self::assertInstanceOf(StaleSubmission::class, $result);
            self::assertSame(StaleSubmission::MISMATCH, $result->reason);
            self::assertSame('Reload and review the latest changes', $result->reload->label);
            self::assertSame('This form is out of date. Reload and try again.', $result->reload->message);
        }
    }

    public function test_it_binds_the_operation_actor_and_target_to_the_immutable_command(): void
    {
        $verifier = $this->verifier();
        $token = $verifier->issue($this->snapshot());

        foreach ([
            $this->snapshot(operation: 'adviser.appoint'),
            $this->snapshot(actorId: 13),
            $this->snapshot(targetId: 23),
        ] as $differentCommand) {
            $result = $verifier->verify($token, $differentCommand);

            self::assertInstanceOf(StaleSubmission::class, $result);
            self::assertSame(StaleSubmission::MISMATCH, $result->reason);
        }
    }

    public function test_it_rejects_expired_and_tampered_snapshots_without_mutating_cache_state(): void
    {
        Carbon::setTestNow('2026-07-25 12:00:00');
        $verifier = $this->verifier(ttlSeconds: 60);
        $token = $verifier->issue($this->snapshot());
        Cache::forever('freshness-test-sentinel', ['audit_writes' => 0]);

        Carbon::setTestNow('2026-07-25 12:01:01');
        $expired = $verifier->verify($token, $this->snapshot());
        $currentToken = $verifier->issue($this->snapshot());
        $lastCharacter = substr($currentToken, -1);
        $tampered = $verifier->verify(
            substr($currentToken, 0, -1).($lastCharacter === 'A' ? 'B' : 'A'),
            $this->snapshot(),
        );

        self::assertInstanceOf(StaleSubmission::class, $expired);
        self::assertSame(StaleSubmission::EXPIRED, $expired->reason);
        self::assertInstanceOf(StaleSubmission::class, $tampered);
        self::assertSame(StaleSubmission::INVALID, $tampered->reason);
        self::assertSame(['audit_writes' => 0], Cache::get('freshness-test-sentinel'));
    }

    public function test_snapshot_payload_order_is_stable_for_locked_conflict_rows(): void
    {
        $verifier = $this->verifier();
        $snapshot = $this->snapshot(conflicts: [
            FreshnessRecord::pivot(19, 1, 44, 88),
            FreshnessRecord::pivot(18, 2, 44, 77),
        ]);
        $sameRecordsInLockOrder = $this->snapshot(conflicts: [
            FreshnessRecord::pivot(18, 2, 44, 77),
            FreshnessRecord::pivot(19, 1, 44, 88),
        ]);

        self::assertInstanceOf(FreshnessAccepted::class, $verifier->verify(
            $verifier->issue($snapshot),
            $sameRecordsInLockOrder,
        ));
    }

    private function verifier(int $ttlSeconds = 300): FreshnessVerifier
    {
        return new FreshnessVerifier($this->app->make(Encrypter::class), $ttlSeconds);
    }

    /** @param list<FreshnessRecord>|null $conflicts */
    private function snapshot(
        string $operation = 'officer.appoint',
        int $actorId = 12,
        int $targetId = 22,
        int $targetUserVersion = 7,
        int $organizationVersion = 11,
        int $slotPivotVersion = 5,
        int $conflictPivotVersion = 2,
        ?array $conflicts = null,
    ): FreshnessSnapshot {
        return new FreshnessSnapshot(
            operation: $operation,
            actorId: $actorId,
            targetId: $targetId,
            users: [
                FreshnessRecord::user($actorId, 3),
                FreshnessRecord::user($targetId, $targetUserVersion),
            ],
            organization: FreshnessRecord::organization(44, $organizationVersion),
            slot: FreshnessSlot::occupied(
                44,
                'President',
                FreshnessRecord::pivot(88, $slotPivotVersion, 44, 99),
            ),
            conflictingPivots: $conflicts ?? [
                FreshnessRecord::pivot(89, $conflictPivotVersion, 44, $targetId),
            ],
        );
    }
}
