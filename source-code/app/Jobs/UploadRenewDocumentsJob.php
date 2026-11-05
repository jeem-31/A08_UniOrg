<?php

namespace App\Jobs;

use App\Mail\RenewOrgSubmitted;
use App\Models\ApplyOrg;
use App\Services\DocumentIntelligence\SupportedDocumentCatalog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UploadRenewDocumentsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $backoff = 10;

    public function __construct(
        public int $applyOrgId,
        public array $tmpPaths,
        public array $memberTmpFiles,
        public array $otherTmpFiles,
        public string $storageDisk,
        public array $validated,
        public int $organizationId,
    ) {
        if (config('queue.default') !== 'sync') {
            $this->onConnection('redis')->onQueue('default');
        }
    }

    public function handle(SupportedDocumentCatalog $catalog): void
    {
        $applyOrg = ApplyOrg::find($this->applyOrgId);
        if (! $applyOrg) {
            Log::error("[UploadRenewDocumentsJob] ApplyOrg #{$this->applyOrgId} not found");
            $this->cleanupTmp();
            return;
        }

        Log::info("[UploadRenewDocumentsJob] Starting R2 upload for Renew ApplyOrg #{$applyOrg->id} org {$this->organizationId}");

        $uploadedDocuments = [];
        $uploadedPaths = [];

        try {
            $storeToR2 = function (string $tmpPath, string $originalName, string $field, string $slot) use (&$uploadedDocuments) {
                $localDisk = Storage::disk('local');
                if (! $localDisk->exists($tmpPath)) {
                    throw new \RuntimeException("Tmp file missing: {$tmpPath}");
                }
                $fullTmpPath = storage_path('app/' . $tmpPath);
                $fingerprint = hash_file('sha256', $fullTmpPath);
                if (! is_string($fingerprint)) {
                    throw new \RuntimeException("Unable to fingerprint {$tmpPath}");
                }
                $sourceVersion = (string) Str::uuid();
                $bytes = $localDisk->get($tmpPath);
                $r2Path = 'system-requests/' . Str::uuid() . '-' . $originalName;
                $r2Disk = Storage::disk($this->storageDisk);
                $r2Disk->put($r2Path, $bytes);

                $doc = [
                    'field' => $field,
                    'slot' => $slot,
                    'name' => $originalName,
                    'disk' => $this->storageDisk,
                    'path' => $r2Path,
                    'source_version' => $sourceVersion,
                    'source_fingerprint' => strtolower($fingerprint),
                ];
                $uploadedDocuments[] = $doc;
                return $doc;
            };

            foreach ($this->tmpPaths as $field => $info) {
                if (! isset($info['tmp'])) continue;
                $doc = $storeToR2($info['tmp'], $info['name'], $field, SupportedDocumentCatalog::PRIMARY_SLOT);
                $uploadedPaths[$field] = $doc['path'];
            }

            if (! empty($this->memberTmpFiles)) {
                $memberFiles = [];
                foreach ($this->memberTmpFiles as $entry) {
                    $doc = $storeToR2($entry['tmp'], $entry['name'], 'list_of_members', 'member-' . Str::uuid());
                    $memberFiles[] = [
                        'path' => $doc['path'],
                        'name' => $doc['name'],
                        'slot' => $doc['slot'],
                        'storage_disk' => $doc['disk'],
                        'source_version' => $doc['source_version'],
                        'source_fingerprint' => $doc['source_fingerprint'],
                    ];
                    // $uploadedDocuments already pushed inside $storeToR2 closure — don't double-push
                }
                $uploadedPaths['list_of_members'] = json_encode($memberFiles, JSON_THROW_ON_ERROR);
            }

            if (! empty($this->otherTmpFiles)) {
                $otherFiles = [];
                foreach ($this->otherTmpFiles as $entry) {
                    $doc = $storeToR2($entry['tmp'], $entry['name'], 'other_files', 'other-' . Str::uuid());
                    $otherFiles[] = [
                        'path' => $doc['path'],
                        'name' => $doc['name'],
                        'slot' => $doc['slot'],
                        'storage_disk' => $doc['disk'],
                        'source_version' => $doc['source_version'],
                        'source_fingerprint' => $doc['source_fingerprint'],
                    ];
                    // Already pushed via closure — avoid duplicate
                }
                $uploadedPaths['other_files'] = json_encode($otherFiles, JSON_THROW_ON_ERROR);
            }

            $commands = [];
            DB::transaction(function () use ($applyOrg, $uploadedPaths, $uploadedDocuments, $catalog, &$commands) {
                $applyOrg->update($uploadedPaths);
                // Defensive: skip fields not in catalog (future-proof) and log
                $filtered = array_filter($uploadedDocuments, function (array $d) use ($catalog) {
                    $ok = $catalog->supports($d['field']);
                    if (! $ok) {
                        Log::warning("[UploadRenewDocumentsJob] Skipping unsupported field for AI: {$d['field']}");
                    }
                    return $ok;
                });
                $descriptors = array_map(
                    fn (array $d) => new \App\Services\DocumentIntelligence\StoredDocumentDescriptor(
                        (int) $applyOrg->getKey(),
                        $d['field'],
                        $d['slot'],
                        $catalog->definition($d['field'])->label,
                        $d['name'],
                        $d['disk'],
                        $d['path'],
                        $d['source_version'],
                        $d['source_fingerprint'],
                    ),
                    array_values($filtered)
                );
                $registrar = app(\App\Services\DocumentIntelligence\DocumentAnalysisRegistrar::class);
                $commands = $registrar->register($applyOrg, $descriptors);
            });

            foreach ($commands as $command) {
                try {
                    \App\Jobs\ProcessDocumentAnalysis::dispatch(
                        $command->analysisId,
                        $command->sourceVersion,
                        $command->cycleId,
                    );
                } catch (\Throwable $e) {
                    Log::error('Failed to dispatch document analysis', ['analysis_id' => $command->analysisId, 'msg' => $e->getMessage()]);
                }
            }

            // Notify renewer (typed mailable, like Apply)
            try {
                $to = $this->validated['renewer_email'] ?? $this->validated['requester_email'] ?? null;
                $name = trim(($this->validated['renewer_first_name'] ?? $this->validated['requester_first_name'] ?? '') . ' ' . ($this->validated['renewer_last_name'] ?? $this->validated['requester_last_name'] ?? ''));
                $orgName = $applyOrg->organization_name ?? $this->validated['organization_name'] ?? 'Organization';
                if ($to) {
                    Mail::to($to)->send(new RenewOrgSubmitted($orgName, $name, 'request', 'renew'));
                }
            } catch (\Throwable $e) {
                Log::error("[UploadRenewDocumentsJob] Mail failed: " . $e->getMessage());
            }

            try {
                $osasAdmins = \App\Models\User::where('role', 'osas_admin')->get();
                foreach ($osasAdmins as $admin) {
                    $admin->notify(new \App\Notifications\NewApplyOrgNotification($applyOrg, 'renew'));
                }
            } catch (\Throwable $e) {
                Log::error("[UploadRenewDocumentsJob] OSAS notify failed: " . $e->getMessage());
            }

            $this->cleanupTmp();
            Log::info("[UploadRenewDocumentsJob] Completed R2 upload for ApplyOrg #{$applyOrg->id} with " . count($uploadedDocuments) . " docs");

        } catch (\Throwable $e) {
            Log::error("[UploadRenewDocumentsJob] R2 upload failed for ApplyOrg #{$applyOrg->id}: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            $this->cleanupTmpOnFailure();
            try {
                $to = $this->validated['renewer_email'] ?? $this->validated['requester_email'] ?? null;
                $name = $this->validated['renewer_first_name'] ?? $this->validated['requester_first_name'] ?? '';
                $orgName = $this->validated['organization_name'] ?? $applyOrg->organization_name ?? 'Organization';
                if ($to) {
                    Mail::raw(
                        "Hello {$name},\n\nYour renewal application '{$orgName}' encountered a storage error while uploading documents to Cloudflare R2: {$e->getMessage()}\n\nPlease retry your submission by re-uploading your documents. If the problem persists, contact OSAS.\n\nThank you.",
                        fn ($m) => $m->to($to)->subject('Upload Failed - Please Retry Your Renewal')
                    );
                }
            } catch (\Throwable $mailErr) {
                Log::error("[UploadRenewDocumentsJob] Retry mail failed: " . $mailErr->getMessage());
            }
            try {
                $applyOrg->update(['admin_notes' => ($applyOrg->admin_notes ? $applyOrg->admin_notes . "\n" : "") . "[System] R2 upload failed at " . now() . ": " . $e->getMessage() . " - Please re-upload."]);
            } catch (\Throwable $updErr) {}
            throw $e;
        }
    }

    private function cleanupTmp(): void
    {
        $disk = Storage::disk('local');
        $allTmps = [];
        foreach ($this->tmpPaths as $info) {
            if (isset($info['tmp'])) $allTmps[] = $info['tmp'];
        }
        foreach ($this->memberTmpFiles as $entry) {
            if (isset($entry['tmp'])) $allTmps[] = $entry['tmp'];
        }
        foreach ($this->otherTmpFiles as $entry) {
            if (isset($entry['tmp'])) $allTmps[] = $entry['tmp'];
        }
        foreach ($allTmps as $tmp) {
            if ($tmp) {
                try { $disk->delete($tmp); } catch (\Throwable $e) {}
                $dir = dirname($tmp);
                try {
                    $files = $disk->files($dir);
                    if (empty($files)) $disk->deleteDirectory($dir);
                } catch (\Throwable $e) {}
            }
        }
    }

    private function cleanupTmpOnFailure(): void
    {
        try {
            $attempts = method_exists($this, 'attempts') ? $this->attempts() : 0;
        } catch (\Throwable $e) {
            $attempts = 0;
        }
        if ($attempts >= $this->tries) {
            Log::warning("[UploadRenewDocumentsJob] Final attempt {$attempts}/{$this->tries} failed, cleaning tmp for ApplyOrg #{$this->applyOrgId}");
            $this->cleanupTmp();
        } else {
            Log::warning("[UploadRenewDocumentsJob] Keep tmp for retry {$attempts}/{$this->tries} for ApplyOrg #{$this->applyOrgId} - not deleting");
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("[UploadRenewDocumentsJob] Final failure after retries for ApplyOrg #{$this->applyOrgId}: " . $exception->getMessage());
        try { $this->cleanupTmp(); } catch (\Throwable $e) {}
        try {
            $to = $this->validated['renewer_email'] ?? $this->validated['requester_email'] ?? null;
            $name = $this->validated['renewer_first_name'] ?? $this->validated['requester_first_name'] ?? '';
            $orgName = $this->validated['organization_name'] ?? 'Organization';
            if ($to) {
                Mail::raw(
                    "Hello {$name},\n\nYour renewal application '{$orgName}' failed to upload after multiple retries. Please retry your submission.\n\nError: {$exception->getMessage()}\n\nThank you.",
                    fn ($m) => $m->to($to)->subject('Upload Failed - Please Retry Your Renewal')
                );
            }
        } catch (\Throwable $e) {}
    }
}
