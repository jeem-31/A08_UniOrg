<?php

namespace Tests\Feature;

use App\Models\AdminChat;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StudentChatAttachmentTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private User $student;

    private User $orgAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();

        $this->student = User::factory()->create(['role' => 'student']);
        $this->student->organizations()->attach($this->organization->id, [
            'role' => 'Member',
            'membership_status' => 'Active',
        ]);

        $this->orgAdmin = User::factory()->create(['role' => 'admin']);
        $this->orgAdmin->organizations()->attach($this->organization->id, [
            'role' => 'President',
            'membership_status' => 'Active',
        ]);

        \App\Models\OrganizationSettings::create([
            'organization_id' => $this->organization->id,
            'has_chatbot' => true,
        ]);
    }

    private function actingAsStudent()
    {
        return $this->actingAs($this->student)
            ->withSession(['current_organization_id' => $this->organization->id]);
    }

    private function actingAsOrgAdmin()
    {
        return $this->actingAs($this->orgAdmin)
            ->withSession(['current_organization_id' => $this->organization->id]);
    }

    public function test_student_can_send_message_with_attachment(): void
    {
        Storage::fake(config('filesystems.default'));

        $response = $this->actingAsStudent()->post(
            route('chatbot.send'),
            [
                'message' => 'Here is my document',
                'attachment' => UploadedFile::fake()->image('report.png', 100, 100),
            ],
            ['Accept' => 'application/json']
        );

        $response->assertOk()
            ->assertJsonPath('message.message', 'Here is my document')
            ->assertJsonPath('message.is_image', true)
            ->assertJsonPath('message.file_type', 'png')
            ->assertJsonPath('message.attachment_name', 'report.png');

        $chat = AdminChat::latest('id')->first();
        $this->assertSame('student', $chat->channel);
        $this->assertSame($this->student->id, $chat->user_id);
        $this->assertSame('student', $chat->sender_type);
        $this->assertNotNull($chat->attachment_path);
        $this->assertTrue(Storage::disk(config('filesystems.default'))->exists($chat->attachment_path));

        $response->assertJsonPath('message.attachment_url', route('chatbot.attachment', $chat->id));
    }

    public function test_student_can_send_image_only_without_text(): void
    {
        Storage::fake(config('filesystems.default'));

        $response = $this->actingAsStudent()->post(
            route('chatbot.send'),
            [
                'attachment' => UploadedFile::fake()->image('photo.png', 100, 100),
            ],
            ['Accept' => 'application/json']
        );

        $response->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseHas('admin_chats', [
            'channel' => 'student',
            'user_id' => $this->student->id,
            'message' => '',
            'sender_type' => 'student',
        ]);
        $this->assertNotNull(AdminChat::latest('id')->first()->attachment_path);
    }

    public function test_admin_can_send_attachment_to_student(): void
    {
        Storage::fake(config('filesystems.default'));

        $response = $this->actingAsOrgAdmin()->post(
            route('admin.chatbot.send'),
            [
                'user_id' => $this->student->id,
                'message' => 'See attached file',
                'attachment' => UploadedFile::fake()->create('notes.pdf', 1024, 'application/pdf'),
            ],
            ['Accept' => 'application/json']
        );

        $response->assertOk()
            ->assertJsonPath('message.message', 'See attached file')
            ->assertJsonPath('message.file_type', 'pdf')
            ->assertJsonPath('message.attachment_name', 'notes.pdf');

        $chat = AdminChat::latest('id')->first();
        $this->assertSame('student', $chat->channel);
        $this->assertSame($this->student->id, $chat->user_id);
        $this->assertSame('admin', $chat->sender_type);
        $this->assertNotNull($chat->attachment_path);
        $this->assertTrue(Storage::disk(config('filesystems.default'))->exists($chat->attachment_path));
    }

    public function test_student_attachment_shows_in_get_messages(): void
    {
        Storage::fake(config('filesystems.default'));

        $chat = AdminChat::create([
            'channel' => 'student',
            'organization_id' => $this->organization->id,
            'user_id' => $this->student->id,
            'admin_id' => $this->orgAdmin->id,
            'message' => 'File sent',
            'sender_type' => 'admin',
            'is_read' => false,
            'attachment_path' => 'chat/file.pdf',
            'attachment_name' => 'file.pdf',
        ]);

        $response = $this->actingAsStudent()->getJson(route('chatbot.messages'));

        $response->assertOk();

        $message = collect($response->json('messages'))
            ->firstWhere('id', $chat->id);

        $this->assertNotNull($message);
        $this->assertSame('file.pdf', $message['attachment_name']);
        $this->assertSame('pdf', $message['file_type']);
        $this->assertSame(route('chatbot.attachment', $chat->id), $message['attachment_url']);
    }

    public function test_student_attachment_appears_in_admin_payload(): void
    {
        Storage::fake(config('filesystems.default'));

        $chat = AdminChat::create([
            'channel' => 'student',
            'organization_id' => $this->organization->id,
            'user_id' => $this->student->id,
            'admin_id' => $this->orgAdmin->id,
            'message' => 'Proof of payment',
            'sender_type' => 'student',
            'is_read' => false,
            'attachment_path' => 'chat/proof.png',
            'attachment_name' => 'proof.png',
        ]);

        $response = $this->actingAsOrgAdmin()->getJson(route('admin.chatbot.students'));

        $response->assertOk();

        $contact = collect($response->json('students'))
            ->firstWhere('id', $this->student->id);

        $this->assertNotNull($contact);
        $message = collect($contact['messages'])->firstWhere('id', $chat->id);
        $this->assertNotNull($message);
        $this->assertSame('proof.png', $message['attachment_name']);
        $this->assertSame('png', $message['file_type']);
    }

    public function test_student_can_download_own_attachment(): void
    {
        Storage::fake(config('filesystems.default'));

        Storage::disk(config('filesystems.default'))->put('chat/file.png', 'png-bytes');

        $chat = AdminChat::create([
            'channel' => 'student',
            'organization_id' => $this->organization->id,
            'user_id' => $this->student->id,
            'admin_id' => $this->orgAdmin->id,
            'message' => '',
            'sender_type' => 'admin',
            'is_read' => false,
            'attachment_path' => 'chat/file.png',
            'attachment_name' => 'file.png',
        ]);

        $response = $this->actingAsStudent()->get(route('chatbot.attachment', $chat->id));

        $response->assertOk();
    }

    public function test_student_cannot_download_another_students_attachment(): void
    {
        $otherStudent = User::factory()->create(['role' => 'student']);
        $otherStudent->organizations()->attach($this->organization->id, [
            'role' => 'Member',
            'membership_status' => 'Active',
        ]);

        $chat = AdminChat::create([
            'channel' => 'student',
            'organization_id' => $this->organization->id,
            'user_id' => $otherStudent->id,
            'admin_id' => $this->orgAdmin->id,
            'message' => '',
            'sender_type' => 'admin',
            'is_read' => false,
            'attachment_path' => 'chat/other.png',
            'attachment_name' => 'other.png',
        ]);

        $response = $this->actingAsStudent()->get(route('chatbot.attachment', $chat->id));

        $response->assertForbidden();
    }

    public function test_student_video_attachments_are_rejected(): void
    {
        Storage::fake(config('filesystems.default'));

        $response = $this->actingAsStudent()->post(
            route('chatbot.send'),
            [
                'attachment' => UploadedFile::fake()->create('movie.mp4', 1024, 'video/mp4'),
            ],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(422);
        $this->assertDatabaseMissing('admin_chats', [
            'user_id' => $this->student->id,
        ]);
    }
}
