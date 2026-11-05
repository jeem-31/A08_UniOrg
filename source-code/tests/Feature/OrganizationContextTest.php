<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OrganizationContextTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that organization context is set on admin login
     */
    public function test_organization_context_is_set_on_admin_login(): void
    {
        // Create an organization
        $organization = Organization::create([
            'name' => 'Test Organization',
            'type' => 'Mother Org',
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

        // Attach user to organization
        $user->organizations()->attach($organization->id, [
            'role' => 'President',
            'membership_status' => 'Active',
        ]);

        // Attempt login
        $response = $this->post('/login', [
            'student_id' => 'TEST001',
            'password' => 'password',
        ]);

        // Assert redirect to the unified dashboard; it selects the active role view.
        $response->assertRedirect(route('dashboard'));

        // Assert session has organization context
        $this->assertEquals($organization->id, session('current_organization_id'));
    }

    /**
     * Test that organization context defaults to first organization
     */
    public function test_organization_context_defaults_to_first_organization(): void
    {
        // Create multiple organizations
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

        // Attach user to multiple organizations
        $user->organizations()->attach($org1->id, [
            'role' => 'President',
            'membership_status' => 'Active',
        ]);

        $user->organizations()->attach($org2->id, [
            'role' => 'Secretary',
            'membership_status' => 'Active',
        ]);

        // Attempt login
        $response = $this->post('/login', [
            'student_id' => 'TEST002',
            'password' => 'password',
        ]);

        // Assert redirect to the unified dashboard; it selects the active role view.
        $response->assertRedirect(route('dashboard'));

        // Assert session has organization context (should be first organization)
        $this->assertEquals($org1->id, session('current_organization_id'));
    }

    /**
     * Test that organization context is not set for student users
     */
    public function test_organization_context_is_set_for_students(): void
    {
        $organization = Organization::create([
            'name' => 'Student Organization',
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
            'student_id' => 'TEST003',
            'course' => 'BSCS',
            'year_level' => '4',
            'section' => 'A',
            'role' => 'student',
            'is_active' => true,
        ]);
        $user->organizations()->attach($organization->id, [
            'role' => 'Member',
            'membership_status' => 'Active',
        ]);

        // Attempt login
        $response = $this->post('/login', [
            'student_id' => 'TEST003',
            'password' => 'password',
        ]);

        // Assert redirect to student dashboard
        $response->assertRedirect(route('dashboard'));

        // Regular students also receive organization context for scoped pages.
        $this->assertEquals($organization->id, session('current_organization_id'));
    }

    /**
     * Test that existing session organization context is preserved
     */
    public function test_existing_organization_context_is_preserved(): void
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
            'student_id' => 'TEST004',
            'course' => 'BSCS',
            'year_level' => '4',
            'section' => 'A',
            'role' => 'admin',
            'is_active' => true,
        ]);

        // Attach user to multiple organizations
        $user->organizations()->attach($org1->id, [
            'role' => 'President',
            'membership_status' => 'Active',
        ]);

        $user->organizations()->attach($org2->id, [
            'role' => 'Secretary',
            'membership_status' => 'Active',
        ]);

        // Set existing organization context in session
        session(['current_organization_id' => $org2->id]);

        // Attempt login
        $response = $this->post('/login', [
            'student_id' => 'TEST004',
            'password' => 'password',
        ]);

        // Assert redirect to the unified dashboard; it selects the active role view.
        $response->assertRedirect(route('dashboard'));

        // Assert session preserves the existing organization context
        $this->assertEquals($org2->id, session('current_organization_id'));
    }
}
