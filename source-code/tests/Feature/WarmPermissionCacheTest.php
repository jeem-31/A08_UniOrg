<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use App\Services\OrganizationPermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WarmPermissionCacheTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The membership rows below reference organizations 1 and 2 directly, so
        // create them to satisfy the organization_user foreign keys.
        Organization::factory()->count(2)->create();

        // Clear cache before each test
        Cache::flush();
    }

    public function test_command_caches_permissions_for_active_admins(): void
    {
        // Create an active admin user with organization membership
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        // Create organization membership
        DB::table('organization_user')->insert([
            'user_id' => $admin->id,
            'organization_id' => 1,
            'role' => 'President',
            'membership_status' => 'Active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Verify cache is empty before command
        $cacheKey = "org_permission:{$admin->id}:1";
        $this->assertFalse(Cache::has($cacheKey));

        // Run the command
        $this->artisan('permission:warm-cache')
            ->expectsOutput('Starting permission cache warming...')
            ->expectsOutput('Permission cache warmed successfully!')
            ->assertExitCode(0);

        // Verify cache is populated after command
        $this->assertTrue(Cache::has($cacheKey));
        $this->assertEquals('President', Cache::get($cacheKey));
    }

    public function test_command_skips_inactive_admins(): void
    {
        // Create an inactive admin user
        $inactiveAdmin = User::factory()->create([
            'role' => 'admin',
            'is_active' => false,
        ]);

        DB::table('organization_user')->insert([
            'user_id' => $inactiveAdmin->id,
            'organization_id' => 1,
            'role' => 'Secretary',
            'membership_status' => 'Active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Run the command
        $this->artisan('permission:warm-cache')
            ->assertExitCode(0);

        // Verify cache is NOT populated for inactive user
        $cacheKey = "org_permission:{$inactiveAdmin->id}:1";
        $this->assertFalse(Cache::has($cacheKey));
    }

    public function test_command_handles_multiple_organizations(): void
    {
        // Create admin with multiple organization memberships
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        DB::table('organization_user')->insert([
            [
                'user_id' => $admin->id,
                'organization_id' => 1,
                'role' => 'President',
                'membership_status' => 'Active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => $admin->id,
                'organization_id' => 2,
                'role' => 'Treasurer',
                'membership_status' => 'Active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // Run the command
        $this->artisan('permission:warm-cache')
            ->assertExitCode(0);

        // Verify both organizations are cached
        $this->assertTrue(Cache::has("org_permission:{$admin->id}:1"));
        $this->assertEquals('President', Cache::get("org_permission:{$admin->id}:1"));
        
        $this->assertTrue(Cache::has("org_permission:{$admin->id}:2"));
        $this->assertEquals('Treasurer', Cache::get("org_permission:{$admin->id}:2"));
    }

    public function test_command_skips_pending_memberships(): void
    {
        // Create admin with pending membership
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        DB::table('organization_user')->insert([
            'user_id' => $admin->id,
            'organization_id' => 1,
            'role' => 'Secretary',
            'membership_status' => 'Pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Run the command
        $this->artisan('permission:warm-cache')
            ->assertExitCode(0);

        // Verify cache is NOT populated for pending membership
        $cacheKey = "org_permission:{$admin->id}:1";
        $this->assertFalse(Cache::has($cacheKey));
    }

    public function test_command_handles_no_active_admins(): void
    {
        // Ensure no active admins exist
        User::where('role', 'admin')->update(['is_active' => false]);

        // Run the command
        $this->artisan('permission:warm-cache')
            ->expectsOutput('Starting permission cache warming...')
            ->expectsOutput('No active admin users found.')
            ->assertExitCode(0);
    }
}
