<?php

namespace Tests\Property\AccountManagement;

use App\Models\User;
use Eris\Generator;
use Eris\TestTrait;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountPropertiesTest extends TestCase
{
    use RefreshDatabase;
    use TestTrait;

    /**
     * @group property
     * Feature: osas-account-management
     * Property 1: Protected access is role- and activity-scoped.
     */
    public function test_protected_access_is_role_and_activity_scoped(): void
    {
        $this->forAll(
            Generator\elements('student', 'faculty', 'admin', 'osas_admin', 'super_admin'),
            Generator\bool()
        )
            ->withMaxSize(100)
            ->then(function ($role, $isActive) {
                // Each active Dean needs its own college so the one-active-Dean-
                // per-department rule never collides within this property run.
                $departmentId = $role === 'super_admin' && $isActive
                    ? random_int(1000, 999999)
                    : null;

                $user = User::factory()->create([
                    'role' => $role,
                    'is_active' => $isActive,
                    'department_id' => $departmentId,
                ]);

                $response = $this->actingAs($user)->get('/osas/accounts');

                if ($isActive && in_array($role, ['osas_admin', 'super_admin'])) {
                    $response->assertStatus(200);
                } else {
                    $response->assertStatus(403);
                }
            });
    }

    /**
     * @group property
     * Feature: osas-account-management
     * Property 4: Valid account transitions preserve declared field boundaries.
     */
    public function test_valid_account_transitions_preserve_declared_field_boundaries(): void
    {
        $this->forAll(
            \Tests\Generators\AccountManagementGenerators::validProfile()
        )
            ->withMaxSize(100)
            ->then(function ($profile) {
                $admin = User::factory()->create([
                    'role' => 'osas_admin',
                    'is_active' => true,
                ]);

                // Append required valid fields
                $profile['first_name'] = $profile['first_name'] ?: 'Test';
                $profile['last_name'] = $profile['last_name'] ?: 'User';
                $profile['email'] = $profile['email'] ?: uniqid().'@example.com';

                $response = $this->actingAs($admin)->post('/osas/accounts', $profile);
                
                $response->assertRedirect();
                $response->assertSessionHasNoErrors();
                
                $this->assertDatabaseHas('users', [
                    'email' => $profile['email'],
                    'first_name' => $profile['first_name'],
                    'last_name' => $profile['last_name'],
                ]);
            });
    }

    /**
     * @group property
     * Feature: osas-account-management
     * Property 5: Invalid account inputs and duplicate identities are atomic.
     */
    public function test_invalid_account_inputs_are_atomic(): void
    {
        $this->forAll(
            \Tests\Generators\AccountManagementGenerators::invalidProfile()
        )
            ->withMaxSize(100)
            ->then(function ($profile) {
                $admin = User::factory()->create([
                    'role' => 'osas_admin',
                    'is_active' => true,
                ]);

                $countBefore = User::count();

                $response = $this->actingAs($admin)->post('/osas/accounts', $profile);
                
                $response->assertSessionHasErrors();
                
                $countAfter = User::count();
                $this->assertEquals($countBefore, $countAfter);
            });
    }

    /**
     * @group property
     * Feature: osas-account-management
     * Property 7: Account lifecycle transitions preserve identity and related access.
     */
    public function test_account_lifecycle_transitions_preserve_identity(): void
    {
        $this->forAll(
            Generator\elements('activate', 'deactivate')
        )
            ->withMaxSize(100)
            ->then(function ($action) {
                $admin = User::factory()->create([
                    'role' => 'osas_admin',
                    'is_active' => true,
                ]);

                $target = User::factory()->create([
                    'is_active' => $action === 'activate' ? false : true,
                ]);

                $response = $this->actingAs($admin)->put("/osas/accounts/{$target->id}/lifecycle", [
                    'action' => $action,
                    'freshness' => 0,
                ]);

                $response->assertRedirect();
                
                $target->refresh();
                $this->assertEquals($action === 'activate', $target->is_active);
            });
    }
}
