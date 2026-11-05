<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationPosition;
use App\Models\OrganizationRolePermission;
use App\Models\OrganizationSettings;
use App\Models\User;
use App\Notifications\PositionRemovedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SettingsControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        // Create organization
        $this->organization = Organization::factory()->create([
            'name' => 'Test Organization',
        ]);

        // Create admin user
        $this->adminUser = User::factory()->create([
            'role' => 'admin',
        ]);
        $this->adminUser->organizations()->attach($this->organization->id, [
            'role' => 'President',
            'membership_status' => 'Active',
        ]);
        session(['current_organization_id' => $this->organization->id]);
    }

    /** @test */
    public function admin_can_access_settings_page()
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.settings.index'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.settings.index');
        $response->assertViewHas('theme');
    }

    /** @test */
    public function admin_can_update_primary_color_via_ajax()
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson(route('admin.settings.update'), [
                'primary_color' => '#FF5733',
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Theme settings saved successfully',
        ]);

        $this->assertDatabaseHas('organization_settings', [
            'organization_id' => $this->organization->id,
            'primary_color' => '#FF5733',
        ]);
    }

    /** @test */
    public function admin_can_update_complementary_color_via_ajax()
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson(route('admin.settings.update'), [
                'complementary_color' => '#33FF57',
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Theme settings saved successfully',
        ]);

        $this->assertDatabaseHas('organization_settings', [
            'organization_id' => $this->organization->id,
            'complementary_color' => '#33FF57',
        ]);
    }

    /** @test */
    public function admin_can_upload_logo_via_ajax()
    {
        Storage::fake('public');
        config(['filesystems.default' => 'public']);

        $file = UploadedFile::fake()->image('logo.png', 100, 100)->size(1024);

        $response = $this->actingAs($this->adminUser)
            ->postJson(route('admin.settings.update'), [
                'logo' => $file,
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Theme settings saved successfully',
        ]);

        // Verify logo was stored
        $settings = OrganizationSettings::where('organization_id', $this->organization->id)->first();
        $this->assertNotNull($settings);
        $this->assertNotNull($settings->logo_url);
        Storage::disk('public')->assertExists($settings->getRawOriginal('logo_url'));
    }

    /** @test */
    public function invalid_hex_color_returns_validation_error_via_ajax()
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson(route('admin.settings.update'), [
                'primary_color' => 'invalid-color',
            ]);

        $response->assertStatus(422);
        $response->assertJsonStructure([
            'success',
            'message',
            'errors' => ['primary_color'],
        ]);
    }

    /** @test */
    public function invalid_logo_format_returns_validation_error_via_ajax()
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->create('document.txt', 100);

        $response = $this->actingAs($this->adminUser)
            ->postJson(route('admin.settings.update'), [
                'logo' => $file,
            ]);

        $response->assertStatus(422);
        $response->assertJsonStructure([
            'success',
            'message',
            'errors' => ['logo'],
        ]);
    }

    /** @test */
    public function oversized_logo_returns_validation_error_via_ajax()
    {
        Storage::fake('public');

        // Create a file larger than 2MB
        $file = UploadedFile::fake()->image('logo.png')->size(3000);

        $response = $this->actingAs($this->adminUser)
            ->postJson(route('admin.settings.update'), [
                'logo' => $file,
            ]);

        $response->assertStatus(422);
        $response->assertJsonStructure([
            'success',
            'message',
            'errors' => ['logo'],
        ]);
    }

    /** @test */
    public function session_cache_cleared_after_update()
    {
        // Set session cache
        session(['organization_logo' => 'old-logo.png', 'organization_name' => 'Old Name']);

        $this->actingAs($this->adminUser)
            ->postJson(route('admin.settings.update'), [
                'primary_color' => '#FF5733',
            ]);

        // Verify session cache was cleared
        $this->assertNull(session('organization_logo'));
        $this->assertNull(session('organization_name'));
    }

    /** @test */
    public function update_works_with_traditional_form_submission()
    {
        $response = $this->actingAs($this->adminUser)
            ->post(route('admin.settings.update'), [
                'primary_color' => '#FF5733',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Theme settings saved successfully');

        $this->assertDatabaseHas('organization_settings', [
            'organization_id' => $this->organization->id,
            'primary_color' => '#FF5733',
        ]);
    }

    /** @test */
    public function same_position_holders_are_grouped_under_one_role_row(): void
    {
        $bm1 = User::factory()->create(['role' => 'admin']);
        $bm1->organizations()->attach($this->organization->id, [
            'role' => 'Business Manager',
            'membership_status' => 'Active',
        ]);

        $bm2 = User::factory()->create(['role' => 'admin']);
        $bm2->organizations()->attach($this->organization->id, [
            'role' => 'Business Manager',
            'membership_status' => 'Active',
        ]);

        OrganizationSettings::create([
            'organization_id' => $this->organization->id,
            'has_documents' => true,
            'has_payments' => true,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.settings.index'));

        $response->assertStatus(200);

        $adminsByRole = $response->viewData('adminsByRole');
        $this->assertTrue($adminsByRole->has('Business Manager'));
        $this->assertCount(1, $adminsByRole->keys()->filter(fn ($role) => $role === 'Business Manager'));
        $this->assertSame(
            [$bm1->id, $bm2->id],
            $adminsByRole['Business Manager']->pluck('id')->sort()->values()->all(),
        );

        // Both holders share one module profile — the matrix defaults for Business Manager.
        $rolePermissions = $response->viewData('rolePermissions');
        $this->assertTrue($rolePermissions['Business Manager']['documents']['default']);
        $this->assertTrue($rolePermissions['Business Manager']['payments']['default']);
    }

    /** @test */
    public function role_permission_toggle_applies_to_every_spelling_of_the_position(): void
    {
        $bm1 = User::factory()->create(['role' => 'admin']);
        $bm1->organizations()->attach($this->organization->id, [
            'role' => 'Business Manager',
            'membership_status' => 'Active',
        ]);

        $bm2 = User::factory()->create(['role' => 'admin']);
        $bm2->organizations()->attach($this->organization->id, [
            'role' => 'Business Manager',
            'membership_status' => 'Active',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->postJson(route('admin.settings.role-permissions.update'), [
                'role' => 'Business Manager',
                'module' => 'events',
                'is_granted' => true,
            ]);

        $response->assertStatus(200)->assertJson(['success' => true]);

        $this->assertDatabaseHas('organization_role_permissions', [
            'organization_id' => $this->organization->id,
            'role' => 'Business Manager',
            'module' => 'events',
            'is_granted' => true,
        ]);
    }

    /** @test */
    public function president_can_delete_a_custom_position_and_clean_up_its_assignee_and_permissions(): void
    {
        Notification::fake();

        $position = OrganizationPosition::create([
            'organization_id' => $this->organization->id,
            'name' => 'Social Media Manager',
        ]);
        OrganizationRolePermission::create([
            'organization_id' => $this->organization->id,
            'role' => $position->name,
            'module' => 'announcements',
            'is_granted' => true,
        ]);

        $assignedMember = User::factory()->create([
            'role' => 'admin',
        ]);
        $assignedMember->organizations()->attach($this->organization->id, [
            'role' => $position->name,
            'membership_status' => 'Active',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->delete(route('admin.settings.positions.destroy', $position));

        $response->assertRedirect(route('admin.settings.index', ['tab' => 'positions']));
        $response->assertSessionHas('success', "Position '{$position->name}' was deleted. 1 member returned to Member.");
        $this->assertDatabaseMissing('organization_positions', ['id' => $position->id]);
        $this->assertDatabaseMissing('organization_role_permissions', [
            'organization_id' => $this->organization->id,
            'role' => $position->name,
        ]);
        $this->assertDatabaseHas('organization_user', [
            'organization_id' => $this->organization->id,
            'user_id' => $assignedMember->id,
            'role' => 'Member',
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $assignedMember->id,
            'role' => 'student',
            'active_view' => null,
        ]);
        Notification::assertSentTo($assignedMember, PositionRemovedNotification::class);
    }
}
