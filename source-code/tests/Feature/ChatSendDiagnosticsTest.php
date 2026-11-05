<?php

namespace Tests\Feature;

use App\Models\AdminChat;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ChatSendDiagnosticsTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private User $orgAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();

        $this->orgAdmin = User::factory()->create(['role' => 'admin']);
        $this->orgAdmin->organizations()->attach($this->organization->id, [
            'role' => 'President',
            'membership_status' => 'Active',
        ]);
    }

    private function actingAsOrgAdmin()
    {
        return $this->actingAs($this->orgAdmin)
            ->withSession(['current_organization_id' => $this->organization->id]);
    }

    /** Real PNG bytes so MIME sniffing behaves like a browser upload. */
    private function realPngFile(): UploadedFile
    {
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='
        );
        $path = tempnam(sys_get_temp_dir(), 'chatpng').'.png';
        file_put_contents($path, $png);

        return new UploadedFile($path, 'screenshot.png', 'image/png', null, true);
    }

    /** Real PDF bytes. */
    private function realPdfFile(): UploadedFile
    {
        $pdf = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF";
        $path = tempnam(sys_get_temp_dir(), 'chatpdf').'.pdf';
        file_put_contents($path, $pdf);

        return new UploadedFile($path, 'report.pdf', 'application/pdf', null, true);
    }

    public function test_text_only_send_works(): void
    {
        $response = $this->actingAsOrgAdmin()->postJson(route('admin.chat.send'), [
            'message' => 'Hello OSAS',
        ]);

        $response->assertOk()->assertJson(['success' => true]);
    }

    public function test_real_png_upload_works(): void
    {
        $response = $this->actingAsOrgAdmin()->post(
            route('admin.chat.send'),
            ['message' => 'Here is a real png', 'attachment' => $this->realPngFile()],
            ['Accept' => 'application/json']
        );

        $response->assertOk()->assertJson(['success' => true]);
        $this->assertDatabaseHas('admin_chats', ['message' => 'Here is a real png']);
    }

    public function test_real_pdf_upload_works(): void
    {
        $response = $this->actingAsOrgAdmin()->post(
            route('admin.chat.send'),
            ['message' => 'Here is a real pdf', 'attachment' => $this->realPdfFile()],
            ['Accept' => 'application/json']
        );

        $response->assertOk()->assertJson(['success' => true]);
        $this->assertDatabaseHas('admin_chats', ['message' => 'Here is a real pdf']);
    }

    public function test_upload_without_accept_header_still_works(): void
    {
        // Browser fetch sets Accept: application/json but simulate a plain form too.
        $response = $this->actingAsOrgAdmin()->post(route('admin.chat.send'), [
            'message' => 'No accept header',
            'attachment' => $this->realPngFile(),
        ]);

        $response->assertOk()->assertJson(['success' => true]);
    }

    public function test_multipart_with_csrf_header_like_browser(): void
    {
        $response = $this->actingAsOrgAdmin()->post(
            route('admin.chat.send'),
            ['message' => 'Browser style', 'attachment' => $this->realPdfFile()],
            [
                'X-CSRF-TOKEN' => csrf_token(),
                'X-Requested-With' => 'XMLHttpRequest',
                'Accept' => 'application/json',
            ]
        );

        $response->assertOk()->assertJson(['success' => true]);
    }

    public function test_string_attachment_is_rejected_with_clear_error(): void
    {
        // Some clients send the attachment field as a plain string (e.g. a
        // filename from a form without multipart enctype). This must never
        // crash the file-type checks - it should fail with a clear message.
        $response = $this->actingAsOrgAdmin()->postJson(route('admin.chat.send'), [
            'message' => 'With string attachment',
            'attachment' => 'not-a-real-file.png',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('attachment');
        $this->assertDatabaseMissing('admin_chats', ['message' => 'With string attachment']);
    }

    public function test_file_just_under_20mb_is_accepted(): void
    {
        $response = $this->actingAsOrgAdmin()->post(
            route('admin.chat.send'),
            [
                'message' => 'Under the limit',
                'attachment' => UploadedFile::fake()->create('big.pdf', 20479, 'application/pdf'),
            ],
            ['Accept' => 'application/json']
        );

        $response->assertOk()->assertJson(['success' => true]);
    }

    public function test_file_over_20mb_is_rejected(): void
    {
        $response = $this->actingAsOrgAdmin()->post(
            route('admin.chat.send'),
            [
                'message' => 'Over the limit',
                'attachment' => UploadedFile::fake()->create('huge.pdf', 20481, 'application/pdf'),
            ],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(422);
        $this->assertDatabaseMissing('admin_chats', ['message' => 'Over the limit']);
    }
}
