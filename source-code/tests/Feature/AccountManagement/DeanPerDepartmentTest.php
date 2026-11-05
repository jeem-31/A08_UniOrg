<?php

namespace Tests\Feature\AccountManagement;

use App\Models\Department;
use App\Models\Event;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DeanPerDepartmentTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function two_deans_in_different_departments_can_both_be_active(): void
    {
        $this->seedDepartments();

        $first = User::factory()->create(['role' => 'super_admin', 'is_active' => true, 'department_id' => 1]);
        $second = User::factory()->create(['role' => 'super_admin', 'is_active' => true, 'department_id' => 2]);

        $this->assertDatabaseHas('users', ['id' => $first->id, 'role' => 'super_admin', 'is_active' => true]);
        $this->assertDatabaseHas('users', ['id' => $second->id, 'role' => 'super_admin', 'is_active' => true]);
    }

    /** @test */
    public function a_department_cannot_have_two_active_deans(): void
    {
        $this->seedDepartments();

        User::factory()->create(['role' => 'super_admin', 'is_active' => true, 'department_id' => 1]);

        try {
            User::factory()->create(['role' => 'super_admin', 'is_active' => true, 'department_id' => 1]);
            $this->fail('A second active Dean for the same department should be rejected.');
        } catch (\Illuminate\Database\QueryException $exception) {
            $this->assertSame(1, User::where('role', 'super_admin')->where('is_active', true)->count());
        }
    }

    /** @test */
    public function creating_a_dean_without_a_department_is_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'osas_admin', 'is_active' => true]);

        $response = $this->actingAs($admin)->post('/osas/accounts', [
            'first_name' => 'New',
            'last_name' => 'Dean',
            'email' => 'dean.without.college@example.com',
            'role' => 'super_admin',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertSessionHasErrors('department_id');
        $this->assertDatabaseMissing('users', ['email' => 'dean.without.college@example.com']);
    }

    /** @test */
    public function creating_a_dean_for_a_college_with_an_active_dean_is_rejected(): void
    {
        $this->seedDepartments();
        $admin = User::factory()->create(['role' => 'osas_admin', 'is_active' => true]);
        User::factory()->create(['role' => 'super_admin', 'is_active' => true, 'department_id' => 1]);

        $response = $this->actingAs($admin)->post('/osas/accounts', [
            'first_name' => 'Second',
            'last_name' => 'Dean',
            'email' => 'second.dean@example.com',
            'role' => 'super_admin',
            'department_id' => 1,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertSessionHasErrors('department_id');
        $this->assertDatabaseMissing('users', ['email' => 'second.dean@example.com']);
    }

    /** @test */
    public function creating_a_dean_for_a_free_college_succeeds(): void
    {
        $this->seedDepartments();
        $admin = User::factory()->create(['role' => 'osas_admin', 'is_active' => true]);

        $response = $this->actingAs($admin)->post('/osas/accounts', [
            'first_name' => 'Fresh',
            'last_name' => 'Dean',
            'email' => 'fresh.dean@example.com',
            'role' => 'super_admin',
            'department_id' => 1,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('users', [
            'email' => 'fresh.dean@example.com',
            'role' => 'super_admin',
            'department_id' => 1,
        ]);
    }

    /** @test */
    public function a_dean_cannot_approve_an_event_of_another_college(): void
    {
        $dean = User::factory()->create(['role' => 'super_admin', 'is_active' => true, 'department_id' => 1]);
        $otherCollege = Organization::factory()->create(['department_id' => 2, 'status' => 'Approved']);
        $event = Event::create([
            'organization_id' => $otherCollege->id,
            'user_id' => $dean->id,
            'title' => 'Other college event',
            'description' => 'An event from another college.',
            'start_date' => now()->addDay(),
            'end_date' => now()->addDay()->addHours(2),
            'venue' => 'Campus A',
            'approval_status' => 'pending',
        ]);

        $response = $this->actingAs($dean)->post("/dean/events/{$event->id}/approve");

        $response->assertStatus(403);
        $this->assertDatabaseHas('events', ['id' => $event->id, 'approval_status' => 'pending']);
    }

    /** @test */
    public function a_dean_can_approve_an_event_of_their_own_college(): void
    {
        $dean = User::factory()->create(['role' => 'super_admin', 'is_active' => true, 'department_id' => 1]);
        $ownCollege = Organization::factory()->create(['department_id' => 1, 'status' => 'Approved']);
        $event = Event::create([
            'organization_id' => $ownCollege->id,
            'user_id' => $dean->id,
            'title' => 'Own college event',
            'description' => 'An event from the Dean’s own college.',
            'start_date' => now()->addDay(),
            'end_date' => now()->addDay()->addHours(2),
            'venue' => 'Campus A',
            'approval_status' => 'pending',
        ]);

        $response = $this->actingAs($dean)->post("/dean/events/{$event->id}/approve");

        $response->assertStatus(302);
        $this->assertDatabaseHas('events', ['id' => $event->id, 'approval_status' => 'approved']);
    }

    /** @test */
    public function system_access_role_labels_match_the_hierarchy(): void
    {
        $this->assertSame('Dean', \App\Services\AccountManagement\SystemAccessRole::SuperAdmin->label());
        $this->assertSame('OSAS Admin', \App\Services\AccountManagement\SystemAccessRole::OsasAdmin->label());
        $this->assertSame('Admin', \App\Services\AccountManagement\SystemAccessRole::Admin->label());
        $this->assertSame('Student', \App\Services\AccountManagement\SystemAccessRole::Student->label());
    }

    private function seedDepartments(): void
    {
        // The departments migration already seeds the fixed college list; only
        // fall back to fixtures when the schema carries no rows.
        if (DB::table('departments')->count() >= 2) {
            return;
        }

        DB::table('departments')->insert([
            ['department_name' => 'College A', 'department_acronym' => 'CA'],
            ['department_name' => 'College B', 'department_acronym' => 'CB'],
        ]);
    }
}
