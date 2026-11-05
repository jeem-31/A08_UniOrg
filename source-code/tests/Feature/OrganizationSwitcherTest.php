<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use App\Services\OrganizationPermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OrganizationSwitcherTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that admin can switch between organizations
     */
    public function test_admin_can_switch_organizations(): void
    {
        // Create organizations
        $org1 = Organization::create([
            'name' => 'First Organization',
            'type' => 'Mother Org',
            'status' => 'Approved',
            'has_system' => true,
        ]);

        $org2 = Organization::create([
            'name' => 'Second Organization',
            'type' => 'Sub-Org',
            'status' => 'Approved',
            'has_system' => true,
        ]);

        // Create an admin user
        $user = User::create([
            'first_name' => 'Test',
            'last_name' => 'Admin',
            'email' => 'admin@test.com',
            'password' => Hash::make('password'),
            'student_id' => 'TEST001',
            'course' => 'BSCS',
            'year_level' => '4',
            'section' => 'A',
            'role' => 'admin',
            'is_active' => true,
        ]);

        // Attach user to both organizations
        $user->organizations()->attach($org1->id, [
            'role' => 'President',
            'membership_status' => 'Active',
        ]);

        $user->organizations()->attach($org2->id, [
            'role' => 'Secretary',
            'membership_status' => 'Active',
        ]);

        // Login and set initial organization context
        $this->actingAs($user);
        session(['current_organization_id' => $org1->id]);

        // Switch to second organization
        $response = $this->post(route('organization.switch'), [
            'organization_id' => $org2->id,
        ]);

        // Assert successful redirect
        $response->assertRedirect();
        $response->assertSessionHas('success', 'Organization switched successfully.');

        // Assert session has new organization context
        $this->assertEquals($org2->id, session('current_organization_id'));
    }

    /**
     * Test that permission cache is invalidated on organization switch
     */
    public function test_permission_cache_invalidated_on_organization_switch(): void
    {
        // Create organizations
        $org1 = Organization::create([
            'name' => 'First Organization',
            'type' => 'Mother Org',
            'status' => 'Approved',
            'has_system' => true,
        ]);

        $org2 = Organization::create([
            'name' => 'Second Organization',
            'type' => 'Sub-Org',
            'status' => 'Approved',
            'has_system' => true,
        ]);

        // Create an admin user
        $user = User::create([
            'first_name' => 'Test',
            'last_name' => 'Admin',
            'email' => 'admin@test.com',
            'password' => Hash::make('password'),
            'student_id' => 'TEST002',
            'course' => 'BSCS',
            'year_level' => '4',
            'section' => 'A',
            'role' => 'admin',
            'is_active' => true,
        ]);

        // Attach user to both organizations
        $user->organizations()->attach($org1->id, [
            'role' => 'President',
            'membership_status' => 'Active',
        ]);

        $user->organizations()->attach($org2->id, [
            'role' => 'Treasurer',
            'membership_status' => 'Active',
        ]);

        // Login and set initial organization context
        $this->actingAs($user);
        session(['current_organization_id' => $org1->id]);

        // Cache the permission for org2
        $cacheKey = "org_permission:{$user->id}:{$org2->id}";
        Cache::put($cacheKey, 'Treasurer', 3600);

        // Verify cache exists
        $this->assertTrue(Cache::has($cacheKey));

        // Switch to second organization
        $this->post(route('organization.switch'), [
            'organization_id' => $org2->id,
        ]);

        // Verify cache was invalidated
        $this->assertFalse(Cache::has($cacheKey));
    }

    /**
     * Test that user cannot switch to organization they don't belong to
     */
    public function test_user_cannot_switch_to_unauthorized_organization(): void
    {
        // Create organizations
        $org1 = Organization::create([
            'name' => 'First Organization',
            'type' => 'Mother Org',
            'status' => 'Approved',
            'has_system' => true,
        ]);

        $org2 = Organization::create([
            'name' => 'Second Organization',
            'type' => 'Sub-Org',
            'status' => 'Approved',
            'has_system' => true,
        ]);

        // Create an admin user
        $user = User::create([
            'first_name' => 'Test',
            'last_name' => 'Admin',
            'email' => 'admin@test.com',
            'password' => Hash::make('password'),
            'student_id' => 'TEST003',
            'course' => 'BSCS',
            'year_level' => '4',
            'section' => 'A',
            'role' => 'admin',
            'is_active' => true,
        ]);

        // Attach user only to first organization
        $user->organizations()->attach($org1->id, [
            'role' => 'President',
            'membership_status' => 'Active',
        ]);

        // Login and set initial organization context
        $this->actingAs($user);
        session(['current_organization_id' => $org1->id]);

        // Attempt to switch to unauthorized organization
        $response = $this->post(route('organization.switch'), [
            'organization_id' => $org2->id,
        ]);

        // Assert error message
        $response->assertRedirect();
        $response->assertSessionHas('error', 'You do not have access to this organization.');

        // Assert session still has original organization context
        $this->assertEquals($org1->id, session('current_organization_id'));
    }

    /**
     * Test that student users cannot switch organizations
     */
    public function test_student_cannot_switch_organizations(): void
    {
        // Create organization
        $org = Organization::create([
            'name' => 'Test Organization',
            'type' => 'Mother Org',
            'status' => 'Approved',
            'has_system' => true,
        ]);

        // Create a student user
        $user = User::create([
            'first_name' => 'Test',
            'last_name' => 'Student',
            'email' => 'student@test.com',
            'password' => Hash::make('password'),
            'student_id' => 'TEST004',
            'course' => 'BSCS',
            'year_level' => '4',
            'section' => 'A',
            'role' => 'student',
            'is_active' => true,
        ]);

        // Login as student
        $this->actingAs($user);

        // Attempt to switch organization
        $response = $this->post(route('organization.switch'), [
            'organization_id' => $org->id,
        ]);

        // Assert error (student doesn't have organization access)
        $response->assertRedirect();
        $response->assertSessionHas('error', 'You do not have access to this organization.');
    }

    /**
     * Test that organization switcher validates organization_id
     */
    public function test_organization_switcher_validates_organization_id(): void
    {
        // Create an admin user
        $user = User::create([
            'first_name' => 'Test',
            'last_name' => 'Admin',
            'email' => 'admin@test.com',
            'password' => Hash::make('password'),
            'student_id' => 'TEST005',
            'course' => 'BSCS',
            'year_level' => '4',
            'section' => 'A',
            'role' => 'admin',
            'is_active' => true,
        ]);

        // Login
        $this->actingAs($user);

        // Attempt to switch with invalid organization_id
        $response = $this->post(route('organization.switch'), [
            'organization_id' => 99999, // Non-existent organization
        ]);

        // Assert validation error
        $response->assertSessionHasErrors('organization_id');
    }
}
