<?php

namespace Tests\Feature;

use App\Models\AdminChat;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\TestCase;

class OsasFloatingChatSmokeTest extends TestCase
{
    use RefreshDatabase;

    private User $osas;
    private User $orgAdmin;
    private Organization $orgA;
    private Organization $orgB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->osas = User::factory()->create(['role' => 'osas_admin']);
        $this->orgAdmin = User::factory()->create(['role' => 'admin']);
        $this->orgA = Organization::factory()->create(['name' => 'Test Org Alpha', 'org_acronym' => 'TOA']);
        $this->orgB = Organization::factory()->create(['name' => 'Test Org Beta', 'org_acronym' => 'TOB']);

        $this->orgAdmin->organizations()->attach($this->orgA->id, [
            'role' => 'President',
            'membership_status' => 'Active',
        ]);

        \App\Models\OrganizationSettings::create([
            'organization_id' => $this->orgA->id,
            'has_chatbot' => true,
        ]);
    }

    public function test_osas_can_list_orgs_with_unread(): void
    {
        AdminChat::create([
            'channel' => 'osas',
            'organization_id' => $this->orgA->id,
            'user_id' => $this->orgAdmin->id,
            'message' => 'Help needed',
            'sender_type' => 'org',
            'is_read' => \DB::raw('CAST(0 AS BOOLEAN)'),
        ]);

        $response = $this->actingAs($this->osas)->getJson(route('osas.chat.orgs'));
        $response->assertOk();

        $orgs = collect($response->json('organizations'));
        $this->assertTrue($orgs->contains('id', $this->orgA->id));
        $alpha = $orgs->firstWhere('id', $this->orgA->id);
        $this->assertEquals(1, $alpha['unread_count']);
        $this->assertEquals('Help needed', $alpha['last_message']);
        $this->assertNull($alpha['logo_url']);
    }

    public function test_orgs_include_logo_url_when_settings_have_logo(): void
    {
        \App\Models\OrganizationSettings::where('organization_id', $this->orgA->id)->update([
            'logo_url' => 'logos/logo_test.png',
        ]);

        $response = $this->actingAs($this->osas)->getJson(route('osas.chat.orgs'));
        $response->assertOk();

        $alpha = collect($response->json('organizations'))->firstWhere('id', $this->orgA->id);
        $this->assertNotNull($alpha['logo_url']);
        $this->assertStringContainsString('logos/logo_test.png', $alpha['logo_url']);
    }

    public function test_osas_unread_count_aggregates(): void
    {
        foreach ([$this->orgA, $this->orgB] as $org) {
            AdminChat::create([
                'channel' => 'osas',
                'organization_id' => $org->id,
                'user_id' => $this->orgAdmin->id,
                'message' => 'Hi',
                'sender_type' => 'org',
                'is_read' => \DB::raw('CAST(0 AS BOOLEAN)'),
            ]);
        }

        $this->actingAs($this->osas)
            ->getJson(route('osas.chat.unread-count'))
            ->assertOk()
            ->assertJson(['count' => 2]);
    }

    public function test_osas_can_broadcast_to_multiple_orgs(): void
    {
        $response = $this->actingAs($this->osas)->postJson(route('osas.chat.send'), [
            'message' => 'Announcement for all',
            'organization_ids' => [$this->orgA->id, $this->orgB->id],
        ]);

        $response->assertOk()->assertJson(['success' => true]);

        $this->assertEquals(2, AdminChat::where('channel', 'osas')->where('sender_type', 'osas')->count());
        foreach ([$this->orgA, $this->orgB] as $org) {
            $this->assertEquals(1, AdminChat::where('channel', 'osas')->where('organization_id', $org->id)->count());
        }
    }

    public function test_osas_send_accepts_multipart_form_data_like_the_widget(): void
    {
        $response = $this->actingAs($this->osas)->post(route('osas.chat.send'), [
            '_token' => csrf_token(),
            'message' => 'Widget multipart send',
            'organization_ids' => [$this->orgA->id],
        ]);

        $response->assertOk()->assertJson(['success' => true]);

        $this->assertEquals(1, AdminChat::where('channel', 'osas')
            ->where('organization_id', $this->orgA->id)
            ->where('message', 'Widget multipart send')
            ->count());
    }

    public function test_org_admin_can_send_and_see_own_thread(): void
    {
        session(['current_organization_id' => $this->orgA->id]);

        $response = $this->actingAs($this->orgAdmin)->postJson(route('admin.chat.send'), [
            'message' => 'From the org side',
        ]);
        $response->assertOk()->assertJson(['success' => true]);

        // OSAS replies
        AdminChat::create([
            'channel' => 'osas',
            'organization_id' => $this->orgA->id,
            'user_id' => $this->osas->id,
            'message' => 'Got it',
            'sender_type' => 'osas',
            'is_read' => \DB::raw('CAST(0 AS BOOLEAN)'),
        ]);

        $thread = $this->actingAs($this->orgAdmin)
            ->getJson(route('admin.chat.messages') . '?organization_id=' . $this->orgA->id . '&last_id=0');
        $thread->assertOk();
        $this->assertEquals(2, count($thread->json('messages')));
    }

    public function test_org_admin_cannot_read_other_org_thread(): void
    {
        session(['current_organization_id' => $this->orgA->id]);

        $this->actingAs($this->orgAdmin)
            ->getJson(route('admin.chat.messages') . '?organization_id=' . $this->orgB->id . '&last_id=0')
            ->assertForbidden();
    }

    public function test_org_admin_cannot_send_using_foreign_org_id(): void
    {
        session(['current_organization_id' => $this->orgA->id]);

        $this->actingAs($this->orgAdmin)->postJson(route('osas.chat.send'), [
            'message' => 'sneaky',
            'organization_ids' => [$this->orgB->id],
        ]);

        // Organization side always uses its own session org
        $this->assertEquals(0, AdminChat::where('organization_id', $this->orgB->id)->count());
    }

    public function test_admin_badge_includes_osas_unread(): void
    {
        session(['current_organization_id' => $this->orgA->id]);

        AdminChat::create([
            'channel' => 'osas',
            'organization_id' => $this->orgA->id,
            'user_id' => $this->osas->id,
            'message' => 'From OSAS',
            'sender_type' => 'osas',
            'is_read' => \DB::raw('CAST(0 AS BOOLEAN)'),
        ]);

        $this->actingAs($this->orgAdmin)
            ->getJson('/admin/chat/unread-count')
            ->assertOk()
            ->assertJson(['count' => 1]);
    }

    public function test_get_students_includes_osas_contact(): void
    {
        session(['current_organization_id' => $this->orgA->id]);

        AdminChat::create([
            'channel' => 'osas',
            'organization_id' => $this->orgA->id,
            'user_id' => $this->osas->id,
            'message' => 'Hello org',
            'sender_type' => 'osas',
            'is_read' => \DB::raw('CAST(0 AS BOOLEAN)'),
        ]);

        $response = $this->actingAs($this->orgAdmin)->getJson('/admin/chatbot/students');
        $response->assertOk();

        $contact = collect($response->json('students'))->firstWhere('id', 'osas');
        $this->assertNotNull($contact);
        $this->assertEquals('osas', $contact['channel']);
        $this->assertEquals('osas_admin', $contact['role']);
        $this->assertEquals(1, $contact['unread_count']);
        $this->assertEquals('Hello org', $contact['last_message']);
        $this->assertCount(1, $contact['messages']);
    }

    public function test_osas_thread_messages_endpoint_marks_read(): void
    {
        session(['current_organization_id' => $this->orgA->id]);

        AdminChat::create([
            'channel' => 'osas',
            'organization_id' => $this->orgA->id,
            'user_id' => $this->osas->id,
            'message' => 'Unread from OSAS',
            'sender_type' => 'osas',
            'is_read' => \DB::raw('CAST(0 AS BOOLEAN)'),
        ]);

        $response = $this->actingAs($this->orgAdmin)->getJson('/admin/chatbot/messages/osas');
        $response->assertOk();
        $this->assertCount(1, $response->json('messages'));

        $this->assertEquals(0, AdminChat::allOrganizations()->unreadFromOsas($this->orgA->id)->count());
    }

    public function test_org_admin_does_not_appear_in_students_list_after_osas_send(): void
    {
        session(['current_organization_id' => $this->orgA->id]);

        // Org admin sends to OSAS - creates a channel=osas row with their own user_id.
        $this->actingAs($this->orgAdmin)->postJson(route('admin.chat.send'), [
            'message' => 'From the org side',
        ])->assertOk();

        // OSAS replies to the org.
        AdminChat::create([
            'channel' => 'osas',
            'organization_id' => $this->orgA->id,
            'user_id' => $this->osas->id,
            'message' => 'Got it',
            'sender_type' => 'osas',
            'is_read' => \DB::raw('CAST(0 AS BOOLEAN)'),
        ]);

        $response = $this->actingAs($this->orgAdmin)->getJson('/admin/chatbot/students');
        $response->assertOk();

        $ids = collect($response->json('students'))->pluck('id')->all();
        // No self-contact, no OSAS admin user contact - only the synthetic 'osas' contact.
        $this->assertNotContains($this->orgAdmin->id, $ids);
        $this->assertNotContains($this->osas->id, $ids);
        $this->assertContains('osas', $ids);
    }

    public function test_osas_admin_user_never_appears_as_student_contact(): void
    {
        session(['current_organization_id' => $this->orgA->id]);

        // Simulate the old bug: a student-channel message involving the OSAS admin.
        AdminChat::create([
            'channel' => 'student',
            'organization_id' => $this->orgA->id,
            'user_id' => $this->osas->id,
            'admin_id' => $this->orgAdmin->id,
            'message' => 'Wrong contact test',
            'sender_type' => 'admin',
            'is_read' => \DB::raw('CAST(0 AS BOOLEAN)'),
        ]);

        $response = $this->actingAs($this->orgAdmin)->getJson('/admin/chatbot/students');
        $response->assertOk();

        $ids = collect($response->json('students'))->pluck('id')->all();
        $this->assertNotContains($this->osas->id, $ids);
    }
}
