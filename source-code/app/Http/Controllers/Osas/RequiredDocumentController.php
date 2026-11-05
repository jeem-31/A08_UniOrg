<?php

namespace App\Http\Controllers\Osas;

use App\Http\Controllers\Controller;
use App\Models\ApplyOrg;
use App\Models\Department;
use App\Models\Organization;
use App\Models\OsasDocumentSubmission;
use App\Models\OsasRequiredDocument;
use App\Services\OsasRequiredDocs\ReportSubmissionPipeline;
use App\ViewModels\CurrentDocumentAnalysisPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RequiredDocumentController extends Controller
{
    public function index(Request $request)
    {
        // Sanitized (never 302): unknown filter values are ignored.
        $typeKeys = array_keys(OsasRequiredDocument::typeLabels());
        $departmentId = is_numeric($request->query('department_id')) ? (int) $request->query('department_id') : null;
        if ($departmentId && ! Department::whereKey($departmentId)->exists()) {
            $departmentId = null;
        }
        $documentKey = in_array($request->query('document_key'), $typeKeys, true) ? $request->query('document_key') : null;
        $status = in_array($request->query('status'), ['active', 'archived'], true) ? $request->query('status') : null;
        $search = is_string($request->query('q')) ? trim(substr($request->query('q'), 0, 255)) : '';
        $filters = array_filter([
            'q' => $search !== '' ? $search : null,
            'department_id' => $departmentId,
            'document_key' => $documentKey,
            'status' => $status,
        ]);

        $requirements = OsasRequiredDocument::query()
            ->with(['department', 'organization'])
            ->when($departmentId, fn ($query) => $query->where('department_id', $departmentId))
            ->when($documentKey, fn ($query) => $query->where('document_key', $documentKey))
            ->when($status === 'active', fn ($query) => $query->active())
            ->when($status === 'archived', fn ($query) => $query->where('is_active', DB::raw('FALSE')))
            ->when($search !== '', function ($query) use ($search) {
                // Library-style matching: literal, case-insensitive, works on
                // both PostgreSQL and SQLite. Covers titles/notes/org names
                // plus AI-generated tags and summaries of submitted files.
                $keyword = function_exists('mb_strtolower') ? mb_strtolower($search, 'UTF-8') : strtolower($search);
                $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $keyword).'%';
                $text = static fn (string $column): string => "LOWER(COALESCE(CAST({$column} AS TEXT), ''))";
                $query->where(function ($searchQuery) use ($pattern, $text) {
                    $searchQuery->whereRaw($text('osas_required_documents.custom_title')." LIKE ? ESCAPE '!'", [$pattern])
                        ->orWhereRaw($text('osas_required_documents.description')." LIKE ? ESCAPE '!'", [$pattern])
                        ->orWhereHas(
                            'organization',
                            static fn ($org) => $org->whereRaw($text('organizations.name')." LIKE ? ESCAPE '!'", [$pattern])
                        )
                        ->orWhereExists(function ($exists) use ($pattern, $text) {
                            $exists->select(DB::raw('1'))
                                ->from('osas_document_submissions as s')
                                ->join('processed_documents as p', 'p.id', '=', 's.processed_document_id')
                                ->whereColumn('s.required_document_id', 'osas_required_documents.id')
                                ->where(function ($ai) use ($pattern, $text) {
                                    $ai->whereRaw($text('p.tags')." LIKE ? ESCAPE '!'", [$pattern])
                                        ->orWhereRaw($text('p.summary')." LIKE ? ESCAPE '!'", [$pattern]);
                                });
                        });
                });
            })
            ->orderByDesc('is_active')
            ->orderBy('deadline_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $requirements->getCollection()->each(function (OsasRequiredDocument $requirement): void {
            $requirement->setAttribute('target_label', $this->targetLabel($requirement));
            $requirement->setAttribute('stats', $this->requirementStats($requirement));
        });

        $departments = Department::orderBy('department_name')->get(['id', 'department_name', 'department_acronym']);

        return view('osas.required-documents.index', [
            'requirements' => $requirements,
            'departments' => $departments,
            'typeLabels' => OsasRequiredDocument::typeLabels(),
            'filters' => [
                'q' => $search !== '' ? $search : '',
                'department_id' => $departmentId ? (string) $departmentId : '',
                'document_key' => $documentKey ?? '',
                'status' => $status ?? '',
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'department_id' => ['required', 'exists:departments,id'],
            'organization_id' => ['nullable', 'exists:organizations,id'],
            'document_key' => ['required', Rule::in(array_keys(OsasRequiredDocument::typeLabels()))],
            'custom_title' => ['nullable', 'string', 'max:255', 'required_if:document_key,other'],
            'description' => ['nullable', 'string', 'max:2000'],
            'deadline_at' => ['nullable', 'date', 'after:now'],
        ]);

        if (! empty($validated['organization_id'])) {
            $org = Organization::find($validated['organization_id']);
            if ($org === null || (int) $org->department_id !== (int) $validated['department_id']) {
                return back()->withErrors([
                    'organization_id' => 'The organization does not belong to the selected department.',
                ])->withInput();
            }
        }

        $duplicate = OsasRequiredDocument::query()
            ->active()
            ->where('document_key', $validated['document_key'])
            ->where('department_id', $validated['department_id'])
            ->when(
                ! empty($validated['organization_id']),
                fn ($query) => $query->where('organization_id', $validated['organization_id']),
                fn ($query) => $query->whereNull('organization_id'),
            )
            ->when(
                ($validated['document_key'] ?? null) === OsasRequiredDocument::KEY_OTHER,
                fn ($query) => $query->where('custom_title', $validated['custom_title'] ?? null),
            )
            ->exists();

        if ($duplicate) {
            return back()->withErrors([
                'document_key' => 'This document is already required for the selected target.',
            ])->withInput();
        }

        OsasRequiredDocument::create([
            'department_id' => $validated['department_id'],
            'organization_id' => $validated['organization_id'] ?? null,
            'document_key' => $validated['document_key'],
            'custom_title' => $validated['document_key'] === OsasRequiredDocument::KEY_OTHER
                ? ($validated['custom_title'] ?? null)
                : null,
            'description' => $validated['description'] ?? null,
            'deadline_at' => $validated['deadline_at'] ?? null,
            'is_active' => true,
            'created_by' => Auth::id(),
        ]);

        return back()->with('success', 'Report requirement published. Organizations can now see it in their report submissions page.');
    }

    public function toggle(OsasRequiredDocument $requiredDocument)
    {
        $requiredDocument->update(['is_active' => ! $requiredDocument->is_active]);

        return back()->with(
            'success',
            $requiredDocument->is_active ? 'Report requirement re-activated.' : 'Report requirement archived. Organizations can no longer submit to it.'
        );
    }

    public function submissions(
        OsasRequiredDocument $requiredDocument,
        ReportSubmissionPipeline $pipeline,
        CurrentDocumentAnalysisPresenter $presenter,
    ) {
        $requiredDocument->load(['department', 'organization']);
        $organizations = $this->targetOrganizations($requiredDocument);

        $rows = [];
        foreach ($organizations as $organization) {
            $latest = OsasDocumentSubmission::query()
                ->where('required_document_id', $requiredDocument->getKey())
                ->where('organization_id', $organization->getKey())
                ->orderByDesc('version')
                ->first();

            $history = OsasDocumentSubmission::query()
                ->where('required_document_id', $requiredDocument->getKey())
                ->where('organization_id', $organization->getKey())
                ->orderByDesc('version')
                ->get();

            $analysis = $this->analysisFor($pipeline, $presenter, $requiredDocument, (int) $organization->getKey());

            $rows[] = [
                'organization' => $organization,
                'pending_application' => null,
                'latest' => $latest,
                'history' => $history,
                'analysis' => $analysis,
            ];
        }

        // Bulk requirements also surface pending applications (no org row yet):
        // they cannot submit until approved, but OSAS sees them in scope.
        foreach ($this->pendingApplications($requiredDocument) as $application) {
            $rows[] = [
                'organization' => null,
                'pending_application' => $application,
                'latest' => null,
                'history' => collect(),
                'analysis' => null,
            ];
        }

        return view('osas.required-documents.submissions', [
            'requirement' => $requiredDocument,
            'rows' => $rows,
        ]);
    }

    public function review(Request $request, OsasDocumentSubmission $submission)
    {
        $validated = $request->validate([
            'action' => ['required', Rule::in(['approve', 'reject'])],
            'remarks' => ['nullable', 'string', 'max:2000', 'required_if:action,reject'],
        ]);

        $submission->update([
            'status' => $validated['action'] === 'approve'
                ? OsasDocumentSubmission::STATUS_APPROVED_FOR_SIGNATURE
                : OsasDocumentSubmission::STATUS_REJECTED,
            'remarks' => $validated['remarks'] ?? null,
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ]);

        // Keep only the latest version reviewable: older versions stay as history.
        if ($validated['action'] === 'approve') {
            OsasDocumentSubmission::query()
                ->where('required_document_id', $submission->required_document_id)
                ->where('organization_id', $submission->organization_id)
                ->where('id', '<>', $submission->getKey())
                ->where('status', OsasDocumentSubmission::STATUS_SUBMITTED)
                ->update([
                    'status' => OsasDocumentSubmission::STATUS_REJECTED,
                    'remarks' => 'Superseded by a newer approved version.',
                    'reviewed_by' => Auth::id(),
                    'reviewed_at' => now(),
                ]);
        }

        return back()->with(
            'success',
            $validated['action'] === 'approve'
                ? 'Submission marked as For Signature.'
                : 'Submission rejected. The organization can upload again.'
        );
    }

    private function targetLabel(OsasRequiredDocument $requirement): string
    {
        if ($requirement->organization_id) {
            return $requirement->organization?->name ?? 'Organization';
        }

        return 'All orgs in '.($requirement->department?->department_name ?? 'department');
    }

    /**
     * @return array{orgs:int,submitted:int,for_signature:int,rejected:int,pending:int}
     */
    private function requirementStats(OsasRequiredDocument $requirement): array
    {
        $orgIds = $this->targetOrganizations($requirement)->pluck('id');

        $latestIds = OsasDocumentSubmission::query()
            ->select('organization_id', DB::raw('MAX(version) as max_version'))
            ->where('required_document_id', $requirement->getKey())
            ->whereIn('organization_id', $orgIds->all() ?: [0])
            ->groupBy('organization_id')
            ->get();

        $submitted = 0;
        $forSignature = 0;
        $rejected = 0;
        foreach ($latestIds as $row) {
            $latest = OsasDocumentSubmission::query()
                ->where('required_document_id', $requirement->getKey())
                ->where('organization_id', $row->organization_id)
                ->where('version', $row->max_version)
                ->first();
            if ($latest === null) {
                continue;
            }
            match ($latest->status) {
                OsasDocumentSubmission::STATUS_APPROVED_FOR_SIGNATURE => $forSignature++,
                OsasDocumentSubmission::STATUS_REJECTED => $rejected++,
                default => $submitted++,
            };
        }

        return [
            'orgs' => $orgIds->count(),
            'submitted' => $submitted,
            'for_signature' => $forSignature,
            'rejected' => $rejected,
            'pending' => $this->pendingApplications($requirement)->count(),
        ];
    }

    /**
     * New (not yet approved) applications in scope of a bulk requirement.
     * These have no organization row yet, so they cannot submit — OSAS just
     * sees them here and they pick the requirement up once approved.
     *
     * @return \Illuminate\Support\Collection<int, ApplyOrg>
     */
    private function pendingApplications(OsasRequiredDocument $requirement): mixed
    {
        if ($requirement->organization_id) {
            return collect();
        }

        return ApplyOrg::query()
            ->whereNull('organization_id')
            ->where('department_id', $requirement->department_id)
            ->whereNotNull('application_type')
            ->whereIn('status', ['Pending', 'For-Checking'])
            ->orderBy('organization_name')
            ->get();
    }

    /**
     * @return \Illuminate\Support\Collection<int, Organization>
     */
    private function targetOrganizations(OsasRequiredDocument $requirement): mixed
    {
        if ($requirement->organization_id) {
            return Organization::whereKey($requirement->organization_id)->get();
        }

        return Organization::where('department_id', $requirement->department_id)
            ->where('status', 'Approved')
            ->orderBy('name')
            ->get();
    }

    /**
     * Same summary card shape as the accreditation workspace, with the
     * requirement title swapped in for the generic "Additional Documents" label.
     *
     * @return array<string,mixed>|null
     */
    private function analysisFor(
        ReportSubmissionPipeline $pipeline,
        CurrentDocumentAnalysisPresenter $presenter,
        OsasRequiredDocument $requirement,
        int $organizationId,
    ): ?array {
        $organization = Organization::find($organizationId);
        if ($organization === null) {
            return null;
        }

        $linkers = ApplyOrg::query()
            ->where('organization_id', $organizationId)
            ->where('request_type', 'request')
            ->whereNull('application_type')
            ->orderByDesc('id')
            ->get();

        if ($linkers->isEmpty()) {
            return null;
        }

        $slot = $pipeline->analysisSlot($requirement, $organizationId);

        // One linker per school year (plus pre-fix rows without a year):
        // newest first so the current submission's summary wins.
        foreach ($linkers as $linker) {
            $linker->load('processedDocuments');

            foreach ($presenter->forApplication($linker) as $card) {
                if (($card['document_slot'] ?? null) === $slot) {
                    $card['requirement_label'] = $requirement->displayTitle();

                    return $card;
                }
            }
        }

        return null;
    }
}
