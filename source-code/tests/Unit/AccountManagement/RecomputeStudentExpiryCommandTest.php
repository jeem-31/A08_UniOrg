<?php

namespace Tests\Unit\AccountManagement;

use App\Models\Organization;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** **Validates: Requirement 4.9 - the backfill command recomputes users.expires_at for students** */
final class RecomputeStudentExpiryCommandTest extends TestCase
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

    private function approveOrganization(): void
    {
        Organization::factory()->create([
            'status' => 'Approved',
            'accreditation_started_at' => '2026-08-01 00:00:00',
            'accreditation_expires_at' => '2027-03-31 23:59:59',
        ]);
    }

    public function test_it_backfills_expires_at_for_students_from_the_period_start(): void
    {
        $this->approveOrganization();

        $firstYear = User::factory()->create([
            'role' => 'student',
            'year_level' => '1st Year',
            'expires_at' => null,
        ]);

        $this->artisan('accounts:recompute-expiry')
            ->expectsOutputToContain('1 student account(s)')
            ->assertExitCode(0);

        self::assertSame('2030-08-01 00:00:00', $firstYear->fresh()->expires_at->toDateTimeString());
    }

    public function test_it_updates_a_stale_expires_at_and_skips_unmatched_rows(): void
    {
        $this->approveOrganization();

        $stale = User::factory()->create([
            'role' => 'student',
            'year_level' => '4th Year',
            'expires_at' => '2026-01-01 00:00:00',
        ]);
        $alreadyCorrect = User::factory()->create([
            'role' => 'student',
            'year_level' => '2nd Year',
            'expires_at' => '2029-08-01 00:00:00',
        ]);
        $unknownYear = User::factory()->create([
            'role' => 'student',
            'year_level' => 'Alumni',
            'expires_at' => null,
        ]);
        $admin = User::factory()->create([
            'role' => 'admin',
            'year_level' => null,
            'expires_at' => null,
        ]);

        $this->artisan('accounts:recompute-expiry')
            ->expectsOutputToContain('2 student account(s)')
            ->assertExitCode(0);
        self::assertSame('2027-08-01 00:00:00', $stale->fresh()->expires_at->toDateTimeString());
        self::assertSame('2029-08-01 00:00:00', $alreadyCorrect->fresh()->expires_at->toDateTimeString());
        self::assertNull($unknownYear->fresh()->expires_at);
        self::assertNull($admin->fresh()->expires_at);
    }

    public function test_dry_run_reports_planned_values_without_saving(): void
    {
        $this->approveOrganization();

        $firstYear = User::factory()->create([
            'role' => 'student',
            'year_level' => '1st Year',
            'expires_at' => null,
        ]);

        $buffer = new \Symfony\Component\Console\Output\BufferedOutput;
        $style = new \Illuminate\Console\OutputStyle(
            new \Symfony\Component\Console\Input\ArrayInput([]),
            $buffer,
        );
        $this->app->instance(\Illuminate\Console\OutputStyle::class, $style);
        $this->app->make(\Illuminate\Contracts\Console\Kernel::class)
            ->call('accounts:recompute-expiry', ['--dry-run' => true], $style);

        $output = $buffer->fetch();
        self::assertStringContainsString("[dry-run] {$firstYear->id}", $output);
        self::assertStringContainsString('2030-08-01 00:00:00', $output);

        self::assertNull($firstYear->fresh()->expires_at);
    }

    public function test_it_uses_august_start_when_no_period_has_been_set(): void
    {
        $firstYear = User::factory()->create([
            'role' => 'student',
            'year_level' => '1st Year',
            'expires_at' => null,
        ]);

        $this->artisan('accounts:recompute-expiry')
            ->assertExitCode(0);

        self::assertNotNull($firstYear->fresh()->expires_at);
    }
}
