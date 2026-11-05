<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessDocumentAnalysis;
use App\Jobs\SendConfigurationLinkEmail;
use App\Jobs\UploadApplyDocumentsJob;
use App\Mail\ApplyOrgApproved;
use App\Mail\ApplyOrgRejected;
use App\Mail\ApplyOrgSubmitted;
use App\Models\ApplyOrg;
use App\Models\ConfigurationToken;
use App\Models\Department;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\NewApplyOrgNotification;
use App\Services\CertificationOfOfficersFixedLayoutParser;
use App\Services\CertificationOfOfficersPdfExtractor;
use App\Services\DocumentIntelligence\DocumentAnalysisDispatchCommand;
use App\Services\DocumentIntelligence\DocumentAnalysisRegistrar;
use App\Services\DocumentIntelligence\StoredDocumentDescriptor;
use App\Services\DocumentIntelligence\SupportedDocumentCatalog;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ApplyOrgController extends Controller
{
    public function __construct(
        private readonly DocumentAnalysisRegistrar $documentAnalysisRegistrar,
        private readonly SupportedDocumentCatalog $supportedDocumentCatalog,
    ) {}

    /**
     * Detect whether the current request comes from a mobile/tablet device
     * based on the User-Agent header. Used to restrict the platform creation
     * and organization application flow to desktop browsers only.
     */
    protected function isMobileDevice(Request $request): bool
    {
        $userAgent = strtolower((string) $request->header('User-Agent'));

        if ($userAgent === '') {
            return false;
        }

        // Common mobile and tablet identifiers
        $mobileKeywords = [
            'android', 'webos', 'iphone', 'ipad', 'ipod', 'blackberry',
            'iemobile', 'opera mini', 'mobile', 'tablet', 'silk',
            'kindle', 'playbook', 'windows phone',
        ];

        foreach ($mobileKeywords as $keyword) {
            if (str_contains($userAgent, $keyword)) {
                return true;
            }
        }

        return false;
    }

    public function showCombined(Request $request)
    {
        // Restrict this feature to desktop browsers only
        if ($this->isMobileDevice($request)) {
            return redirect()->route('org.login')->with(
                'error',
                'Creating a platform or applying for an organization is available on desktop only. Please open this page on a computer or laptop to continue.'
            );
        }

        $departments = Department::all();
        $organizations = Organization::where('status', 'Approved')
            ->where('has_system', 'false')
            ->get();

        return view('auth.apply-org-combined', compact('departments', 'organizations'));
    }

    /**
     * Check if a Mother Org has already submitted members for a specific department.
     * Used for Sub Orgs during application so they don't have to upload the list again.
     */
    public function checkMotherOrgMembers($departmentId)
    {
        // Check if there's an approved Mother Org in this department
        $hasMotherOrg = Organization::where('department_id', $departmentId)
            ->where('type', 'Mother Org')
            ->where('status', 'Approved')
            ->exists();

        return response()->json([
            'has_mother_org' => $hasMotherOrg,
        ]);
    }

    /**
     * Public lookup of the OSAS-assigned Dean for a department.
     * Display-only (Apply Step 5 autofill) — submit never trusts this and
     * always resolves the Dean server-side. Returns public directory info.
     */
    public function assignedDean($departmentId)
    {
        $dean = User::where('role', 'super_admin')
            ->where('department_id', $departmentId)
            ->whereRaw('CAST(users.is_active AS INTEGER) = 1')
            ->first(['student_id', 'first_name', 'middle_name', 'middle_initial', 'last_name', 'suffix', 'email']);

        if (! $dean) {
            return response()->json(['has_dean' => false]);
        }

        return response()->json([
            'has_dean' => true,
            'id_number' => $dean->student_id,
            'first_name' => $dean->first_name,
            'middle_initial' => $dean->middle_initial ?: ($dean->middle_name ? mb_substr($dean->middle_name, 0, 1) : null),
            'last_name' => $dean->last_name,
            'suffix' => $dean->suffix,
            'email' => $dean->email,
        ]);
    }

    /**
     * Derive the requester position from the ID digit count.
     * 8 digits (0000-0000) => Organization President,
     * 10 digits (00-0000-0000) => Organization Adviser, else null.
     */
    public static function positionFromIdFormat(mixed $id): ?string
    {
        $digits = preg_replace('/\D/', '', (string) ($id ?? ''));
        if (strlen($digits) === 8) {
            return 'Organization President';
        }
        if (strlen($digits) === 10) {
            return 'Organization Adviser';
        }

        return null;
    }

    /**
     * Submit system request (entry point)
     */
    public function submit(Request $request)
    {
        // Restrict this feature to desktop browsers only (safety net for direct POSTs)
        if ($this->isMobileDevice($request)) {
            $message = 'Creating a platform or applying for an organization is available on desktop only. Please open this page on a computer or laptop to continue.';

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $message,
                ], 403);
            }

            return redirect()->route('org.login')->with('error', $message);
        }

        // Prevent script timeout for large file uploads
        set_time_limit(0);

        $requestType = $request->input('request_type', 'request');

        if ($requestType === 'activate') {
            return $this->submitActivateRequest($request);
        }

        return $this->submitApplyRequest($request);
    }

    /**
     * Submit apply request (new org - full application)
     */
    private function submitApplyRequest(Request $request)
    {
        $rules = [
            'organization_name' => 'required|string|max:255',
            'organization_acronym' => 'nullable|string|max:50',
            'organization_type' => 'required|in:Mother Org,Sub Org,Non-Academic',
            'department_id' => 'nullable|exists:departments,id',
            'requester_student_id' => ['nullable','string','max:50','regex:/^(\d{4}-\d{4}|\d{2}-\d{4}-\d{4})$/'],
            'requester_first_name' => 'required|string|max:255',
            'requester_last_name' => 'required|string|max:255',
            'requester_email' => 'required|email|max:255',
            'requester_position' => 'nullable|in:Organization Adviser,Organization President',
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
            'list_of_members' => 'nullable|array',
            'list_of_members.*' => 'file|mimes:pdf,jpg,jpeg,png|max:10240',
            'other_files' => 'nullable|array|max:20',
            'other_files.*' => 'file|mimes:pdf,jpg,jpeg,png|max:10240',
        ];

        // The membership roster is mandatory for Mother Org only (Non-Academic is optional, Sub Org may rely on Mother Org)
        if ($request->input('organization_type') === 'Mother Org') {
            $rules['list_of_members'] = 'required|array';
        }

        $validator = \Validator::make($request->all(), $rules);

        // An officer card that has a name but no email or ID would otherwise
        // be silently dropped when persisted, so fail loudly instead.
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

        // Hard block: Cannot create Sub Org without an approved Mother Org in the same department
        $validator->after(function ($validator) use ($request) {
            if ($request->input('organization_type') === 'Sub Org' && $request->input('department_id')) {
                $deptId = $request->input('department_id');
                // Non-Academic dept 11 is not used for Sub Org, but check anyway
                if ($deptId != 11) {
                    $hasMother = Organization::where('department_id', $deptId)
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

        // Strict per-position ID format: President => 0000-0000 (4-4), Adviser => 00-0000-0000 (2-4-4)
        $validator->after(function ($validator) use ($request) {
            $position = $request->input('requester_position');
            $studentId = $request->input('requester_student_id');
            if (!empty($studentId) && !empty($position)) {
                if ($position === 'Organization President' && !preg_match('/^\d{4}-\d{4}$/', $studentId)) {
                    $validator->errors()->add('requester_student_id', 'President ID must be 0000-0000 (8 digits, e.g. 2021-0001).');
                }
                if ($position === 'Organization Adviser' && !preg_match('/^\d{2}-\d{4}-\d{4}$/', $studentId)) {
                    $validator->errors()->add('requester_student_id', 'Adviser ID must be 00-0000-0000 (10 digits, e.g. 00-1002-0000).');
                }
            }
        });

        $validated = $validator->validate();

        // Position is derived from the requester ID format — never trusted
        // from the client. 8 digits (0000-0000) => President, 10 digits
        // (00-0000-0000) => Adviser. A posted position that conflicts with
        // the ID format is rejected as tampering.
        $derivedRequesterPosition = self::positionFromIdFormat($validated['requester_student_id'] ?? $request->input('requester_student_id'));
        $postedRequesterPosition = $request->input('requester_position');
        if (! empty($postedRequesterPosition) && ! empty($derivedRequesterPosition)
            && $postedRequesterPosition !== $derivedRequesterPosition) {
            return response()->json([
                'success' => false,
                'message' => 'Requester position does not match the ID format.',
            ], 422);
        }
        $validated['requester_position'] = $derivedRequesterPosition;

        // Force department_id to 11 if the organization type is Non-Academic
        if (($validated['organization_type'] ?? '') === 'Non-Academic') {
            $validated['department_id'] = 11;
        }

        // The position is optional; normalize an empty value to NULL so the
        // column never stores a blank string.
        $validated['requester_position'] = ! empty($validated['requester_position'])
            ? $validated['requester_position']
            : null;

        $officers = $validated['officers'];

        // --- DUPLICATE CHECK (student_id-based) — email can be updated when same student_id, so dedupe by ID not email ---
        $studentIdsInForm = [];
        $adviserIdsInForm = [];
        $emailToStudentMap = []; // email(lower) => student_id (null if none) for student_id-aware email DB check

        $requesterEmail = $request->input('requester_email');
        $requesterPosition = $request->input('requester_position');
        // Map requester email to its student_id — prioritize direct input, fallback to officer slot
        $directReqId = $request->input('requester_student_id');
        $requesterStudentIdForMap = $directReqId ?? null;
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
        // server-side from department_id — no manual Dean input.
        $resolvedDeptForDean = $validated['department_id'] ?? null;
        if ($resolvedDeptForDean) {
            $deanAssigned = User::where('role', 'super_admin')
                ->where('department_id', $resolvedDeptForDean)
                ->where('is_active', DB::raw('TRUE'))
                ->exists();
            if (! $deanAssigned) {
                return response()->json([
                    'success' => false,
                    'message' => 'No Dean is assigned for this college yet. Please contact OSAS to set the Dean first.',
                ], 422);
            }
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
                // In-form email duplicate is only an error when two different student_ids share the same email
                if (isset($emailToStudentMap[$emailLower])) {
                    $existingId = $emailToStudentMap[$emailLower];
                    // If both have no ID or same ID, let student_id duplicate handle it; only flag when IDs differ
                    if ($existingId !== $linkedId && !($existingId === null && $linkedId === null)) {
                        // Allow same email for same student_id (already deduped by ID), but block different IDs sharing email
                        // For requester-officer same-person case, IDs will match, so no error
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

        // Validate duplicates against existing DB — by student_id only; email is checked student_id-aware
        foreach ($studentIdsInForm as $studentId) {
            $exists = User::where('student_id', $studentId)
                ->where(function ($query) {
                    $query->where('is_active', \DB::raw('TRUE'))
                        ->orWhereNotNull('apply_org_id');
                })
                ->exists();
            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => "Student ID '{$studentId}' is already registered as an active officer or has a pending application in another organization.",
                ]);
            }
        }

        foreach ($adviserIdsInForm as $adviserId) {
            $exists = User::where('student_id', $adviserId)
                ->where(function ($query) {
                    $query->where('is_active', \DB::raw('TRUE'))
                        ->orWhereNotNull('apply_org_id');
                })
                ->exists();
            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => "Adviser/Dean ID Number '{$adviserId}' is already registered as an active user or has a pending application in another organization.",
                ]);
            }
        }

        // Email DB check — only block if email exists for a DIFFERENT student_id
        foreach ($emailToStudentMap as $emailLower => $linkedId) {
            if ($requesterEmail && strtolower($emailLower) === strtolower($requesterEmail)) {
                // Requester email is allowed to overlap with its officer slot; already handled
                continue;
            }
            $existing = User::where('email', $emailLower)
                ->where(function ($query) {
                    $query->where('is_active', \DB::raw('TRUE'))
                        ->orWhereNotNull('apply_org_id');
                })
                ->first();
            if ($existing) {
                $existingId = $existing->student_id;
                // Allow if same student_id (email update case), block only if different ID
                if ($linkedId !== null && $existingId !== null && strtolower($linkedId) === strtolower($existingId)) {
                    continue;
                }
                if ($linkedId === null && $existingId === null) {
                    // Both without ID but same email — treat as duplicate
                    return response()->json([
                        'success' => false,
                        'message' => "Email '{$emailLower}' is already registered or has a pending application in another organization.",
                    ]);
                }
                if ($linkedId === null || $existingId === null || strtolower($linkedId) !== strtolower($existingId)) {
                    return response()->json([
                        'success' => false,
                        'message' => "Email '{$emailLower}' is already registered for a different student ID. Use the correct student ID to update email.",
                    ]);
                }
            }
        }

        // 1. PREPARE TMP FILES FOR ASYNC R2 UPLOAD (fast local, then queue)
        // Validate required fields done above — now store uploads to local tmp so user gets instant success.
        // R2 upload will happen async via UploadApplyDocumentsJob, with email retry on failure.
        $storageDisk = storage_disk();
        $tmpPaths = []; // scalar field => ['tmp'=>path,'name'=>original]
        $memberTmpFiles = [];
        $otherTmpFiles = [];
        $localTmpBase = 'tmp/system-requests/' . Str::uuid();
        try {
            $documentFields = [
                'adviser_commitment',
                'application_form',
                'certification_of_officers',
                'plan_of_activities',
                'constitution_bylaws',
                'certification_dean',
                'financial_report',
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
            // Cleanup any tmp already stored
            foreach (array_merge(array_column($tmpPaths, 'tmp'), array_column($memberTmpFiles, 'tmp'), array_column($otherTmpFiles, 'tmp')) as $tmp) {
                try { Storage::disk('local')->delete($tmp); } catch (\Throwable $ex) {}
            }
            Log::error('Tmp store failed in submitApplyRequest: '.$e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to process uploads: '.$e->getMessage(),
            ], 500);
        }

        // 2. DATABASE OPERATIONS — create record without file paths (files filled async by job)
        $uploadedPaths = []; // will be filled by job after R2 upload
        $uploadedDocuments = []; // kept for rollback compatibility (empty now, job handles)
        $dispatchCommands = [];
        $transactionCommitted = false;
        DB::beginTransaction();
        try {
            // Async R2: files are in local tmp, not yet on R2 — store tmp paths so OSAS sees Pending instead of No Documents.
            // Job will later UPDATE with real R2 paths (system-requests/). Keep file fields as tmp strings for initial INSERT.
            $fileFields = ['adviser_commitment','application_form','certification_of_officers','plan_of_activities','constitution_bylaws','certification_dean','financial_report','list_of_members','other_files','accomplishment_reports'];
            foreach ($fileFields as $ff) unset($validated[$ff]);
            $uploadedPaths = [];
            $finalData = array_merge($validated, $uploadedPaths);
            $finalData['status'] = 'Pending';
            $finalData['request_type'] = 'apply';
            $finalData['application_type'] = 'apply';
            $finalData['current_step'] = 6;
            $finalData['department_id'] = $validated['department_id'] ?? null;
            // Set file columns to tmp paths (visible as Pending in OSAS) — job will UPDATE to R2 paths (system-requests/)
            // Scalar docs: tmp/system-requests/<uuid>/file
            foreach (['adviser_commitment','application_form','certification_of_officers','plan_of_activities','constitution_bylaws','certification_dean','financial_report'] as $field) {
                if (isset($tmpPaths[$field]['tmp'])) {
                    $finalData[$field] = $tmpPaths[$field]['tmp'];
                } else {
                    $finalData[$field] = null;
                }
            }
            // List of members: json with tmp paths (unlimited)
            if (!empty($memberTmpFiles)) {
                $tmpMemberJson = array_map(fn($e) => ['path' => $e['tmp'], 'name' => $e['name']], $memberTmpFiles);
                $finalData['list_of_members'] = json_encode($tmpMemberJson, JSON_THROW_ON_ERROR);
            } else {
                $finalData['list_of_members'] = null;
            }
            // Other files
            if (!empty($otherTmpFiles)) {
                $tmpOtherJson = array_map(fn($e) => ['path' => $e['tmp'], 'name' => $e['name']], $otherTmpFiles);
                $finalData['other_files'] = json_encode($tmpOtherJson, JSON_THROW_ON_ERROR);
            } else {
                $finalData['other_files'] = null;
            }
            $finalData['accomplishment_reports'] = null;
            // Remove non-fillable arrays to keep $finalData clean (officers/dean are handled via users, not ApplyOrg columns)
            unset($finalData['officers'], $finalData['dean']);

            $applyOrg = ApplyOrg::create($finalData);

            // Save requester to users table if not exists (checking both email and student_id if available)
            $requesterEmail = $validated['requester_email'];
            $requesterPosition = $validated['requester_position'] ?? null;
            $requesterStudentId = null;
            $requesterCourse = null;
            $requesterYearLevel = null;
            $requesterSection = null;

            // Extract additional fields — prioritize direct requester_student_id input (student_id-based autofill), fallback to officer slot
            $directRequesterId = $validated['requester_student_id'] ?? $request->input('requester_student_id') ?? null;
            if ($requesterPosition === 'Organization President') {
                if (isset($officers['president'])) {
                    $officers['president']['email'] = $requesterEmail; // Force matching email to avoid duplicate user creation
                    // Sync officer student_id with direct input if provided
                    if ($directRequesterId) $officers['president']['student_id'] = $directRequesterId;
                }
                $presData = $officers['president'] ?? [];
                $requesterStudentId = $directRequesterId ?? $presData['student_id'] ?? null;
                $requesterCourse = $presData['course'] ?? null;
                $requesterYearLevel = $presData['year_level'] ?? null;
                $requesterSection = $presData['section'] ?? null;
            } elseif ($requesterPosition === 'Organization Adviser' || $requesterPosition === 'Organization Senior Adviser' || $requesterPosition === 'Organization Junior Adviser') {
                if ($requesterPosition === 'Organization Adviser') {
                    // Generic Adviser: try senior first, fallback to junior
                    $adviserKey = isset($officers['senior_adviser']) ? 'senior_adviser' : (isset($officers['junior_adviser']) ? 'junior_adviser' : null);
                } else {
                    $adviserKey = $requesterPosition === 'Organization Senior Adviser' ? 'senior_adviser' : 'junior_adviser';
                }
                if ($adviserKey && isset($officers[$adviserKey])) {
                    $officers[$adviserKey]['email'] = $requesterEmail;
                    if ($directRequesterId) $officers[$adviserKey]['id_number'] = $directRequesterId;
                    $requesterStudentId = $directRequesterId ?? $officers[$adviserKey]['id_number'] ?? null;
                } else {
                    $requesterStudentId = $directRequesterId;
                }
            } else {
                $requesterStudentId = $directRequesterId;
            }

            // Student_id-first lookup — email can be updated when same student_id
            $requesterUser = null;
            if ($requesterStudentId) {
                $requesterUser = User::where('student_id', $requesterStudentId)->first();
            }
            if (!$requesterUser && $requesterEmail) {
                $requesterUser = User::where('email', $requesterEmail)->first();
            }

            $reqTempPosition = ($requesterPosition === 'Organization President') ? 'President' : 'Adviser';

            if (! $requesterUser) {
                User::create([
                    'student_id' => $requesterStudentId,
                    'first_name' => $validated['requester_first_name'],
                    'last_name' => $validated['requester_last_name'],
                    'email' => $requesterEmail,
                    'role' => 'admin', // Default role for organization management
                    'department_id' => $validated['department_id'] ?? null,
                    'course' => $requesterCourse,
                    'year_level' => $requesterYearLevel,
                    'section' => $requesterSection,
                    'password' => bcrypt(Str::random(16)), // Dummy password
                    'is_active' => false,
                    'apply_org_id' => $applyOrg->id,
                    'temp_position' => $reqTempPosition,
                ]);
            } else {
                // Link existing user — found by student_id first, so email can be updated if changed
                $requesterUser->update([
                    'student_id' => $requesterStudentId ?? $requesterUser->student_id,
                    'email' => $requesterEmail ?? $requesterUser->email,
                    'first_name' => $validated['requester_first_name'] ?? $requesterUser->first_name,
                    'last_name' => $validated['requester_last_name'] ?? $requesterUser->last_name,
                    'course' => $requesterCourse ?? $requesterUser->course,
                    'year_level' => $requesterYearLevel ?? $requesterUser->year_level,
                    'section' => $requesterSection ?? $requesterUser->section,
                    'apply_org_id' => $applyOrg->id,
                    'department_id' => $validated['department_id'] ?? null,
                    'temp_position' => $reqTempPosition,
                ]);
            }

            // Dean is OSAS-assigned per college — already validated above, nothing to create here.

            // Save officers directly to users table (using the already synced local array)
            Log::info('Officers Payload received in submitApplyRequest:', ['officers' => $officers]);

            foreach ($officers as $positionKey => $officerData) {
                $studentId = $officerData['student_id'] ?? $officerData['id_number'] ?? null;
                $officerEmail = $officerData['email'] ?? null;

                if ($officerEmail || $studentId) {
                    // Student_id-first lookup — email is mutable when same ID
                    $officerUser = null;
                    if ($studentId) {
                        $officerUser = User::where('student_id', $studentId)->first();
                    }
                    if (!$officerUser && $officerEmail) {
                        $officerUser = User::where('email', $officerEmail)->first();
                    }

                    // Format and sanitize the position key nicely.
                    // Slot keys like 'business_manager', 'pio', 'senior_adviser',
                    // 'junior_adviser' must be resolved to their canonical
                    // display labels BEFORE passing through sanitizeRole, otherwise the
                    // generic keyword matching will produce the wrong string.
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
                    // "Other position" slots (custom_N) carry their label in the
                    // position_name field; default slots use the canonical map.
                    // Preserve verbatim position_name for custom slots — user can rename/delete
                    // (keep custom_1 key, next add -> custom_2 via max+1). No Custom_1 fallback.
                    if (\App\Services\CertificationOfOfficersRoleSlotRegistry::isCustomSlot($positionKey)) {
                        $formattedPosition = trim((string) ($officerData['position_name'] ?? ''));
                        if ($formattedPosition === '' && !empty($officerData['position'])) {
                            $formattedPosition = trim((string) $officerData['position']);
                        }
                        // If still empty, leave empty (frontend required will block); do not fallback to Sanitize Custom_1
                    } else {
                        $formattedPosition = $slotLabelMap[$positionKey] ?? self::sanitizeRole($positionKey);
                    }

                    // Backend split: 1 letter (even with ".") -> middle_initial, 2+ letters -> middle_name
                    $rawMiddle = trim((string) ($officerData['middle_name'] ?? $officerData['middle_initial'] ?? ''));
                    $cleanMiddle = str_replace(['.', ' '], '', $rawMiddle);
                    if ($rawMiddle === '') {
                        $middleName = null;
                        $middleInitial = null;
                    } elseif (mb_strlen($cleanMiddle) === 1) {
                        $middleInitial = strtoupper($cleanMiddle);
                        $middleName = null;
                    } else {
                        $middleName = $rawMiddle;
                        $middleInitial = null;
                    }

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
                            'department_id' => $officerData['department_id'] ?? $validated['department_id'] ?? null,
                            'password' => bcrypt(Str::random(16)),
                            'is_active' => false,
                            'apply_org_id' => $applyOrg->id,
                            'temp_position' => $formattedPosition,
                        ]);
                    } else {
                        // Existing user — student_id is identity, email is mutable (update even if changed)
                        $updateData = [
                            'apply_org_id' => $applyOrg->id,
                            'department_id' => $officerData['department_id'] ?? $validated['department_id'] ?? null,
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
                        // Student_id is the dedupe key — always set if provided
                        if ($studentId) {
                            $updateData['student_id'] = $studentId;
                        }
                        // Email updatable when same student_id (or when no ID yet) — overwrite if provided
                        if ($officerEmail) {
                            $updateData['email'] = $officerEmail;
                        }

                        $officerUser->update($updateData);
                    }
                }
            }

            DB::commit();
            $transactionCommitted = true;

            // Dispatch async R2 upload — user gets instant success, files go to Cloudflare R2 (system-requests/) in background.
            // On R2 failure, job will email requester to retry (per user request).
            // Queue payloads must be serializable — strip UploadedFile objects and nested arrays
            $validatedForQueue = array_filter(
                $validated,
                fn ($v) => is_scalar($v) || $v === null
            );
            try {
                UploadApplyDocumentsJob::dispatch(
                    $applyOrg->id,
                    $tmpPaths,
                    $memberTmpFiles,
                    $otherTmpFiles,
                    $storageDisk,
                    $validatedForQueue
                );
                Log::info("[ApplyOrg] Dispatched UploadApplyDocumentsJob for #{$applyOrg->id} with " . (count($tmpPaths) + count($memberTmpFiles) + count($otherTmpFiles)) . " tmp files");
            } catch (\Throwable $dispatchErr) {
                Log::error("[ApplyOrg] Failed to dispatch UploadApplyDocumentsJob: " . $dispatchErr->getMessage());
                // Fallback: try sync upload so user doesn't lose data (rare)
                // Still return success — job will retry via queue
            }

            return response()->json([
                'success' => true,
                'message' => 'Successfully Submitted! Your application has been received. Please wait for OSAS approval through email.',
            ], 202);

        } catch (\Throwable $e) {
            if (! $transactionCommitted && DB::connection()->transactionLevel() > 0) {
                DB::rollBack();
            }
            if (! $transactionCommitted) {
                $this->deleteNewlyUploadedDocuments($uploadedDocuments);
                // Cleanup local tmp files if DB failed before async job
                foreach (array_merge($tmpPaths, $memberTmpFiles, $otherTmpFiles) as $entry) {
                    $tmp = is_array($entry) ? ($entry['tmp'] ?? $entry) : $entry;
                    if ($tmp) try { Storage::disk('local')->delete($tmp); } catch (\Throwable $ex) {}
                }
            }
            Log::error('Database Error in submitApplyRequest: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to save application: '.$e->getMessage(),
            ], 500);
        }
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
                // The pending record remains durable for the reconciler to redeliver.
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
                Log::warning('Failed to clean up a newly uploaded application document.', [
                    'disk' => $document['disk'],
                    'path' => $document['path'],
                    'message' => $failure->getMessage(),
                ]);
            }
        }
    }

    /**
     * Submit activate request (for existing approved organizations)
     */
    private function submitActivateRequest(Request $request)
    {
        $validated = $request->validate([
            'organization_name' => 'required|string|max:255',
            'organization_acronym' => 'required|string|max:50',
            'organization_type' => 'required|in:Mother Org,Sub Org,Non-Academic',
            'activator_student_id' => ['required','string','max:50','regex:/^(\d{4}-\d{4}|\d{2}-\d{4}-\d{4})$/'],
            'activator_first_name' => 'required|string|max:255',
            'activator_last_name' => 'required|string|max:255',
            'activator_middle_name' => 'nullable|string|max:255',
            'activator_email' => 'required|email|max:255',
        ]);

        try {
            // 1. Verify organization exists and is approved
            $organization = Organization::where('name', $validated['organization_name'])
                ->where('org_acronym', $validated['organization_acronym'])
                ->where('status', 'Approved')
                ->first();

            if (! $organization) {
                return response()->json([
                    'success' => false,
                    'message' => 'Organization not found or not yet approved. Please make sure the details match exactly.',
                ], 404);
            }

            // 2. Check if organization already has a system
            if ($organization->has_system) {
                return response()->json([
                    'success' => false,
                    'message' => 'This organization already has a system configured.',
                ], 400);
            }

            // 3. Security Check: Verify if activator is the President or an Adviser
            $activatorOfficer = User::where(function ($q) use ($validated) {
                $q->where('student_id', $validated['activator_student_id'])
                    ->orWhere('email', $validated['activator_email']);
            })
                ->whereHas('organizations', function ($q) use ($organization) {
                    $q->where('organization_id', $organization->id)
                        ->whereIn('role', ['President', 'Adviser']);
                })
                ->first();

            if (! $activatorOfficer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Security check failed. Only the Organization President or Adviser can activate the system.',
                ], 403);
            }

            DB::beginTransaction();

            // 4. Find or create the user (Activator)
            $user = User::where('student_id', $validated['activator_student_id'])
                ->orWhere('email', $validated['activator_email'])
                ->first();

            if (! $user) {
                $user = User::create([
                    'first_name' => $validated['activator_first_name'],
                    'last_name' => $validated['activator_last_name'],
                    'middle_name' => $validated['activator_middle_name'],
                    'student_id' => $validated['activator_student_id'],
                    'email' => $validated['activator_email'],
                    'password' => bcrypt(Str::random(16)), // Temporary password
                    'role' => 'admin',
                    'is_active' => false, // Not active yet until configuration
                ]);
            } else {
                // Update existing user details using raw DB update
                DB::table('users')
                    ->where('id', $user->id)
                    ->update([
                        'first_name' => $validated['activator_first_name'],
                        'last_name' => $validated['activator_last_name'],
                        'middle_name' => $validated['activator_middle_name'],
                        'updated_at' => now(),
                    ]);
                // Reload user to get fresh data
                $user->refresh();
            }

            // 5. Link user to organization if not already linked
            $existingLink = DB::table('organization_user')
                ->where('organization_id', $organization->id)
                ->where('user_id', $user->id)
                ->exists();

            if (! $existingLink) {
                DB::table('organization_user')->insert([
                    'organization_id' => $organization->id,
                    'user_id' => $user->id,
                    'role' => 'President', // Assuming activator is President
                    'membership_status' => 'Active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // 6. Generate Configuration Token
            $configToken = ConfigurationToken::generate($organization->id, $user->id);

            DB::commit();

            // 7. Dispatch email job AFTER transaction commits (async processing)
            // This avoids nested transaction issues and sends email asynchronously
            try {
                SendConfigurationLinkEmail::dispatch($user->email, $configToken->id);
            } catch (\Exception $jobError) {
                Log::error('Failed to dispatch configuration email job: '.$jobError->getMessage());
                // Don't return error since data is already committed
            }

            return response()->json([
                'success' => true,
                'message' => 'System activation initiated! A configuration link has been sent to '.$user->email.'. Please check your email and complete the system configuration.',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Activation Error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to initiate system activation: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Approve a system request
     */
    public function approve(Request $request, ApplyOrg $applyOrg)
    {
        if ($applyOrg->auto_generated) {
            return response()->json([
                'success' => false,
                'message' => 'This renewal is awaiting the organization to re-upload documents. Approval is not available until the renewal is completed.',
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Resolve the registry record before marking the request as approved.
            // This guarantees every approved accreditation is visible in OSAS > Organizations.
            if ($applyOrg->request_type === 'apply') {
                $organization = Organization::firstOrCreate(
                    [
                        'name' => $applyOrg->organization_name,
                        'org_acronym' => $applyOrg->organization_acronym,
                    ],
                    [
                        'type' => $applyOrg->organization_type,
                        'status' => 'Approved',
                        'department_id' => $applyOrg->department_id,
                    ]
                );
            } else {
                $organization = $applyOrg->organization_id
                    ? Organization::find($applyOrg->organization_id)
                    : null;

                $organization ??= Organization::firstOrCreate(
                    [
                        'name' => $applyOrg->organization_name,
                        'org_acronym' => $applyOrg->organization_acronym,
                    ],
                    [
                        'type' => $applyOrg->organization_type === 'Non-Academic' ? 'Non-Academic' : 'Mother Org',
                        'status' => 'Approved',
                        'department_id' => $applyOrg->department_id,
                    ]
                );
            }

            $accreditationPeriod = Organization::currentAccreditationPeriod();

            $organization->update([
                'status' => 'Approved',
                'archived_at' => null,
                'accreditation_started_at' => $accreditationPeriod?->started_at,
                'accreditation_expires_at' => $accreditationPeriod?->expires_at,
            ]);

            $applyOrg->update([
                'organization_id' => $organization->id,
                'status' => 'Approved',
                'accreditation_year' => Organization::accreditationSchoolYear(
                    $accreditationPeriod?->started_at,
                    $accreditationPeriod?->expires_at,
                ),
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
                'admin_notes' => $request->admin_notes,
            ]);

            // Link requester and officers to organization
            $requester = User::where('email', $applyOrg->requester_email)->first();
            if ($organization) {
                // Attach the requester
                if ($requester) {
                    $requesterRole = $applyOrg->requester_position === 'Organization President' ? 'President' : 'Adviser';

                    $canonicalRoles = array_merge(
                        \App\Services\AccountManagement\OrganizationPositionRegistry::officerRoles(),
                        \App\Services\AccountManagement\OrganizationPositionRegistry::adviserRoles(),
                    );

                    // Deactivate any existing active holder of the same canonical slot
                    if (in_array($requesterRole, $canonicalRoles, true)) {
                        DB::table('organization_user')
                            ->where('organization_id', $organization->id)
                            ->where('role', $requesterRole)
                            ->where('membership_status', 'Active')
                            ->where('user_id', '!=', $requester->id)
                            ->delete();
                    }

                    $existingRequesterPivot = DB::table('organization_user')
                        ->where('organization_id', $organization->id)
                        ->where('user_id', $requester->id)
                        ->first();

                    if ($existingRequesterPivot) {
                        DB::table('organization_user')
                            ->where('organization_id', $organization->id)
                            ->where('user_id', $requester->id)
                            ->update([
                                'role' => $requesterRole,
                                'membership_status' => 'Active',
                                'updated_at' => now(),
                            ]);
                    } else {
                        $organization->users()->attach($requester->id, [
                            'role' => $requesterRole,
                            'membership_status' => 'Active',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }

                    // Approval activates the requester account as well as the membership.
                    // Requesters created during submission start inactive until approval.
                    if (! $requester->is_active) {
                        $requester->update(['is_active' => true]);
                    }
                }

                // Attach other officers submitted in the application
                $canonicalRoles = array_merge(
                    \App\Services\AccountManagement\OrganizationPositionRegistry::officerRoles(),
                    \App\Services\AccountManagement\OrganizationPositionRegistry::adviserRoles(),
                );

                $officerUsers = User::where('apply_org_id', $applyOrg->id)->get();

                // Everyone attached in this approval batch (officers + requester) is
                // protected from the stale-holder cleanup below so two people holding
                // the same position (e.g. two Business Managers) both stay attached.
                $batchUserIds = array_merge(
                    $officerUsers->pluck('id')->all(),
                    $requester ? [$requester->id] : [],
                );

                foreach ($officerUsers as $officerUser) {
                    $rawRole = trim((string) ($officerUser->temp_position ?? ''));
                    if ($rawRole === '') {
                        $role = 'Member';
                    } else {
                        $sanitized = self::sanitizeRole($rawRole);
                        // Preserve verbatim custom position names (e.g. Assistant Secretary stays Assistant Secretary, not Secretary)
                        // Keep custom_1 key editable/deletable: delete -> next add is custom_2 via max+1, rename via position_name
                        if ($sanitized !== $rawRole && in_array($sanitized, $canonicalRoles, true) && !in_array($rawRole, $canonicalRoles, true)) {
                            $role = $rawRole;
                        } else {
                            $role = $sanitized;
                        }
                    }

                    // Deactivate any existing active holder of the same canonical slot
                    // who is not part of this approval batch (only for canonical roles —
                    // skip Member / Dean / non-canonical).
                    if (in_array($role, $canonicalRoles, true)) {
                        DB::table('organization_user')
                            ->where('organization_id', $organization->id)
                            ->where('role', $role)
                            ->where('membership_status', 'Active')
                            ->whereNotIn('user_id', $batchUserIds)
                            ->delete();
                    }

                    $existingPivot = DB::table('organization_user')
                        ->where('organization_id', $organization->id)
                        ->where('user_id', $officerUser->id)
                        ->first();

                    if ($existingPivot) {
                        DB::table('organization_user')
                            ->where('organization_id', $organization->id)
                            ->where('user_id', $officerUser->id)
                            ->update([
                                'role' => $role,
                                'membership_status' => 'Active',
                                'updated_at' => now(),
                            ]);
                    } else {
                        $organization->users()->attach($officerUser->id, [
                            'role' => $role,
                            'membership_status' => 'Active',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }

                    // Approval activates accounts created during submission. Clear the
                    // temporary position only after the membership was persisted.
                    $officerUser->update([
                        'is_active' => true,
                        'temp_position' => null,
                    ]);
                }
            }

            DB::commit();

            // Dispatch background job — OCR on list_of_members runs async so OSAS admin
            // gets an instant response. Student IDs land in enrolled_students within minutes.
            \App\Jobs\ProcessApprovedApplication::dispatch($applyOrg->id, $requester?->id ?? 0);

            // Send Approval Email directly (synchronous for maximum reliability)
            try {
                Mail::to($applyOrg->requester_email)->send(
                    new ApplyOrgApproved(
                        $applyOrg->organization_name,
                        $requester->student_id ?? 'N/A',
                        'Check your student portal',
                        $applyOrg->requester_first_name.' '.$applyOrg->requester_last_name,
                        $applyOrg->requester_position
                    )
                );
                Log::info('Approval Email Sent Successfully to: '.$applyOrg->requester_email);
            } catch (\Exception $mailError) {
                Log::error('Approval Email sending failed: '.$mailError->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'Request approved successfully. The organization is now active.',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Approval Error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to approve request: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reject a system request
     */
    public function reject(Request $request, ApplyOrg $applyOrg)
    {
        $request->validate([
            'reason' => 'required|string',
        ]);

        try {
            $applyOrg->update([
                'status' => 'Rejected',
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
                'admin_notes' => $request->reason,
            ]);

            // Send Email
            try {
                Mail::to($applyOrg->requester_email)->send(
                    new ApplyOrgRejected(
                        $applyOrg->organization_name,
                        $applyOrg->requester_first_name.' '.$applyOrg->requester_last_name,
                        $request->reason
                    )
                );
                Log::info('Rejection Email Sent Successfully to: '.$applyOrg->requester_email);
            } catch (\Exception $mailError) {
                Log::error('Mail Error in reject: '.$mailError->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'Request rejected successfully.',
            ]);

        } catch (\Exception $e) {
            Log::error('Rejection Error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to reject request: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Sanitize and map input roles to clean standards
     */
    public static function sanitizeRole($position)
    {
        if (empty($position)) {
            return 'Member';
        }

        $posClean = strtolower(trim(preg_replace('/\s+/', ' ', str_replace(['_', '-'], ' ', $position))));

        // 1. President Mapping
        if (str_contains($posClean, 'president') && ! str_contains($posClean, 'vice')) {
            return 'President';
        }

        // 2. Vice President Mapping
        if (str_contains($posClean, 'vice president') || str_contains($posClean, 'vicepresident')) {
            return 'Vice President';
        }

        // 3. Secretary Mapping
        if (str_contains($posClean, 'secretary')) {
            return 'Secretary';
        }

        // 4. Treasurer Mapping
        if (str_contains($posClean, 'treasurer')) {
            return 'Treasurer';
        }

        // 5. Auditor Mapping
        if (str_contains($posClean, 'auditor')) {
            return 'Auditor';
        }

        // 6. Public Information Officer / PIO Mapping
        if ($posClean === 'pio' || str_contains($posClean, 'public info') || str_contains($posClean, 'pio ') || str_starts_with($posClean, 'pio')) {
            return 'Public Information Officer';
        }

        // 7. Business Manager Mapping
        if (str_contains($posClean, 'business manager') || str_contains($posClean, 'businness') || str_contains($posClean, 'biz manager')) {
            return 'Business Manager';
        }

        // 8. COMELEC Mapping
        if (str_contains($posClean, 'comelec') || str_contains($posClean, 'comlec') || str_contains($posClean, 'election')) {
            return 'COMELEC';
        }

        // 9. Adviser Mapping — all adviser variants map to plain 'Adviser'
        if (str_contains($posClean, 'junior adviser') || str_contains($posClean, 'junior advisor')) {
            return 'Adviser';
        }
        if (str_contains($posClean, 'adviser') || str_contains($posClean, 'advisor')) {
            return 'Adviser';
        }

        // 10. Dean Mapping
        if (str_contains($posClean, 'dean')) {
            return 'Dean';
        }

        // 11. Year Representatives Mapping — canonical uses "Year" not "yr"
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

        // 12. Event Coordinator Mapping
        if (str_contains($posClean, 'event') || str_contains($posClean, 'coordinator')) {
            return 'Event Coordinator';
        }

        // Default Fallback formatted nicely
        return ucwords($position);
    }

    /**
     * Return a local, deterministic prefill result for a Certification PDF.
     */
    public function prefillFromOfficers(Request $request)
    {
        set_time_limit(120);

        $request->validate([
            'certification_of_officers' => 'required|file|mimes:pdf|max:10240',
        ]);

        $rawRequestToken = $request->input('request_token', '');
        $requestToken = is_string($rawRequestToken)
            ? substr((string) preg_replace('/[^A-Za-z0-9._:-]/', '', $rawRequestToken), 0, 128)
            : '';
        $emptyData = [
            'dean' => null,
            'senior_adviser' => null,
            'junior_adviser' => null,
            'officers' => [],
            'college' => null,
        ];
        $fullPath = null;

        try {
            $file = $request->file('certification_of_officers');
            $filename = uniqid('', true).'.pdf';
            $tempDir = storage_path('app/temp/prefill');
            if (! is_dir($tempDir) && ! mkdir($tempDir, 0755, true) && ! is_dir($tempDir)) {
                throw new \RuntimeException('Temporary prefill storage is unavailable.');
            }

            $fullPath = $tempDir.DIRECTORY_SEPARATOR.$filename;
            $file->move($tempDir, $filename);

            // Keep a persistent debug copy for inspection
            $debugPath = storage_path('app/temp/last_uploaded_certification.pdf');
            @copy($fullPath, $debugPath);

            $pages = (new CertificationOfOfficersPdfExtractor)->extract($fullPath);
            $result = (new CertificationOfOfficersFixedLayoutParser)->parse($pages);
            $data = $result['data'];

            $hasOfficers = ! empty($data['officers']);
            $hasDeanOrAdviser = ! empty($data['senior_adviser']) || ! empty($data['junior_adviser']);

            // If native parser found no officers, run Visual OCR fallback or text fallback
            if (! $hasOfficers || $result['outcome'] === 'manual_required') {
                if (class_exists(\App\Services\OcrService::class)) {
                    try {
                        $visualOcrText = app(\App\Services\OcrService::class)->basahinAngPictureVisual($fullPath, 16);
                        if (! empty(trim($visualOcrText))) {
                            $ocrPages = [];
                            foreach (preg_split('/--- PAGE BREAK ---/i', $visualOcrText) as $idx => $txt) {
                                $txt = trim($txt);
                                if ($txt === '') continue;
                                $ocrPages[] = ['index' => $idx, 'text' => $txt, 'form_fields' => [], 'preferred_values' => []];
                            }
                            if (! empty($ocrPages)) {
                                $visualResult = (new CertificationOfOfficersFixedLayoutParser)->parse($ocrPages);
                                if (! empty($visualResult['data']['officers'])) {
                                    $data = $visualResult['data'];
                                    $result['outcome'] = $visualResult['outcome'];
                                    $result['warnings'] = $visualResult['warnings'];
                                    $pages = $ocrPages;
                                } elseif (CertificationOfOfficersFixedLayoutParser::isTemplateText($visualOcrText)) {
                                    // Loose fallback runs only on template-plausible
                                    // text so unrelated documents (e.g. Plan of
                                    // Activities) can never fabricate officers.
                                    $fallback = $this->fallbackExtractFromText($ocrPages);
                                    if (! empty($fallback['officers'])) {
                                        $data = $fallback;
                                        $result['outcome'] = 'completed';
                                        $result['warnings'] = [];
                                        $pages = $ocrPages;
                                    }
                                }
                            }
                        }
                    } catch (\Throwable $e) {
                        // Fallback silently if visual OCR is unavailable
                    }
                }

                if (empty($data['officers'])) {
                    $currentText = implode("\n", array_map(fn ($p) => $p['text'] ?? '', $pages));
                    if (CertificationOfOfficersFixedLayoutParser::isTemplateText($currentText)) {
                        $fallback = $this->fallbackExtractFromText($pages);
                        if (! empty($fallback['officers'])) {
                            $data = $fallback;
                            $result['outcome'] = 'completed';
                            $result['warnings'] = [];
                        }
                    }
                }
            }

            // Smart hybrid, AI-first: the deterministic path runs first and is
            // instant. When it already covered the whole document with no
            // warnings and returned every certifier, Gemini adds nothing
            // (deterministic wins per slot), so the AI call is skipped and
            // standard PDFs respond immediately. When the deterministic path
            // is incomplete, Gemini runs as the primary parser and its guarded
            // output fills the empty slots.
            $allText = implode("\n--- PAGE BREAK ---\n", array_map(fn ($p) => $p['text'] ?? '', $pages));
            $dataSource = $this->prefillDataSource($data, $result['outcome']);
            $isDeterministicallyComplete = $result['outcome'] === 'completed'
                && $result['warnings'] === []
                && ! empty($data['senior_adviser'])
                && ! empty($data['junior_adviser']);
            if (! $isDeterministicallyComplete && trim($allText) !== '') {
                try {
                    $aiData = $this->parseWithGemini($allText);
                    $merged = (new \App\Services\CertificationOfOfficersPrefillMerger)->merge($data, $aiData, $allText);
                    if ($merged['source'] !== 'fixed') {
                        $data = $merged['data'];
                        $dataSource = $merged['source'];
                        if (! empty($data['officers'])) {
                            $result['outcome'] = 'completed';
                            $result['warnings'] = [];
                        }
                    }
                } catch (\Throwable $e) {
                    \Log::warning('[prefillFromOfficers] Gemini merge skipped: ' . $e->getMessage());
                }
            }

            $rawTextSnippet = implode("\n", array_map(fn($p) => $p['text'], $pages));
            $rawTextSnippet = mb_substr($rawTextSnippet, 0, 300);

            return response()->json([
                'success' => true,
                'outcome' => $result['outcome'],
                'request_token' => $requestToken,
                'debug_file_info' => [
                    'original_name' => $file->getClientOriginalName(),
                    'size' => filesize($debugPath),
                    'mime' => $file->getClientMimeType(),
                    'text_snippet' => $rawTextSnippet,
                ],
                'data' => [
                    'dean' => $data['dean'] ?? null,
                    'senior_adviser' => $data['senior_adviser'] ?? null,
                    'junior_adviser' => $data['junior_adviser'] ?? null,
                    'officers' => $data['officers'] ?? [],
                    'college' => $data['college'] ?? null,
                ],
                'data_source' => $dataSource ?? 'fixed',
                'warnings' => $result['warnings'],
            ]);
        } catch (\Throwable $exception) {
            Log::warning('Certification of Officers prefill could not be completed locally: ' . $exception->getMessage(), [
                'exception' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ]);

            return response()->json([
                'success' => true,
                'outcome' => 'manual_required',
                'request_token' => $requestToken,
                'data' => $emptyData,
                'data_source' => 'none',
                'warnings' => ['We could not read this Certification of Officers PDF. Please complete the fields manually.'],
            ]);
        } finally {
            if (is_string($fullPath) && is_file($fullPath)) {
                @unlink($fullPath);
            }
            // Remove the leftover debug copy of the uploaded certification.
            @unlink(storage_path('app/temp/last_uploaded_certification.pdf'));
        }
    }

    /**
     * Parse ONE List of Members file for a page range and return name +
     * student-ID rows. Paginated (page_from/page_size) so every page stays
     * readable without breaching execution limits — the client repeats the
     * call with the next range until complete is true. Local-only extraction
     * (OcrService: native PDF text first, Ghostscript render + Tesseract
     * fallback); no Vision/NLP calls. Optional, editable, nonblocking.
     */
    public function parseMemberRows(Request $request)
    {
        set_time_limit(120);

        $request->validate([
            'member_file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'page_from' => 'nullable|integer|min:1|max:5000',
            'page_size' => 'nullable|integer|min:1|max:30',
            'request_key' => 'nullable|string|max:128',
        ]);

        $requestKey = $this->memberLookupRequestKey($request);
        $pageFrom = max(1, (int) $request->input('page_from', 1));
        $pageSize = (int) $request->input('page_size', 25);
        $pageSize = min(max($pageSize, 1), 30);
        $tempPath = null;

        try {
            $file = $request->file('member_file');
            $ext = strtolower((string) $file->getClientOriginalExtension());
            $ext = in_array($ext, ['pdf', 'jpg', 'jpeg', 'png'], true) ? $ext : 'pdf';
            $tempDir = storage_path('app/temp/member-lookup');
            if (! is_dir($tempDir) && ! mkdir($tempDir, 0755, true) && ! is_dir($tempDir)) {
                throw new \RuntimeException('Temporary member lookup storage is unavailable.');
            }
            $tempPath = $tempDir.DIRECTORY_SEPARATOR.uniqid('', true).'.'.$ext;
            $file->move($tempDir, basename($tempPath));

            $pagesTotal = $this->memberFilePageCount($tempPath, $ext);
            if ($pageFrom > $pagesTotal) {
                return response()->json([
                    'success' => true,
                    'request_key' => $requestKey,
                    'rows' => [],
                    'pages_total' => $pagesTotal,
                    'pages_done' => $pagesTotal,
                    'complete' => true,
                ]);
            }
            $offset = $pageFrom - 1;
            $take = min($pageSize, $pagesTotal - $offset);

            $rows = [];
            if ($take > 0) {
                /** @var \App\Services\OcrService $ocr */
                $ocr = app(\App\Services\OcrService::class);
                $text = (string) $ocr->basahinAngPicture($tempPath, $take, $offset);
                $rows = (new \App\Services\OfficerIdLookupService)->parseMemberRows($text);
            }
            $pagesDone = min($offset + $take, $pagesTotal);

            return response()->json([
                'success' => true,
                'request_key' => $requestKey,
                'rows' => $rows,
                'pages_total' => $pagesTotal,
                'pages_done' => $pagesDone,
                'complete' => $pagesDone >= $pagesTotal,
            ]);
        } catch (\Throwable $exception) {
            Log::warning('[parseMemberRows] Failed: '.$exception->getMessage());

            return response()->json([
                'success' => false,
                'request_key' => $requestKey,
                'message' => 'We could not read part of the List of Members. Student IDs may need manual entry.',
            ]);
        } finally {
            if (is_string($tempPath) && is_file($tempPath)) {
                @unlink($tempPath);
            }
        }
    }

    /**
     * Match officer names against accumulated member rows. Instant, pure
     * deterministic logic — the heavy extraction already happened in
     * parseMemberRows calls. Never throws to the client.
     */
    public function matchOfficerIds(Request $request)
    {
        $request->validate([
            'officers' => 'required|string|max:20000',
            'rows' => 'required|string|max:500000',
            'request_key' => 'nullable|string|max:128',
        ]);

        $requestKey = $this->memberLookupRequestKey($request);

        $officers = json_decode((string) $request->input('officers'), true);
        if (! is_array($officers)) {
            return response()->json([
                'success' => false,
                'request_key' => $requestKey,
                'message' => 'Invalid officers payload.',
            ], 422);
        }
        $rows = json_decode((string) $request->input('rows'), true);
        if (! is_array($rows)) {
            return response()->json([
                'success' => false,
                'request_key' => $requestKey,
                'message' => 'Invalid rows payload.',
            ], 422);
        }

        $officers = $this->sanitizeLookupOfficers($officers);
        $rows = array_slice(array_values($rows), 0, 5000);
        $results = (new \App\Services\OfficerIdLookupService)->matchRows($rows, $officers);

        // Per-slot outcome summary for live diagnostics (slot keys and
        // outcome types only — never names): matched / picker(n) / empty
        // with the best confidence seen.
        $slotOutcomes = [];
        foreach ($results as $slot => $result) {
            $best = 0.0;
            foreach ($result['candidates'] ?? [] as $candidate) {
                $best = max($best, (float) ($candidate['confidence'] ?? 0));
            }
            $slotOutcomes[(string) $slot] = ! empty($result['match'])
                ? 'matched'
                : ($best > 0 ? 'picker('.count($result['candidates'] ?? []).'):'.round($best, 2) : 'empty');
        }

        Log::info('[matchOfficerIds] Completed', [
            'rows_received' => count($rows),
            'slots' => count($results),
            'matched' => count(array_filter($slotOutcomes, static fn ($o) => $o === 'matched')),
            'pickers' => count(array_filter($slotOutcomes, static fn ($o) => str_starts_with($o, 'picker'))),
            'slot_outcomes' => $slotOutcomes,
        ]);

        return response()->json([
            'success' => true,
            'request_key' => $requestKey,
            'rows_received' => count($rows),
            'results' => $results,
        ]);
    }

    /**
     * List a department's Mother Org member files (names only, no IDs).
     * Lets Sub Org applicants match officer IDs without re-uploading the
     * roster the Mother Org already submitted.
     */
    public function motherOrgMemberFiles($departmentId)
    {
        $departmentId = (int) $departmentId;
        $mother = Organization::where('department_id', $departmentId)
            ->where('type', 'Mother Org')
            ->where('status', 'Approved')
            ->first();

        $applyOrg = $this->resolveMotherOrgApplication($departmentId);
        $files = [];
        if ($applyOrg) {
            foreach ($this->resolveMemberFileEntries($applyOrg) as $index => $entry) {
                $files[] = ['index' => $index, 'name' => $entry['original_name']];
            }
        }

        return response()->json([
            'has_mother_org' => $mother !== null,
            'mother_org_name' => $mother?->name,
            'files' => $files,
        ]);
    }

    /**
     * Parse one stored Mother Org member file for a page range and accumulate
     * rows server-side; with finish=true, match officer names over everything
     * accumulated. Raw rows never leave the server (IDs are login identifiers
     * and the form is public) — only match results are returned.
     */
    public function matchMotherOrgIds(Request $request)
    {
        set_time_limit(120);

        $request->validate([
            'department_id' => 'required|integer|min:1',
            'officers' => 'required|string|max:20000',
            'request_key' => 'required|string|max:128',
            'finish' => 'nullable|boolean',
            'file_index' => 'required_if:finish,false|nullable|integer|min:0|max:50',
            'page_from' => 'nullable|integer|min:1|max:5000',
            'page_size' => 'nullable|integer|min:1|max:30',
        ]);

        $requestKey = $this->memberLookupRequestKey($request);
        $cacheKey = 'mother-id-lookup-'.$requestKey;

        $officers = json_decode((string) $request->input('officers'), true);
        if (! is_array($officers)) {
            return response()->json([
                'success' => false,
                'request_key' => $requestKey,
                'message' => 'Invalid officers payload.',
            ], 422);
        }
        $officers = $this->sanitizeLookupOfficers($officers);

        if ($request->boolean('finish')) {
            $accumulated = Cache::get($cacheKey, []);
            if (! is_array($accumulated)) {
                $accumulated = [];
            }
            $results = (new \App\Services\OfficerIdLookupService)->matchRows($accumulated, $officers);
            Cache::forget($cacheKey);

            Log::info('[matchMotherOrgIds] Completed', [
                'department_id' => (int) $request->input('department_id'),
                'rows_received' => count($accumulated),
                'slots' => count($results),
            ]);

            return response()->json([
                'success' => true,
                'request_key' => $requestKey,
                'rows_received' => count($accumulated),
                'results' => $results,
            ]);
        }

        $pageFrom = max(1, (int) $request->input('page_from', 1));
        $pageSize = (int) $request->input('page_size', 25);
        $pageSize = min(max($pageSize, 1), 30);
        $tempPath = null;

        try {
            $applyOrg = $this->resolveMotherOrgApplication((int) $request->input('department_id'));
            if (! $applyOrg) {
                return response()->json([
                    'success' => false,
                    'request_key' => $requestKey,
                    'message' => 'No Mother Org member list found for this department.',
                ]);
            }

            $entries = $this->resolveMemberFileEntries($applyOrg);
            $entry = $entries[(int) $request->input('file_index', 0)] ?? null;
            if (! $entry) {
                return response()->json([
                    'success' => false,
                    'request_key' => $requestKey,
                    'message' => 'Member file not found.',
                ]);
            }

            $tempPath = $this->downloadMemberFile($entry);
            if (! $tempPath) {
                return response()->json([
                    'success' => false,
                    'request_key' => $requestKey,
                    'message' => 'We could not read the Mother Org member file.',
                ]);
            }

            $ext = strtolower(pathinfo($tempPath, PATHINFO_EXTENSION));
            $pagesTotal = $this->memberFilePageCount($tempPath, $ext);
            if ($pageFrom > $pagesTotal) {
                return response()->json([
                    'success' => true,
                    'request_key' => $requestKey,
                    'rows_added' => 0,
                    'rows_total' => count(Cache::get($cacheKey, []) ?: []),
                    'pages_total' => $pagesTotal,
                    'pages_done' => $pagesTotal,
                    'complete' => true,
                ]);
            }
            $offset = $pageFrom - 1;
            $take = min($pageSize, $pagesTotal - $offset);

            /** @var \App\Services\OcrService $ocr */
            $ocr = app(\App\Services\OcrService::class);
            $text = (string) $ocr->basahinAngPicture($tempPath, $take, $offset);
            $rows = (new \App\Services\OfficerIdLookupService)->parseMemberRows($text);

            $accumulated = Cache::get($cacheKey, []);
            if (! is_array($accumulated)) {
                $accumulated = [];
            }
            array_push($accumulated, ...$rows);
            $accumulated = array_slice($accumulated, 0, 20000);
            Cache::put($cacheKey, $accumulated, 1800);
            $pagesDone = min($offset + $take, $pagesTotal);

            return response()->json([
                'success' => true,
                'request_key' => $requestKey,
                'rows_added' => count($rows),
                'rows_total' => count($accumulated),
                'pages_total' => $pagesTotal,
                'pages_done' => $pagesDone,
                'complete' => $pagesDone >= $pagesTotal,
            ]);
        } catch (\Throwable $exception) {
            Log::warning('[matchMotherOrgIds] Failed: '.$exception->getMessage());

            return response()->json([
                'success' => false,
                'request_key' => $requestKey,
                'message' => 'We could not read the Mother Org member list. Student IDs need manual entry.',
            ]);
        } finally {
            if (is_string($tempPath) && is_file($tempPath)) {
                @unlink($tempPath);
            }
        }
    }

    /**
     * Find the Mother Org application holding the department roster: latest
     * Mother Org application with a readable list_of_members, Approved first.
     */
    private function resolveMotherOrgApplication(int $departmentId): ?ApplyOrg
    {
        $candidates = ApplyOrg::where('department_id', $departmentId)
            ->where('organization_type', 'Mother Org')
            ->whereNotNull('list_of_members')
            ->orderByRaw("CASE WHEN status = 'Approved' THEN 0 ELSE 1 END")
            ->latest('id')
            ->limit(5)
            ->get();

        foreach ($candidates as $candidate) {
            if (count($this->resolveMemberFileEntries($candidate)) > 0) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Decode an ApplyOrg list_of_members field into download descriptors.
     * Mirrors StudentListProcessor::resolveFiles (kept local: that one is
     * private to the service).
     *
     * @return list<array{path:string,disk:string,original_name:string}>
     */
    private function resolveMemberFileEntries(ApplyOrg $applyOrg): array
    {
        $raw = $applyOrg->list_of_members;
        if (empty($raw)) {
            return [];
        }
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }
        if (! is_array($raw)) {
            return [];
        }
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
            if (empty($path) || ! is_string($path)) {
                continue;
            }
            $files[] = [
                'path' => $path,
                'disk' => $entry['storage_disk'] ?? $entry['disk'] ?? $disk,
                'original_name' => $entry['name'] ?? $entry['original_filename'] ?? basename($path),
            ];
        }

        return $files;
    }

    /**
     * Download a stored member file to a local temp path for the OCR tools.
     * Mirrors StudentListProcessor::localise. Returns null when unreadable.
     */
    private function downloadMemberFile(array $fileInfo): ?string
    {
        try {
            $fs = Storage::disk($fileInfo['disk']);
            $tmp = tempnam(sys_get_temp_dir(), 'uniorg_mother_').'.'.pathinfo($fileInfo['path'], PATHINFO_EXTENSION);

            if (method_exists($fs->getAdapter(), 'getPathPrefix')
                || in_array($fileInfo['disk'], ['local', 'public'], true)
            ) {
                $local = $fs->path($fileInfo['path']);
                if (! is_file($local)) {
                    @unlink($tmp);
                    return null;
                }
                copy($local, $tmp);

                return $tmp;
            }

            file_put_contents($tmp, $fs->get($fileInfo['path']));

            return $tmp;
        } catch (\Throwable $e) {
            Log::warning('[matchMotherOrgIds] Member file download failed: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Count the pages of a member file (PDF via parser, images are 1 page).
     */
    private function memberFilePageCount(string $tempPath, string $ext): int
    {
        if ($ext !== 'pdf') {
            return 1;
        }

        try {
            $parser = new \Smalot\PdfParser\Parser();

            return max(1, count($parser->parseFile($tempPath)->getPages()));
        } catch (\Throwable $e) {
            return 1;
        }
    }

    /**
     * Shared request-key sanitizer for the member lookup endpoints.
     */
    private function memberLookupRequestKey(Request $request): string
    {
        $raw = $request->input('request_key', '');

        return is_string($raw)
            ? substr((string) preg_replace('/[^A-Za-z0-9._:-]/', '', $raw), 0, 128)
            : '';
    }

    /**
     * Cap officer slots and trim name parts to bound the matching work.
     */
    private function sanitizeLookupOfficers(array $officers): array
    {
        $officers = array_slice($officers, 0, 60, true);
        foreach ($officers as $slot => $officer) {
            if (! is_array($officer)) {
                unset($officers[$slot]);
                continue;
            }
            foreach (['first_name', 'middle_name', 'last_name', 'suffix'] as $field) {
                $officers[$slot][$field] = mb_substr(trim((string) ($officer[$field] ?? '')), 0, 100);
            }
        }

        return $officers;
    }

    /**
     * Label which source produced the prefill data before the AI merge runs.
     */
    private function prefillDataSource(array $data, string $outcome): string
    {
        $hasAny = ! empty($data['officers'])
            || ! empty($data['senior_adviser'])
            || ! empty($data['junior_adviser']);

        if (! $hasAny && $outcome === 'manual_required') {
            return 'none';
        }

        return 'fixed';
    }

    /**
     * Use the Gemini API to parse the Certification of Officers text.
     * Returns the same data structure as CertificationOfOfficersFixedLayoutParser.
     *
     * @return array{dean:?array,senior_adviser:?array,junior_adviser:?array,officers:array}
     */
    private function parseWithGemini(string $fullText): array
    {
        /** @var \App\Services\GroqService $groq */
        $groq = app(\App\Services\GroqService::class);

        if (! $groq->isConfigured()) {
            throw new \RuntimeException('No AI service configured.');
        }

        $prompt = <<<PROMPT
You are parsing an LSPU Certification of Officers PDF. Each page certifies one student officer.

Extract ALL officers from the text below. Return a JSON object with this exact structure:
{
  "dean": {"first_name": "", "middle_name": "", "last_name": "", "suffix": ""},
  "senior_adviser": {"first_name": "", "middle_name": "", "last_name": "", "suffix": ""},
  "junior_adviser": {"first_name": "", "middle_name": "", "last_name": "", "suffix": ""},
  "officers": {
    "SLOT_KEY": {"first_name": "", "middle_name": "", "last_name": "", "suffix": "", "year_level": "", "section": ""},
    ...
  }
}

SLOT_KEY values (use exactly these):
president, vice_president, secretary,
treasurer, auditor, business_manager,
pio (Public Information Officer), event_coordinator,
rep_1st_year, rep_2nd_year, rep_3rd_year, rep_4th_year, comelec

Rules:
- Names in "LAST, FIRST M." format — parse correctly into first_name, middle_name, last_name
- year_level = number only (e.g. "2"), section = letter(s) only (e.g. "A")
- dean = person signed as "Dean" or "Assoc. Dean" or "Dean/Assoc. Dean"
- senior_adviser = first Organization Adviser found across all pages
- junior_adviser = second Organization Adviser found
- If a position appears more than once (e.g. two Business Managers), keep the first on its canonical slot key and use custom_1, custom_2, ... for the rest, setting "position_name" to the human-readable label (e.g. "Business Manager")
- Ignore page numbers, dates, boilerplate text
- Return ONLY valid JSON, no markdown, no explanation

Document text:
PROMPT;

        $prompt .= "\n\n" . mb_substr(preg_replace('/\n{2,}/', "\n", $fullText), 0, 30000);

        $response = $groq->generate($prompt, 4096, true);
        if ($response === null || trim($response) === '') {
            throw new \RuntimeException('All AI services failed.');
        }

        // Strip markdown code fences if present
        $response = preg_replace('/```json\s*/i', '', $response);
        $response = preg_replace('/```\s*/i', '', $response);
        $response = trim($response);

        $json = json_decode($response, true);
        if (! is_array($json) || json_last_error() !== JSON_ERROR_NONE) {
            $start = strpos($response, '{');
            $end = strrpos($response, '}');
            if ($start !== false && $end !== false) {
                $json = json_decode(substr($response, $start, $end - $start + 1), true);
            }
        }

        if (! is_array($json)) {
            throw new \UnexpectedValueException('Gemini returned unparseable JSON for certification.');
        }

        $normalizeNameFields = function (?array $person): ?array {
            if (empty($person)) return null;
            return [
                'first_name'  => (string) ($person['first_name'] ?? ''),
                'middle_name' => (string) ($person['middle_name'] ?? ''),
                'last_name'   => (string) ($person['last_name'] ?? ''),
                'suffix'      => (string) ($person['suffix'] ?? ''),
            ];
        };

        $officers = [];
        foreach ($json['officers'] ?? [] as $slot => $officer) {
            if (empty($officer)) continue;
            $officers[$slot] = [
                'first_name'  => (string) ($officer['first_name'] ?? ''),
                'middle_name' => (string) ($officer['middle_name'] ?? ''),
                'last_name'   => (string) ($officer['last_name'] ?? ''),
                'suffix'      => (string) ($officer['suffix'] ?? ''),
                'year_level'  => (string) ($officer['year_level'] ?? ''),
                'section'     => (string) ($officer['section'] ?? ''),
                'course'      => (string) ($officer['course'] ?? ''),
            ];
            if (! empty($officer['position_name'])) {
                $officers[$slot]['position_name'] = (string) $officer['position_name'];
            }
        }

        return [
            'dean'           => $normalizeNameFields($json['dean'] ?? null),
            'senior_adviser' => $normalizeNameFields($json['senior_adviser'] ?? null),
            'junior_adviser' => $normalizeNameFields($json['junior_adviser'] ?? null),
            'officers'       => $officers,
        ];
    }

    /**
     * Fallback generic text extractor when fixed layout parser fails or produces empty results
     */
    private function fallbackExtractFromText(array $pages): array
    {
        $fullText = implode("\n", array_column($pages, 'text'));
        $lines = array_map('trim', preg_split('/[\n\r]+/', $fullText));
        $lines = array_values(array_filter($lines));
        $entries = [];

        // 1. Check for Certificate of Registration (COR) format
        if (preg_match('/Student\s+Name\s*[:\-–]\s*([^\t\n\r]+)/i', $fullText, $m)) {
            $rawName = trim(preg_split('/\t/', trim($m[1]))[0]);
            if (! empty($rawName)) {
                $nameParts = $this->splitName($rawName);
                $entries[] = [
                    'position' => 'PRESIDENT',
                    'first_name' => $nameParts['first_name'],
                    'middle_initial' => $nameParts['middle_name'],
                    'last_name' => $nameParts['last_name'],
                ];
            }
        }

        // 2. Fixed sentence "This certifies that [Name]" pattern
        preg_match_all('/This\s+certifies\s+that\s+([^,\n\r]+)/i', $fullText, $sentenceMatches, PREG_SET_ORDER);
        foreach ($sentenceMatches as $sm) {
            $rawName = trim($sm[1]);
            if (! empty($rawName) && strlen($rawName) > 2) {
                $nameParts = $this->splitName($rawName);
                $entries[] = [
                    'position' => 'PRESIDENT',
                    'first_name' => $nameParts['first_name'],
                    'middle_initial' => $nameParts['middle_name'],
                    'last_name' => $nameParts['last_name'],
                ];
            }
        }

        // 3. Expanded positions map for officer lists / certifications
        $positionMap = [
            'PRESIDENT' => 'PRESIDENT',
            'PRES.' => 'PRESIDENT',
            'VICE PRESIDENT' => 'VICE PRESIDENT',
            'VICE-PRESIDENT' => 'VICE PRESIDENT',
            'VP' => 'VICE PRESIDENT',
            'SECRETARY' => 'SECRETARY',
            'SEC.' => 'SECRETARY',
            'TREASURER' => 'TREASURER',
            'TREAS.' => 'TREASURER',
            'AUDITOR' => 'AUDITOR',
            'AUD.' => 'AUDITOR',
            'PUBLIC INFORMATION OFFICER' => 'PUBLIC INFORMATION OFFICER',
            'P.I.O.' => 'PUBLIC INFORMATION OFFICER',
            'PIO' => 'PUBLIC INFORMATION OFFICER',
            'P.R.O.' => 'PUBLIC INFORMATION OFFICER',
            'BUSINESS MANAGER' => 'BUSINESS MANAGER',
            'BUS. MGR.' => 'BUSINESS MANAGER',
            'EVENT COORDINATOR' => 'EVENT COORDINATOR',
            'COORDINATOR' => 'EVENT COORDINATOR',
            '1ST YEAR REPRESENTATIVE' => '1ST YEAR REPRESENTATIVE',
            '1 ST YEAR REPRESENTATIVE' => '1ST YEAR REPRESENTATIVE',
            'ST YEAR REPRESENTATIVE' => '1ST YEAR REPRESENTATIVE',
            '1ST YEAR REP' => '1ST YEAR REPRESENTATIVE',
            '2ND YEAR REPRESENTATIVE' => '2ND YEAR REPRESENTATIVE',
            '2 ND YEAR REPRESENTATIVE' => '2ND YEAR REPRESENTATIVE',
            'ND YEAR REPRESENTATIVE' => '2ND YEAR REPRESENTATIVE',
            '2ND YEAR REP' => '2ND YEAR REPRESENTATIVE',
            '3RD YEAR REPRESENTATIVE' => '3RD YEAR REPRESENTATIVE',
            '3 RD YEAR REPRESENTATIVE' => '3RD YEAR REPRESENTATIVE',
            'RD YEAR REPRESENTATIVE' => '3RD YEAR REPRESENTATIVE',
            '3RD YEAR REP' => '3RD YEAR REPRESENTATIVE',
            '4TH YEAR REPRESENTATIVE' => '4TH YEAR REPRESENTATIVE',
            '4 TH YEAR REPRESENTATIVE' => '4TH YEAR REPRESENTATIVE',
            'TH YEAR REPRESENTATIVE' => '4TH YEAR REPRESENTATIVE',
            '4TH YEAR REP' => '4TH YEAR REPRESENTATIVE',
        ];

        $totalLines = count($lines);
        for ($i = 0; $i < $totalLines; $i++) {
            $line = $lines[$i];

            foreach ($positionMap as $search => $posName) {
                if (stripos($line, $search) !== false) {
                    $nameStr = '';

                    // Check if name is on same line with delimiter
                    if (preg_match('/^(.+?)\s*[:\-–()]\s*(.+)$/i', $line, $m)) {
                        if (stripos($m[1], $search) !== false) {
                            $nameStr = trim($m[2], " \t\n\r\0\x0B()-");
                        } else {
                            $nameStr = trim($m[1], " \t\n\r\0\x0B()-");
                        }
                    } else {
                        // Strip out position word
                        $cleaned = trim(preg_replace('/'.preg_quote($search, '/').'/i', '', $line), " \t\n\r\0\x0B()-:");
                        if (strlen($cleaned) > 2) {
                            $nameStr = $cleaned;
                        } elseif ($i > 0 && strlen($lines[$i - 1]) > 2 && ! preg_match('/(CERTIFICATION|PAGE|LSPU|OSAS|UNIVERSITY)/i', $lines[$i - 1])) {
                            // Check previous line
                            $nameStr = $lines[$i - 1];
                        } elseif ($i < $totalLines - 1 && strlen($lines[$i + 1]) > 2 && ! preg_match('/(CERTIFICATION|PAGE|LSPU|OSAS|UNIVERSITY)/i', $lines[$i + 1])) {
                            // Check next line
                            $nameStr = $lines[$i + 1];
                        }
                    }

                    if (! empty($nameStr) && strlen($nameStr) > 2 && ! preg_match('/(OFFICER|POSITION|ROLE|NAME|SIGNATURE|NOTED|APPROVED|STUDENT ORGANIZATION|ORGANIZATION OF|COLLEGE OF|SERVICES|PROBATION|ELECTED|APPOINTED|BONAFIDE|LSPU)/i', $nameStr)) {
                        $nameParts = $this->splitName($nameStr);
                        $entries[] = [
                            'position' => $posName,
                            'first_name' => $nameParts['first_name'],
                            'middle_initial' => $nameParts['middle_name'],
                            'last_name' => $nameParts['last_name'],
                        ];
                    }
                    break;
                }
            }
        }

        $certifiers = $this->extractCertifiers($fullText);

        // 4. Page-level candidate name extraction for unlabelled name blocks
        foreach ($pages as $p) {
            $pText = $p['text'] ?? '';
            $pLines = array_map('trim', explode("\n", $pText));
            $pLines = array_values(array_filter($pLines));

            $foundPos = null;
            foreach ($pLines as $pLine) {
                foreach ($positionMap as $search => $pos) {
                    if (stripos($pLine, $search) !== false) {
                        $foundPos = $pos;
                        break 2;
                    }
                }
            }

            if (! $foundPos) continue;

            $candidates = [];
            foreach ($pLines as $pLine) {
                if (preg_match('/^[A-Z\s.,\-]+$/i', $pLine) && ! preg_match('/(REPUBLIC|POLYTECHNIC|UNIVERSITY|PROVINCE|OFFICE|AFFAIRS|SERVICES|CERTIFICATION|DATE|THIS CERTIFIES|STUDENT|BACHELOR|SCIENCE|COMPUTER|COLLEGE|PROBATION|ELECTED|APPOINTED|ORGANIZATION|CERTIFIED|ADVISER|DEAN|NOTED|DIRECTOR|CHAIRPERSON|REV|SF-006)/i', $pLine)) {
                    if (strlen($pLine) > 3) {
                        $candidates[] = $pLine;
                    }
                }
            }

            if (! empty($candidates)) {
                $nameParts = $this->splitName($candidates[0]);
                $entries[] = [
                    'position' => $foundPos,
                    'first_name' => $nameParts['first_name'],
                    'middle_initial' => $nameParts['middle_name'],
                    'last_name' => $nameParts['last_name'],
                ];

                if (isset($candidates[1]) && empty($certifiers)) {
                    $certifiers[] = ['name' => $candidates[1], 'role' => 'Senior Adviser'];
                }
                if (isset($candidates[2]) && count($certifiers) < 2) {
                    $certifiers[] = ['name' => $candidates[2], 'role' => 'Junior Adviser'];
                }
                if (isset($candidates[3]) && count($certifiers) < 3) {
                    $certifiers[] = ['name' => $candidates[3], 'role' => 'Dean'];
                }
            }
        }

        return $this->mapPrefillData($entries, $certifiers);
    }

    /**
     * Extract a field value from PDF text using label-based regex
     */
    private function extractPdfField($text, $label)
    {
        if (preg_match('/'.$label.'\s*[:.\-]?\s*([^\n\r]+)/i', $text, $m)) {
            $value = trim($m[1]);
            $value = preg_replace('/\s+/', ' ', $value);

            return $value;
        }

        return '';
    }

    /**
     * Extract certifiers (advisers, dean) from PDF text
     */
    private function extractCertifiers($text)
    {
        $certifiers = [];

        // Try to find "Certified by" section
        if (preg_match('/Certified by[:\s]*(.+?)(?=Noted by|$)/is', $text, $section)) {
            $block = $section[1];
            // Split by newlines or common separators
            $lines = preg_split('/[\n\r]+/', $block);
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) {
                    continue;
                }
                // Try to separate name from role/position
                $name = '';
                $role = '';
                if (preg_match('/^(.+?)\s*[-–]\s*(.+)$/', $line, $m)) {
                    $name = trim($m[1]);
                    $role = trim($m[2]);
                } elseif (preg_match('/^(.+?),\s*(.+)$/', $line, $m)) {
                    $name = trim($m[1]);
                    $role = trim($m[2]);
                } else {
                    $name = $line;
                }
                if (! empty($name)) {
                    $certifiers[] = ['name' => $name, 'role' => $role];
                }
            }
        }

        return $certifiers;
    }

    /**
     * Split a full name into first, middle, last parts
     */
    private function splitName($fullName)
    {
        $parts = preg_split('/\s+/', trim($fullName));
        $result = ['first_name' => '', 'middle_name' => '', 'last_name' => '', 'suffix' => ''];

        if (empty($parts) || empty($parts[0])) {
            return $result;
        }

        $last = array_pop($parts);
        // Check for suffix in last part
        $suffixes = ['JR', 'SR', 'II', 'III', 'IV', 'JR.', 'SR.', 'DIT', 'LPT', 'PHD', 'EDD', 'MD'];
        $suffix = '';
        if (in_array(strtoupper($last), $suffixes)) {
            $suffix = $last;
            $last = array_pop($parts);
        }
        $result['last_name'] = $last ?? '';
        $result['suffix'] = $suffix;

        if (! empty($parts)) {
            $result['first_name'] = array_shift($parts);
        }
        if (! empty($parts)) {
            $result['middle_name'] = implode(' ', $parts);
        }

        return $result;
    }

    /**
     * Map extracted PDF entries to form position keys
     */
    private function mapPrefillData($entries, $certifiers)
    {
        $officers = [];
        $counters = [];

        $positionMap = [
            'PRESIDENT' => 'president',
            'VICE PRESIDENT' => 'vice_president',
            'SECRETARY' => 'secretary',
            'TREASURER' => 'treasurer',
            'AUDITOR' => 'auditor',
            'PUBLIC INFORMATION OFFICER' => 'pio',
            'BUSINESS MANAGER' => 'business_manager',
            'EVENT COORDINATOR' => 'event_coordinator',
            '1ST YEAR REPRESENTATIVE' => 'rep_1st_year',
            '2ND YEAR REPRESENTATIVE' => 'rep_2nd_year',
            '3RD YEAR REPRESENTATIVE' => 'rep_3rd_year',
            '4TH YEAR REPRESENTATIVE' => 'rep_4th_year',
        ];

        foreach ($entries as $entry) {
            $posUpper = strtoupper(trim($entry['position']));
            $baseKey = $positionMap[$posUpper] ?? null;

            if (! $baseKey) {
                // Try fuzzy match
                foreach ($positionMap as $pdfPos => $formKey) {
                    $sim = similar_text($posUpper, $pdfPos, $perc);
                    if ($perc > 70) {
                        $baseKey = $formKey;
                        break;
                    }
                }
            }
            if (! $baseKey) {
                continue;
            }

            // Handle multi-count positions
            $multiKeys = ['secretary', 'pio', 'business_manager', 'event_coordinator'];
            if (in_array($baseKey, $multiKeys)) {
                if (! isset($counters[$baseKey])) {
                    $counters[$baseKey] = 0;
                }
                $counters[$baseKey]++;
                $key = $baseKey.($counters[$baseKey] > 1 ? "_{$counters[$baseKey]}" : '');
            } else {
                $key = $baseKey;
            }

            $officers[] = [
                'position' => $key,
                'first_name' => $entry['first_name'],
                'middle_name' => $entry['middle_initial'],
                'last_name' => $entry['last_name'],
            ];
        }

        // Extract advisers and dean from certifiers
        $seniorAdviser = null;
        $juniorAdviser = null;
        $dean = null;

        $adviserCount = 0;
        foreach ($certifiers as $c) {
            $roleLower = strtolower($c['role'] ?? '');
            $name = $c['name'] ?? '';

            if (str_contains($roleLower, 'dean') || str_contains($roleLower, 'assoc')) {
                $nameParts = $this->splitName($name);
                // Check for degree suffix at end of full certifier entry
                if (preg_match('/,\s*([A-Z]+)$/', $name, $deg)) {
                    if (empty($nameParts['suffix'])) {
                        $nameParts['suffix'] = $deg[1];
                    }
                    $name = preg_replace('/,\s*[A-Z]+$/', '', $name);
                    $nameParts = $this->splitName($name);
                    $nameParts['suffix'] = $deg[1];
                }
                $dean = $nameParts;
            } elseif (str_contains($roleLower, 'adviser') || str_contains($roleLower, 'advisor')) {
                $adviserCount++;
                $nameParts = $this->splitName($name);
                if ($adviserCount === 1) {
                    $seniorAdviser = $nameParts;
                } else {
                    $juniorAdviser = $nameParts;
                }
            }
        }

        return [
            'senior_adviser' => $seniorAdviser,
            'junior_adviser' => $juniorAdviser,
            'dean' => $dean,
            'officers' => $officers,
        ];
    }
}
