<?php

namespace Tests\Unit\AccountManagement;

use App\Models\AccountManagementAudit;
use App\Models\User;
use App\Services\AccountManagement\StudentAccountValidity;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** **Validates: Requirement 4.9 - the scheduled expiry job deactivates fully expired students** */
final class ExpireStudentAccountsCommandTest extends TestCase
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

    public function test_it_runs_the_expiry_runner_and_reports_deactivated_count(): void
    {
        $fullyExpired = User::factory()->create([
            'role' => 'student',
            'is_active' => true,
            'expires_at' => now()->subMonths(StudentAccountValidity::GRACE_MONTHS + 1),
        ]);
        $inGrace = User::factory()->create([
            'role' => 'student',
            'is_active' => true,
            'expires_at' => now()->subMonths(StudentAccountValidity::GRACE_MONTHS)->addDay(),
        ]);

        $this->artisan('accounts:expire-students')
            ->expectsOutputToContain('1 student account(s)')
            ->assertExitCode(0);

        self::assertFalse((bool) $fullyExpired->fresh()->is_active);
        self::assertTrue((bool) $inGrace->fresh()->is_active);
        self::assertSame(1, AccountManagementAudit::query()->count());
    }

    public function test_it_reports_zero_when_nothing_is_due(): void
    {
        User::factory()->create([
            'role' => 'student',
            'is_active' => true,
            'expires_at' => now()->addYear(),
        ]);

        $this->artisan('accounts:expire-students')
            ->expectsOutputToContain('0 student account(s)')
            ->assertExitCode(0);
    }
}
