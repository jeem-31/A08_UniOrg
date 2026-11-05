<?php

namespace Tests\Feature;

use App\Http\Controllers\Student\EdushareController;
use App\Models\EdushareItem;
use App\Models\Organization;
use App\Models\OrganizationSettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class EdushareUploadLimitsTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;
    private User $student;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        config(['filesystems.default' => 'local']);
        Storage::fake('local');

        $this->organization = Organization::factory()->create(['status' => 'Approved', 'has_system' => true]);
        OrganizationSettings::create(['organization_id' => $this->organization->id, 'has_edushare' => true]);
        $this->student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $this->admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->student->organizations()->attach($this->organization->id, ['role' => 'Member', 'membership_status' => 'Active']);
        $this->admin->organizations()->attach($this->organization->id, ['role' => 'President', 'membership_status' => 'Active']);
        $this->withSession(['current_organization_id' => $this->organization->id]);
    }

    public function test_student_upload_above_twenty_mb_is_rejected_without_storing_files(): void
    {
        $this->actingAs($this->student)->postJson('/edushare/upload', [
            'files' => [UploadedFile::fake()->create('large.pdf', 20481, 'application/pdf')],
        ])->assertUnprocessable()->assertJsonValidationErrors('files.0');

        $this->assertNothingWasUploaded();
    }

    public function test_an_oversized_batch_is_rejected_before_any_file_is_saved(): void
    {
        $this->actingAs($this->student)->postJson('/edushare/upload', [
            'files' => [
                UploadedFile::fake()->create('first.pdf', 12288, 'application/pdf'),
                UploadedFile::fake()->create('second.pdf', 9216, 'application/pdf'),
            ],
        ])->assertUnprocessable()->assertJsonValidationErrors('files');

        $this->assertNothingWasUploaded();
    }

    public function test_more_than_ten_files_are_rejected(): void
    {
        $files = array_map(static fn (int $number) => UploadedFile::fake()->create('file'.$number.'.pdf', 1, 'application/pdf'), range(1, 11));
        $this->actingAs($this->student)->postJson('/edushare/upload', ['files' => $files])
            ->assertUnprocessable()->assertJsonValidationErrors('files');

        $this->assertNothingWasUploaded();
    }

    public function test_ten_files_at_the_combined_size_limit_are_accepted(): void
    {
        $files = array_map(static fn (int $number) => UploadedFile::fake()->create('file'.$number.'.pdf', 2048, 'application/pdf'), range(1, 10));
        $this->actingAs($this->student)->postJson('/edushare/upload', ['files' => $files])
            ->assertOk()->assertJsonPath('success', true);

        self::assertSame(10, EdushareItem::count());
        self::assertCount(10, Storage::disk('local')->allFiles());
    }

    public function test_admin_upload_uses_the_same_size_limit(): void
    {
        $this->actingAs($this->admin)->postJson('/admin/edushare/upload', [
            'file' => UploadedFile::fake()->create('large.pdf', 20481, 'application/pdf'),
        ])->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->assertNothingWasUploaded();

        $this->postJson('/admin/edushare/upload', [
            'file' => UploadedFile::fake()->create('allowed.pdf', 20480, 'application/pdf'),
        ])->assertOk()->assertJsonPath('success', true);
        self::assertSame(1, EdushareItem::count());
    }

    public function test_rejected_replacement_does_not_delete_the_existing_file(): void
    {
        $this->actingAs($this->student);
        Storage::disk('local')->put('edushare/existing.pdf', 'existing file');
        $item = EdushareItem::create([
            'user_id' => $this->student->id,
            'organization_id' => $this->organization->id,
            'title' => 'Existing',
            'category' => 'documentation',
            'file_path' => 'edushare/existing.pdf',
            'file_type' => 'pdf',
        ]);
        $request = Request::create('/edushare/'.$item->id, 'PUT', [
            'title' => 'Replacement', 'category' => 'documentation',
        ], [], ['file' => UploadedFile::fake()->create('large.pdf', 20481, 'application/pdf')]);

        try {
            app(EdushareController::class)->update($request, $item);
            self::fail('An oversized replacement must be rejected.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('file', $exception->errors());
        }

        self::assertSame('edushare/existing.pdf', $item->fresh()->file_path);
        Storage::disk('local')->assertExists('edushare/existing.pdf');
    }

    private function assertNothingWasUploaded(): void
    {
        self::assertSame(0, EdushareItem::count());
        self::assertSame([], Storage::disk('local')->allFiles());
    }
}
