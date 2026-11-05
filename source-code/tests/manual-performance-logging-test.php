<?php

/**
 * Manual Performance Logging Test
 * 
 * This script verifies that performance logging is working correctly.
 * Run with: php tests/manual-performance-logging-test.php
 * 
 * Expected behavior:
 * 1. Cache miss should be logged on first access
 * 2. Cache hit should be logged on subsequent access
 * 3. Authorization check duration should be logged
 * 4. Logs should appear in storage/logs/performance.log
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\Organization;
use App\Services\OrganizationPermissionService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

echo "=== Performance Logging Test ===\n\n";

// 1. Find or create test user and organization
$user = User::where('role', 'admin')->first();
if (!$user) {
    echo "Error: No admin user found. Please create one first.\n";
    exit(1);
}

$org = Organization::first();
if (!$org) {
    echo "Error: No organization found. Please create one first.\n";
    exit(1);
}

echo "Using User ID: {$user->id}\n";
echo "Using Organization ID: {$org->id}\n\n";

// 2. Ensure user has a position in the organization
$orgUser = DB::table('organization_user')
    ->where('user_id', $user->id)
    ->where('organization_id', $org->id)
    ->first();

if (!$orgUser) {
    DB::table('organization_user')->insert([
        'user_id' => $user->id,
        'organization_id' => $org->id,
        'role' => 'President',
        'membership_status' => 'Active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    echo "Created organization_user relationship with role: President\n\n";
} else {
    echo "Existing organization_user relationship with role: {$orgUser->role}\n\n";
}

// 3. Clear cache to ensure cache miss
Cache::flush();
echo "Cache cleared\n\n";

// 4. Get permission service
$service = app(OrganizationPermissionService::class);

// 5. Test cache miss (first access)
echo "Test 1: Cache Miss (First Access)\n";
echo "Calling getPosition()...\n";
$position = $service->getPosition($user->id, $org->id);
echo "Position retrieved: $position\n";
echo "Check storage/logs/performance.log for cache_hit: false\n\n";

// 6. Test cache hit (second access)
echo "Test 2: Cache Hit (Second Access)\n";
echo "Calling getPosition() again...\n";
$position = $service->getPosition($user->id, $org->id);
echo "Position retrieved: $position\n";
echo "Check storage/logs/performance.log for cache_hit: true\n\n";

// 7. Test authorization check with duration logging
echo "Test 3: Authorization Check with Duration\n";
echo "Calling hasAccess() for 'announcements' module...\n";
$hasAccess = $service->hasAccess($user->id, $org->id, 'announcements', 'full');
echo "Access granted: " . ($hasAccess ? 'Yes' : 'No') . "\n";
echo "Check storage/logs/performance.log for authorization check with duration_ms\n\n";

// 8. Test denied authorization
echo "Test 4: Denied Authorization Check\n";
echo "Calling hasAccess() for 'payments' module with Auditor role...\n";

// Change role to Auditor for this test
DB::table('organization_user')
    ->where('user_id', $user->id)
    ->where('organization_id', $org->id)
    ->update(['role' => 'Auditor']);

// Clear cache to pick up new role
$service->invalidateCache($user->id, $org->id);

$hasAccess = $service->hasAccess($user->id, $org->id, 'payments', 'full');
echo "Access granted: " . ($hasAccess ? 'Yes' : 'No') . "\n";
echo "Check storage/logs/performance.log for authorization check with granted: false\n\n";

echo "=== Test Complete ===\n";
echo "Review the logs at: storage/logs/performance.log\n";
echo "\nExpected log entries:\n";
echo "1. Permission cache with cache_hit: false (Test 1)\n";
echo "2. Permission cache with cache_hit: true (Test 2)\n";
echo "3. Authorization check with granted: true and duration_ms (Test 3)\n";
echo "4. Authorization check with granted: false and duration_ms (Test 4)\n";
