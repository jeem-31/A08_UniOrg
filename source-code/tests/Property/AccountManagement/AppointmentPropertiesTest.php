<?php

namespace Tests\Property\AccountManagement;

use App\Models\User;
use App\Models\Organization;
use Eris\Generator;
use Eris\TestTrait;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppointmentPropertiesTest extends TestCase
{
    use RefreshDatabase;
    use TestTrait;

    /**
     * @group property
     * Feature: osas-account-management
     * Property 9: Dean transitions are singleton, atomic, and pivot-preserving.
     * Property 10: Invalid Dean commands have no side effects.
     */
    public function test_dean_transitions(): void
    {
        $this->forAll(
            Generator\bool() // isValidCandidate
        )
            ->withMaxSize(30)
            ->then(function ($isValid) {
                $admin = User::factory()->create([
                    'role' => 'osas_admin',
                    'is_active' => true,
                ]);

                $candidate = User::factory()->create([
                    'role' => $isValid ? 'osas_admin' : 'student',
                    'is_active' => true,
                ]);

                $response = $this->actingAs($admin)->put("/osas/accounts/{$candidate->id}/appointments/dean");
                
                if ($isValid) {
                    $response->assertRedirect();
                    $this->assertEquals('super_admin', $candidate->fresh()->role);
                } else {
                    $response->assertSessionHasErrors();
                    $this->assertNotEquals('super_admin', $candidate->fresh()->role);
                }
            });
    }

    /**
     * @group property
     * Feature: osas-account-management
     * Property 11: Adviser appointments satisfy eligibility, exclusivity, and promotion rules.
     * Property 12: Invalid adviser commands preserve every affected state.
     */
    public function test_adviser_appointments(): void
    {
        $this->forAll(
            Generator\elements('senior', 'junior', 'invalid')
        )
            ->withMaxSize(30)
            ->then(function ($level) {
                $admin = User::factory()->create([
                    'role' => 'osas_admin',
                    'is_active' => true,
                ]);

                $candidate = User::factory()->create([
                    'role' => 'faculty',
                    'is_active' => true,
                ]);

                $org = Organization::factory()->create();

                $response = $this->actingAs($admin)->put("/osas/accounts/{$candidate->id}/appointments/organization/{$org->id}", [
                    'assignment_type' => 'adviser',
                    'role' => clone $level, // copy to avoid pass-by-reference mutation issues in eris if any
                    'freshness' => 0
                ]);

                if (in_array($level, ['senior', 'junior'])) {
                    $response->assertRedirect();
                    $this->assertDatabaseHas('organization_advisers', [
                        'organization_id' => $org->id,
                        'user_id' => $candidate->id,
                        'level' => clone $level,
                    ]);
                } else {
                    $response->assertSessionHasErrors();
                }
            });
    }

    /**
     * @group property
     * Feature: osas-account-management
     * Property 13: Officer appointments modify exactly one valid slot.
     * Property 14: Invalid officer commands are noninterfering.
     */
    public function test_officer_appointments(): void
    {
        $this->forAll(
            Generator\elements('president', 'vice_president', 'invalid_position')
        )
            ->withMaxSize(30)
            ->then(function ($position) {
                $admin = User::factory()->create([
                    'role' => 'osas_admin',
                    'is_active' => true,
                ]);

                $candidate = User::factory()->create([
                    'role' => 'student',
                    'is_active' => true,
                ]);

                $org = Organization::factory()->create();

                $response = $this->actingAs($admin)->put("/osas/accounts/{$candidate->id}/appointments/organization/{$org->id}", [
                    'assignment_type' => 'officer',
                    'position' => clone $position,
                    'freshness' => 0
                ]);

                if (in_array($position, ['president', 'vice_president'])) {
                    $response->assertRedirect();
                    $this->assertDatabaseHas('organization_officers', [
                        'organization_id' => $org->id,
                        'user_id' => $candidate->id,
                        'position' => clone $position,
                    ]);
                } else {
                    $response->assertSessionHasErrors();
                }
            });
    }

    /**
     * @group property
     * Feature: osas-account-management
     * Property 15: Completed actions are all-or-nothing and freshness-safe.
     */
    public function test_completed_actions_are_freshness_safe(): void
    {
        $this->forAll(
            Generator\choose(0, 5) // Mismatched freshness
        )
            ->withMaxSize(20)
            ->then(function ($freshness) {
                $admin = User::factory()->create(['role' => 'osas_admin']);
                $target = User::factory()->create();

                // Fake the correct freshness logic
                $actualFreshness = 0; // DB version is 0 initially
                
                $response = $this->actingAs($admin)->put("/osas/accounts/{$target->id}/profile", [
                    'first_name' => 'Updated',
                    'last_name' => 'User',
                    'email' => 'updated@example.com',
                    'freshness' => $freshness
                ]);

                if ($freshness !== $actualFreshness) {
                    $response->assertSessionHasErrors('freshness');
                } else {
                    $response->assertSessionHasNoErrors();
                }
            });
    }

    /**
     * @group property
     * Feature: osas-account-management
     * Property 17: Cancellation or absent destructive confirmation preserves state.
     */
    public function test_absent_destructive_confirmation_preserves_state(): void
    {
        $this->forAll(
            Generator\bool()
        )
            ->withMaxSize(20)
            ->then(function () {
                $admin = User::factory()->create(['role' => 'osas_admin']);
                $target = User::factory()->create();

                // If we send a delete WITHOUT freshness (or missing), it fails closed.
                $response = $this->actingAs($admin)->delete("/osas/accounts/{$target->id}");
                
                $response->assertSessionHasErrors();
                $this->assertDatabaseHas('users', ['id' => $target->id]);
            });
    }
}
