<?php

namespace Tests\Unit;

use App\Models\User;
use App\Policies\OsasAccountPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

final class OsasAccountPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_an_active_osas_administrator_may_manage_accounts(): void
    {
        $policy = app(OsasAccountPolicy::class);

        foreach ([
            ['role' => 'osas_admin', 'is_active' => true, 'expected' => true],
            ['role' => 'osas_admin', 'is_active' => false, 'expected' => false],
            ['role' => 'super_admin', 'is_active' => true, 'expected' => false],
            ['role' => 'admin', 'is_active' => true, 'expected' => false],
            ['role' => 'student', 'is_active' => true, 'expected' => false],
        ] as $case) {
            $actor = User::factory()->create([
                'role' => $case['role'],
                'is_active' => $case['is_active'],
            ]);

            self::assertSame($case['expected'], $policy->manage($actor));
        }
    }

    public function test_policy_reloads_the_actor_before_authorizing_direct_invocations(): void
    {
        $actor = User::factory()->create(['role' => 'osas_admin', 'is_active' => true]);
        $policy = app(OsasAccountPolicy::class);

        User::query()->whereKey($actor->id)->update(['is_active' => false]);
        self::assertFalse($policy->manage($actor));

        User::query()->whereKey($actor->id)->update(['is_active' => true, 'role' => 'admin']);
        self::assertFalse($policy->manage($actor));

        User::query()->whereKey($actor->id)->update(['role' => 'osas_admin']);
        self::assertTrue($policy->manage($actor));

        User::query()->whereKey($actor->id)->delete();
        self::assertFalse($policy->manage($actor));
    }

    public function test_registered_gate_uses_the_fresh_policy_check(): void
    {
        $actor = User::factory()->create(['role' => 'osas_admin', 'is_active' => true]);

        self::assertTrue(Gate::forUser($actor)->allows(OsasAccountPolicy::ABILITY));

        User::query()->whereKey($actor->id)->update(['is_active' => false]);

        self::assertFalse(Gate::forUser($actor)->allows(OsasAccountPolicy::ABILITY));
    }
}
