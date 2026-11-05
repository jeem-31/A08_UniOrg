<?php

namespace App\Services\DocumentIntelligence;

use App\Models\ApplyOrg;
use InvalidArgumentException;

final class SupportedDocumentCatalog
{
    public const PRIMARY_SLOT = 'primary';

    /** @var array<string, array{label: string, cardinality: DocumentCardinality}> */
    private const DEFINITIONS = [
        'accreditation_form' => ['label' => 'Accreditation Form', 'cardinality' => DocumentCardinality::Scalar],
        'letter_of_application' => ['label' => 'Letter of Application', 'cardinality' => DocumentCardinality::Scalar],
        'constitution_bylaws' => ['label' => 'Constitution and By-Laws', 'cardinality' => DocumentCardinality::Scalar],
        'adviser_commitment' => ['label' => 'Adviser Commitment Form', 'cardinality' => DocumentCardinality::Scalar],
        'application_form' => ['label' => 'Application Form', 'cardinality' => DocumentCardinality::Scalar],
        'certification_of_officers' => ['label' => 'Certification of Officers', 'cardinality' => DocumentCardinality::Scalar],
        'plan_of_activities' => ['label' => 'Plan of Activities', 'cardinality' => DocumentCardinality::Scalar],
        'certification_dean' => ['label' => 'Dean Certification', 'cardinality' => DocumentCardinality::Scalar],
        'financial_report' => ['label' => 'Financial Report', 'cardinality' => DocumentCardinality::Scalar],
        'accomplishment_reports' => ['label' => 'Accomplishment Report', 'cardinality' => DocumentCardinality::Scalar],
        'renewal_form' => ['label' => 'Renewal Form', 'cardinality' => DocumentCardinality::Scalar],
        'list_of_members' => ['label' => 'List of Members', 'cardinality' => DocumentCardinality::MultiFile],
        'other_files' => ['label' => 'Additional Documents', 'cardinality' => DocumentCardinality::MultiFile],
    ];

    /** @return list<string> */
    public function fields(): array
    {
        return array_keys(self::DEFINITIONS);
    }

    /** @return list<SupportedDocumentDefinition> */
    public function all(): array
    {
        return array_map(
            fn (string $field): SupportedDocumentDefinition => $this->definition($field),
            $this->fields(),
        );
    }

    public function supports(string $field): bool
    {
        return array_key_exists($field, self::DEFINITIONS);
    }

    public function definition(string $field): SupportedDocumentDefinition
    {
        if (! $this->supports($field)) {
            throw new InvalidArgumentException("Unsupported document field [{$field}].");
        }

        return new SupportedDocumentDefinition(
            $field,
            self::DEFINITIONS[$field]['label'],
            self::DEFINITIONS[$field]['cardinality'],
        );
    }

    /**
     * Decode only catalogued document attributes into immutable, read-only sources.
     *
     * @param  ApplyOrg|array<string, mixed>  $application
     * @return list<StoredDocumentSource>
     */
    public function sourcesFor(ApplyOrg|array $application, ?string $defaultStorageDisk = null): array
    {
        $defaultStorageDisk ??= storage_disk();
        $sources = [];

        foreach ($this->all() as $definition) {
            $value = $application instanceof ApplyOrg
                ? $application->getAttribute($definition->field)
                : ($application[$definition->field] ?? null);

            if ($definition->isMultiFile()) {
                array_push($sources, ...$this->decodeMultiFile($definition, $value, $defaultStorageDisk));

                continue;
            }

            $source = $this->decodeScalar($definition, $value, $defaultStorageDisk);
            if ($source !== null) {
                $sources[] = $source;
            }
        }

        return $sources;
    }

    /** @param ApplyOrg|array<string, mixed> $application */
    public function decode(ApplyOrg|array $application, ?string $defaultStorageDisk = null): array
    {
        return $this->sourcesFor($application, $defaultStorageDisk);
    }

    private function decodeScalar(
        SupportedDocumentDefinition $definition,
        mixed $value,
        string $defaultStorageDisk,
    ): ?StoredDocumentSource {
        $metadata = $this->metadata($value);

        return $metadata === null
            ? null
            : $this->source($definition, self::PRIMARY_SLOT, $metadata, $defaultStorageDisk);
    }

    /** @return list<StoredDocumentSource> */
    private function decodeMultiFile(
        SupportedDocumentDefinition $definition,
        mixed $value,
        string $defaultStorageDisk,
    ): array {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($value)) {
            return [];
        }

        if (isset($value['path']) || isset($value['storage_path'])) {
            $value = [$value];
        }

        $sources = [];
        foreach (array_values($value) as $entry) {
            $metadata = $this->metadata($entry);
            if ($metadata === null) {
                continue;
            }

            $slot = $this->firstString($metadata, ['slot', 'document_slot']);
            $slot ??= $this->legacyMembershipSlot((string) $metadata['path']);
            $sources[] = $this->source($definition, $slot, $metadata, $defaultStorageDisk);
        }

        return $sources;
    }

    /** @return array<string, mixed>|null */
    private function metadata(mixed $value): ?array
    {
        if (is_string($value)) {
            $value = trim($value);
            if ($value === '') {
                return null;
            }

            $decoded = str_starts_with($value, '{') ? json_decode($value, true) : null;
            $value = is_array($decoded) ? $decoded : ['path' => $value];
        }

        if (! is_array($value)) {
            return null;
        }

        $path = $this->firstString($value, ['path', 'storage_path', 'file_path']);
        if ($path === null) {
            return null;
        }

        $value['path'] = $path;

        return $value;
    }

    /** @param array<string, mixed> $metadata */
    private function source(
        SupportedDocumentDefinition $definition,
        string $slot,
        array $metadata,
        string $defaultStorageDisk,
    ): StoredDocumentSource {
        $path = (string) $metadata['path'];
        $originalFilename = $this->firstString($metadata, ['name', 'original_filename', 'originalFilename'])
            ?? $this->filenameFromPath($path);
        $storageDisk = $this->firstString($metadata, ['storage_disk', 'disk', 'storageDisk'])
            ?? $defaultStorageDisk;

        return new StoredDocumentSource(
            $definition->field,
            $slot,
            $definition->label,
            $originalFilename,
            $storageDisk,
            $path,
            $this->firstString($metadata, ['source_version', 'sourceVersion']),
            $this->firstString($metadata, ['source_fingerprint', 'sourceFingerprint', 'digest']),
        );
    }

    /** @param array<string, mixed> $values @param list<string> $keys */
    private function firstString(array $values, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $values[$key] ?? null;
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    private function filenameFromPath(string $path): string
    {
        $normalized = str_replace('\\', '/', $path);

        return rawurldecode(basename($normalized));
    }

    private function legacyMembershipSlot(string $path): string
    {
        return 'member-'.substr(hash('sha256', $path), 0, 32);
    }
}
