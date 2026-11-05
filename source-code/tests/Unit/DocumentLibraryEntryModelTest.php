<?php

namespace Tests\Unit;

use App\Models\ApplyOrg;
use App\Models\DocumentLibraryEntry;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Tests\TestCase;

/** **Validates: Requirements 2.1, 2.8, 7.6, 8.5, 8.7, 8.8** */
class DocumentLibraryEntryModelTest extends TestCase
{
    public function test_projection_fields_cannot_be_mass_assigned(): void
    {
        $this->expectException(MassAssignmentException::class);

        new DocumentLibraryEntry([
            'apply_org_id' => 10,
            'document_field' => 'constitution_bylaws',
            'document_slot' => 'primary',
            'file_path' => 'private/constitution.pdf',
        ]);
    }

    public function test_default_serialization_omits_protected_source_identity(): void
    {
        $entry = (new DocumentLibraryEntry)->forceFill([
            'id' => 15,
            'apply_org_id' => 10,
            'document_field' => 'constitution_bylaws',
            'document_slot' => 'primary',
            'requirement_label' => 'Constitution and By-Laws',
            'original_filename' => 'constitution.pdf',
            'storage_disk' => 'r2',
            'file_path' => 'private/constitution.pdf',
            'source_version' => '68c1f9c0-65c2-4ab8-a66b-2b3329dd1aa7',
            'source_fingerprint' => str_repeat('a', 64),
        ]);

        $serialized = $entry->toArray();

        $this->assertSame(15, $serialized['id']);
        $this->assertSame('constitution.pdf', $serialized['original_filename']);
        $this->assertArrayNotHasKey('storage_disk', $serialized);
        $this->assertArrayNotHasKey('file_path', $serialized);
        $this->assertArrayNotHasKey('source_version', $serialized);
        $this->assertArrayNotHasKey('source_fingerprint', $serialized);
    }

    public function test_application_relationships_use_explicit_foreign_keys(): void
    {
        $entryRelation = (new DocumentLibraryEntry)->applyOrg();
        $entriesRelation = (new ApplyOrg)->documentLibraryEntries();
        $organizationRelation = (new ApplyOrg)->organization();

        $this->assertSame('apply_org_id', $entryRelation->getForeignKeyName());
        $this->assertSame('apply_org_id', $entriesRelation->getForeignKeyName());
        $this->assertSame('organization_id', $organizationRelation->getForeignKeyName());
    }
}
