<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const SUPPORTED_FIELDS = [
        'accreditation_form',
        'letter_of_application',
        'constitution_bylaws',
        'adviser_commitment',
        'application_form',
        'certification_of_officers',
        'plan_of_activities',
        'certification_dean',
        'financial_report',
        'accomplishment_reports',
        'list_of_members',
    ];

    /** @var array<string, string|null> */
    private array $fingerprints = [];

    public function up(): void
    {
        $this->addNullableLifecycleColumns();
        $this->createAttemptLedger();
        $this->backfillAndReconcileLegacyRows();
        $this->validateBackfill();
        $this->makeLogicalSlotRequired();
        $this->addIndexes();
        $this->addSafeChecks();
    }

    public function down(): void
    {
        $this->dropSafeChecks();

        Schema::table('processed_documents', function (Blueprint $table) {
            $table->dropUnique('pd_apply_field_slot_uq');
            $table->dropIndex('pd_apply_status_idx');
            $table->dropIndex('pd_source_cycle_idx');
            $table->dropIndex('pd_completed_source_idx');
        });

        Schema::dropIfExists('document_processing_attempts');

        Schema::table('processed_documents', function (Blueprint $table) {
            $table->dropColumn([
                'document_slot',
                'storage_disk',
                'source_version',
                'source_fingerprint',
                'association_ready_at',
                'active_cycle_id',
                'cycle_kind',
                'cycle_attempt_count',
                'processing_started_at',
                'processing_duration_ms',
                'output_source_version',
                'failure_code',
                'summary_version',
                'summary_processed_at',
                'resummarization_status',
                'resummarization_cycle_id',
                'resummarization_error_message',
                'last_resummarization_changed',
            ]);
        });
    }

    private function addNullableLifecycleColumns(): void
    {
        Schema::table('processed_documents', function (Blueprint $table) {
            $table->string('document_slot')->nullable();
            $table->string('storage_disk')->nullable();
            $table->uuid('source_version')->nullable();
            $table->char('source_fingerprint', 64)->nullable();
            $table->timestamp('association_ready_at')->nullable();
            $table->uuid('active_cycle_id')->nullable();
            $table->string('cycle_kind', 20)->nullable();
            $table->unsignedTinyInteger('cycle_attempt_count')->default(0);
            $table->timestamp('processing_started_at')->nullable();
            $table->unsignedBigInteger('processing_duration_ms')->nullable();
            $table->uuid('output_source_version')->nullable();
            $table->string('failure_code', 64)->nullable();
            $table->unsignedInteger('summary_version')->default(1);
            $table->timestamp('summary_processed_at')->nullable();
            $table->string('resummarization_status', 20)->default('idle');
            $table->uuid('resummarization_cycle_id')->nullable();
            $table->text('resummarization_error_message')->nullable();
            $table->boolean('last_resummarization_changed')->nullable();
        });
    }

    private function createAttemptLedger(): void
    {
        Schema::create('document_processing_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('processed_document_id')
                ->constrained('processed_documents')
                ->cascadeOnDelete();
            $table->uuid('cycle_id');
            $table->uuid('source_version');
            $table->string('operation', 20);
            $table->unsignedTinyInteger('ordinal');
            $table->string('outcome', 20)->default('running');
            $table->string('failure_class', 64)->nullable();
            $table->string('provider_status_code', 64)->nullable();
            $table->text('sanitized_message')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->unsignedBigInteger('duration_ms')->nullable();
            $table->timestamps();

            $table->unique(
                ['processed_document_id', 'cycle_id', 'ordinal'],
                'dpa_document_cycle_ordinal_uq'
            );
            $table->index(['processed_document_id', 'outcome'], 'dpa_document_outcome_idx');
        });
    }

    private function backfillAndReconcileLegacyRows(): void
    {
        $applications = DB::table('apply_orgs')
            ->whereIn('id', DB::table('processed_documents')->select('apply_org_id'))
            ->get()
            ->keyBy('id');
        $now = now();
        $prepared = [];

        foreach (DB::table('processed_documents')->orderBy('id')->get() as $row) {
            $application = $applications->get($row->apply_org_id);
            $source = $this->legacySource($application, $row);
            $disk = $source['disk'];
            $path = (string) $row->file_path;
            $fingerprint = $source['matches']
                ? $this->fingerprint($disk, $path)
                : null;
            $sourceVersion = $this->uuidOrNew($source['source_version']);
            $validCompleted = $row->processing_status === 'completed'
                && trim((string) $row->summary) !== ''
                && $row->processed_at !== null
                && $fingerprint !== null
                && $source['matches'];

            $prepared[$row->id] = [
                'row' => $row,
                'slot' => $source['slot'],
                'disk' => $disk,
                'source_version' => $sourceVersion,
                'fingerprint' => $fingerprint,
                'matches' => $source['matches'],
                'valid_completed' => $validCompleted,
                'now' => $now,
            ];
        }

        $groups = [];
        foreach ($prepared as $id => $item) {
            $key = $item['row']->apply_org_id."\0".$item['row']->document_field."\0".$item['slot'];
            $groups[$key][] = $id;
        }

        foreach ($groups as $ids) {
            usort($ids, fn (int $left, int $right): int => $this->canonicalScore($prepared[$right]) <=> $this->canonicalScore($prepared[$left])
                    ?: $right <=> $left
            );

            $canonicalId = array_shift($ids);
            $this->publishBackfill($canonicalId, $prepared[$canonicalId]);

            if ($ids !== []) {
                DB::table('processed_documents')->whereIn('id', $ids)->delete();
            }
        }
    }

    /** @param array<string, mixed> $item */
    private function canonicalScore(array $item): int
    {
        $row = $item['row'];

        return ($item['matches'] ? 1_000_000 : 0)
            + ($item['fingerprint'] !== null ? 100_000 : 0)
            + ($item['valid_completed'] ? 10_000 : 0)
            + ($row->processing_status === 'completed' ? 1_000 : 0)
            + ($row->processed_at !== null ? 100 : 0);
    }

    /** @param array<string, mixed> $item */
    private function publishBackfill(int $id, array $item): void
    {
        $row = $item['row'];
        $update = [
            'document_slot' => $item['slot'],
            'storage_disk' => $item['disk'],
            'source_version' => $item['source_version'],
            'source_fingerprint' => $item['fingerprint'],
            'association_ready_at' => $item['matches'] && $item['fingerprint'] !== null ? $item['now'] : null,
            'active_cycle_id' => null,
            'cycle_kind' => null,
            'cycle_attempt_count' => 0,
            'processing_started_at' => null,
            'processing_duration_ms' => null,
            'output_source_version' => null,
            'failure_code' => null,
            'summary_version' => 1,
            'summary_processed_at' => null,
            'resummarization_status' => 'idle',
            'resummarization_cycle_id' => null,
            'resummarization_error_message' => null,
            'last_resummarization_changed' => null,
        ];

        if ($item['valid_completed']) {
            $update['processing_status'] = 'completed';
            $update['output_source_version'] = $item['source_version'];
            $update['summary_processed_at'] = $row->processed_at;
            $update['error_message'] = null;
        } elseif (! $item['matches']) {
            $this->failLegacyRow($update, 'legacy_association_unverifiable',
                'The legacy document association could not be verified. Upload the document again to process it.');
        } elseif ($item['fingerprint'] === null) {
            $this->failLegacyRow($update, 'legacy_source_unverifiable',
                'The stored document could not be verified. Retry processing or upload the document again.');
        } elseif ($row->processing_status === 'failed') {
            $this->failLegacyRow($update, 'legacy_processing_failed',
                'Legacy document processing did not complete. Retry processing to try again.');
        } elseif ($row->processing_status === 'completed') {
            $this->failLegacyRow($update, 'legacy_output_invalid',
                'The legacy AI output could not be verified. Retry processing to generate a new result.');
        } else {
            $update['processing_status'] = 'pending';
            $update['active_cycle_id'] = (string) Str::uuid();
            $update['cycle_kind'] = 'initial';
            $update['error_message'] = null;
            $update['processed_at'] = null;
        }

        DB::table('processed_documents')->where('id', $id)->update($update);
    }

    /** @param array<string, mixed> $update */
    private function failLegacyRow(array &$update, string $code, string $message): void
    {
        $update['processing_status'] = 'failed';
        $update['failure_code'] = $code;
        $update['error_message'] = $message;
        $update['processed_at'] = null;
    }

    /**
     * @return array{slot:string,disk:string,source_version:?string,matches:bool}
     */
    private function legacySource(?object $application, object $row): array
    {
        $default = (string) config('filesystems.default', 'local');
        $fallbackSlot = $row->document_field === 'list_of_members'
            ? 'member-'.substr(hash('sha256', (string) $row->file_path), 0, 32)
            : 'primary';
        $fallback = [
            'slot' => $fallbackSlot,
            'disk' => $default,
            'source_version' => null,
            'matches' => false,
        ];

        if ($application === null || ! in_array($row->document_field, self::SUPPORTED_FIELDS, true)) {
            return $fallback;
        }

        $value = $application->{$row->document_field} ?? null;
        if ($row->document_field !== 'list_of_members') {
            $metadata = $this->metadata($value);
            if ($metadata === null || ! $this->samePath($metadata['path'], $row->file_path)) {
                return $fallback;
            }

            return [
                'slot' => 'primary',
                'disk' => $metadata['disk'] ?? $default,
                'source_version' => $metadata['source_version'] ?? null,
                'matches' => true,
            ];
        }

        $entries = is_string($value) ? json_decode($value, true) : $value;
        if (! is_array($entries)) {
            return $fallback;
        }
        if (isset($entries['path']) || isset($entries['storage_path'])) {
            $entries = [$entries];
        }

        foreach ($entries as $entry) {
            $metadata = $this->metadata($entry);
            if ($metadata === null || ! $this->samePath($metadata['path'], $row->file_path)) {
                continue;
            }

            return [
                'slot' => $metadata['slot']
                    ?? 'member-'.substr(hash('sha256', $metadata['path']), 0, 32),
                'disk' => $metadata['disk'] ?? $default,
                'source_version' => $metadata['source_version'] ?? null,
                'matches' => true,
            ];
        }

        return $fallback;
    }

    /** @return array{path:string,disk?:string,slot?:string,source_version?:string}|null */
    private function metadata(mixed $value): ?array
    {
        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return null;
            }
            $decoded = str_starts_with($trimmed, '{') ? json_decode($trimmed, true) : null;
            $value = is_array($decoded) ? $decoded : ['path' => $trimmed];
        }
        if (! is_array($value)) {
            return null;
        }

        $path = $this->firstString($value, ['path', 'storage_path', 'file_path']);
        if ($path === null) {
            return null;
        }

        return array_filter([
            'path' => $path,
            'disk' => $this->firstString($value, ['storage_disk', 'disk', 'storageDisk']),
            'slot' => $this->firstString($value, ['slot', 'document_slot']),
            'source_version' => $this->firstString($value, ['source_version', 'sourceVersion']),
        ], fn (mixed $item): bool => $item !== null);
    }

    /** @param array<string, mixed> $values @param list<string> $keys */
    private function firstString(array $values, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (is_string($values[$key] ?? null) && trim($values[$key]) !== '') {
                return trim($values[$key]);
            }
        }

        return null;
    }

    private function samePath(string $left, string $right): bool
    {
        return ltrim(str_replace('\\', '/', trim($left)), '/')
            === ltrim(str_replace('\\', '/', trim($right)), '/');
    }

    private function uuidOrNew(?string $candidate): string
    {
        return $candidate !== null && Str::isUuid($candidate)
            ? $candidate
            : (string) Str::uuid();
    }

    private function fingerprint(string $disk, string $path): ?string
    {
        $cacheKey = $disk."\0".$path;
        if (array_key_exists($cacheKey, $this->fingerprints)) {
            return $this->fingerprints[$cacheKey];
        }

        $stream = null;
        try {
            $stream = Storage::disk($disk)->readStream($path);
            if (! is_resource($stream) && str_starts_with($path, 'storage/')) {
                $stream = Storage::disk($disk)->readStream(substr($path, 8));
            }
            if (! is_resource($stream)) {
                return $this->fingerprints[$cacheKey] = null;
            }

            $context = hash_init('sha256');
            hash_update_stream($context, $stream);

            return $this->fingerprints[$cacheKey] = hash_final($context);
        } catch (Throwable) {
            return $this->fingerprints[$cacheKey] = null;
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    private function validateBackfill(): void
    {
        $invalidSlots = DB::table('processed_documents')
            ->whereNull('document_slot')
            ->orWhere('document_slot', '')
            ->exists();
        $duplicates = DB::table('processed_documents')
            ->select('apply_org_id', 'document_field', 'document_slot')
            ->groupBy('apply_org_id', 'document_field', 'document_slot')
            ->havingRaw('COUNT(*) > 1')
            ->exists();
        $invalidCompleted = DB::table('processed_documents')
            ->where('processing_status', 'completed')
            ->where(function ($query) {
                $query->whereNull('processed_at')
                    ->orWhereNull('source_version')
                    ->orWhereNull('source_fingerprint')
                    ->orWhereNull('output_source_version');
            })
            ->exists();

        if ($invalidSlots || $duplicates || $invalidCompleted) {
            throw new RuntimeException('Document analysis backfill validation failed; constraints were not enabled.');
        }
    }

    private function makeLogicalSlotRequired(): void
    {
        Schema::table('processed_documents', function (Blueprint $table) {
            $table->string('document_slot')->default('primary')->nullable(false)->change();
        });
    }

    private function addIndexes(): void
    {
        Schema::table('processed_documents', function (Blueprint $table) {
            $table->unique(
                ['apply_org_id', 'document_field', 'document_slot'],
                'pd_apply_field_slot_uq'
            );
            $table->index(['apply_org_id', 'processing_status'], 'pd_apply_status_idx');
            $table->index(['source_version', 'active_cycle_id'], 'pd_source_cycle_idx');
            $table->index(
                ['processing_status', 'output_source_version'],
                'pd_completed_source_idx'
            );
        });
    }

    private function addSafeChecks(): void
    {
        if (! in_array(DB::getDriverName(), ['pgsql', 'mysql'], true)) {
            return;
        }

        $checks = [
            'pd_cycle_kind_check' => "cycle_kind IS NULL OR cycle_kind IN ('initial', 'retry')",
            'pd_cycle_attempt_count_check' => 'cycle_attempt_count BETWEEN 0 AND 3',
            'pd_resummarization_status_check' => "resummarization_status IN ('idle', 'processing', 'failed')",
            'pd_fingerprint_check' => 'source_fingerprint IS NULL OR CHAR_LENGTH(source_fingerprint) = 64',
            'pd_completion_check' => "processing_status <> 'completed' OR (processed_at IS NOT NULL AND source_version IS NOT NULL AND source_fingerprint IS NOT NULL AND output_source_version = source_version)",
        ];
        $attemptChecks = [
            'dpa_operation_check' => "operation IN ('initial', 'retry', 'resummarize')",
            'dpa_outcome_check' => "outcome IN ('running', 'succeeded', 'failed', 'stale')",
            'dpa_ordinal_check' => 'ordinal BETWEEN 1 AND 3',
            'dpa_duration_check' => 'duration_ms IS NULL OR duration_ms >= 0',
        ];

        foreach ($checks as $name => $expression) {
            DB::statement("ALTER TABLE processed_documents ADD CONSTRAINT {$name} CHECK ({$expression})");
        }
        foreach ($attemptChecks as $name => $expression) {
            DB::statement("ALTER TABLE document_processing_attempts ADD CONSTRAINT {$name} CHECK ({$expression})");
        }
    }

    private function dropSafeChecks(): void
    {
        $driver = DB::getDriverName();
        if (! in_array($driver, ['pgsql', 'mysql'], true)) {
            return;
        }

        $tables = [
            'processed_documents' => [
                'pd_cycle_kind_check',
                'pd_cycle_attempt_count_check',
                'pd_resummarization_status_check',
                'pd_fingerprint_check',
                'pd_completion_check',
            ],
            'document_processing_attempts' => [
                'dpa_operation_check',
                'dpa_outcome_check',
                'dpa_ordinal_check',
                'dpa_duration_check',
            ],
        ];

        foreach ($tables as $table => $constraints) {
            foreach ($constraints as $constraint) {
                if ($driver === 'pgsql') {
                    DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$constraint}");
                } else {
                    DB::statement("ALTER TABLE {$table} DROP CHECK {$constraint}");
                }
            }
        }
    }
};
