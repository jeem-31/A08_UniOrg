<?php

namespace Tests\Integration\AccountManagement;

use App\Models\User;
use App\Models\Organization;
use App\Models\OrganizationAdviser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use PDOException;
use Illuminate\Database\QueryException;

class PostgresConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function database_constraints_prevent_concurrent_same_slot_assignments()
    {
        $org = Organization::factory()->create();
        $user1 = User::factory()->create(['role' => 'faculty']);
        $user2 = User::factory()->create(['role' => 'faculty']);

        // Directly insert user1 into the senior adviser slot
        OrganizationAdviser::create([
            'organization_id' => $org->id,
            'user_id' => $user1->id,
            'level' => 'senior',
        ]);

        // Attempting to insert user2 into the same senior slot for the same org
        // This should trigger a unique constraint violation at the database level if the constraint exists
        $exceptionThrown = false;
        try {
            DB::table('organization_advisers')->insert([
                'organization_id' => $org->id,
                'user_id' => $user2->id,
                'level' => 'senior',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (QueryException $e) {
            $exceptionThrown = true;
            // E.g., SQLSTATE[23505]: Unique violation
        }

        $this->assertTrue($exceptionThrown, 'Expected a unique constraint violation when assigning two senior advisers to the same organization.');
    }

    /** @test */
    public function transaction_rolls_back_on_failure()
    {
        $admin = User::factory()->create(['role' => 'osas_admin', 'is_active' => true]);
        $target = User::factory()->create(['role' => 'student']);

        // Simulate a request with an invalid freshness to check if everything rolls back cleanly
        $countBefore = DB::table('account_management_audits')->count();

        $response = $this->actingAs($admin)->put("/osas/accounts/{$target->id}/profile", [
            'first_name' => 'Updated',
            'last_name' => 'User',
            'email' => 'updated@example.com',
            'freshness' => 9999 // Invalid freshness
        ]);

        $response->assertSessionHasErrors('freshness');
        
        $countAfter = DB::table('account_management_audits')->count();
        $this->assertEquals($countBefore, $countAfter, 'Failed transaction should not commit any audit rows.');
    }
}
