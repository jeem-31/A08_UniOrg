<?php

/**
 * Manual Cache Invalidation Test
 * 
 * This script demonstrates that cache invalidation works when:
 * 1. A user's position (role) changes in organization_user table
 * 2. A user's membership_status changes in organization_user table
 * 
 * To run this test:
 * 1. Ensure your database is set up and migrations are run
 * 2. Run: php artisan tinker
 * 3. Copy and paste the code below
 * 
 * Expected behavior:
 * - Cache should be invalidated when role or membership_status changes
 * - Cache should NOT be invalidated when other fields change
 */

// Example usage in tinker:

/*

// 1. Create a test user and organization
$user = \App\Models\User::factory()->create(['role' => 'admin']);
$org = \App\Models\Organization::create(['name' => 'Test Org', 'type' => 'Academic', 'status' => 'Active']);

// 2. Attach user to organization
$user->organizations()->attach($org->id, ['role' => 'Secretary', 'membership_status' => 'Active']);

// 3. Get permission service and warm cache
$service = app(\App\Services\OrganizationPermissionService::class);
$position = $service->getPosition($user->id, $org->id);
echo "Initial position: $position\n"; // Should output: Secretary

// 4. Verify cache exists
$cacheKey = "org_permission:{$user->id}:{$org->id}";
echo "Cache exists: " . (Cache::has($cacheKey) ? 'Yes' : 'No') . "\n"; // Should output: Yes
echo "Cached value: " . Cache::get($cacheKey) . "\n"; // Should output: Secretary

// 5. Update position using DB query (simulating direct database update)
DB::table('organization_user')
    ->where('user_id', $user->id)
    ->where('organization_id', $org->id)
    ->update(['role' => 'President']);

// 6. Check if cache was invalidated
echo "Cache exists after update: " . (Cache::has($cacheKey) ? 'Yes' : 'No') . "\n"; // Should output: No

// 7. Get position again (should fetch from database)
$newPosition = $service->getPosition($user->id, $org->id);
echo "New position: $newPosition\n"; // Should output: President

// 8. Test membership_status change
echo "\n--- Testing membership_status change ---\n";
echo "Cache exists: " . (Cache::has($cacheKey) ? 'Yes' : 'No') . "\n"; // Should output: Yes (re-cached)

DB::table('organization_user')
    ->where('user_id', $user->id)
    ->where('organization_id', $org->id)
    ->update(['membership_status' => 'Pending']);

echo "Cache exists after status change: " . (Cache::has($cacheKey) ? 'Yes' : 'No') . "\n"; // Should output: No

// 9. Cleanup
$user->organizations()->detach();
$user->delete();
$org->delete();

echo "\nTest completed successfully!\n";

*/

?>
