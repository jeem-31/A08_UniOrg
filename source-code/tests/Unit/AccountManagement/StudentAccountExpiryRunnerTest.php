<?php

namespace Tests\Unit\AccountManagement;

use App\Models\AccountManagementAudit;
use App\Models\User;
use App\Services\AccountManagement\AccountManagementAuditWriter;
use App\Services\AccountManagement\StudentAccountExpiryRunner;
use App\Services\AccountManagement\StudentAccountValidity;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** **Validates: Requirements 2.1, 2.2, 2.3, 3.9, 4.9** */
final class StudentAccountExpiryRunnerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-01 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_deactivates_students_whose_grace_window_has_fully_closed(): void
    {
        $fullyExpired = User::factory()->create([
            'role' => 'student',
            'is_active' => true,
            'expires_at' => now()->subMonths(StudentAccountValidity::GRACE_MONTHS + 1),
        ]);

        $result = $this->runner()->run();

        self::assertSame(['deactivated' => 1], $result);
        self::assertFalse((bool) $fullyExpired->fresh()->is_active);

        $audit = AccountManagementAudit::query()->sole();
        self::assertSame(AccountManagementAudit::ACTION_ACCOUNT_EXPIRED, $audit->action);
        self::assertNull($audit->actor_user_id);
        self::assertSame('System (auto-expire)', $audit->actor_display);
        self::assertSame($fullyExpired->id, $audit->affected_user_id);
        self::assertSame(['state' => ['before' => 'Active', 'after' => 'Inactive']], $audit->changes);
    }

    public function test_it_leaves_students_inside_the_grace_window_active(): void
    {
        $inGrace = User::factory()->create([
            'role' => 'student',
            'is_active' => true,
            'expires_at' => now()->subMonths(StudentAccountValidity::GRACE_MONTHS)->addDay(),
        ]);

        $result = $this->runner()->run();

        self::assertSame(['deactivated' => 0], $result);
        self::assertTrue((bool) $inGrace->fresh()->is_active);
        self::assertSame(0, AccountManagementAudit::query()->count());
    }

    public function test_it_leaves_non_students_and_accounts_without_an_expiry_untouched(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
            'expires_at' => now()->subMonths(StudentAccountValidity::GRACE_MONTHS + 1),
        ]);
        $noExpiry = User::factory()->create([
            'role' => 'student',
            'is_active' => true,
            'expires_at' => null,
        ]);

        $result = $this->runner()->run();

        self::assertSame(['deactivated' => 0], $result);
        self::assertTrue((bool) $admin->fresh()->is_active);
        self::assertTrue((bool) $noExpiry->fresh()->is_active);
        self::assertSame(0, AccountManagementAudit::query()->count());
    }

    public function test_it_never_reactivates_an_already_inactive_student(): void
    {
        $inactive = User::factory()->create([
            'role' => 'student',
            'is_active' => false,
            'expires_at' => now()->subMonths(StudentAccountValidity::GRACE_MONTHS + 1),
        ]);

        $result = $this->runner()->run();

        self::assertSame(['deactivated' => 0], $result);
        self::assertFalse((bool) $inactive->fresh()->is_active);
        self::assertSame(0, AccountManagementAudit::query()->count());
    }

    public function test_a_second_run_is_idempotent_and_records_no_duplicate_audit_entries(): void
    {
        User::factory()->create([
            'role' => 'student',
            'is_active' => true,
            'expires_at' => now()->subMonths(StudentAccountValidity::GRACE_MONTHS + 1),
        ]);

        self::assertSame(['deactivated' => 1], $this->runner()->run());
        self::assertSame(['deactivated' => 0], $this->runner()->run());
        self::assertSame(1, AccountManagementAudit::query()->count());
    }

    private function runner(): StudentAccountExpiryRunner
    {
        return new StudentAccountExpiryRunner(new AccountManagementAuditWriter);
    }
}
