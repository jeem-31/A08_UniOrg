<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsRoutesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that admin can access settings page
     */
    public function test_admin_can_access_settings_page(): void
    {
        // Create organization
        $organization = Organization::factory()->create();

        // Create admin user
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);
        $admin->organizations()->attach($organization->id, [
            'role' => 'President',
            'membership_status' => 'Active',
        ]);

        // Act as admin and access settings page
        $response = $this->actingAs($admin)
            ->withSession(['current_organization_id' => $organization->id])
            ->get(route('admin.settings.index'));

        // Assert successful response
        $response->assertStatus(200);
    }

    /**
     * Test that non-admin cannot access settings page
     */
    public function test_non_admin_cannot_access_settings_page(): void
    {
        // Create organization
        $organization = Organization::factory()->create();

        // Create student user
        $student = User::factory()->create([
            'role' => 'student',
        ]);

        // Act as student and try to access settings page
        $response = $this->actingAs($student)->get(route('admin.settings.index'));

        // Assert forbidden or redirect
        $this->assertTrue(
            $response->status() === 403 || $response->status() === 302,
            'Non-admin should not be able to access settings page'
        );
    }

    /**
     * Test that settings routes are registered correctly
     */
    public function test_settings_routes_are_registered(): void
    {
        // Check that the routes exist
        $this->assertTrue(
            \Illuminate\Support\Facades\Route::has('admin.settings.index'),
            'admin.settings.index route should exist'
        );

        $this->assertTrue(
            \Illuminate\Support\Facades\Route::has('admin.settings.update'),
            'admin.settings.update route should exist'
        );
    }

    /**
     * Test that settings routes have correct URIs
     */
    public function test_settings_routes_have_correct_uris(): void
    {
        $indexRoute = route('admin.settings.index', [], false);
        $updateRoute = route('admin.settings.update', [], false);

        $this->assertEquals('/admin/settings', $indexRoute);
        $this->assertEquals('/admin/settings', $updateRoute);
    }
}
