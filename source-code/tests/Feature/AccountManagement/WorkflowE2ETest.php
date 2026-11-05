<?php

namespace Tests\Feature\AccountManagement;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkflowE2ETest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function osas_admin_can_navigate_directory_and_view_details()
    {
        $admin = User::factory()->create(['role' => 'osas_admin', 'is_active' => true]);
        $target = User::factory()->create();

        $response = $this->actingAs($admin)->get('/osas/accounts');
        $response->assertStatus(200);
        $response->assertSee($target->first_name);

        $response = $this->actingAs($admin)->get("/osas/accounts/{$target->id}");
        $response->assertStatus(200);
        $response->assertSee($target->email);
    }

    /** @test */
    public function inactive_login_is_denied()
    {
        $admin = User::factory()->create(['role' => 'osas_admin', 'is_active' => false]);
        
        $response = $this->actingAs($admin)->get('/osas/accounts');
        $response->assertStatus(302);
    }

    /** @test */
    public function ordinary_student_cannot_access_osas_routes()
    {
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        
        $response = $this->actingAs($student)->get('/osas/accounts');
        $response->assertStatus(403);
    }

    /** @test */
    public function create_and_edit_account_workflows()
    {
        $admin = User::factory()->create(['role' => 'osas_admin', 'is_active' => true]);

        // Access create form
        $response = $this->actingAs($admin)->get('/osas/accounts/create');
        $response->assertStatus(200);

        // Submit create form
        $response = $this->post('/osas/accounts', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'role' => 'student',
            'password' => 'password123',
            'password_confirmation' => 'password123'
        ]);
        $response->assertRedirect();
        $this->assertDatabaseHas('users', ['email' => 'john.doe@example.com']);
        $newUser = User::where('email', 'john.doe@example.com')->first();

        // Access edit form
        $response = $this->get("/osas/accounts/{$newUser->id}/edit");
        $response->assertStatus(200);

        // Submit edit form with the management version the form displayed
        $response = $this->put("/osas/accounts/{$newUser->id}/profile", [
            'first_name' => 'Johnny',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'freshness' => $newUser->management_version
        ]);
        $response->assertRedirect();
        $this->assertDatabaseHas('users', ['first_name' => 'Johnny']);
    }

    /** @test */
    public function admin_can_deactivate_and_reactivate_an_account()
    {
        $admin = User::factory()->create(['role' => 'osas_admin', 'is_active' => true]);
        $target = User::factory()->create(['is_active' => true]);

        $response = $this->actingAs($admin)->put("/osas/accounts/{$target->id}/lifecycle", [
            'action' => 'deactivate',
            'freshness' => $target->management_version,
        ]);
        $response->assertRedirect();
        $this->assertDatabaseHas('users', ['id' => $target->id, 'is_active' => false]);

        $freshVersion = User::find($target->id)->management_version;

        $response = $this->actingAs($admin)->put("/osas/accounts/{$target->id}/lifecycle", [
            'action' => 'activate',
            'freshness' => $freshVersion,
        ]);
        $response->assertRedirect();
        $this->assertDatabaseHas('users', ['id' => $target->id, 'is_active' => true]);
    }

    /** @test */
    public function admin_can_change_an_account_role()
    {
        $admin = User::factory()->create(['role' => 'osas_admin', 'is_active' => true]);
        $target = User::factory()->create(['role' => 'student']);

        $response = $this->actingAs($admin)->put("/osas/accounts/{$target->id}/role", [
            'role' => 'admin',
            'freshness' => $target->management_version,
        ]);
        $response->assertRedirect();
        $this->assertDatabaseHas('users', ['id' => $target->id, 'role' => 'admin']);
    }

    /** @test */
    public function admin_can_permanently_delete_an_account_with_a_confirmation_token()
    {
        $admin = User::factory()->create(['role' => 'osas_admin', 'is_active' => true]);
        $target = User::factory()->create();

        // The show page issues a confirmation token bound to the account and version.
        $show = $this->actingAs($admin)->get("/osas/accounts/{$target->id}");
        $show->assertStatus(200);
        $confirmation = $this->confirmationTokenFrom($show->getContent());

        $response = $this->actingAs($admin)->delete("/osas/accounts/{$target->id}", [
            'confirmation' => $confirmation,
            'freshness' => $target->management_version,
        ]);
        $response->assertRedirect();
        $this->assertDatabaseMissing('users', ['id' => $target->id]);
    }

    /** @test */
    public function admin_can_appoint_a_dean_when_no_dean_exists()
    {
        $admin = User::factory()->create(['role' => 'osas_admin', 'is_active' => true]);
        $target = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->post("/osas/accounts/{$target->id}/appointments/dean", [
            'candidate_id' => $target->id,
            'freshness' => $target->management_version,
        ]);
        $response->assertRedirect();
        $this->assertDatabaseHas('users', ['id' => $target->id, 'role' => 'super_admin']);
    }

    /** @test */
    public function admin_can_replace_the_current_dean_with_a_confirmation_token()
    {
        $admin = User::factory()->create(['role' => 'osas_admin', 'is_active' => true]);
        $currentDean = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $target = User::factory()->create(['role' => 'admin']);

        // The dean replacement form binds a confirmation to the current Dean.
        $show = $this->actingAs($admin)->get("/osas/accounts/{$target->id}");
        $show->assertStatus(200);
        $tokens = $this->confirmationTokensFrom($show->getContent());
        $this->assertCount(2, $tokens); // delete token first, dean-replacement token second

        $response = $this->actingAs($admin)->post("/osas/accounts/{$target->id}/appointments/dean", [
            'candidate_id' => $target->id,
            'outcome' => 'reassign',
            'former_dean_role' => 'osas_admin',
            'confirmation' => $tokens[1],
            'freshness' => $target->management_version,
        ]);
        $response->assertRedirect();

        $this->assertDatabaseHas('users', ['id' => $target->id, 'role' => 'super_admin', 'is_active' => true]);
        $this->assertDatabaseHas('users', [
            'id' => $currentDean->id,
            'role' => 'osas_admin',
            'is_active' => false,
        ]);
    }

    private function confirmationTokenFrom(string $html): string
    {
        $tokens = $this->confirmationTokensFrom($html);

        return $tokens[0];
    }

    /** @return list<string> */
    private function confirmationTokensFrom(string $html): array
    {
        preg_match_all('/name="confirmation" value="([^"]+)"/', $html, $matches);

        return $matches[1];
    }
}
