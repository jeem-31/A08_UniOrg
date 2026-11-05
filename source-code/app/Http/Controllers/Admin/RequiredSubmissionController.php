<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\OsasDocumentSubmission;
use App\Models\OsasRequiredDocument;
use App\Services\OsasRequiredDocs\ReportSubmissionPipeline;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RequiredSubmissionController extends Controller
{
    public function __construct(
        private readonly ReportSubmissionPipeline $pipeline,
    ) {}

    public function index()
    {
        $organization = $this->currentOrganization();
        if (! $organization) {
            return redirect()->route('admin.dashboard')->with('error', 'No organization found. Please contact administrator.');
        }

        $requirements = $this->pipeline->requirementsForOrganization((int) $organization->getKey())->get();

        $cards = [];
        foreach ($requirements as $requirement) {
            $latest = OsasDocumentSubmission::query()
                ->where('required_document_id', $requirement->getKey())
                ->where('organization_id', $organization->getKey())
                ->orderByDesc('version')
                ->first();

            $history = OsasDocumentSubmission::query()
                ->where('required_document_id', $requirement->getKey())
                ->where('organization_id', $organization->getKey())
                ->orderByDesc('version')
                ->get();

            $cards[] = [
                'requirement' => $requirement,
                'latest' => $latest,
                'history' => $history,
            ];
        }

        return view('admin.required-submissions.index', [
            'organization' => $organization,
            'cards' => $cards,
        ]);
    }

    public function store(Request $request)
    {
        $organization = $this->currentOrganization();
        if (! $organization) {
            return back()->with('error', 'Organization context lost.');
        }

        $validated = $request->validate([
            'required_document_id' => ['required', 'exists:osas_required_documents,id'],
            'document' => ['required', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png', 'max:20480'],
        ]);

        $requirement = OsasRequiredDocument::find($validated['required_document_id']);
        if (! $this->requirementAppliesTo($requirement, (int) $organization->getKey())) {
            return back()->with('error', 'This requirement is not assigned to your organization.');
        }

        $latest = OsasDocumentSubmission::query()
            ->where('required_document_id', $requirement->getKey())
            ->where('organization_id', $organization->getKey())
            ->orderByDesc('version')
            ->first();

        if ($latest && $latest->status === OsasDocumentSubmission::STATUS_SUBMITTED) {
            return back()->with('error', 'You already have a submission under review. Please wait for OSAS feedback before uploading again.');
        }

        $file = $request->file('document');
        $storedPath = null;
        $storedDisk = null;
        $commands = [];
        $submission = null;

        DB::beginTransaction();
        try {
            $linker = $this->pipeline->linkerForOrganization($organization);

            $result = $this->pipeline->storeAndRegister(
                $linker,
                $requirement,
                (int) $organization->getKey(),
                $file,
            );

            $storedPath = $result['path'];
            $storedDisk = $result['disk'];
            $commands = $result['commands'];

            $submission = OsasDocumentSubmission::create([
                'required_document_id' => $requirement->getKey(),
                'organization_id' => $organization->getKey(),
                'submitted_by' => Auth::id(),
                'file_path' => $result['path'],
                'storage_disk' => $result['disk'],
                'original_filename' => $result['name'],
                'mime' => $file->getClientMimeType(),
                'size' => $file->getSize(),
                'status' => OsasDocumentSubmission::STATUS_SUBMITTED,
                'version' => $latest ? ((int) $latest->version + 1) : 1,
            ]);

            $analysisId = $this->pipeline->currentAnalysisId(
                $linker->fresh()->load('processedDocuments'),
                $this->pipeline->analysisSlot($requirement, (int) $organization->getKey()),
            );
            if ($analysisId !== null) {
                $submission->update(['processed_document_id' => $analysisId]);
            }

            DB::commit();
        } catch (\Throwable $e) {
            if (DB::connection()->transactionLevel() > 0) {
                DB::rollBack();
            }
            if ($storedPath && $storedDisk) {
                try {
                    \Illuminate\Support\Facades\Storage::disk($storedDisk)->delete($storedPath);
                } catch (\Throwable) {
                }
            }
            Log::error('Required-document submission failed: '.$e->getMessage());

            return back()->with('error', 'Failed to submit the document: '.$e->getMessage());
        }

        $this->pipeline->dispatchCommands($commands);

        return back()->with('success', 'Document submitted. OSAS will review it and the summary will appear once processing finishes.');
    }

    private function currentOrganization(): ?Organization
    {
        $user = Auth::user();
        $currentOrgId = session('current_organization_id');

        return $currentOrgId
            ? $user->organizations()->find($currentOrgId)
            : ($user->activeOrganizations()->first() ?? $user->organizations()->first());
    }

    private function requirementAppliesTo(?OsasRequiredDocument $requirement, int $organizationId): bool
    {
        if ($requirement === null || ! $requirement->is_active) {
            return false;
        }

        if ((int) $requirement->organization_id === $organizationId) {
            return true;
        }

        if ($requirement->organization_id === null) {
            $organization = Organization::find($organizationId);

            return $organization
                && (int) $organization->department_id === (int) $requirement->department_id;
        }

        return false;
    }
}
