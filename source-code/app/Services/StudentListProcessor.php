<?php

namespace App\Services;

use App\Models\ApplyOrg;
use App\Models\EnrolledStudent;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Processes the "List of Members" documents submitted in an org application.
 * Extracts student ID numbers (format: 0000-0000) via OCR and upserts them
 * into the enrolled_students table so applicants can register on UniOrg.
 */
class StudentListProcessor
{
    /**
     * Regex pattern for student ID numbers: exactly 4 digits, dash, 4 digits.
     * The \s? handles OCR artifacts where a space is inserted after the dash
     * (e.g. "0123- 1560" → normalised to "0123-1560").
     */
    private const STUDENT_ID_PATTERN = '/\b\d{4}-\s?\d{4}\b/';

    public function __construct(
        private readonly OcrService $ocr,
        private readonly GoogleCloudVisionService $vision,
    ) {}

    /**
     * Process all list_of_members files in the application and
     * upsert extracted student IDs into enrolled_students.
     *
     * @return array{extracted: int, inserted: int, skipped: int, errors: int}
     */
    public function process(ApplyOrg $applyOrg): array
    {
        $files = $this->resolveFiles($applyOrg);

        if (empty($files)) {
            Log::warning("[StudentListProcessor] No list_of_members files found for ApplyOrg #{$applyOrg->id}");
            return ['extracted' => 0, 'inserted' => 0, 'skipped' => 0, 'errors' => 0];
        }

        $allStudentIds = [];
        $errors = 0;

        foreach ($files as $fileInfo) {
            try {
                $text = $this->extractText($fileInfo['path'], $fileInfo['disk']);

                if (empty(trim($text))) {
                    Log::warning("[StudentListProcessor] Empty text extracted from file: {$fileInfo['original_name']}");
                    continue;
                }

                $ids = $this->parseStudentIds($text);

                Log::info("[StudentListProcessor] Extracted " . count($ids) . " student ID(s) from {$fileInfo['original_name']}", [
                    'apply_org_id' => $applyOrg->id,
                    'ids' => $ids,
                ]);

                array_push($allStudentIds, ...$ids);
            } catch (\Throwable $e) {
                $errors++;
                Log::error("[StudentListProcessor] Failed to process file {$fileInfo['original_name']}: " . $e->getMessage(), [
                    'apply_org_id' => $applyOrg->id,
                ]);
            }
        }

        // Deduplicate across all files
        $uniqueIds = array_values(array_unique($allStudentIds));

        $inserted = 0;
        $skipped  = 0;

        foreach ($uniqueIds as $studentId) {
            $exists = EnrolledStudent::where('student_id_number', $studentId)->exists();

            if ($exists) {
                $skipped++;
                continue;
            }

            EnrolledStudent::create(['student_id_number' => $studentId]);
            $inserted++;
        }

        $summary = [
            'extracted' => count($uniqueIds),
            'inserted'  => $inserted,
            'skipped'   => $skipped,
            'errors'    => $errors,
        ];

        Log::info("[StudentListProcessor] Done for ApplyOrg #{$applyOrg->id}", $summary);

        return $summary;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Decode the list_of_members JSON field and return an array of file descriptors.
     *
     * @return list<array{path: string, disk: string, original_name: string}>
     */
    private function resolveFiles(ApplyOrg $applyOrg): array
    {
        $raw = $applyOrg->list_of_members;

        if (empty($raw)) {
            return [];
        }

        // The field is stored as a JSON array of file metadata objects.
        // Each entry may be a plain path string or an object with 'path'/'storage_path' keys.
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [['path' => $raw]];
        }

        if (! is_array($raw)) {
            return [];
        }

        // Single-file shorthand: the array itself is one metadata object
        if (isset($raw['path']) || isset($raw['storage_path'])) {
            $raw = [$raw];
        }

        $disk = config('filesystems.default', 'public');
        $files = [];

        foreach ($raw as $entry) {
            if (is_string($entry)) {
                $entry = ['path' => $entry];
            }

            if (! is_array($entry)) {
                continue;
            }

            $path = $entry['path'] ?? $entry['storage_path'] ?? $entry['file_path'] ?? null;

            if (empty($path)) {
                continue;
            }

            $files[] = [
                'path'          => $path,
                'disk'          => $entry['storage_disk'] ?? $entry['disk'] ?? $disk,
                'original_name' => $entry['name'] ?? $entry['original_filename'] ?? basename($path),
            ];
        }

        return $files;
    }

    /**
     * Download the file to a local temp path (if needed) and extract its text.
     */
    private function extractText(string $storagePath, string $disk): string
    {
        // Get a local absolute path we can pass to the OCR tools
        $localPath = $this->localise($storagePath, $disk);

        try {
            return $this->doExtract($localPath);
        } finally {
            // Clean up the temp copy if we created one
            if (str_starts_with($localPath, sys_get_temp_dir())) {
                @unlink($localPath);
            }
        }
    }

    /**
     * Resolve a storage path to a local filesystem path, downloading from S3/R2 if necessary.
     */
    private function localise(string $storagePath, string $disk): string
    {
        $fs = Storage::disk($disk);

        // For local/public disks the underlying path is directly accessible
        if (method_exists($fs->getAdapter(), 'getPathPrefix')
            || in_array($disk, ['local', 'public'], true)
        ) {
            return $fs->path($storagePath);
        }

        // For remote disks (S3, R2) download to a temp file
        $tmpPath = tempnam(sys_get_temp_dir(), 'uniorg_member_') . '.' . pathinfo($storagePath, PATHINFO_EXTENSION);
        file_put_contents($tmpPath, $fs->get($storagePath));

        return $tmpPath;
    }

    /**
     * Run OCR on the local file. Prefers Google Cloud Vision when enabled.
     */
    private function doExtract(string $localPath): string
    {
        $ext = strtolower(pathinfo($localPath, PATHINFO_EXTENSION));

        // --- Google Cloud Vision (preferred when available) ---
        if ($this->vision->isEnabled()) {
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'], true)) {
                $result = $this->vision->extractText($localPath);
                if ($result['success']) {
                    return $result['text'];
                }
            }

            if ($ext === 'pdf') {
                $result = $this->vision->extractTextFromPdf($localPath);
                if ($result['success']) {
                    return $result['text'];
                }
            }
        }

        // --- Fallback: local Tesseract OCR / PDF parser ---
        return (string) $this->ocr->basahinAngPicture($localPath, maxPages: 0 /* all pages */);
    }

    /**
     * Extract all student ID numbers matching 0000-0000 from raw OCR text.
     * Normalises OCR artifacts like "0123- 1560" → "0123-1560".
     *
     * @return list<string>
     */
    private function parseStudentIds(string $text): array
    {
        preg_match_all(self::STUDENT_ID_PATTERN, $text, $matches);

        // Normalise: remove any OCR-inserted space between dash and digits
        $ids = array_map(
            static fn(string $id): string => preg_replace('/(\d{4})-\s(\d{4})/', '$1-$2', $id),
            $matches[0] ?? [],
        );

        // Deduplicate within this file
        return array_values(array_unique($ids));
    }
}
