<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use App\Notifications\AccountStatusChangedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SettingsAccountsTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $organization;

    protected User $adviser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create([
            'name' => 'Test Organization',
        ]);

        $this->adviser = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);
        $this->adviser->organizations()->attach($this->organization->id, [
            'role' => 'Adviser',
            'membership_status' => 'Active',
        ]);

        session(['current_organization_id' => $this->organization->id]);
    }

    private function president(): User
    {
        $president = User::factory()->create(['role' => 'admin']);
        $president->organizations()->attach($this->organization->id, [
            'role' => 'President',
            'membership_status' => 'Active',
        ]);

        return $president;
    }

    private function member(string $orgRole = 'Member'): User
    {
        $member = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $member->organizations()->attach($this->organization->id, [
            'role' => $orgRole,
            'membership_status' => 'Active',
        ]);

        return $member;
    }

    public function test_adviser_sees_manage_accounts_tab_and_account_list(): void
    {
        $member = $this->member();

        $response = $this->actingAs($this->adviser)->get(route('admin.settings.index'));

        $response->assertStatus(200);
        $response->assertSee('Manage Accounts', false);
        $response->assertViewHas('accounts');
        $this->assertTrue(
            $response->viewData('accounts')->contains('id', $member->id),
            'Adviser should see the org member in the accounts list.'
        );
    }

    public function test_president_does_not_see_manage_accounts_tab(): void
    {
        $president = $this->president();
        $this->member();

        $response = $this->actingAs($president)->get(route('admin.settings.index'));

        $response->assertStatus(200);
        $response->assertDontSee('activeTab=\'accounts\'', false);
        $response->assertDontSee('x-data="accountsPanel(', false);
        $this->assertTrue(
            $response->viewData('accounts')->isEmpty(),
            'Presidents must not receive the manage accounts data.'
        );
    }

    public function test_adviser_can_deactivate_a_member(): void
    {
        Notification::fake();
        $member = $this->member();

        $response = $this->actingAs($this->adviser)
            ->postJson(route('admin.settings.accounts.toggle'), [
                'user_id' => $member->id,
                'is_active' => false,
            ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $member->refresh();
        $this->assertFalse((bool) $member->is_active);
        $this->assertSame('Inactive', $member->organizations()->first()->pivot->membership_status);

        Notification::assertSentTo($member, AccountStatusChangedNotification::class);
    }

    public function test_adviser_can_reactivate_a_member(): void
    {
        Notification::fake();
        $member = $this->member();
        $member->update(['is_active' => false]);
        $member->organizations()->updateExistingPivot($this->organization->id, [
            'membership_status' => 'Inactive',
        ]);

        $response = $this->actingAs($this->adviser)
            ->postJson(route('admin.settings.accounts.toggle'), [
                'user_id' => $member->id,
                'is_active' => true,
            ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $member->refresh();
        $this->assertTrue((bool) $member->is_active);
        $this->assertSame('Active', $member->organizations()->first()->pivot->membership_status);

        Notification::assertSentTo($member, AccountStatusChangedNotification::class);
    }

    public function test_non_adviser_cannot_toggle_account_status(): void
    {
        $president = $this->president();
        $member = $this->member();

        $response = $this->actingAs($president)
            ->postJson(route('admin.settings.accounts.toggle'), [
                'user_id' => $member->id,
                'is_active' => false,
            ]);

        $response->assertStatus(403);

        $member->refresh();
        $this->assertTrue((bool) $member->is_active);
        $this->assertSame('Active', $member->organizations()->first()->pivot->membership_status);
    }

    public function test_adviser_cannot_deactivate_self(): void
    {
        $response = $this->actingAs($this->adviser)
            ->postJson(route('admin.settings.accounts.toggle'), [
                'user_id' => $this->adviser->id,
                'is_active' => false,
            ]);

        $response->assertStatus(422);
        $this->assertTrue((bool) $this->adviser->refresh()->is_active);
    }

    public function test_adviser_cannot_deactivate_protected_roles(): void
    {
        foreach (['Adviser', 'Organization Adviser', 'Dean', 'COMELEC'] as $protectedRole) {
            $protected = User::factory()->create(['role' => 'admin', 'is_active' => true]);
            $protected->organizations()->attach($this->organization->id, [
                'role' => $protectedRole,
                'membership_status' => 'Active',
            ]);

            $response = $this->actingAs($this->adviser)
                ->postJson(route('admin.settings.accounts.toggle'), [
                    'user_id' => $protected->id,
                    'is_active' => false,
                ]);

            $response->assertStatus(422);
            $this->assertTrue((bool) $protected->refresh()->is_active);
        }
    }

    public function test_account_names_are_displayed_in_title_case(): void
    {
        $member = User::factory()->create([
            'role' => 'student',
            'is_active' => true,
            'first_name' => 'JUAN CARLOS',
            'middle_name' => 'DELA',
            'last_name' => 'CRUZ',
        ]);
        $member->organizations()->attach($this->organization->id, [
            'role' => 'Secretary',
            'membership_status' => 'Active',
        ]);

        $response = $this->actingAs($this->adviser)->get(route('admin.settings.index'));

        $response->assertStatus(200);
        $displayed = $response->viewData('accounts')->firstWhere('id', $member->id);
        $this->assertSame('Juan Carlos Dela Cruz', $displayed->full_name);
    }

    public function test_adviser_cannot_deactivate_member_of_another_organization(): void
    {
        $otherOrg = Organization::factory()->create(['name' => 'Other Organization']);
        $otherMember = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $otherMember->organizations()->attach($otherOrg->id, [
            'role' => 'Member',
            'membership_status' => 'Active',
        ]);

        $response = $this->actingAs($this->adviser)
            ->postJson(route('admin.settings.accounts.toggle'), [
                'user_id' => $otherMember->id,
                'is_active' => false,
            ]);

        $response->assertStatus(404);
        $this->assertTrue((bool) $otherMember->refresh()->is_active);
    }
}
