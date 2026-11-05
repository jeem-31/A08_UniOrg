<?php

namespace Tests\Unit;

use App\Services\DocumentIntelligence\DocumentCardinality;
use App\Services\DocumentIntelligence\StoredDocumentDescriptor;
use App\Services\DocumentIntelligence\SupportedDocumentCatalog;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/** **Validates: Requirements 1.2, 3.1, 4.3, 4.7, 8.4, 9.1, 9.3** */
final class SupportedDocumentCatalogTest extends TestCase
{
    public function test_it_exposes_only_the_exact_supported_fields_labels_and_cardinalities(): void
    {
        $definitions = (new SupportedDocumentCatalog)->all();

        $actual = [];
        foreach ($definitions as $definition) {
            $actual[$definition->field] = [$definition->label, $definition->cardinality];
        }

        self::assertSame([
            'accreditation_form' => ['Accreditation Form', DocumentCardinality::Scalar],
            'letter_of_application' => ['Letter of Application', DocumentCardinality::Scalar],
            'constitution_bylaws' => ['Constitution and By-Laws', DocumentCardinality::Scalar],
            'adviser_commitment' => ['Adviser Commitment Form', DocumentCardinality::Scalar],
            'application_form' => ['Application Form', DocumentCardinality::Scalar],
            'certification_of_officers' => ['Certification of Officers', DocumentCardinality::Scalar],
            'plan_of_activities' => ['Plan of Activities', DocumentCardinality::Scalar],
            'certification_dean' => ['Dean Certification', DocumentCardinality::Scalar],
            'financial_report' => ['Financial Report', DocumentCardinality::Scalar],
            'accomplishment_reports' => ['Accomplishment Report', DocumentCardinality::Scalar],
            'list_of_members' => ['List of Members', DocumentCardinality::MultiFile],
        ], $actual);
    }

    public function test_scalar_paths_use_primary_slots_and_arbitrary_attributes_are_ignored(): void
    {
        $sources = (new SupportedDocumentCatalog)->decode([
            'id' => 42,
            'application_form' => 'system-requests/generated-form.pdf',
            'renewal_form' => 'system-requests/not-supported.pdf',
            'admin_notes' => 'system-requests/not-a-document.pdf',
        ], 'r2');

        self::assertCount(1, $sources);
        self::assertSame('application_form', $sources[0]->field);
        self::assertSame(SupportedDocumentCatalog::PRIMARY_SLOT, $sources[0]->slot);
        self::assertSame('generated-form.pdf', $sources[0]->originalFilename);
        self::assertSame('r2', $sources[0]->storageDisk);
    }

    public function test_legacy_membership_json_retains_names_and_has_repeatable_distinct_slots(): void
    {
        $attributes = ['list_of_members' => json_encode([
            ['path' => 'system-requests/a.pdf', 'name' => 'Members Alpha.pdf'],
            ['path' => 'system-requests/b.pdf', 'name' => 'Members Beta.pdf'],
        ], JSON_THROW_ON_ERROR)];
        $reordered = ['list_of_members' => json_encode(array_reverse(
            json_decode($attributes['list_of_members'], true, flags: JSON_THROW_ON_ERROR)
        ), JSON_THROW_ON_ERROR)];
        $catalog = new SupportedDocumentCatalog;

        $first = $catalog->decode($attributes, 'legacy-disk');
        $second = $catalog->decode($attributes, 'legacy-disk');
        $byPath = function (array $sources): array {
            $slots = array_column($sources, 'slot', 'storagePath');
            ksort($slots);

            return $slots;
        };

        self::assertSame(['Members Alpha.pdf', 'Members Beta.pdf'], array_column($first, 'originalFilename'));
        self::assertSame(array_column($first, 'slot'), array_column($second, 'slot'));
        self::assertSame($byPath($first), $byPath($catalog->decode($reordered, 'legacy-disk')));
        self::assertCount(2, array_unique(array_column($first, 'slot')));
        self::assertSame(['legacy-disk', 'legacy-disk'], array_column($first, 'storageDisk'));
    }

    public function test_scalar_metadata_decoding_is_stable_and_preserves_source_identity(): void
    {
        $attributes = ['constitution_bylaws' => json_encode([
            'path' => 'applications/opaque-source',
            'name' => 'Constitution 2026.pdf',
            'storage_disk' => 'protected',
            'source_version' => '018f3f64-8bc7-7c66-badd-123456789abc',
            'source_fingerprint' => str_repeat('b', 64),
        ], JSON_THROW_ON_ERROR)];
        $catalog = new SupportedDocumentCatalog;

        $first = $catalog->decode($attributes, 'fallback')[0];
        $second = $catalog->decode($attributes, 'another-fallback')[0];

        self::assertEquals($first, $second);
        self::assertSame(SupportedDocumentCatalog::PRIMARY_SLOT, $first->slot);
        self::assertSame('Constitution 2026.pdf', $first->originalFilename);
        self::assertSame('protected', $first->storageDisk);
        self::assertTrue($first->hasSourceIdentity());
    }

    public function test_new_metadata_retains_original_name_disk_slot_and_source_identity(): void
    {
        $source = (new SupportedDocumentCatalog)->decode([
            'list_of_members' => [[
                'path' => 'system-requests/opaque-object',
                'name' => 'Original Members.xlsx',
                'slot' => 'member-018f3f64',
                'storage_disk' => 'private-documents',
                'source_version' => '018f3f64-8bc7-7c66-badd-123456789abc',
                'source_fingerprint' => str_repeat('a', 64),
            ]],
        ], 'fallback')[0];

        self::assertSame('Original Members.xlsx', $source->originalFilename);
        self::assertSame('private-documents', $source->storageDisk);
        self::assertSame('member-018f3f64', $source->slot);
        self::assertTrue($source->hasSourceIdentity());

        $descriptor = $source->descriptorFor(99);
        self::assertSame(99, $descriptor->applyOrgId);
        self::assertSame($source->sourceVersion, $descriptor->sourceVersion);
        self::assertTrue((new ReflectionClass(StoredDocumentDescriptor::class))->isReadOnly());
    }

    public function test_unsupported_fields_are_rejected_by_direct_lookup(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new SupportedDocumentCatalog)->definition('renewal_form');
    }
}
