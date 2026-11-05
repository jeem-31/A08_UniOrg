<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\User;
use App\Services\OrganizationPermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PermissionCacheInvalidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Clear cache before each test
        Cache::flush();
    }

    public function test_cache_invalidates_when_position_changes(): void
    {
        // Create admin user and organization
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $organization = Organization::factory()->create();

        // Create organization membership
        $admin->organizations()->attach($organization->id, [
            'role' => 'Secretary',
            'membership_status' => 'Active',
        ]);

        // Warm the cache
        $service = app(OrganizationPermissionService::class);
        $position = $service->getPosition($admin->id, $organization->id);
        
        $this->assertEquals('Secretary', $position);
        
        // Verify cache exists
        $cacheKey = "org_permission:{$admin->id}:{$organization->id}";
        $this->assertTrue(Cache::has($cacheKey));
        $this->assertEquals('Secretary', Cache::get($cacheKey));

        // Update through the observed pivot model so cache invalidation follows production behavior.
        OrganizationUser::query()
            ->where('user_id', $admin->id)
            ->where('organization_id', $organization->id)
            ->firstOrFail()
            ->update(['role' => 'President']);

        // Verify cache was invalidated
        $this->assertFalse(Cache::has($cacheKey));

        // Verify new position is fetched from database
        $newPosition = $service->getPosition($admin->id, $organization->id);
        $this->assertEquals('President', $newPosition);
    }

    public function test_cache_invalidates_when_membership_status_changes(): void
    {
        // Create admin user and organization
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $organization = Organization::factory()->create();

        // Create organization membership
        $admin->organizations()->attach($organization->id, [
            'role' => 'Treasurer',
            'membership_status' => 'Active',
        ]);

        // Warm the cache
        $service = app(OrganizationPermissionService::class);
        $position = $service->getPosition($admin->id, $organization->id);
        
        $this->assertEquals('Treasurer', $position);
        
        // Verify cache exists
        $cacheKey = "org_permission:{$admin->id}:{$organization->id}";
        $this->assertTrue(Cache::has($cacheKey));

        // Update through the observed pivot model so cache invalidation follows production behavior.
        OrganizationUser::query()
            ->where('user_id', $admin->id)
            ->where('organization_id', $organization->id)
            ->firstOrFail()
            ->update(['membership_status' => 'Pending']);

        // Verify cache was invalidated
        $this->assertFalse(Cache::has($cacheKey));

        // Verify position is now null (Pending memberships are not returned)
        $newPosition = $service->getPosition($admin->id, $organization->id);
        $this->assertNull($newPosition);
    }

    public function test_cache_invalidates_when_membership_deleted(): void
    {
        // Create admin user and organization
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $organization = Organization::factory()->create();

        // Create organization membership
        $admin->organizations()->attach($organization->id, [
            'role' => 'Auditor',
            'membership_status' => 'Active',
        ]);

        // Warm the cache
        $service = app(OrganizationPermissionService::class);
        $position = $service->getPosition($admin->id, $organization->id);
        
        $this->assertEquals('Auditor', $position);
        
        // Verify cache exists
        $cacheKey = "org_permission:{$admin->id}:{$organization->id}";
        $this->assertTrue(Cache::has($cacheKey));

        // Delete through the observed pivot model.
        OrganizationUser::query()
            ->where('user_id', $admin->id)
            ->where('organization_id', $organization->id)
            ->firstOrFail()
            ->delete();

        // Verify cache was invalidated
        $this->assertFalse(Cache::has($cacheKey));

        // Verify position is now null
        $newPosition = $service->getPosition($admin->id, $organization->id);
        $this->assertNull($newPosition);
    }

    public function test_cache_not_invalidated_when_unrelated_fields_change(): void
    {
        // Create admin user and organization
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $organization = Organization::factory()->create();

        // Create organization membership
        $admin->organizations()->attach($organization->id, [
            'role' => 'Vice_president',
            'membership_status' => 'Active',
        ]);

        // Warm the cache
        $service = app(OrganizationPermissionService::class);
        $position = $service->getPosition($admin->id, $organization->id);
        
        $this->assertEquals('Vice_president', $position);
        
        // Verify cache exists
        $cacheKey = "org_permission:{$admin->id}:{$organization->id}";
        $this->assertTrue(Cache::has($cacheKey));

        // Update only the timestamp through the observed model; unrelated changes keep the cache.
        OrganizationUser::query()
            ->where('user_id', $admin->id)
            ->where('organization_id', $organization->id)
            ->firstOrFail()
            ->update(['updated_at' => now()->addMinute()]);

        // Verify cache still exists (should not be invalidated)
        $this->assertTrue(Cache::has($cacheKey));
        $this->assertEquals('Vice_president', Cache::get($cacheKey));
    }

    public function test_cache_invalidation_only_affects_specific_user_org_pair(): void
    {
        // Create two admin users and two organizations
        $admin1 = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $admin2 = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        
        $org1 = Organization::factory()->create();
        $org2 = Organization::factory()->create();

        // Create memberships
        $admin1->organizations()->attach($org1->id, [
            'role' => 'President',
            'membership_status' => 'Active',
        ]);
        
        $admin1->organizations()->attach($org2->id, [
            'role' => 'Secretary',
            'membership_status' => 'Active',
        ]);
        
        $admin2->organizations()->attach($org1->id, [
            'role' => 'Treasurer',
            'membership_status' => 'Active',
        ]);

        // Warm all caches
        $service = app(OrganizationPermissionService::class);
        $service->getPosition($admin1->id, $org1->id);
        $service->getPosition($admin1->id, $org2->id);
        $service->getPosition($admin2->id, $org1->id);

        // Verify all caches exist
        $this->assertTrue(Cache::has("org_permission:{$admin1->id}:{$org1->id}"));
        $this->assertTrue(Cache::has("org_permission:{$admin1->id}:{$org2->id}"));
        $this->assertTrue(Cache::has("org_permission:{$admin2->id}:{$org1->id}"));

        // Update through the observed pivot model.
        OrganizationUser::query()
            ->where('user_id', $admin1->id)
            ->where('organization_id', $org1->id)
            ->firstOrFail()
            ->update(['role' => 'Vice_president']);

        // Verify only admin1-org1 cache was invalidated
        $this->assertFalse(Cache::has("org_permission:{$admin1->id}:{$org1->id}"));
        $this->assertTrue(Cache::has("org_permission:{$admin1->id}:{$org2->id}"));
        $this->assertTrue(Cache::has("org_permission:{$admin2->id}:{$org1->id}"));
    }

    public function test_cache_invalidates_on_logout(): void
    {
        // Create admin user and organizations
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $org1 = Organization::factory()->create();
        $org2 = Organization::factory()->create();

        // Create memberships in multiple organizations
        $admin->organizations()->attach($org1->id, [
            'role' => 'President',
            'membership_status' => 'Active',
        ]);
        
        $admin->organizations()->attach($org2->id, [
            'role' => 'Secretary',
            'membership_status' => 'Active',
        ]);

        // Warm the cache for both organizations
        $service = app(OrganizationPermissionService::class);
        $service->getPosition($admin->id, $org1->id);
        $service->getPosition($admin->id, $org2->id);

        // Verify caches exist
        $cacheKey1 = "org_permission:{$admin->id}:{$org1->id}";
        $cacheKey2 = "org_permission:{$admin->id}:{$org2->id}";
        $this->assertTrue(Cache::has($cacheKey1));
        $this->assertTrue(Cache::has($cacheKey2));

        // Login and logout
        $this->actingAs($admin);
        $response = $this->post(route('logout'));

        // Verify both caches were invalidated
        $this->assertFalse(Cache::has($cacheKey1));
        $this->assertFalse(Cache::has($cacheKey2));

        // Verify redirect to the organization login landing page
        $response->assertRedirect('/org/login');
    }
}
