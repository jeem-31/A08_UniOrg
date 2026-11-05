<?php

namespace App\Http\Controllers\Osas;

use App\Http\Controllers\Controller;
use App\Models\ApplyOrg;
use App\Models\ApplyOrgDocumentArchive;
use App\Models\ArchivedDocumentLibraryEntry;
use App\Models\ArchivedProcessedDocument;
use App\Models\DocumentLibraryEntry;
use App\Models\ProcessedDocument;
use App\Services\DocumentIntelligence\CurrentDocumentAnalysisLocator;
use App\Services\DocumentLibrary\CurrentDocumentLibraryEntryLocator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

final class DocumentLibrarySourceController extends Controller
{
    public function __construct(
        private readonly CurrentDocumentLibraryEntryLocator $entries,
        private readonly CurrentDocumentAnalysisLocator $analyses,
    ) {}

    public function __invoke(
        string $applyOrg,
        string $document,
    ): JsonResponse|RedirectResponse|StreamedResponse {
        // Union lookup: try live, then archived (Option B) — applyOrg may be live id (14) or archive id (4)
        $liveApplyOrg = ApplyOrg::query()->useWritePdo()->find($applyOrg);
        $archiveFromUrl = null;
        if ($liveApplyOrg === null) {
            $archiveFromUrl = ApplyOrgDocumentArchive::find($applyOrg);
            abort_if($archiveFromUrl === null, 404, 'The requested document is unavailable.');
        }

        $entry = DocumentLibraryEntry::query()->useWritePdo()->find($document);
        $archivedEntry = null;
        $isArchived = false;
        if ($entry === null) {
            $archivedEntry = ArchivedDocumentLibraryEntry::query()->find($document);
            if ($archivedEntry !== null) {
                $entry = $archivedEntry;
                $isArchived = true;
            }
        }
        abort_if($entry === null, 404, 'The requested document is unavailable.');

        if ($isArchived) {
            $this->authorizeForArchived($archivedEntry, $liveApplyOrg, $archiveFromUrl);
            /** @var ArchivedDocumentLibraryEntry $entry */
            $archive = ApplyOrgDocumentArchive::find($archivedEntry->archive_id);
            abort_if($archive === null, 404, 'The requested document is unavailable.');
            // For archived, serve directly from archived storage without redirect to analysis (analysis is also archived)
            try {
                $stream = Storage::disk((string) $entry->storage_disk)->readStream((string) $entry->file_path);
            } catch (Throwable) {
                $stream = false;
            }
            if (! is_resource($stream)) {
                return response()->json(['message' => 'The requested document is unavailable.'], 404);
            }
            $filename = basename(str_replace('\\', '/', (string) $entry->original_filename));
            $filename = preg_replace('/[\x00-\x1F\x7F]/', '', $filename);
            $filename = is_string($filename) && $filename !== '' ? $filename : 'document';
            $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            $contentType = match ($extension) {
                'pdf' => 'application/pdf',
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'jpg', 'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                default => 'application/octet-stream',
            };
            $fallbackExtension = preg_replace('/[^A-Za-z0-9]/', '', $extension);
            $fallback = 'document'.($fallbackExtension === '' ? '' : '.'.$fallbackExtension);
            return response()->stream(function () use ($stream): void {
                try { fpassthru($stream); } finally { fclose($stream); }
            }, 200, [
                'Content-Type' => $contentType,
                'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_INLINE, $filename, $fallback),
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        abort_if($liveApplyOrg === null, 404, 'The requested document is unavailable.');
        /** @var DocumentLibraryEntry $entry */
        $this->authorize('viewSource', [$entry, $liveApplyOrg]);
        $source = $this->entries->find($liveApplyOrg, $entry);
        abort_if($source === null, 404, 'The requested document is unavailable.');

        $currentApplication = $liveApplyOrg->fresh();
        abort_if($currentApplication === null, 404, 'The requested document is unavailable.');
        abort_if($currentApplication->status !== 'Approved', 404, 'The requested document is unavailable.');

        $analysis = ProcessedDocument::query()->useWritePdo()
            ->where('apply_org_id', $currentApplication->getKey())
            ->where('document_field', $source->field)
            ->where('document_slot', $source->slot)
            ->first();
        if ($analysis !== null && $this->analyses->isCurrent($currentApplication, $analysis)) {
            return redirect()->route('osas.apply-org.analyses.source', [$currentApplication, $analysis]);
        }

        try {
            $stream = Storage::disk($source->storageDisk)->readStream($source->storagePath);
        } catch (Throwable) {
            $stream = false;
        }

        if (! is_resource($stream)) {
            return response()->json(['message' => 'The requested document is unavailable.'], 404);
        }

        $filename = basename(str_replace('\\', '/', $source->originalFilename));
        $filename = preg_replace('/[\x00-\x1F\x7F]/', '', $filename);
        $filename = is_string($filename) && $filename !== '' ? $filename : 'document';
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $contentType = match ($extension) {
            'pdf' => 'application/pdf',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            default => 'application/octet-stream',
        };
        $fallbackExtension = preg_replace('/[^A-Za-z0-9]/', '', $extension);
        $fallback = 'document'.($fallbackExtension === '' ? '' : '.'.$fallbackExtension);

        return response()->stream(function () use ($stream): void {
            try {
                fpassthru($stream);
            } finally {
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $contentType,
            'Content-Disposition' => HeaderUtils::makeDisposition(
                HeaderUtils::DISPOSITION_INLINE,
                $filename,
                $fallback,
            ),
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function authorizeForArchived(ArchivedDocumentLibraryEntry $entry, ?ApplyOrg $liveApplyOrg, ?ApplyOrgDocumentArchive $archiveFromUrl): void
    {
        $user = Auth::user();
        abort_unless($user instanceof \App\Models\User && $user->role === 'osas_admin', 403);

        $archive = ApplyOrgDocumentArchive::find($entry->archive_id);
        abort_if($archive === null, 404, 'The requested document is unavailable.');

        // If the URL's applyOrg was an archive id, allow if it matches the entry's archive
        if ($archiveFromUrl !== null && (int) $archiveFromUrl->id === (int) $archive->id) {
            return;
        }

        // If we have a live ApplyOrg, allow if same organization
        if ($liveApplyOrg !== null) {
            $liveOrgId = $liveApplyOrg->organization_id;
            $archOrgId = $archive->organization_id;
            $sourceId = $archive->source_apply_org_id;
            if ((int) $liveApplyOrg->getKey() === (int) $archive->id || (int) $liveApplyOrg->getKey() === (int) $sourceId) {
                return;
            }
            if ($liveOrgId !== null && $archOrgId !== null && (int) $liveOrgId === (int) $archOrgId) {
                return;
            }
        }

        // Fallback: osas_admin can view any archived
    }
}
