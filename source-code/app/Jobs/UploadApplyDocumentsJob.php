<?php

namespace App\Jobs;

use App\Mail\ApplyOrgSubmitted;
use App\Models\ApplyOrg;
use App\Services\DocumentIntelligence\SupportedDocumentCatalog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UploadApplyDocumentsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $backoff = 10;

    /**
     * @param int $applyOrgId
     * @param array<string,string> $tmpPaths map field => tmp local path (for scalar) or json for list members
     * @param array<string,array{tmp:string,name:string}> $memberTmpFiles list of members tmp files with original names
     * @param array<string,array{tmp:string,name:string}> $otherTmpFiles other files
     * @param string $storageDisk target R2 disk (s3)
     * @param array $validated validated data for email/context
     */
    public function __construct(
        public int $applyOrgId,
        public array $tmpPaths,
        public array $memberTmpFiles,
        public array $otherTmpFiles,
        public string $storageDisk,
        public array $validated,
    ) {
        if (config('queue.default') !== 'sync') {
            $this->onConnection('redis')->onQueue('default');
        }
    }

    public function handle(SupportedDocumentCatalog $catalog): void
    {
        $applyOrg = ApplyOrg::find($this->applyOrgId);
        if (! $applyOrg) {
            Log::error("[UploadApplyDocumentsJob] ApplyOrg #{$this->applyOrgId} not found");
            $this->cleanupTmp();
            return;
        }

        Log::info("[UploadApplyDocumentsJob] Starting R2 upload for ApplyOrg #{$applyOrg->id}");

        $uploadedDocuments = [];
        $uploadedPaths = [];

        try {
            // Helper to store tmp file to R2 with fingerprint
            $storeToR2 = function (string $tmpPath, string $originalName, string $field, string $slot) use (&$uploadedDocuments, &$uploadedPaths) {
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
                // Read bytes and put to R2
                $bytes = $localDisk->get($tmpPath);
                $r2Path = 'system-requests/' . Str::uuid() . '-' . $originalName;
                // Ensure R2 disk
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

            // Scalar documents
            foreach ($this->tmpPaths as $field => $info) {
                // $info is ['tmp'=>path, 'name'=>original]
                if (! isset($info['tmp'])) continue;
                $doc = $storeToR2($info['tmp'], $info['name'], $field, SupportedDocumentCatalog::PRIMARY_SLOT);
                $uploadedPaths[$field] = $doc['path'];
            }

            // List of members (unlimited)
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
                    $uploadedDocuments[] = $doc;
                }
                $uploadedPaths['list_of_members'] = json_encode($memberFiles, JSON_THROW_ON_ERROR);
            }

            // Other files
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
                    $uploadedDocuments[] = $doc;
                }
                $uploadedPaths['other_files'] = json_encode($otherFiles, JSON_THROW_ON_ERROR);
            }

            // Update ApplyOrg with R2 paths and register analyses inside transaction (required by registrar)
            $commands = [];
            DB::transaction(function () use ($applyOrg, $uploadedPaths, $uploadedDocuments, $catalog, &$commands) {
                $applyOrg->update($uploadedPaths);

                $filtered = array_filter($uploadedDocuments, function (array $d) use ($catalog) {
                    $ok = $catalog->supports($d['field']);
                    if (! $ok) {
                        Log::warning("[UploadApplyDocumentsJob] Skipping unsupported field for AI: {$d['field']}");
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

            // Dispatch after transaction commits
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

            // Send success email (OSAS notification is done via DB notification in controller or here)
            try {
                Mail::to($this->validated['requester_email'])->send(
                    new ApplyOrgSubmitted(
                        $this->validated['organization_name'],
                        $this->validated['requester_first_name'] . ' ' . $this->validated['requester_last_name'],
                        'apply'
                    )
                );
                Log::info("[UploadApplyDocumentsJob] Success email sent to {$this->validated['requester_email']} for ApplyOrg #{$applyOrg->id}");
            } catch (\Throwable $e) {
                Log::error("[UploadApplyDocumentsJob] Mail failed for ApplyOrg #{$applyOrg->id}: " . $e->getMessage());
            }

            // Notify OSAS admins
            try {
                $osasAdmins = \App\Models\User::where('role', 'osas_admin')->get();
                foreach ($osasAdmins as $admin) {
                    $admin->notify(new \App\Notifications\NewApplyOrgNotification($applyOrg, 'apply'));
                }
            } catch (\Throwable $e) {
                Log::error("[UploadApplyDocumentsJob] OSAS notify failed: " . $e->getMessage());
            }

            $this->cleanupTmp();

            Log::info("[UploadApplyDocumentsJob] Completed R2 upload for ApplyOrg #{$applyOrg->id} with " . count($uploadedDocuments) . " docs");

        } catch (\Throwable $e) {
            Log::error("[UploadApplyDocumentsJob] R2 upload failed for ApplyOrg #{$applyOrg->id}: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            $this->cleanupTmpOnFailure();
            // Send retry email
            try {
                $to = $this->validated['requester_email'] ?? null;
                if ($to) {
                    Mail::raw(
                        "Hello {$this->validated['requester_first_name']},\n\nYour organization application '{$this->validated['organization_name']}' encountered a storage error while uploading documents to Cloudflare R2: {$e->getMessage()}\n\nPlease retry your submission by re-uploading your documents. If the problem persists, contact OSAS.\n\nThank you.",
                        function ($msg) use ($to) {
                            $msg->to($to)->subject('Upload Failed - Please Retry Your Application');
                        }
                    );
                    Log::info("[UploadApplyDocumentsJob] Retry email sent to {$to}");
                }
            } catch (\Throwable $mailErr) {
                Log::error("[UploadApplyDocumentsJob] Retry mail failed: " . $mailErr->getMessage());
            }
            // Mark ApplyOrg as needing retry if needed
            try {
                $applyOrg->update(['admin_notes' => ($applyOrg->admin_notes ? $applyOrg->admin_notes . "\n" : "") . "[System] R2 upload failed at " . now() . ": " . $e->getMessage() . " - Please re-upload."]);
            } catch (\Throwable $updErr) {
                Log::error("[UploadApplyDocumentsJob] Failed to update admin_notes: " . $updErr->getMessage());
            }
            throw $e; // rethrow for queue retry
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
                try { $disk->delete($tmp); } catch (\Throwable $e) { Log::warning("Tmp delete failed {$tmp}: " . $e->getMessage()); }
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
        // Keep tmp for retry until final attempt — otherwise next retry gets "Tmp file missing"
        try {
            $attempts = method_exists($this, 'attempts') ? $this->attempts() : 0;
        } catch (\Throwable $e) {
            $attempts = 0;
        }
        if ($attempts >= $this->tries) {
            Log::warning("[UploadApplyDocumentsJob] Final attempt {$attempts}/{$this->tries} failed, cleaning tmp for ApplyOrg #{$this->applyOrgId}");
            $this->cleanupTmp();
        } else {
            Log::warning("[UploadApplyDocumentsJob] Keep tmp for retry {$attempts}/{$this->tries} for ApplyOrg #{$this->applyOrgId} - not deleting");
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("[UploadApplyDocumentsJob] Final failure after retries for ApplyOrg #{$this->applyOrgId}: " . $exception->getMessage());
        // Cleanup tmp on final failure to avoid leak
        try { $this->cleanupTmp(); } catch (\Throwable $e) {}
        // Final email already sent in handle catch, but ensure one more if not sent
        try {
            $to = $this->validated['requester_email'] ?? null;
            if ($to) {
                Mail::raw(
                    "Hello {$this->validated['requester_first_name']},\n\nYour organization application '{$this->validated['organization_name']}' failed to upload after multiple retries. Please retry your submission.\n\nError: {$exception->getMessage()}\n\nThank you.",
                    function ($msg) use ($to) {
                        $msg->to($to)->subject('Upload Failed - Please Retry Your Application');
                    }
                );
            }
        } catch (\Throwable $e) {
            Log::error("[UploadApplyDocumentsJob] Final retry mail failed: " . $e->getMessage());
        }
    }
}
