<?php

namespace Tests\Feature;

use App\Models\AdminChat;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OsasChatTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private User $orgAdmin;

    private User $osasAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();

        $this->orgAdmin = User::factory()->create(['role' => 'admin']);
        $this->orgAdmin->organizations()->attach($this->organization->id, [
            'role' => 'President',
            'membership_status' => 'Active',
        ]);

        $this->osasAdmin = User::factory()->create(['role' => 'osas_admin']);
    }

    /** Org-side helpers */
    private function actingAsOrgAdmin()
    {
        return $this->actingAs($this->orgAdmin)
            ->withSession(['current_organization_id' => $this->organization->id]);
    }

    private function actingAsOsas()
    {
        return $this->actingAs($this->osasAdmin);
    }

    /** Test helpers */
    private function chat(string $message, bool $isFromOsas): AdminChat
    {
        return AdminChat::allOrganizations()->create([
            'channel' => 'osas',
            'organization_id' => $this->organization->id,
            'user_id' => $isFromOsas ? $this->osasAdmin->id : $this->orgAdmin->id,
            'message' => $message,
            'sender_type' => $isFromOsas ? 'osas' : 'org',
            'is_read' => false,
        ]);
    }

    public function test_chat_routes_are_registered(): void
    {
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('admin.chat.index'));
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('admin.chat.send'));
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('admin.chat.messages'));
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('admin.chat.history'));
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('admin.chat.attachment'));

        $this->assertTrue(\Illuminate\Support\Facades\Route::has('osas.chat.orgs'));
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('osas.chat.unread-count'));
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('osas.chat.send'));
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('osas.chat.messages'));
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('osas.chat.history'));
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('osas.chat.attachment'));
    }

    public function test_chat_page_requires_selected_organization(): void
    {
        $this->actingAs($this->orgAdmin)
            ->getJson(route('admin.chat.messages') . '?organization_id=999&last_id=0')
            ->assertStatus(422);
    }

    public function test_org_send_message_creates_chat_and_notifies_osas(): void
    {
        Notification::fake();

        $response = $this->actingAsOrgAdmin()->postJson(route('admin.chat.send'), [
            'message' => 'Hello OSAS, we need help.',
        ]);

        $response->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('admin_chats', [
            'channel' => 'osas',
            'organization_id' => $this->organization->id,
            'user_id' => $this->orgAdmin->id,
            'message' => 'Hello OSAS, we need help.',
            'sender_type' => 'org',
        ]);

        Notification::assertSentTo(
            [$this->osasAdmin],
            \App\Notifications\NewOsasChatNotification::class
        );
    }

    public function test_osas_send_message_creates_chat(): void
    {
        $response = $this->actingAsOsas()->postJson(route('osas.chat.send'), [
            'message' => 'Hello organization!',
            'organization_ids' => [$this->organization->id],
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('admin_chats', [
            'channel' => 'osas',
            'organization_id' => $this->organization->id,
            'user_id' => $this->osasAdmin->id,
            'message' => 'Hello organization!',
            'sender_type' => 'osas',
        ]);
    }

    public function test_send_message_requires_message_or_attachment(): void
    {
        $response = $this->actingAsOrgAdmin()->postJson(route('admin.chat.send'), [
            'message' => '',
        ]);

        $response->assertStatus(422);
    }

    public function test_get_messages_returns_only_new_messages(): void
    {
        $old = $this->chat('Old message', false);
        $this->chat('Another old message', true);

        $response = $this->actingAsOsas()->getJson(
            route('osas.chat.messages') . '?organization_id=' . $this->organization->id . '&last_id=' . $old->id
        );

        $response->assertOk()
            ->assertJsonCount(1, 'messages')
            ->assertJsonPath('messages.0.message', 'Another old message');
    }

    public function test_get_messages_marks_incoming_as_read(): void
    {
        $msg = $this->chat('From OSAS', true);
        $this->assertFalse((bool) $msg->fresh()->is_read);

        $this->actingAsOrgAdmin()->getJson(
            route('admin.chat.messages') . '?organization_id=' . $this->organization->id . '&last_id=0'
        );

        $this->assertTrue((bool) $msg->fresh()->is_read);
    }

    public function test_org_floating_chat_marks_osas_messages_as_read(): void
    {
        \App\Models\OrganizationSettings::create([
            'organization_id' => $this->organization->id,
            'has_chatbot' => true,
        ]);

        $msg = $this->chat('From OSAS', true);
        $this->assertFalse((bool) $msg->fresh()->is_read);

        $this->actingAsOrgAdmin()->get('/admin/chatbot/messages/osas');

        $this->assertTrue((bool) $msg->fresh()->is_read);
    }

    public function test_students_payload_bundles_osas_thread_messages(): void
    {
        \App\Models\OrganizationSettings::create([
            'organization_id' => $this->organization->id,
            'has_chatbot' => true,
        ]);

        for ($i = 0; $i < 120; $i++) {
            $this->chat("Message {$i}", false);
        }

        $response = $this->actingAsOrgAdmin()->getJson('/admin/chatbot/students');

        $response->assertOk();

        $osasContact = collect($response->json('students'))
            ->first(fn ($student) => ($student['channel'] ?? null) === 'osas');

        $this->assertNotNull($osasContact);
        // Only the newest 50 are bundled; older messages load on demand.
        $this->assertCount(50, $osasContact['messages']);
        $this->assertTrue($osasContact['has_more']);
        $this->assertNotNull($osasContact['oldest_id']);
    }

    public function test_osas_thread_endpoint_returns_latest_50_messages(): void
    {
        \App\Models\OrganizationSettings::create([
            'organization_id' => $this->organization->id,
            'has_chatbot' => true,
        ]);

        for ($i = 0; $i < 120; $i++) {
            $this->chat("Message {$i}", false);
        }

        $response = $this->actingAsOrgAdmin()->getJson('/admin/chatbot/messages/osas');

        $response->assertOk()
            ->assertJsonCount(50, 'messages')
            ->assertJsonPath('has_more', true);

        // The newest message (Message 119) must be included in the batch.
        $ids = collect($response->json('messages'))->pluck('id')->all();
        $newest = AdminChat::allOrganizations()->latest('id')->first();
        $this->assertContains($newest->id, $ids);
    }

    public function test_orgs_payload_bundles_latest_50_messages_with_has_more(): void
    {
        for ($i = 0; $i < 120; $i++) {
            $this->chat("Message {$i}", false);
        }

        $response = $this->actingAsOsas()->getJson(route('osas.chat.orgs'));

        $response->assertOk();

        $org = collect($response->json('organizations'))
            ->firstWhere('id', $this->organization->id);

        $this->assertCount(50, $org['messages']);
        $this->assertTrue($org['has_more']);
        $this->assertNotNull($org['oldest_id']);
    }

    public function test_orgs_payload_last_message_shows_attachment_when_latest_is_attachment_only(): void
    {
        AdminChat::allOrganizations()->create([
            'channel' => 'osas',
            'organization_id' => $this->organization->id,
            'user_id' => $this->orgAdmin->id,
            'message' => '',
            'sender_type' => 'org',
            'is_read' => false,
            'attachment_path' => 'chat/attachment-only.png',
            'attachment_name' => 'attachment-only.png',
        ]);

        $response = $this->actingAsOsas()->getJson(route('osas.chat.orgs'));

        $response->assertOk();

        $org = collect($response->json('organizations'))
            ->firstWhere('id', $this->organization->id);

        $this->assertSame('[Attachment]', $org['last_message']);
    }

    public function test_history_returns_older_batch_with_has_more_flag(): void
    {
        $messages = [];
        for ($i = 0; $i < 120; $i++) {
            $messages[] = $this->chat("Message {$i}", false);
        }

        // 120 messages => ids 1..120. The initial page loads the newest 50 (ids 71..120),
        // so the oldest loaded message is id 71 (index 70).
        $oldestLoaded = $messages[70];

        $response = $this->actingAsOrgAdmin()->getJson(
            route('admin.chat.history') . '?organization_id=' . $this->organization->id . '&before_id=' . $oldestLoaded->id
        );

        $response->assertOk()
            ->assertJsonCount(50, 'messages')
            ->assertJsonPath('has_more', true);

        // Batch is ascending (oldest first), so messages.0.id is the new oldest cursor.
        $second = $this->actingAsOrgAdmin()->getJson(
            route('admin.chat.history') . '?organization_id=' . $this->organization->id . '&before_id=' . $response->json('messages.0.id')
        );

        $second->assertOk()
            ->assertJsonCount(20, 'messages')
            ->assertJsonPath('has_more', false);
    }

    public function test_send_message_with_attachment_stores_file(): void
    {
        Storage::fake(config('filesystems.default'));

        $response = $this->actingAsOrgAdmin()->post(
            route('admin.chat.send'),
            [
                'message' => 'Here is the file',
                'attachment' => UploadedFile::fake()->image('report.png', 100, 100),
            ],
            ['Accept' => 'application/json']
        );

        $response->assertOk()
            ->assertJsonPath('message.is_image', true)
            ->assertJsonPath('message.file_type', 'png')
            ->assertJsonPath('message.attachment_name', 'report.png');

        $chat = AdminChat::allOrganizations()->latest('id')->first();
        $this->assertNotNull($chat->attachment_path);
        $this->assertSame('report.png', $chat->attachment_name);
        $this->assertTrue(Storage::disk(config('filesystems.default'))->exists($chat->attachment_path));

        $response->assertJsonPath('message.attachment_url', route('admin.chat.attachment', $chat->id));
    }

    public function test_org_can_send_image_only_without_text(): void
    {
        Storage::fake(config('filesystems.default'));

        $response = $this->actingAsOrgAdmin()->post(
            route('admin.chat.send'),
            [
                'attachment' => UploadedFile::fake()->image('photo.png', 100, 100),
            ],
            ['Accept' => 'application/json']
        );

        $response->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseHas('admin_chats', [
            'channel' => 'osas',
            'organization_id' => $this->organization->id,
            'message' => '',
            'sender_type' => 'org',
        ]);
        $this->assertNotNull(AdminChat::allOrganizations()->latest('id')->first()->attachment_path);
    }

    public function test_org_can_send_image_and_text_together(): void
    {
        Storage::fake(config('filesystems.default'));

        $response = $this->actingAsOrgAdmin()->post(
            route('admin.chat.send'),
            [
                'message' => 'Look at this photo!',
                'attachment' => UploadedFile::fake()->image('photo.png', 100, 100),
            ],
            ['Accept' => 'application/json']
        );

        $response->assertOk()
            ->assertJsonPath('message.message', 'Look at this photo!')
            ->assertJsonPath('message.is_image', true);

        $chat = AdminChat::allOrganizations()->latest('id')->first();
        $this->assertSame('Look at this photo!', $chat->message);
        $this->assertNotNull($chat->attachment_path);
    }

    public function test_osas_can_send_image_only_without_text(): void
    {
        Storage::fake(config('filesystems.default'));

        $response = $this->actingAsOsas()->post(
            route('osas.chat.send'),
            [
                'organization_ids' => [$this->organization->id],
                'attachment' => UploadedFile::fake()->image('notice.png', 100, 100),
            ],
            ['Accept' => 'application/json']
        );

        $response->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseHas('admin_chats', [
            'channel' => 'osas',
            'organization_id' => $this->organization->id,
            'message' => '',
            'sender_type' => 'osas',
        ]);
    }

    public function test_osas_can_send_image_and_text_together(): void
    {
        Storage::fake(config('filesystems.default'));

        $response = $this->actingAsOsas()->post(
            route('osas.chat.send'),
            [
                'message' => 'Important announcement',
                'organization_ids' => [$this->organization->id],
                'attachment' => UploadedFile::fake()->image('notice.png', 100, 100),
            ],
            ['Accept' => 'application/json']
        );

        $response->assertOk()
            ->assertJsonPath('message.message', 'Important announcement')
            ->assertJsonPath('message.is_image', true);

        $this->assertDatabaseHas('admin_chats', [
            'channel' => 'osas',
            'organization_id' => $this->organization->id,
            'message' => 'Important announcement',
            'sender_type' => 'osas',
        ]);
    }

    public function test_video_attachments_are_rejected(): void
    {
        $response = $this->actingAsOrgAdmin()->post(
            route('admin.chat.send'),
            [
                'message' => 'Check this video',
                'attachment' => UploadedFile::fake()->create('clip.mp4', 100, 'video/mp4'),
            ],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(422);
        $this->assertDatabaseMissing('admin_chats', [
            'message' => 'Check this video',
        ]);
    }

    public function test_attachment_download_route_serves_file(): void
    {
        Storage::fake(config('filesystems.default'));

        $chat = AdminChat::allOrganizations()->create([
            'channel' => 'osas',
            'organization_id' => $this->organization->id,
            'user_id' => $this->orgAdmin->id,
            'message' => 'Look at this',
            'sender_type' => 'org',
            'is_read' => false,
            'attachment_path' => UploadedFile::fake()->create('doc.pdf', 5)->store('chat', config('filesystems.default')),
            'attachment_name' => 'original-doc.pdf',
        ]);

        $response = $this->actingAsOsas()->get(route('osas.chat.attachment', $chat->id));

        $response->assertOk();
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition') ?? '');
        $this->assertStringContainsString('original-doc.pdf', $response->headers->get('Content-Disposition') ?? '');
    }

    public function test_admin_unread_count_includes_osas_messages(): void
    {
        \App\Models\OrganizationSettings::create([
            'organization_id' => $this->organization->id,
            'has_chatbot' => true,
        ]);

        $this->chat('Unread from OSAS', true);
        $this->chat('Read from OSAS', true);
        AdminChat::allOrganizations()
            ->where('message', 'Read from OSAS')
            ->update(['is_read' => true]);

        $response = $this->actingAsOrgAdmin()->getJson('/admin/chat/unread-count');

        $response->assertOk()
            ->assertJsonPath('count', 1);
    }
}
