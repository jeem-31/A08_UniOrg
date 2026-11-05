<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessDocumentAnalysis;
use App\Jobs\UploadRenewDocumentsJob;
use App\Models\ApplyOrg;
use App\Models\Department;
use App\Models\ElectionOfficerTransition;
use App\Models\Organization;
use App\Models\User;
use App\Services\DocumentIntelligence\DocumentAnalysisDispatchCommand;
use App\Services\DocumentIntelligence\DocumentAnalysisRegistrar;
use App\Services\DocumentIntelligence\StoredDocumentDescriptor;
use App\Services\DocumentIntelligence\SupportedDocumentCatalog;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RenewController extends Controller
{
    public function __construct(
        private readonly DocumentAnalysisRegistrar $documentAnalysisRegistrar,
        private readonly SupportedDocumentCatalog $supportedDocumentCatalog,
    ) {}

    public function index()
    {
        try {
            $user = Auth::user();

            // Advisers + President + Dean can access renew (keep users but not in organization_user for others)
            $isAllowedRenew = false;
            if ($user->role === 'super_admin') {
                $isAllowedRenew = true;
            } else {
                $hasAdviserRole = DB::table('organization_user')
                    ->where('user_id', $user->id)
                    ->whereIn('role', ['Senior Adviser', 'Junior Adviser', 'Adviser', 'President', 'Organization President'])
                    ->exists();
                if ($hasAdviserRole) {
                    $isAllowedRenew = true;
                } else {
                    // Fallback for case-insensitive President variants (e.g. 'president' stored differently)
                    $hasPresidentRole = DB::table('organization_user')
                        ->where('user_id', $user->id)
                        ->whereRaw("LOWER(role) LIKE '%president%'")
                        ->exists();
                    if ($hasPresidentRole) {
                        $isAllowedRenew = true;
                    }
                }
            }
            if (!$isAllowedRenew) {
                return redirect()->route('dashboard')->with('error', 'Only Senior Adviser, Junior Adviser, President, or Dean can access renewal.');
            }

            // Get organization from session or user's active organization (fix: $user->organization is null for advisers, use pivot)
            $currentOrgId = session('current_organization_id');
            $organization = $currentOrgId
                ? $user->organizations()->find($currentOrgId)
                : ($user->activeOrganizations()->first() ?? $user->organizations()->first());

            // If still no organization, redirect back with error
            if (! $organization) {
                return redirect()->route('dashboard')->with('error', 'No organization found. Please contact administrator.');
            }

            $departments = \App\Models\Department::all();
            $hasMotherOrg = false;
            try {
                $hasMotherOrg = \App\Models\Organization::where('department_id', $organization->department_id)
                    ->where('type', 'Mother Org')
                    ->where('status', 'Approved')
                    ->exists();
            } catch (\Throwable $e) {
                $hasMotherOrg = false;
            }

            // Display Dean is always the OSAS-assigned college Dean (active
            // super_admin of the department) — never derived from the
            // Certification of Officers or stale org-level rows. Fallbacks
            // below only apply when no college Dean is set.
            $existingDean = null;
            if ($organization->department_id) {
                try {
                    $existingDean = \App\Models\User::where('department_id', $organization->department_id)
                        ->where('role', 'super_admin')
                        ->whereRaw('CAST(users.is_active AS INTEGER) = 1')
                        ->first();

                    if (! $existingDean) {
                        $existingDean = \App\Models\User::whereHas('organizations', function ($q) use ($organization) {
                            $q->where('organization_id', $organization->id)
                              ->whereRaw("LOWER(role) LIKE '%dean%'");
                        })->first();
                    }

                    if (! $existingDean) {
                        $existingDean = \App\Models\User::where('department_id', $organization->department_id)
                            ->where('role', 'super_admin')
                            ->whereRaw("LOWER(COALESCE(temp_position,'Dean')) LIKE '%dean%'")
                            ->first();
                    }

                    if (! $existingDean) {
                        $existingDean = \App\Models\User::where('department_id', $organization->department_id)
                            ->where('role', 'super_admin')
                            ->first();
                    }
                } catch (\Throwable $e) {
                    $existingDean = null;
                }
            }

            // Prefill renewer position from current org membership
            $rawPos = null;
            if ($currentOrgId && method_exists($user, 'getPositionInOrganization')) {
                try {
                    $rawPos = $user->getPositionInOrganization($currentOrgId);
                } catch (\Throwable $e) {
                    $rawPos = null;
                }
            }
            if (! $rawPos) {
                try {
                    $pivot = \Illuminate\Support\Facades\DB::table('organization_user')
                        ->where('organization_id', $organization->id)
                        ->where('user_id', $user->id)
                        ->orderByDesc('id')
                        ->first();
                    $rawPos = $pivot->role ?? null;
                } catch (\Throwable $e) {
                    $rawPos = null;
                }
            }
            $renewerPosition = null;
            if ($rawPos) {
                $low = strtolower(trim($rawPos));
                if (str_contains($low, 'president')) {
                    $renewerPosition = 'Organization President';
                } elseif (str_contains($low, 'adviser') || str_contains($low, 'advisor') || str_contains($low, 'junior') || str_contains($low, 'senior')) {
                    $renewerPosition = 'Organization Adviser';
                }
            }

            $accreditationStatus = 'none';
            $daysUntil = null;
            $currentPeriod = null;
            $renewBlockedReason = null;
            $renewBlockedTitle = null;
            $renewBlockedMsg = null;
            try {
                if (method_exists($organization, 'accreditationStatus')) {
                    $accreditationStatus = $organization->accreditationStatus();
                }
                if (method_exists($organization, 'daysUntilExpiration')) {
                    $daysUntil = $organization->daysUntilExpiration();
                }
                if (method_exists(\App\Models\Organization::class, 'currentAccreditationPeriod')) {
                    $currentPeriod = \App\Models\Organization::currentAccreditationPeriod();
                }
                // Centralized helper for consistent messages (same as sidebar/dashboard) — now includes global + per-org Start Renewal gates.
                if (method_exists(\App\Models\Organization::class, 'renewBlockInfo')) {
                    $blockInfo = \App\Models\Organization::renewBlockInfo($organization);
                    if ($blockInfo['blocked']) {
                        $title = $blockInfo['title'] ?? '';
                        if ($title === 'Renewal Period Not Set') {
                            $renewBlockedReason = 'no_period';
                        } elseif ($title === 'Renewal Not Yet Started') {
                            $renewBlockedReason = 'not_started_global';
                        } elseif ($title === 'Renewal Not Yet Opened For Your Organization') {
                            $renewBlockedReason = 'not_started_org';
                        } else {
                            $renewBlockedReason = 'still_active';
                        }
                        $renewBlockedTitle = $blockInfo['title'];
                        $renewBlockedMsg = $blockInfo['message'];
                    }
                } else {
                    if (! $currentPeriod || ! $organization->accreditation_started_at || ! $organization->accreditation_expires_at) {
                        $renewBlockedReason = 'no_period';
                        $renewBlockedTitle = 'Renewal Period Not Set';
                        $renewBlockedMsg = 'The accreditation renewal period has not been set or opened yet by the OSAS. Please wait for an official announcement or ask OSAS to click Apply + Start Renewal.';
                    } elseif (method_exists($organization, 'isExpired') && ! $organization->isExpired()) {
                        $renewBlockedReason = 'still_active';
                        $renewBlockedTitle = 'Accreditation Still Active';
                        $renewBlockedMsg = 'Your organization accreditation is still active until '.($organization->accreditation_expires_at?->format('M d, Y') ?? 'expiry').'. Renewal is not yet open.';
                    }
                }
            } catch (\Throwable $e) {
                $renewBlockedReason = null;
                $renewBlockedTitle = null;
                $renewBlockedMsg = null;
            }

            $html = view('admin.renew.index', compact('organization', 'departments', 'hasMotherOrg', 'existingDean', 'renewerPosition', 'accreditationStatus', 'daysUntil', 'currentPeriod', 'renewBlockedReason', 'renewBlockedTitle', 'renewBlockedMsg'))->render();
            return response($html);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Renew index failed: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return redirect()->route('dashboard')->with('error', 'Renew page temporarily unavailable: '.$e->getMessage());
        }
    }

    public function submit(Request $request)
    {
        set_time_limit(0);

        $user = Auth::user();
        $isAllowedRenew = false;
        if ($user->role === 'super_admin') {
            $isAllowedRenew = true;
        } else {
            $hasAdviserRole = DB::table('organization_user')
                ->where('user_id', $user->id)
                ->whereIn('role', ['Senior Adviser', 'Junior Adviser', 'Adviser', 'President', 'Organization President'])
                ->exists();
            if ($hasAdviserRole) {
                $isAllowedRenew = true;
            } else {
                // Fallback for case-insensitive President variants
                $hasPresidentRole = DB::table('organization_user')
                    ->where('user_id', $user->id)
                    ->whereRaw("LOWER(role) LIKE '%president%'")
                    ->exists();
                if ($hasPresidentRole) {
                    $isAllowedRenew = true;
                }
            }
        }
        if (!$isAllowedRenew) {
            return response()->json(['success' => false, 'message' => 'Only Senior Adviser, Junior Adviser, President, or Dean can submit renewal.'], 403);
        }
        $currentOrgId = session('current_organization_id');
        $organization = $currentOrgId
            ? $user->organizations()->find($currentOrgId)
            : ($user->activeOrganizations()->first() ?? $user->organizations()->first());

        if (! $organization) {
            return response()->json(['success' => false, 'message' => 'Organization context lost.'], 400);
        }

        $hasPendingRenewal = ApplyOrg::where('organization_id', $organization->id)
            ->where('application_type', 'renew')
            ->where('status', 'For-Checking')
            ->where('auto_generated', DB::raw('FALSE'))
            ->exists();

        if ($hasPendingRenewal) {
            return response()->json([
                'success' => false,
                'message' => 'This organization already has a pending renewal request.',
            ], 409);
        }

        // Renewal window: global period + global Start Renewal pressed + per-org queue exists + expiry.
        // Blocked until OSAS clicks Start Renewal (global and per-org) — user request: di muna makakapag renew admin.
        $period = \App\Models\Organization::currentAccreditationPeriod();
        if (! $period || ! $organization->accreditation_started_at || ! $organization->accreditation_expires_at) {
            return response()->json([
                'success' => false,
                'message' => 'No renew period is set',
            ], 422);
        }

        // Global gate: at least one auto_generated pending renewal must exist globally.
        $hasGlobalStart = ApplyOrg::where('application_type', 'renew')
            ->where('auto_generated', DB::raw('TRUE'))
            ->exists();
        if (! $hasGlobalStart) {
            return response()->json([
                'success' => false,
                'message' => 'Renewal not yet started by OSAS. Please wait for OSAS to click Start Renewal.',
            ], 422);
        }

        // Per-org gate: this organization's queue must have been opened by OSAS.
        $hasOrgQueue = ApplyOrg::where('organization_id', $organization->id)
            ->where('application_type', 'renew')
            ->where('auto_generated', DB::raw('TRUE'))
            ->where('status', 'Pending')
            ->exists();
        if (! $hasOrgQueue) {
            return response()->json([
                'success' => false,
                'message' => 'Your organization\'s renewal queue hasn\'t been opened by OSAS yet. Please ask OSAS to generate renewals for expired organizations.',
            ], 422);
        }

        if (! $organization->isExpired()) {
            return response()->json([
                'success' => false,
                'message' => 'Renewal is not yet open — accreditation still active.',
            ], 422);
        }

        $rules = [
            'renewer_student_id' => ['nullable','string','max:50','regex:/^(\d{4}-\d{4}|\d{2}-\d{4}-\d{2,4})$/'],
            'renewer_first_name' => 'required|string|max:255',
            'renewer_last_name' => 'required|string|max:255',
            'renewer_email' => 'required|email|max:255',
            'renewer_position' => 'nullable|in:Organization Adviser,Organization President,President,Adviser,Senior Adviser,Junior Adviser,Organization Senior Adviser,Organization Junior Adviser',
            'officers' => 'required|array',
            'officers.*.first_name' => 'required|string|max:255',
            'officers.*.middle_name' => 'nullable|string|max:255',
            'officers.*.middle_initial' => 'nullable|string|max:10',
            'officers.*.last_name' => 'required|string|max:255',
            'officers.*.email' => 'required|email|max:255',
            'officers.*.student_id' => ['nullable','string','max:50','regex:/^\d{4}-\d{4}$/'],
            'officers.*.id_number' => ['nullable','string','max:50','regex:/^\d{2}-\d{4}-\d{4}$/'],
            'officers.*.department_id' => 'nullable|exists:departments,id',
            'officers.*.course' => 'nullable|string|max:255',
            'officers.*.year_level' => 'nullable|string|max:50',
            'officers.*.section' => 'nullable|string|max:50',
            'officers.*.suffix' => 'nullable|string|max:50',
            'officers.*.position_name' => 'nullable|string|max:100',
            'officers.*.position' => 'nullable|string|max:50',
            'adviser_commitment' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'application_form' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'certification_of_officers' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'plan_of_activities' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'constitution_bylaws' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'certification_dean' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'financial_report' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'renewal_form' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'accomplishment_reports' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'list_of_members' => 'nullable|array',
            'list_of_members.*' => 'file|mimes:pdf,jpg,jpeg,png|max:10240',
            'other_files' => 'nullable|array|max:20',
            'other_files.*' => 'file|mimes:pdf,jpg,jpeg,png|max:10240',
        ];

        // Membership roster is mandatory for Mother Org only (Non-Academic optional, Sub Org may rely on Mother Org)
        $orgTypeNormalized = $organization->type === 'Sub-Org' ? 'Sub Org' : $organization->type;
        if ($orgTypeNormalized === 'Mother Org') {
            $rules['list_of_members'] = 'required|array';
        }

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), $rules);

        $validator->after(function ($validator) use ($request) {
            foreach ((array) $request->input('officers', []) as $key => $officer) {
                $hasName = ! empty($officer['first_name']) || ! empty($officer['last_name']);
                $hasContact = ! empty($officer['email'])
                    || ! empty($officer['student_id'])
                    || ! empty($officer['id_number']);
                if ($hasName && ! $hasContact) {
                    $validator->errors()->add(
                        "officers.{$key}.email",
                        'This officer needs an email or an ID number.'
                    );
                }
            }
        });

        // Hard block: Cannot renew Sub Org without an approved Mother Org in the same department (mirrors Apply)
        $validator->after(function ($validator) use ($organization) {
            $orgTypeForMother = $organization->type === 'Sub-Org' ? 'Sub Org' : $organization->type;
            if ($orgTypeForMother === 'Sub Org' && $organization->department_id) {
                $deptId = $organization->department_id;
                if ($deptId != 11) {
                    $hasMother = \App\Models\Organization::where('department_id', $deptId)
                        ->where('type', 'Mother Org')
                        ->where('status', 'Approved')
                        ->exists();
                    if (! $hasMother) {
                        $validator->errors()->add('organization_type', 'Cannot create Sub Org without an approved Mother Org in this department. Please create Mother Org first.');
                        $validator->errors()->add('department_id', 'No Mother Org found in this department.');
                    }
                }
            }
        });

        $validated = $validator->validate();

        // Position is derived from the renewer ID format — never trusted
        // from the client. 8 digits (0000-0000) => President, 10 digits
        // (00-0000-0000) => Adviser. A posted position that conflicts with
        // the ID format is rejected as tampering. With no ID, fall back to
        // the user's organization role (pivot) as before.
        $renewerIdForPosition = $validated['renewer_student_id'] ?? $request->input('renewer_student_id');
        $derivedRenewerPosition = \App\Http\Controllers\ApplyOrgController::positionFromIdFormat($renewerIdForPosition);
        $postedRenewerPosition = $request->input('renewer_position');
        if (! empty($postedRenewerPosition) && ! empty($derivedRenewerPosition)
            && $postedRenewerPosition !== $derivedRenewerPosition) {
            return response()->json([
                'success' => false,
                'message' => 'Renewer position does not match the ID format.',
            ], 422);
        }
        $validated['renewer_position'] = $derivedRenewerPosition;

        // Normalize legacy simple values to 2-option canonical (Organization Adviser / Organization President)
        if ($validated['renewer_position'] === 'President') {
            $validated['renewer_position'] = 'Organization President';
        } elseif (in_array($validated['renewer_position'], ['Adviser', 'Senior Adviser', 'Junior Adviser', 'Organization Senior Adviser', 'Organization Junior Adviser', 'Organization Adviser'], true)) {
            $validated['renewer_position'] = 'Organization Adviser';
        }

        // If renewer_position is still empty, look up the user's role in the organization
        if (empty($validated['renewer_position'])) {
            $orgUserPivot = DB::table('organization_user')
                ->where('user_id', $user->id)
                ->where('organization_id', $organization->id)
                ->first();
            $pivotRole = $orgUserPivot->role ?? '';
            if (stripos($pivotRole, 'president') !== false) {
                $validated['renewer_position'] = 'Organization President';
            } else {
                // Any adviser variant collapses to generic Organization Adviser
                $validated['renewer_position'] = 'Organization Adviser';
            }
        }

        $originalRequesterPosition = $validated['renewer_position'];
        $officers = $validated['officers'];

        // --- DUPLICATE CHECK (student_id-based) — email mutable when same student_id ---
        $studentIdsInForm = [];
        $adviserIdsInForm = [];
        $emailToStudentMap = [];

        $requesterEmail = $validated['renewer_email'] ?? $request->input('renewer_email');
        $requesterPosition = $originalRequesterPosition ?? $validated['renewer_position'] ?? $request->input('renewer_position');
        $directReqIdMap = $validated['renewer_student_id'] ?? $request->input('renewer_student_id') ?? null;
        $requesterStudentIdForMap = $directReqIdMap ?? null;
        if (!$requesterStudentIdForMap) {
            if (($requesterPosition === 'Organization President') && isset($officers['president']['student_id'])) {
                $requesterStudentIdForMap = $officers['president']['student_id'] ?? null;
            } elseif (in_array($requesterPosition, ['Organization Adviser','Organization Senior Adviser','Organization Junior Adviser'], true)) {
                $adviserKeyTmp = $requesterPosition === 'Organization Senior Adviser' ? 'senior_adviser' : ($requesterPosition === 'Organization Junior Adviser' ? 'junior_adviser' : (isset($officers['senior_adviser']) ? 'senior_adviser' : (isset($officers['junior_adviser']) ? 'junior_adviser' : null)));
                if ($adviserKeyTmp && isset($officers[$adviserKeyTmp]['id_number'])) $requesterStudentIdForMap = $officers[$adviserKeyTmp]['id_number'];
            }
        }
        if ($requesterEmail) {
            $emailToStudentMap[strtolower($requesterEmail)] = $requesterStudentIdForMap;
        }

        // Dean is OSAS-managed (one active Dean per college) and autofilled
        // server-side from the organization's department — no manual Dean input.
        $deanDataInput = null;
        $deanIdFromForm = null;
        $deanEmailFromForm = null;

        $assignedRenewDean = $organization->department_id
            ? User::where('role', 'super_admin')
                ->where('department_id', $organization->department_id)
                ->where('is_active', DB::raw('TRUE'))
                ->first()
            : null;
        if (! $assignedRenewDean) {
            return response()->json([
                'success' => false,
                'message' => 'No Dean is assigned for this college yet. Please contact OSAS to set the Dean first.',
            ], 422);
        }

        foreach ($officers as $positionKey => $officerData) {
            $studentId = $officerData['student_id'] ?? null;
            $idNumber = $officerData['id_number'] ?? null;
            $email = $officerData['email'] ?? null;

            if ($studentId) {
                if (in_array($studentId, $studentIdsInForm)) {
                    return response()->json([
                        'success' => false,
                        'message' => "Duplicate Student ID detected: '{$studentId}' is entered multiple times.",
                    ]);
                }
                $studentIdsInForm[] = $studentId;
            }

            if ($idNumber) {
                if (in_array($idNumber, $adviserIdsInForm)) {
                    return response()->json([
                        'success' => false,
                        'message' => "Duplicate ID Number detected: '{$idNumber}' is entered multiple times.",
                    ]);
                }
                $adviserIdsInForm[] = $idNumber;
            }

            if ($email) {
                $emailLower = strtolower($email);
                $linkedId = $studentId ?? $idNumber ?? null;
                if (isset($emailToStudentMap[$emailLower])) {
                    $existingId = $emailToStudentMap[$emailLower];
                    if ($existingId !== $linkedId && !($existingId === null && $linkedId === null)) {
                        $isRequesterMatch = false;
                        if ($requesterEmail && $emailLower === strtolower($requesterEmail)) {
                            $isRequesterPresident = ($positionKey === 'president' && ($requesterPosition === 'Organization President' || ! $requesterPosition));
                            $isRequesterAdviser = (in_array($positionKey, ['senior_adviser', 'junior_adviser'], true) && $requesterPosition === 'Organization Adviser');
                            $isLegacyAdviserMatch = (in_array($positionKey, ['senior_adviser', 'junior_adviser'], true) && in_array($requesterPosition, ['Organization Senior Adviser', 'Organization Junior Adviser'], true));
                            $isRequesterMatch = ($isRequesterPresident || $isRequesterAdviser || $isLegacyAdviserMatch);
                        }
                        if (!$isRequesterMatch) {
                            return response()->json([
                                'success' => false,
                                'message' => "Duplicate Email detected: '{$email}' is entered for different IDs ({$existingId} vs {$linkedId}).",
                            ]);
                        }
                    }
                } else {
                    $emailToStudentMap[$emailLower] = $linkedId;
                }
            }
        }

        foreach ($studentIdsInForm as $studentId) {
            $existingStudent = User::where('student_id', $studentId)
                ->where(function ($query) {
                    $query->where('is_active', DB::raw('TRUE'))
                        ->orWhereNotNull('apply_org_id');
                })
                ->first();
            if ($existingStudent) {
                // Renew exemption: if this student_id is a COMPLETED election winner for THIS org,
                // allow re-registration (adviser already approved via Settings -> completed).
                // Uses election_officer_transitions.winners_snapshot per user request.
                if (ElectionOfficerTransition::hasCompletedWinnerWithStudentId($studentId, (int) $organization->id)) {
                    Log::info("[Renew] Student ID winner exempt for org {$organization->id}: {$studentId}");
                    continue;
                }

                // COMELEC exemption: COMELEC does not undergo student elections.
                // Check if this student is an existing COMELEC officer in organization_user for THIS org,
                // OR is entered in the COMELEC slot in the renewal form and belongs to this org/dept.
                $isExistingComelecForOrg = DB::table('organization_user')
                    ->where('organization_id', $organization->id)
                    ->where('user_id', $existingStudent->id)
                    ->where(function ($q) {
                        $q->whereRaw("UPPER(role) = 'COMELEC'")
                          ->orWhereRaw("LOWER(role) LIKE '%comelec%'");
                    })
                    ->exists();

                $isComelecSlot = false;
                foreach ($officers as $posKey => $officerData) {
                    $id = $officerData['student_id'] ?? $officerData['id_number'] ?? null;
                    if ($id === $studentId && ($posKey === 'comelec' || stripos((string) ($officerData['position'] ?? ''), 'comelec') !== false)) {
                        $isComelecSlot = true;
                        break;
                    }
                }

                $isSameOrgUser = DB::table('organization_user')
                    ->where('organization_id', $organization->id)
                    ->where('user_id', $existingStudent->id)
                    ->exists();

                if ($isExistingComelecForOrg || ($isComelecSlot && ($isSameOrgUser || (int) $existingStudent->department_id === (int) $organization->department_id))) {
                    Log::info("[Renew] COMELEC Student ID exempt for org {$organization->id}: {$studentId}");
                    continue;
                }

                return response()->json([
                    'success' => false,
                    'message' => "Student ID '{$studentId}' is already registered as an active officer or has a pending application in another organization.",
                ]);
            }
        }

        // $deanIdFromForm / $deanEmailFromForm stay null (OSAS-assigned Dean, no form input).

        foreach ($adviserIdsInForm as $adviserId) {
            $normAdviserIdDigits = preg_replace('/\D/', '', (string) $adviserId);
            $existingUser = User::where('student_id', $adviserId)
                ->orWhere(function ($query) use ($normAdviserIdDigits) {
                    if ($normAdviserIdDigits !== '') {
                        $query->whereRaw("REPLACE(REPLACE(student_id, '-', ''), ' ', '') = ?", [$normAdviserIdDigits]);
                    }
                })
                ->where(function ($query) {
                    $query->where('is_active', DB::raw('TRUE'))
                        ->orWhereNotNull('apply_org_id');
                })
                ->first();

            if ($existingUser) {
                // Dean exemption: Deans do not undergo student elections.
                // If this ID is the Dean in the form, and they belong to the same department / organization as Dean, allow renewal.
                $isDeanSlot = ($deanIdFromForm !== null && (
                    $deanIdFromForm === $adviserId ||
                    ($normAdviserIdDigits !== '' && preg_replace('/\D/', '', (string) $deanIdFromForm) === $normAdviserIdDigits)
                ));

                if ($isDeanSlot) {
                    $isSameDepartment = ($organization->department_id && (int) $existingUser->department_id === (int) $organization->department_id)
                        || ($organization->department_id === null || $existingUser->department_id === null);

                    $isExistingDeanForOrg = DB::table('organization_user')
                        ->where('organization_id', $organization->id)
                        ->where('user_id', $existingUser->id)
                        ->whereRaw("LOWER(role) LIKE '%dean%'")
                        ->exists();

                    $isSuperAdminOrDean = in_array($existingUser->role, ['super_admin'], true)
                        || stripos((string) $existingUser->temp_position, 'dean') !== false
                        || (method_exists($existingUser, 'hasDeanMembership') && $existingUser->hasDeanMembership());

                    if ($isSameDepartment || $isExistingDeanForOrg || $isSuperAdminOrDean) {
                        Log::info("[Renew] Dean ID exempt for org {$organization->id} in department {$organization->department_id}: {$adviserId}");
                        continue;
                    }
                }

                // Adviser exemption: Advisers do not undergo student elections.
                // If this ID belongs to an existing active/registered adviser of THIS organization, allow renewal.
                $isExistingOrgAdviser = DB::table('organization_user')
                    ->where('organization_id', $organization->id)
                    ->where('user_id', $existingUser->id)
                    ->where(function ($q) {
                        $q->whereRaw("LOWER(role) LIKE '%adviser%'")
                          ->orWhereRaw("LOWER(role) LIKE '%advisor%'");
                    })
                    ->exists();

                $isLoggedInAdviser = (Auth::id() && (int) $existingUser->id === (int) Auth::id() && (
                    Auth::user()->isAdviser() ||
                    DB::table('organization_user')
                        ->where('organization_id', $organization->id)
                        ->where('user_id', Auth::id())
                        ->where(function ($q) {
                            $q->whereRaw("LOWER(role) LIKE '%adviser%'")
                              ->orWhereRaw("LOWER(role) LIKE '%advisor%'");
                        })
                        ->exists()
                ));

                if ($isExistingOrgAdviser || $isLoggedInAdviser) {
                    Log::info("[Renew] Adviser ID exempt for org {$organization->id}: {$adviserId}");
                    continue;
                }

                // COMELEC ID exemption (if entered via id_number in staff/officer slot):
                $isExistingOrgComelec = DB::table('organization_user')
                    ->where('organization_id', $organization->id)
                    ->where('user_id', $existingUser->id)
                    ->where(function ($q) {
                        $q->whereRaw("UPPER(role) = 'COMELEC'")
                          ->orWhereRaw("LOWER(role) LIKE '%comelec%'");
                    })
                    ->exists();

                if ($isExistingOrgComelec) {
                    Log::info("[Renew] COMELEC ID exempt for org {$organization->id}: {$adviserId}");
                    continue;
                }

                return response()->json([
                    'success' => false,
                    'message' => "Adviser/Dean ID Number '{$adviserId}' is already registered as an active user or has a pending application in another organization.",
                ]);
            }
        }

        foreach ($emailToStudentMap as $emailLower => $linkedId) {
            if ($requesterEmail && strtolower($emailLower) === strtolower($requesterEmail)) {
                continue;
            }
            $existing = User::where('email', $emailLower)
                ->where(function ($query) {
                    $query->where('is_active', DB::raw('TRUE'))
                        ->orWhereNotNull('apply_org_id');
                })
                ->first();
            if ($existing) {
                $existingId = $existing->student_id;
                // Allow same student_id (dash/space-insensitive) — covers winner email update + normal mutable email
                if ($linkedId !== null && $existingId !== null) {
                    $normLinked = strtolower(str_replace(['-', ' '], '', trim((string) $linkedId)));
                    $normExisting = strtolower(str_replace(['-', ' '], '', trim((string) $existingId)));
                    if ($normLinked !== '' && $normLinked === $normExisting) {
                        continue;
                    }
                    // Renew winner exemption: same student_id already handled above, but log winner case explicitly
                    if (ElectionOfficerTransition::hasCompletedWinnerWithStudentId($linkedId, (int) $organization->id)
                        && $normLinked === $normExisting) {
                        Log::info("[Renew] Email winner exempt for org {$organization->id}: {$emailLower} -> {$linkedId}");
                        continue;
                    }
                    if (strtolower($linkedId) === strtolower($existingId)) {
                        continue;
                    }
                }

                // Dean Email Exemption: Same department dean with matching/updatable ID
                $isDeanEmail = ($deanEmailFromForm !== null && strtolower($emailLower) === strtolower($deanEmailFromForm));
                if ($isDeanEmail) {
                    $isSameDeptDean = ($organization->department_id && (int) $existing->department_id === (int) $organization->department_id)
                        || ($organization->department_id === null || $existing->department_id === null);

                    $isOrgDean = DB::table('organization_user')
                        ->where('organization_id', $organization->id)
                        ->where('user_id', $existing->id)
                        ->whereRaw("LOWER(role) LIKE '%dean%'")
                        ->exists();

                    $isSuperAdminOrDeanUser = in_array($existing->role, ['super_admin'], true)
                        || stripos((string) $existing->temp_position, 'dean') !== false
                        || (method_exists($existing, 'hasDeanMembership') && $existing->hasDeanMembership());

                    if ($isSameDeptDean || $isOrgDean || $isSuperAdminOrDeanUser) {
                        if ($linkedId === null || $existingId === null) {
                            Log::info("[Renew] Dean Email exempt for org {$organization->id} (same dean): {$emailLower}");
                            continue;
                        }
                        $nl = strtolower(str_replace(['-', ' '], '', trim((string) $linkedId)));
                        $ne = strtolower(str_replace(['-', ' '], '', trim((string) $existingId)));
                        if ($nl === '' || $ne === '' || $nl === $ne) {
                            Log::info("[Renew] Dean Email exempt for org {$organization->id}: {$emailLower} -> {$linkedId}");
                            continue;
                        }
                    }
                }

                // Adviser Email Exemption: Same org adviser with matching/updatable ID
                $isExistingAdviser = DB::table('organization_user')
                    ->where('organization_id', $organization->id)
                    ->where('user_id', $existing->id)
                    ->where(function ($q) {
                        $q->whereRaw("LOWER(role) LIKE '%adviser%'")
                          ->orWhereRaw("LOWER(role) LIKE '%advisor%'");
                    })
                    ->exists();

                if ($isExistingAdviser) {
                    if ($linkedId === null || $existingId === null) {
                        Log::info("[Renew] Adviser Email exempt for org {$organization->id} (same adviser): {$emailLower}");
                        continue;
                    }
                    $nl = strtolower(str_replace(['-', ' '], '', trim((string) $linkedId)));
                    $ne = strtolower(str_replace(['-', ' '], '', trim((string) $existingId)));
                    if ($nl === '' || $ne === '' || $nl === $ne) {
                        Log::info("[Renew] Adviser Email exempt for org {$organization->id}: {$emailLower} -> {$linkedId}");
                        continue;
                    }
                }

                // COMELEC Email Exemption: Same org COMELEC officer with matching/updatable ID
                $isExistingComelec = DB::table('organization_user')
                    ->where('organization_id', $organization->id)
                    ->where('user_id', $existing->id)
                    ->where(function ($q) {
                        $q->whereRaw("UPPER(role) = 'COMELEC'")
                          ->orWhereRaw("LOWER(role) LIKE '%comelec%'");
                    })
                    ->exists();

                $isComelecEmailInForm = false;
                foreach ($officers as $posKey => $officerData) {
                    $offEmail = $officerData['email'] ?? null;
                    if ($offEmail && strtolower($offEmail) === strtolower($emailLower)) {
                        if ($posKey === 'comelec' || stripos((string) ($officerData['position'] ?? ''), 'comelec') !== false) {
                            $isComelecEmailInForm = true;
                            break;
                        }
                    }
                }

                if ($isExistingComelec || ($isComelecEmailInForm && (int) $existing->department_id === (int) $organization->department_id)) {
                    if ($linkedId === null || $existingId === null) {
                        Log::info("[Renew] COMELEC Email exempt for org {$organization->id} (same comelec): {$emailLower}");
                        continue;
                    }
                    $nl = strtolower(str_replace(['-', ' '], '', trim((string) $linkedId)));
                    $ne = strtolower(str_replace(['-', ' '], '', trim((string) $existingId)));
                    if ($nl === '' || $ne === '' || $nl === $ne) {
                        Log::info("[Renew] COMELEC Email exempt for org {$organization->id}: {$emailLower} -> {$linkedId}");
                        continue;
                    }
                }

                if ($linkedId === null && $existingId === null) {
                    return response()->json([
                        'success' => false,
                        'message' => "Email '{$emailLower}' is already registered or has a pending application in another organization.",
                    ]);
                }
                if ($linkedId === null || $existingId === null || strtolower($linkedId) !== strtolower($existingId)) {
                    // Final dash-insensitive check before blocking — if both normalize same, allow (winner or same person)
                    if ($linkedId !== null && $existingId !== null) {
                        $nl = strtolower(str_replace(['-', ' '], '', trim((string) $linkedId)));
                        $ne = strtolower(str_replace(['-', ' '], '', trim((string) $existingId)));
                        if ($nl !== '' && $nl === $ne) {
                            continue;
                        }
                    }
                    return response()->json([
                        'success' => false,
                        'message' => "Email '{$emailLower}' is already registered for a different student ID. Use the correct student ID to update email.",
                    ]);
                }
            }
        }

        // Prepare tmp files for async R2 upload (fast local, then queue) — like Apply
        $storageDisk = storage_disk();
        $tmpPaths = [];
        $memberTmpFiles = [];
        $otherTmpFiles = [];
        $localTmpBase = 'tmp/system-requests/' . Str::uuid();
        $uploadedPaths = []; // will be filled by job after R2
        $uploadedDocuments = [];
        try {
            $documentFields = [
                'adviser_commitment',
                'application_form',
                'certification_of_officers',
                'plan_of_activities',
                'constitution_bylaws',
                'certification_dean',
                'financial_report',
                'renewal_form',
                'accomplishment_reports',
            ];

            foreach ($documentFields as $field) {
                if ($request->hasFile($field)) {
                    $file = $request->file($field);
                    $tmpPath = $file->store($localTmpBase, 'local');
                    $tmpPaths[$field] = ['tmp' => $tmpPath, 'name' => $file->getClientOriginalName()];
                }
            }

            if ($request->hasFile('list_of_members')) {
                foreach ($request->file('list_of_members') as $file) {
                    $tmpPath = $file->store($localTmpBase, 'local');
                    $memberTmpFiles[] = ['tmp' => $tmpPath, 'name' => $file->getClientOriginalName()];
                }
            }

            if ($request->hasFile('other_files')) {
                foreach ($request->file('other_files') as $file) {
                    $tmpPath = $file->store($localTmpBase, 'local');
                    $otherTmpFiles[] = ['tmp' => $tmpPath, 'name' => $file->getClientOriginalName()];
                }
            }
        } catch (\Throwable $e) {
            foreach (array_merge(array_column($tmpPaths, 'tmp'), array_column($memberTmpFiles, 'tmp'), array_column($otherTmpFiles, 'tmp')) as $tmp) {
                try { Storage::disk('local')->delete($tmp); } catch (\Throwable $ex) {}
            }
            Log::error('Tmp store failed in RenewController: '.$e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to process uploads: '.$e->getMessage()], 500);
        }

        // Register analyses in the same transaction as the application association.
        $dispatchCommands = [];
        $transactionCommitted = false;
        DB::beginTransaction();
        try {
            $currentPeriod = Organization::currentAccreditationPeriod();
            $currentSchoolYear = $currentPeriod
                ? Organization::accreditationSchoolYear($currentPeriod->started_at, $currentPeriod->expires_at)
                : null;

            // Async: store tmp paths so OSAS sees Pending immediately, job will UPDATE to R2
            $renewalData = [
                'organization_id' => $organization->id,
                'organization_name' => $organization->name,
                'organization_acronym' => $organization->org_acronym,
                'organization_type' => $orgTypeNormalized,
                'department_id' => $organization->department_id,
                'request_type' => 'request',
                'application_type' => 'renew',
                'accreditation_year' => $currentSchoolYear,
                'requester_first_name' => $validated['renewer_first_name'],
                'requester_last_name' => $validated['renewer_last_name'],
                'requester_email' => $validated['renewer_email'],
                'requester_position' => $validated['renewer_position'],
                'status' => 'For-Checking',
                'current_step' => 6,
                'adviser_commitment' => $tmpPaths['adviser_commitment']['tmp'] ?? null,
                'application_form' => $tmpPaths['application_form']['tmp'] ?? null,
                'certification_of_officers' => $tmpPaths['certification_of_officers']['tmp'] ?? null,
                'plan_of_activities' => $tmpPaths['plan_of_activities']['tmp'] ?? null,
                'constitution_bylaws' => $tmpPaths['constitution_bylaws']['tmp'] ?? null,
                'certification_dean' => $tmpPaths['certification_dean']['tmp'] ?? null,
                'financial_report' => $tmpPaths['financial_report']['tmp'] ?? null,
                'renewal_form' => $tmpPaths['renewal_form']['tmp'] ?? null,
                'accomplishment_reports' => $tmpPaths['accomplishment_reports']['tmp'] ?? null,
                'list_of_members' => !empty($memberTmpFiles) ? json_encode(array_map(fn($e) => ['path' => $e['tmp'], 'name' => $e['name']], $memberTmpFiles), JSON_THROW_ON_ERROR) : null,
                'other_files' => !empty($otherTmpFiles) ? json_encode(array_map(fn($e) => ['path' => $e['tmp'], 'name' => $e['name']], $otherTmpFiles), JSON_THROW_ON_ERROR) : null,
            ];

            // Find existing pending renewal placeholder or create if not present
            $autoPlaceholder = ApplyOrg::where('organization_id', $organization->id)
                ->where('application_type', 'renew')
                ->whereIn('status', ['Pending', 'For-Checking'])
                ->lockForUpdate()
                ->latest('id')
                ->first();

            if (! $autoPlaceholder) {
                $applyOrg = ApplyOrg::create($renewalData + ['auto_generated' => false, 'admin_notes' => null]);
            } else {
                $autoPlaceholder->update($renewalData + ['auto_generated' => false, 'admin_notes' => null]);
                $applyOrg = $autoPlaceholder->fresh();
            }

            // Save requester to users table if not exists (mirrors ApplyOrgController)
            // Use original adviser variant for officer slot sync, collapsed 'Adviser' for storage handled above
            $requesterEmail = $validated['renewer_email'];
            $requesterPositionForSync = $originalRequesterPosition ?? $validated['renewer_position'] ?? null;
            $requesterPosition = $validated['renewer_position'] ?? null;
            $requesterStudentId = null;
            $requesterCourse = null;
            $requesterYearLevel = null;
            $requesterSection = null;

            $directReqId = $validated['renewer_student_id'] ?? $request->input('renewer_student_id') ?? null;
            if ($requesterPositionForSync === 'Organization President') {
                if (isset($officers['president'])) {
                    $officers['president']['email'] = $requesterEmail;
                    if ($directReqId) $officers['president']['student_id'] = $directReqId;
                }
                $presData = $officers['president'] ?? [];
                $requesterStudentId = $directReqId ?? $presData['student_id'] ?? null;
                $requesterCourse = $presData['course'] ?? null;
                $requesterYearLevel = $presData['year_level'] ?? null;
                $requesterSection = $presData['section'] ?? null;
            } elseif (in_array($requesterPositionForSync, ['Organization Adviser', 'Organization Senior Adviser', 'Organization Junior Adviser'], true) || stripos((string) $requesterPositionForSync, 'adviser') !== false || stripos((string) $requesterPositionForSync, 'advisor') !== false) {
                // Generic Adviser (2-option) + legacy Senior/Junior: try senior then junior slot
                if ($requesterPositionForSync === 'Organization Senior Adviser') {
                    $adviserKey = 'senior_adviser';
                } elseif ($requesterPositionForSync === 'Organization Junior Adviser') {
                    $adviserKey = 'junior_adviser';
                } else {
                    $adviserKey = isset($officers['senior_adviser']) ? 'senior_adviser' : (isset($officers['junior_adviser']) ? 'junior_adviser' : null);
                }
                if ($adviserKey && isset($officers[$adviserKey])) {
                    $officers[$adviserKey]['email'] = $requesterEmail;
                    if ($directReqId) $officers[$adviserKey]['id_number'] = $directReqId;
                    $requesterStudentId = $directReqId ?? $officers[$adviserKey]['id_number'] ?? null;
                } else {
                    $requesterStudentId = $directReqId;
                }
            } else {
                $requesterStudentId = $directReqId;
            }

            // Student_id-first — email mutable
            $requesterUser = null;
            if ($requesterStudentId) {
                $requesterUser = User::where('student_id', $requesterStudentId)->first();
            }
            if (!$requesterUser && $requesterEmail) {
                $requesterUser = User::where('email', $requesterEmail)->first();
            }
            $reqTempPosition = ($requesterPositionForSync === 'Organization President') ? 'President' : 'Adviser';

            if (! $requesterUser) {
                User::create([
                    'student_id' => $requesterStudentId,
                    'first_name' => $validated['renewer_first_name'],
                    'last_name' => $validated['renewer_last_name'],
                    'email' => $requesterEmail,
                    'role' => 'admin',
                    'department_id' => $organization->department_id,
                    'course' => $requesterCourse,
                    'year_level' => $requesterYearLevel,
                    'section' => $requesterSection,
                    'password' => bcrypt(Str::random(16)),
                    'is_active' => false,
                    'apply_org_id' => $applyOrg->id,
                    'temp_position' => $reqTempPosition,
                ]);
            } else {
                $requesterUser->update([
                    'student_id' => $requesterStudentId ?? $requesterUser->student_id,
                    'email' => $requesterEmail ?? $requesterUser->email,
                    'first_name' => $validated['renewer_first_name'] ?? $requesterUser->first_name,
                    'last_name' => $validated['renewer_last_name'] ?? $requesterUser->last_name,
                    'course' => $requesterCourse ?? $requesterUser->course,
                    'year_level' => $requesterYearLevel ?? $requesterUser->year_level,
                    'section' => $requesterSection ?? $requesterUser->section,
                    'apply_org_id' => $applyOrg->id,
                    'department_id' => $organization->department_id,
                    'temp_position' => $reqTempPosition,
                ]);
            }

            // Dean is OSAS-assigned per college — already validated above, nothing to create here.

            // Save officers directly to users table (mirrors Apply)
            Log::info('Officers Payload received in RenewController:', ['officers' => $officers]);

            foreach ($officers as $positionKey => $officerData) {
                $studentId = $officerData['student_id'] ?? $officerData['id_number'] ?? null;
                $officerEmail = $officerData['email'] ?? null;

                if ($officerEmail || $studentId) {
                    $officerUser = null;
                    if ($studentId) {
                        $officerUser = User::where('student_id', $studentId)->first();
                    }
                    if (!$officerUser && $officerEmail) {
                        $officerUser = User::where('email', $officerEmail)->first();
                    }

                    $slotLabelMap = [
                        'president'           => 'President',
                        'vice_president'      => 'Vice President',
                        'secretary'           => 'Secretary',
                        'treasurer'           => 'Treasurer',
                        'auditor'             => 'Auditor',
                        'business_manager'    => 'Business Manager',
                        'pio'                 => 'Public Information Officer',
                        'event_coordinator'   => 'Event Coordinator',
                        'rep_1st_year'        => '1st Year Representative',
                        'rep_2nd_year'        => '2nd Year Representative',
                        'rep_3rd_year'        => '3rd Year Representative',
                        'rep_4th_year'        => '4th Year Representative',
                        'comelec'             => 'COMELEC',
                        'senior_adviser'      => 'Adviser',
                        'junior_adviser'      => 'Adviser',
                    ];
                    // Preserve verbatim position_name for custom slots — same as Apply (no Custom_1)
                    if (\App\Services\CertificationOfOfficersRoleSlotRegistry::isCustomSlot($positionKey)) {
                        $formattedPosition = trim((string) ($officerData['position_name'] ?? ''));
                        if ($formattedPosition === '' && !empty($officerData['position'])) {
                            $formattedPosition = trim((string) $officerData['position']);
                        }
                    } else {
                        $formattedPosition = $slotLabelMap[$positionKey] ?? self::sanitizeRole($positionKey);
                    }

                    // Backend split: 1 letter (even with ".") -> middle_initial, 2+ -> middle_name
                    $rawMiddle = trim((string) ($officerData['middle_name'] ?? $officerData['middle_initial'] ?? ''));
                    $cleanMiddle = str_replace(['.', ' '], '', $rawMiddle);
                    if ($rawMiddle === '') { $middleName = null; $middleInitial = null; }
                    elseif (mb_strlen($cleanMiddle) === 1) { $middleInitial = strtoupper($cleanMiddle); $middleName = null; }
                    else { $middleName = $rawMiddle; $middleInitial = null; }

                    if (! $officerUser) {
                        User::create([
                            'student_id' => $studentId,
                            'first_name' => $officerData['first_name'],
                            'middle_name' => $middleName,
                            'middle_initial' => $middleInitial,
                            'last_name' => $officerData['last_name'],
                            'suffix' => $officerData['suffix'] ?? null,
                            'email' => $officerEmail,
                            'course' => $officerData['course'] ?? null,
                            'year_level' => $officerData['year_level'] ?? null,
                            'section' => $officerData['section'] ?? null,
                            'role' => 'admin',
                            'department_id' => $officerData['department_id'] ?? $organization->department_id,
                            'password' => bcrypt(Str::random(16)),
                            'is_active' => false,
                            'apply_org_id' => $applyOrg->id,
                            'temp_position' => $formattedPosition,
                        ]);
                    } else {
                        $updateData = [
                            'apply_org_id' => $applyOrg->id,
                            'department_id' => $officerData['department_id'] ?? $organization->department_id,
                            'first_name' => $officerData['first_name'] ?? $officerUser->first_name,
                            'middle_name' => $middleName,
                            'middle_initial' => $middleInitial,
                            'last_name' => $officerData['last_name'] ?? $officerUser->last_name,
                            'suffix' => $officerData['suffix'] ?? $officerUser->suffix,
                            'course' => $officerData['course'] ?? $officerUser->course,
                            'year_level' => $officerData['year_level'] ?? $officerUser->year_level,
                            'section' => $officerData['section'] ?? $officerUser->section,
                            'temp_position' => $formattedPosition,
                        ];

                        // student_id is identity, email mutable
                        if ($studentId) {
                            $updateData['student_id'] = $studentId;
                        }
                        if ($officerEmail) {
                            $updateData['email'] = $officerEmail;
                        }

                        $officerUser->update($updateData);
                    }
                }
            }

            DB::commit();
            $transactionCommitted = true;

            // Dispatch async R2 upload — user gets instant 202 success, files go to Cloudflare R2 in background
            // Queue payloads must be serializable — strip UploadedFile objects and nested arrays
            $validatedForQueue = array_filter(
                $validated,
                fn ($v) => is_scalar($v) || $v === null
            );
            try {
                \App\Jobs\UploadRenewDocumentsJob::dispatch(
                    $applyOrg->id,
                    $tmpPaths,
                    $memberTmpFiles,
                    $otherTmpFiles,
                    $storageDisk,
                    $validatedForQueue,
                    $organization->id
                );
                Log::info("[Renew] Dispatched UploadRenewDocumentsJob for #{$applyOrg->id} with " . (count($tmpPaths) + count($memberTmpFiles) + count($otherTmpFiles)) . " tmp files");
            } catch (\Throwable $dispatchErr) {
                Log::error("[Renew] Failed to dispatch UploadRenewDocumentsJob: " . $dispatchErr->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'Successfully Submitted! Your renewal has been received. Please wait for OSAS approval through email.',
            ], 202);
        } catch (\Throwable $e) {
            if (! $transactionCommitted && DB::connection()->transactionLevel() > 0) {
                DB::rollBack();
            }
            if (! $transactionCommitted) {
                // Cleanup local tmp files if DB failed before async job
                foreach (array_merge($tmpPaths, $memberTmpFiles, $otherTmpFiles) as $entry) {
                    $tmp = is_array($entry) ? ($entry['tmp'] ?? $entry) : $entry;
                    if ($tmp) try { Storage::disk('local')->delete($tmp); } catch (\Throwable $ex) {}
                }
            }
            Log::error('Database Error in RenewController: '.$e->getMessage());

            return response()->json(['success' => false, 'message' => 'Failed to save renewal request: '.$e->getMessage()], 500);
        }
    }

    public static function sanitizeRole($position)
    {
        if (empty($position)) {
            return 'Member';
        }

        $posClean = strtolower(trim(preg_replace('/\s+/', ' ', str_replace(['_', '-'], ' ', $position))));

        if (str_contains($posClean, 'president') && ! str_contains($posClean, 'vice')) {
            return 'President';
        }
        if (str_contains($posClean, 'vice president') || str_contains($posClean, 'vicepresident')) {
            return 'Vice President';
        }
        if (str_contains($posClean, 'secretary')) {
            return 'Secretary';
        }
        if (str_contains($posClean, 'treasurer')) {
            return 'Treasurer';
        }
        if (str_contains($posClean, 'auditor')) {
            return 'Auditor';
        }
        if ($posClean === 'pio' || str_contains($posClean, 'public info') || str_contains($posClean, 'pio ') || str_starts_with($posClean, 'pio')) {
            return 'Public Information Officer';
        }
        if (str_contains($posClean, 'business manager') || str_contains($posClean, 'businness') || str_contains($posClean, 'biz manager')) {
            return 'Business Manager';
        }
        if (str_contains($posClean, 'comelec') || str_contains($posClean, 'comlec') || str_contains($posClean, 'election')) {
            return 'COMELEC';
        }
        if (str_contains($posClean, 'junior adviser') || str_contains($posClean, 'junior advisor')) {
            return 'Adviser';
        }
        if (str_contains($posClean, 'adviser') || str_contains($posClean, 'advisor')) {
            return 'Adviser';
        }
        if (str_contains($posClean, 'dean')) {
            return 'Dean';
        }
        if (preg_match('/1\s*st|first/i', $posClean) && str_contains($posClean, 'rep')) {
            return '1st Year Representative';
        }
        if (preg_match('/2\s*nd|second/i', $posClean) && str_contains($posClean, 'rep')) {
            return '2nd Year Representative';
        }
        if (preg_match('/3\s*rd|third/i', $posClean) && str_contains($posClean, 'rep')) {
            return '3rd Year Representative';
        }
        if (preg_match('/4\s*th|fourth/i', $posClean) && str_contains($posClean, 'rep')) {
            return '4th Year Representative';
        }
        if (str_contains($posClean, 'event') || str_contains($posClean, 'coordinator')) {
            return 'Event Coordinator';
        }

        return ucwords($position);
    }

    /**
     * Capture source identity before the temporary upload disappears, then store
     * the exact same file without modifying its bytes.
     *
     * @return array{field:string,slot:string,name:string,disk:string,path:string,source_version:string,source_fingerprint:string}
     */
    private function storeDocumentForRegistration(
        UploadedFile $file,
        string $directory,
        string $field,
        string $slot,
        string $disk,
    ): array {
        $fingerprint = hash_file('sha256', $file->getRealPath());
        if (! is_string($fingerprint)) {
            throw new \RuntimeException("Unable to fingerprint uploaded document [{$field}].");
        }

        $sourceVersion = (string) Str::uuid();
        $originalFilename = $file->getClientOriginalName();
        $path = $file->store($directory, $disk);
        if (! is_string($path) || trim($path) === '') {
            throw new \RuntimeException("Unable to store uploaded document [{$field}].");
        }

        return [
            'field' => $field,
            'slot' => $slot,
            'name' => $originalFilename,
            'disk' => $disk,
            'path' => $path,
            'source_version' => $sourceVersion,
            'source_fingerprint' => strtolower($fingerprint),
        ];
    }

    /**
     * @param  array{field:string,slot:string,name:string,disk:string,path:string,source_version:string,source_fingerprint:string}  $document
     * @return array{path:string,name:string,slot:string,storage_disk:string,source_version:string,source_fingerprint:string}
     */
    private function membershipMetadata(array $document): array
    {
        return [
            'path' => $document['path'],
            'name' => $document['name'],
            'slot' => $document['slot'],
            'storage_disk' => $document['disk'],
            'source_version' => $document['source_version'],
            'source_fingerprint' => $document['source_fingerprint'],
        ];
    }

    /**
     * @param  list<array{field:string,slot:string,name:string,disk:string,path:string,source_version:string,source_fingerprint:string}>  $documents
     * @return list<StoredDocumentDescriptor>
     */
    private function descriptorsForApplication(ApplyOrg $application, array $documents): array
    {
        return array_map(
            fn (array $document): StoredDocumentDescriptor => new StoredDocumentDescriptor(
                (int) $application->getKey(),
                $document['field'],
                $document['slot'],
                $this->supportedDocumentCatalog->definition($document['field'])->label,
                $document['name'],
                $document['disk'],
                $document['path'],
                $document['source_version'],
                $document['source_fingerprint'],
            ),
            $documents,
        );
    }

    /** @param list<DocumentAnalysisDispatchCommand> $commands */
    private function dispatchDocumentAnalysisCommands(array $commands): void
    {
        foreach ($commands as $command) {
            try {
                ProcessDocumentAnalysis::dispatch(
                    $command->analysisId,
                    $command->sourceVersion,
                    $command->cycleId,
                );
            } catch (\Throwable $failure) {
                Log::error('Failed to dispatch automatic document analysis.', [
                    'analysis_id' => $command->analysisId,
                    'message' => $failure->getMessage(),
                ]);
            }
        }
    }

    /**
     * Delete only objects written by this request. Existing application sources
     * are never included in this collection.
     *
     * @param list<array{disk:string,path:string}> $documents
     */
    private function deleteNewlyUploadedDocuments(array $documents): void
    {
        foreach ($documents as $document) {
            try {
                Storage::disk($document['disk'])->delete($document['path']);
            } catch (\Throwable $failure) {
                Log::warning('Failed to clean up a newly uploaded renewal document.', [
                    'disk' => $document['disk'],
                    'path' => $document['path'],
                    'message' => $failure->getMessage(),
                ]);
            }
        }
    }
}
