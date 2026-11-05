<?php

namespace Tests\Unit;

use App\Models\ApplyOrg;
use App\Models\DocumentLibraryEntry;
use App\Models\User;
use App\Policies\DocumentLibraryEntryPolicy;
use App\Services\DocumentLibrary\CurrentDocumentLibraryEntryLocator;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** **Validates: Requirements 8.1, 8.2, 8.3, 8.4, 8.10** */
final class DocumentLibraryEntryPolicyTest extends TestCase
{
    private DocumentLibraryEntryPolicy $policy;
    private CurrentDocumentLibraryEntryLocator $locator;
    private ApplyOrg $application;
    private DocumentLibraryEntry $entry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->beginIsolatedDatabaseSession();
        $this->createSchema();
        $this->policy = app(DocumentLibraryEntryPolicy::class);
        $this->locator = app(CurrentDocumentLibraryEntryLocator::class);
        $this->application = $this->application($this->source('\\documents\\current.pdf'));
        $this->entry = $this->entry($this->application, [
            'file_path' => '/documents/current.pdf',
            'source_fingerprint' => strtoupper(str_repeat('a', 64)),
        ]);
    }

    protected function tearDown(): void
    {
        $connection = DB::connection();

        try {
            if ($connection->getDriverName() === 'pgsql') {
                DB::statement('DROP TABLE IF EXISTS pg_temp.document_library_entries');
                DB::statement('DROP TABLE IF EXISTS pg_temp.apply_orgs');
            } else {
                Schema::dropIfExists('document_library_entries');
                Schema::dropIfExists('apply_orgs');
            }

            $connection->commit();
        } catch (\Throwable) {
            if ($connection->transactionLevel() > 0) {
                $connection->rollBack();
            }
        }

        parent::tearDown();
    }
    public function test_osas_admin_can_access_only_the_fresh_current_catalog_source(): void
    {
        $response = $this->policy->viewSource($this->user('osas_admin'), $this->entry, $this->application);

        self::assertTrue($response->allowed());
        $source = $this->locator->find($this->application, $this->entry);
        self::assertNotNull($source);
        self::assertSame('private', $source->storageDisk);
        self::assertSame('\\documents\\current.pdf', $source->storagePath);

        $wrongRole = $this->policy->viewSource($this->user('admin'), $this->entry, $this->application);
        $this->assertSafeDenial($wrongRole);
    }

    public function test_nested_ownership_catalog_field_slot_disk_and_path_are_all_required(): void
    {
        $otherApplication = $this->application($this->source('\\documents\\current.pdf'));
        $this->assertSafeDenial($this->policy->viewSource(
            $this->user('osas_admin'),
            $this->entry,
            $otherApplication,
        ));

        foreach ([
            ['document_field' => 'unsupported_document'],
            ['document_slot' => 'not-primary'],
            ['storage_disk' => 'public'],
            ['file_path' => 'documents/other.pdf'],
        ] as $change) {
            DB::table('document_library_entries')->where('id', $this->entry->id)->update($change);
            $this->assertSafeDenial($this->policy->viewSource(
                $this->user('osas_admin'),
                $this->entry,
                $this->application,
            ));
            DB::table('document_library_entries')->where('id', $this->entry->id)->update([
                'document_field' => 'application_form',
                'document_slot' => 'primary',
                'storage_disk' => 'private',
                'file_path' => '/documents/current.pdf',
            ]);
        }
    }

    public function test_every_available_source_version_and_fingerprint_must_match(): void
    {
        DB::table('document_library_entries')->where('id', $this->entry->id)
            ->update(['source_version' => 'different-version']);
        $this->assertSafeDenial($this->policy->viewSource(
            $this->user('osas_admin'), $this->entry, $this->application,
        ));

        DB::table('document_library_entries')->where('id', $this->entry->id)->update([
            'source_version' => 'source-v1',
            'source_fingerprint' => str_repeat('b', 64),
        ]);
        $this->assertSafeDenial($this->policy->viewSource(
            $this->user('osas_admin'), $this->entry, $this->application,
        ));
    }
    public function test_fresh_loads_deny_stale_unavailable_and_absent_targets_uniformly(): void
    {
        $expected = $this->denialSignature($this->policy->viewSource(
            $this->user('admin'), $this->entry, $this->application,
        ));

        DB::table('apply_orgs')->where('id', $this->application->id)
            ->update(['application_form' => $this->source('documents/replacement.pdf')]);
        $stale = $this->policy->viewSource(
            $this->user('osas_admin'), $this->entry, $this->application,
        );
        self::assertSame($expected, $this->denialSignature($stale));

        DB::table('apply_orgs')->where('id', $this->application->id)
            ->update(['application_form' => null]);
        $unavailable = $this->policy->viewSource(
            $this->user('osas_admin'), $this->entry, $this->application,
        );
        self::assertSame($expected, $this->denialSignature($unavailable));

        DB::table('document_library_entries')->where('id', $this->entry->id)->delete();
        $absent = $this->policy->viewSource(
            $this->user('osas_admin'), $this->entry, $this->application,
        );
        self::assertSame($expected, $this->denialSignature($absent));
    }

    private function application(?string $source): ApplyOrg
    {
        $application = (new ApplyOrg)->forceFill([
            'application_form' => $source,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $application->save();

        return $application;
    }

    /** @param array<string, mixed> $overrides */
    private function entry(ApplyOrg $application, array $overrides = []): DocumentLibraryEntry
    {
        $entry = (new DocumentLibraryEntry)->forceFill(array_merge([
            'apply_org_id' => $application->id,
            'document_field' => 'application_form',
            'document_slot' => 'primary',
            'requirement_label' => 'Application Form',
            'original_filename' => 'current.pdf',
            'storage_disk' => 'private',
            'file_path' => 'documents/current.pdf',
            'source_version' => 'source-v1',
            'source_fingerprint' => str_repeat('a', 64),
        ], $overrides));
        $entry->save();

        return $entry;
    }
    private function source(
        string $path,
        ?string $version = 'source-v1',
        ?string $fingerprint = null,
    ): string {
        return json_encode(array_filter([
            'path' => $path,
            'name' => 'current.pdf',
            'storage_disk' => 'private',
            'source_version' => $version,
            'source_fingerprint' => $fingerprint ?? str_repeat('a', 64),
        ], static fn (mixed $value): bool => $value !== null), JSON_THROW_ON_ERROR);
    }

    private function user(string $role): User
    {
        return (new User)->forceFill(['role' => $role]);
    }

    private function assertSafeDenial(Response $response): void
    {
        self::assertTrue($response->denied());
        self::assertSame(404, $response->status());
        self::assertSame('The requested document is unavailable.', $response->message());
        self::assertStringNotContainsString('documents/', $response->message());
        self::assertStringNotContainsString('private', $response->message());
    }

    /** @return array{bool, int|null, string|null} */
    private function denialSignature(Response $response): array
    {
        $this->assertSafeDenial($response);

        return [$response->denied(), $response->status(), $response->message()];
    }

    private function beginIsolatedDatabaseSession(): void
    {
        $connection = DB::connection();
        $connection->beginTransaction();

        if ($connection->getDriverName() === 'pgsql') {
            DB::statement('DROP TABLE IF EXISTS pg_temp.document_library_entries');
            DB::statement('DROP TABLE IF EXISTS pg_temp.apply_orgs');
        }
    }

    private function createSchema(): void
    {
        Schema::create('apply_orgs', function (Blueprint $table): void {
            $table->temporary();
            $table->id();
            $table->text('application_form')->nullable();
            $table->timestamps();
        });
        Schema::create('document_library_entries', function (Blueprint $table): void {
            $table->temporary();
            $table->id();
            $table->unsignedBigInteger('apply_org_id');
            $table->string('document_field');
            $table->string('document_slot');
            $table->string('requirement_label');
            $table->string('original_filename');
            $table->string('storage_disk');
            $table->string('file_path');
            $table->string('source_version')->nullable();
            $table->string('source_fingerprint', 64)->nullable();
            $table->timestamps();
        });
    }
}
