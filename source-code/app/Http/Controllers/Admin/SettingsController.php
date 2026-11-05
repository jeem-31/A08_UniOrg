<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OrganizationPosition;
use App\Models\OrganizationRolePermission;
use App\Models\OrganizationUser;
use App\Models\User;
use App\Notifications\PositionAssignedNotification;
use App\Notifications\PositionRemovedNotification;
use App\Services\OrganizationPermissionService;
use App\Services\PermissionMatrix;
use App\Services\ThemeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SettingsController extends Controller
{
    /** Positions that are allowed to access settings */
    private const ALLOWED_POSITIONS = [
        'President', 'Organization President',
        'Adviser', 'Organization Adviser', 'Senior Adviser', 'Junior Adviser',
    ];

    public function __construct(
        private ThemeService $themeService,
        private OrganizationPermissionService $permissionService,
        private PermissionMatrix $matrix,
        private \App\Services\ElectionOfficerTransitionService $transitionService,
    ) {}

    /**
     * Display the settings page (tabbed: Theme Settings + Manage Admins).
     */
    public function index(): View
    {
        $currentOrgId = session('current_organization_id');
        $userPosition = $currentOrgId ? auth()->user()->getPositionInOrganization($currentOrgId) : null;

        if (!in_array($userPosition, self::ALLOWED_POSITIONS)) {
            abort(403, 'Unauthorized access. Only Presidents and Advisers can access theme settings.');
        }

        // The Manage Accounts tab is adviser-only: Presidents can use the other
        // tabs but never see or act on member account status.
        $isAdviser = $currentOrgId && $this->isAdviserPosition($userPosition);

        $theme = $this->themeService->getThemeSettings();

        // ── Manage Admins data ──────────────────────────────────────────────

        // All non-Member, Active officers of the current organization.
        // Adviser and Dean roles are excluded — they are fixed and not manageable here.
        $officers = DB::table('organization_user')
            ->join('users', 'users.id', '=', 'organization_user.user_id')
            ->where('organization_user.organization_id', $currentOrgId)
            ->where('organization_user.membership_status', 'Active')
            ->where('organization_user.role', '!=', 'Member')
            ->whereRaw("LOWER(organization_user.role) NOT LIKE '%adviser%'")
            ->whereRaw("LOWER(organization_user.role) NOT LIKE '%dean%'")
            ->select(
                'users.id',
                'users.first_name',
                'users.last_name',
                'users.suffix',
                'users.photo',
                'organization_user.role',
            )
            ->get();

        // Two people holding the same position can be stored with a " 2" slot
        // suffix ("Business Manager" + "Business Manager 2"), and legacy data
        // may use alternate spellings ("Business_manager", "Vice_president").
        // Normalize every officer to the canonical position so all holders of
        // the same role share one row — and one module profile.
        $officers = $officers->map(function ($officer) {
            $officer->role = $this->matrix->canonicalize(OrganizationUser::formatDisplayLabel($officer->role));
            return $officer;
        });

        // Group officers by role → [ 'Business Manager' => [ {user}, … ], … ]
        $groupedByRole = $officers->groupBy('role');

        // Fixed display order for known officer positions. Any role not listed
        // here (e.g. custom/unknown roles) is appended alphabetically at the end.
        $rolePriority = [
            'President', 'Organization President',
            'Vice President',
            'Secretary',
            'Treasurer',
            'Auditor',
            'Business Manager',
            'PIO',
            'Event Coordinator',
            '1st Year Representative',
            '2nd Year Representative',
            '3rd Year Representative',
            '4th Year Representative',
        ];

        $sortedRoleKeys = $groupedByRole->keys()->sort(function ($a, $b) use ($rolePriority) {
            $ai = array_search($a, $rolePriority, true);
            $bi = array_search($b, $rolePriority, true);
            $ai = $ai !== false ? $ai : (1000 + ord(strtolower($a[0] ?? 'z')));
            $bi = $bi !== false ? $bi : (1000 + ord(strtolower($b[0] ?? 'z')));
            return $ai <=> $bi;
        })->values();

        $adminsByRole = $sortedRoleKeys->mapWithKeys(fn ($role) => [$role => $groupedByRole[$role]]);

        // All modules the org has enabled
        $orgSettings = DB::table('organization_settings')
            ->where('organization_id', $currentOrgId)
            ->first();

        $moduleColumnMap = [
            'announcements' => 'has_announcements',
            'events'        => 'has_events',
            'payments'      => 'has_payments',
            'chatbot'       => 'has_chatbot',
            'elections'     => 'has_elections',
            'library'       => 'has_elibrary',
            'edushare'      => 'has_edushare',
            'documents'     => 'has_documents',
            'complaints'    => 'has_complaints',
        ];

        $enabledModules = [];
        foreach ($moduleColumnMap as $module => $column) {
            $val = $orgSettings?->$column ?? false;
            if ($val === true || $val === 'true' || $val === 't' || $val === 1 || $val === '1') {
                $enabledModules[] = $module;
            }
        }

        // Existing grants for this org, keyed by "role|module". Grant rows may
        // be stored under any spelling of a position ("Business Manager",
        // "Business Manager 2", …), so fold them onto the canonical role — a
        // group must show one consistent profile.
        $grantRecords = OrganizationRolePermission::where('organization_id', $currentOrgId)
            ->get()
            ->map(function ($record) {
                $record->role = $this->matrix->canonicalize(OrganizationUser::formatDisplayLabel($record->role));
                return $record;
            })
            ->reduce(function (array $carry, $record) {
                $key = "{$record->role}|{$record->module}";
                if (!isset($carry[$key]) || (!(bool) $carry[$key]->is_granted && (bool) $record->is_granted)) {
                    $carry[$key] = $record;
                }
                return $carry;
            }, []);

        // Positions created in the Add Positions tab use an explicit profile.
        // This lets the admin choose both the granted and ungranted modules,
        // even when the name happens to match a legacy matrix role.
        $managedPositionNames = OrganizationPosition::where('organization_id', $currentOrgId)
            ->pluck('name')
            ->flip();

        // Build per-role permission display data
        // [ 'Secretary' => [ 'announcements' => ['default'=>true,'granted'=>false], … ] ]
        $rolePermissions = [];
        foreach ($adminsByRole->keys() as $role) {
            $rolePermissions[$role] = [];
            foreach ($enabledModules as $module) {
                $isManagedPosition = isset($managedPositionNames[$role]);
                $defaultAccess = $isManagedPosition ? 'none' : $this->matrix->getAccessLevel($role, $module);
                $isDefault     = in_array($defaultAccess, ['full', 'read']);
                $grantRecord   = $grantRecords["{$role}|{$module}"] ?? null;
                $isGranted     = $grantRecord ? (bool) $grantRecord->is_granted : false;

                // COMELEC is elections-only: ignore any additive grants for other modules
                if ($role === 'COMELEC' && $module !== 'elections') {
                    $isGranted = false;
                }

                $rolePermissions[$role][$module] = [
                    'default' => $isDefault,
                    'granted' => $isGranted,
                    'active'  => $isDefault || $isGranted, // effective access
                ];
            }
        }

        // Classify roles: default positions have built-in matrix access;
        // "other" positions (custom/apply-org roles) have none and rely on
        // grants toggled by the adviser in the Admins tab.
        $isDefaultRole = [];
        foreach ($adminsByRole->keys() as $role) {
            $isDefaultRole[$role] = count($this->matrix->getModulesForPosition($role)) > 0;
        }

        // Roles with zero effective module access — drives the Admins tab badge.
        $zeroAccessCount = $enabledModules
            ? collect($rolePermissions)->filter(
                fn ($perms) => collect($perms)->every(fn ($perm) => !$perm['active'])
            )->count()
            : 0;

        $allModules    = $this->matrix->getAllModules();
        $moduleLabels  = [
            'announcements' => 'Announcements',
            'events'        => 'Events',
            'payments'      => 'Payments',
            'chatbot'       => 'Manage FAQ',
            'elections'     => 'Elections',
            'library'       => 'E-Library',
            'edushare'      => 'EduShare',
            'documents'     => 'Documents',
            'complaints'    => 'Complaints',
        ];
        $moduleIcons   = [
            'announcements' => 'ph-megaphone',
            'events'        => 'ph-calendar-blank',
            'payments'      => 'ph-credit-card',
            'chatbot'       => 'ph-question',
            'elections'     => 'ph-check-square',
            'library'       => 'ph-book',
            'edushare'      => 'ph-share-network',
            'documents'     => 'ph-file-text',
            'complaints'    => 'ph-warning-circle',
        ];

        // Position definitions and their current assignees for the Add Positions tab.
        $positions = OrganizationPosition::where('organization_id', $currentOrgId)
            ->orderBy('name')
            ->get();

        $positionMembers = DB::table('organization_user')
            ->join('users', 'users.id', '=', 'organization_user.user_id')
            ->where('organization_user.organization_id', $currentOrgId)
            ->where('organization_user.membership_status', 'Active')
            ->whereIn('organization_user.role', $positions->pluck('name')->all())
            ->select(
                'users.id',
                'users.first_name',
                'users.last_name',
                'users.suffix',
                'users.student_id',
                'users.photo',
                'organization_user.role',
            )
            ->orderBy('users.last_name')
            ->get()
            ->groupBy('role');

        // A member can be moved from one position to another. Advisers and
        // deans remain outside this student-officer assignment workflow.
        $assignableMembers = DB::table('organization_user')
            ->join('users', 'users.id', '=', 'organization_user.user_id')
            ->where('organization_user.organization_id', $currentOrgId)
            ->where('organization_user.membership_status', 'Active')
            ->where('users.id', '!=', auth()->id())
            ->whereIn('users.role', ['student', 'admin'])
            ->whereRaw("LOWER(organization_user.role) NOT LIKE '%adviser%'")
            ->whereRaw("LOWER(organization_user.role) NOT LIKE '%dean%'")
            ->select(
                'users.id',
                'users.first_name',
                'users.last_name',
                'users.suffix',
                'users.student_id',
                'users.photo',
                'users.role as account_role',
                'organization_user.role as organization_role',
            )
            ->orderBy('users.last_name')
            ->orderBy('users.first_name')
            ->get();

        $positionPermissions = [];
        foreach ($positions as $position) {
            $positionPermissions[$position->name] = collect($allModules)
                ->filter(function ($module) use ($grantRecords, $position) {
                    $record = $grantRecords["{$position->name}|{$module}"] ?? null;
                    return $record ? (bool) $record->is_granted : false;
                })
                ->values()
                ->all();
        }

        // ── Manage Accounts data (adviser-only) ──────────────────────────────
        // Every member account of this org (Active, Pending, Inactive) except
        // advisers, deans, COMELEC, and the logged-in adviser. Keeping the
        // account role and membership role intact makes deactivation fully
        // reversible from this same tab.
        // For the UI the list is split into Admins (officers, President→4th Yr Rep)
        // and Students (Members) and ordered accordingly.
        $accounts = collect();
        if ($isAdviser) {
            $accounts = DB::table('organization_user')
                ->join('users', 'users.id', '=', 'organization_user.user_id')
                ->where('organization_user.organization_id', $currentOrgId)
                ->where('users.id', '!=', auth()->id())
                ->whereRaw("LOWER(organization_user.role) NOT LIKE '%adviser%'")
                ->whereRaw("LOWER(organization_user.role) NOT LIKE '%dean%'")
                ->whereRaw("UPPER(organization_user.role) <> 'COMELEC'")
                ->select(
                    'users.id',
                    'users.first_name',
                    'users.last_name',
                    'users.middle_name',
                    'users.suffix',
                    'users.email',
                    'users.student_id',
                    'users.photo',
                    'users.role as account_role',
                    'users.is_active',
                    'organization_user.role as org_role',
                    'organization_user.membership_status',
                )
                ->get()
                ->map(function ($account) {
                    // Normalize cross-driver boolean representation ('t'/'f' from
                    // Postgres, 1/0 from SQLite) into a real boolean for the UI.
                    $account->is_active = in_array($account->is_active, [true, 1, '1', 't', 'true'], true);
                    // Names may be stored in ALL CAPS; display them in title case
                    // so only the first letter of each word is capitalized.
                    // The suffix is always included in the displayed name.
                    $account->full_name = name_with_suffix(
                        $account->first_name,
                        $account->middle_name,
                        $account->last_name,
                        $account->suffix
                    );
                    $account->photo_url = $account->photo ? storage_url($account->photo) : null;
                    // Consistent display label and admin flag for the UI grouping
                    $account->display_role = OrganizationUser::formatDisplayLabel((string) $account->org_role);
                    $account->is_admin = strtolower(trim((string) $account->org_role)) !== 'member';
                    return $account;
                })
                ->values();

            // President → ... → 4th Year Rep ordering for officers (admins)
            $adminPriority = [
                'President' => 1, 'Organization President' => 1,
                'Vice President' => 2,
                'Secretary' => 3,
                'Treasurer' => 4,
                'Auditor' => 5,
                'Business Manager' => 6,
                'PIO' => 7, 'Public Information Officer' => 7,
                'Event Coordinator' => 8,
                '1st Year Representative' => 9,
                '2nd Year Representative' => 10,
                '3rd Year Representative' => 11,
                '4th Year Representative' => 12,
                'COMELEC' => 13,
            ];

            $accounts = $accounts->sort(function ($a, $b) use ($adminPriority) {
                $aIsAdmin = (bool) $a->is_admin;
                $bIsAdmin = (bool) $b->is_admin;

                // Admins first, then Students
                if ($aIsAdmin !== $bIsAdmin) {
                    return $aIsAdmin ? -1 : 1;
                }

                if ($aIsAdmin && $bIsAdmin) {
                    $aCan = $adminPriority[$a->display_role] ?? $adminPriority[$this->matrix->canonicalize($a->display_role)] ?? 999;
                    $bCan = $adminPriority[$b->display_role] ?? $adminPriority[$this->matrix->canonicalize($b->display_role)] ?? 999;
                    if ($aCan !== $bCan) {
                        return $aCan <=> $bCan;
                    }
                    // Same role level → alphabetical
                    return strcmp(strtolower($a->full_name), strtolower($b->full_name));
                }

                // Both students → alphabetical
                return strcmp(strtolower($a->full_name), strtolower($b->full_name));
            })->values();
        }

        // ── Officer transition (adviser-only, election winners pending approval) ──
        $pendingTransition = null;
        $officerTransitionWinners = [];
        if ($isAdviser) {
            $pendingTransition = \App\Models\ElectionOfficerTransition::where('organization_id', $currentOrgId)
                ->whereIn('status', ['pending', 'scheduled'])
                ->with('election')
                ->latest('id')
                ->first();
            if ($pendingTransition && is_array($pendingTransition->winners_snapshot ?? null)) {
                $posMap = \App\Services\ElectionOfficerTransitionService::POSITION_TO_OFFICER_MAP;
                foreach ($pendingTransition->winners_snapshot as $posKey => $data) {
                    $userId = $data['user_id'] ?? null;
                    $candidateId = $data['candidate_id'] ?? null;
                    $user = $userId ? User::find($userId) : null;
                    $candidate = $candidateId ? \App\Models\Candidate::find($candidateId) : null;
                    $officerTransitionWinners[] = [
                        'position_key' => $posKey,
                        'officer_role' => $posMap[$posKey] ?? $posKey,
                        'candidate_name' => $data['name'] ?? $candidate?->name ?? $user?->full_name ?? '-',
                        'student_id' => $data['student_id'] ?? $user?->student_id ?? '-',
                        'vote_count' => $data['vote_count'] ?? $candidate?->vote_count ?? 0,
                        'photo_url' => $candidate?->photo ? storage_url($candidate->photo) : ($user?->photo ? storage_url($user->photo) : null),
                        'user_id' => $userId,
                    ];
                }
            }
        }

        return view('admin.settings.index', compact(
            'theme',
            'adminsByRole',
            'rolePermissions',
            'isDefaultRole',
            'zeroAccessCount',
            'enabledModules',
            'allModules',
            'moduleLabels',
            'moduleIcons',
            'managedPositionNames',
            'positions',
            'positionMembers',
            'assignableMembers',
            'positionPermissions',
            'accounts',
            'isAdviser',
            'pendingTransition',
            'officerTransitionWinners',
        ));
    }

    /**
     * Update theme settings (existing).
     */
    public function update(Request $request): RedirectResponse|JsonResponse
    {
        $currentOrgId  = session('current_organization_id');
        $userPosition  = $currentOrgId ? auth()->user()->getPositionInOrganization($currentOrgId) : null;

        if (!in_array($userPosition, self::ALLOWED_POSITIONS)) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access. Only Presidents and Advisers can update theme settings.',
                ], 403);
            }
            abort(403, 'Unauthorized access. Only Presidents and Advisers can update theme settings.');
        }

        try {
            $validated = $request->validate([
                'logo'                => 'nullable|image|mimes:png,jpg,jpeg,svg,webp|max:2048',
                'primary_color'       => 'nullable|regex:/^#[0-9A-Fa-f]{6}$/',
                'complementary_color' => 'nullable|regex:/^#[0-9A-Fa-f]{6}$/',
            ]);

            Log::info('Settings update request', [
                'has_logo_file' => $request->hasFile('logo'),
                'logo_valid'    => $request->hasFile('logo') && $request->file('logo')->isValid(),
                'validated_data' => $validated,
            ]);

            $this->themeService->updateThemeSettings($validated);

            // Clear session cache for logo and name
            session()->forget([
                'organization_logo',
                'organization_name',
                "org_logo_{$currentOrgId}",
                "org_name_{$currentOrgId}",
            ]);

            if (isset($validated['primary_color']) || isset($validated['complementary_color']) || isset($validated['logo'])) {
                $settings = \App\Models\OrganizationSettings::where('organization_id', $currentOrgId)->first();
                $logoUrl  = organization_logo();

                try {
                    event(new \App\Events\ThemeColorsUpdated(
                        $currentOrgId,
                        $settings->primary_color,
                        $settings->complementary_color,
                        $logoUrl,
                    ));
                } catch (\Throwable $exception) {
                    // Theme persistence has already succeeded; a transient realtime
                    // transport outage must not turn the save into a false failure.
                    Log::warning('Theme update broadcast failed.', [
                        'exception_class' => $exception::class,
                        'organization_id' => $currentOrgId,
                    ]);
                }
            }

            if ($request->expectsJson() || $request->ajax()) {
                $settings = \App\Models\OrganizationSettings::where('organization_id', $currentOrgId)->first();
                return response()->json([
                    'success'             => true,
                    'message'             => 'Theme settings saved successfully',
                    'primary_color'       => $settings->primary_color,
                    'complementary_color' => $settings->complementary_color,
                    'logo_url'            => organization_logo(),
                ]);
            }

            return redirect()->back()->with('success', 'Theme settings saved successfully');

        } catch (\Illuminate\Validation\ValidationException $e) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors'  => $e->errors(),
                ], 422);
            }
            return redirect()->back()->withErrors($e->errors())->withInput();

        } catch (\Exception $e) {
            Log::error('Theme settings update failed', [
                'error'           => $e->getMessage(),
                'user_id'         => auth()->id(),
                'organization_id' => $currentOrgId,
            ]);

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to save theme settings. Please try again',
                ], 500);
            }
            return redirect()->back()->with('error', 'Failed to save theme settings. Please try again')->withInput();
        }
    }

    /**
     * Toggle a module grant for a role within the current organization.
     * This is the additive layer — it can only GRANT access beyond matrix defaults.
     */
    public function updateRolePermission(Request $request): JsonResponse
    {
        $currentOrgId = session('current_organization_id');
        $userPosition = $currentOrgId ? auth()->user()->getPositionInOrganization($currentOrgId) : null;

        if (!in_array($userPosition, self::ALLOWED_POSITIONS)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate([
            'role'       => 'required|string',
            'module'     => 'required|string|in:announcements,events,payments,chatbot,elections,library,edushare,documents,complaints',
            'is_granted' => 'required|boolean',
        ]);

        $role   = $validated['role'];
        $module = $validated['module'];

        // The UI always sends the canonical position name. Resolve it, then
        // collect every raw spelling used in this org that maps to it
        // ("Business Manager", "Business Manager 2", …) so a toggle applies
        // to all holders of the same position.
        $canonicalRole = $this->matrix->canonicalize(OrganizationUser::formatDisplayLabel($role));

        // COMELEC lock: only elections can be toggled
        if ($canonicalRole === 'COMELEC' && $module !== 'elections') {
            return response()->json([
                'success' => false,
                'message' => 'COMELEC is elections-only — other modules cannot be granted.',
            ], 422);
        }

        $roleVariants = DB::table('organization_user')
            ->where('organization_id', $currentOrgId)
            ->where('membership_status', 'Active')
            ->pluck('role')
            ->unique()
            ->filter(function ($rawRole) use ($canonicalRole) {
                return $this->matrix->canonicalize(OrganizationUser::formatDisplayLabel($rawRole)) === $canonicalRole;
            })
            ->push($canonicalRole)
            ->unique()
            ->values()
            ->all();

        $isManagedPosition = OrganizationPosition::where('organization_id', $currentOrgId)
            ->where('name', $canonicalRole)
            ->exists();

        // Legacy matrix roles are additive and their default access cannot be
        // revoked here. Managed positions use an explicit profile, so both
        // true and false are persisted.
        $defaultAccess = $this->matrix->getAccessLevel($canonicalRole, $module);
        if (!$isManagedPosition && in_array($defaultAccess, ['full', 'read'])) {
            return response()->json([
                'success' => false,
                'message' => "Role '{$canonicalRole}' already has default access to '{$module}' — no grant needed.",
            ], 422);
        }

        // Upsert the grant record for every spelling of the role
        foreach ($roleVariants as $variantRole) {
            OrganizationRolePermission::updateOrCreate(
                [
                    'organization_id' => $currentOrgId,
                    'role'            => $variantRole,
                    'module'          => $module,
                ],
                ['is_granted' => $validated['is_granted']],
            );

            // Invalidate grant caches for this spelling of the role
            $this->permissionService->invalidateGrantCache($currentOrgId, $variantRole);
        }

        // Invalidate per-user caches for all users in this org with this role
        $affectedUserIds = DB::table('organization_user')
            ->where('organization_id', $currentOrgId)
            ->whereIn('role', $roleVariants)
            ->where('membership_status', 'Active')
            ->pluck('user_id');

        foreach ($affectedUserIds as $userId) {
            $this->permissionService->invalidateCache($userId, $currentOrgId);
        }

        // Note: invalidateGrantCache() above already bumps the org's "grants version",
        // which is baked into the sidebar's nav cache key (see AdminNavigationComposer).
        // That makes stale session-cached nav permissions bypass automatically on the
        // affected users' next page load — this works regardless of session driver
        // (file, database, redis, etc.), unlike directly rewriting session rows.

        Log::info('Role permission updated', [
            'org_id'     => $currentOrgId,
            'role'       => $canonicalRole,
            'role_variants' => $roleVariants,
            'module'     => $module,
            'is_granted' => $validated['is_granted'],
            'changed_by' => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => $validated['is_granted']
                ? "Access to '{$module}' granted for {$canonicalRole}."
                : "Access to '{$module}' revoked for {$canonicalRole}.",
        ]);
    }

    /**
     * Create an organization-scoped position and its explicit module profile.
     */
    public function storePosition(Request $request): RedirectResponse
    {
        $organizationId = $this->authorizedOrganizationId();
        $allModules = $this->matrix->getAllModules();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'modules' => ['nullable', 'array'],
            'modules.*' => ['string', 'in:' . implode(',', $allModules)],
        ]);

        $positionName = Str::squish($validated['name']);
        if ($positionName === '') {
            return back()->withErrors(['name' => 'Enter a position name.'])->withInput();
        }

        $nameExists = OrganizationPosition::where('organization_id', $organizationId)
            ->whereRaw('LOWER(name) = ?', [Str::lower($positionName)])
            ->exists();

        $roleExists = DB::table('organization_user')
            ->where('organization_id', $organizationId)
            ->whereRaw('LOWER(role) = ?', [Str::lower($positionName)])
            ->whereRaw("LOWER(role) <> 'member'")
            ->exists();

        if ($nameExists || $roleExists) {
            return back()
                ->withErrors(['name' => 'That position already exists in this organization.'])
                ->withInput()
                ->with('settings_tab', 'positions');
        }

        $selectedModules = array_values(array_unique($validated['modules'] ?? []));

        DB::transaction(function () use ($organizationId, $positionName, $selectedModules, $allModules) {
            OrganizationPosition::create([
                'organization_id' => $organizationId,
                'name' => $positionName,
            ]);

            foreach ($allModules as $module) {
                OrganizationRolePermission::updateOrCreate(
                    [
                        'organization_id' => $organizationId,
                        'role' => $positionName,
                        'module' => $module,
                    ],
                    ['is_granted' => in_array($module, $selectedModules, true)],
                );
            }
        });

        $this->permissionService->invalidateGrantCache($organizationId, $positionName);

        return redirect()
            ->route('admin.settings.index', ['tab' => 'positions'])
            ->with('success', "Position '{$positionName}' was created.")
            ->with('settings_tab', 'positions');
    }

    /**
     * Update the explicit module profile for a position.
     */
    public function updatePosition(Request $request, OrganizationPosition $position): RedirectResponse
    {
        $organizationId = $this->authorizedOrganizationId();
        $position = OrganizationPosition::where('organization_id', $organizationId)
            ->findOrFail($position->id);
        $allModules = $this->matrix->getAllModules();

        $validated = $request->validate([
            'modules' => ['nullable', 'array'],
            'modules.*' => ['string', 'in:' . implode(',', $allModules)],
        ]);

        $selectedModules = array_values(array_unique($validated['modules'] ?? []));

        DB::transaction(function () use ($organizationId, $position, $selectedModules, $allModules) {
            foreach ($allModules as $module) {
                OrganizationRolePermission::updateOrCreate(
                    [
                        'organization_id' => $organizationId,
                        'role' => $position->name,
                        'module' => $module,
                    ],
                    ['is_granted' => in_array($module, $selectedModules, true)],
                );
            }
        });

        $this->permissionService->invalidateGrantCache($organizationId, $position->name);

        return redirect()
            ->route('admin.settings.index', ['tab' => 'positions'])
            ->with('success', "Access for '{$position->name}' was updated.")
            ->with('settings_tab', 'positions');
    }

    /**
     * Assign an active organization member to a position.
     */
    public function assignPosition(Request $request, OrganizationPosition $position): RedirectResponse
    {
        $organizationId = $this->authorizedOrganizationId();
        $position = OrganizationPosition::where('organization_id', $organizationId)
            ->findOrFail($position->id);

        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $membership = OrganizationUser::where('organization_id', $organizationId)
            ->where('user_id', $validated['user_id'])
            ->where('membership_status', 'Active')
            ->first();

        if (!$membership) {
            return back()
                ->with('error', 'Only active members of this organization can be assigned to a position.')
                ->with('settings_tab', 'positions');
        }

        $member = User::findOrFail($validated['user_id']);
        if ($member->id === auth()->id()
            || in_array($member->role, ['super_admin', 'osas_admin'], true)
            || Str::contains(Str::lower((string) $membership->role), ['adviser', 'dean'])) {
            return back()
                ->with('error', 'This account cannot be assigned to an organization position.')
                ->with('settings_tab', 'positions');
        }

        $oldRole = $membership->role;
        $wasStudent = $member->role === 'student';

        DB::transaction(function () use ($membership, $member, $position, $wasStudent) {
            $membership->role = $position->name;
            $membership->save();

            // Keep the assigned student in Student View by default. The
            // existing context switch will then expose Admin View in the
            // student's profile dropdown.
            $member->role = 'admin';
            if ($wasStudent) {
                $member->active_view = 'student';
            }
            $member->save();
        });

        $this->permissionService->invalidateCache($member->id, $organizationId);
        $this->permissionService->invalidateGrantCache($organizationId, $position->name);

        $emailWarning = null;
        if ($oldRole !== $position->name) {
            try {
                $member->notify(new PositionAssignedNotification(
                    organization: \App\Models\Organization::findOrFail($organizationId),
                    position: $position->name,
                    assignedBy: auth()->user()->full_name,
                ));
            } catch (\Throwable $exception) {
                $emailWarning = ' The in-app notification was saved, but the email could not be sent.';
                Log::error('Position assignment email notification failed', [
                    'user_id' => $member->id,
                    'organization_id' => $organizationId,
                    'position' => $position->name,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return redirect()
            ->route('admin.settings.index', ['tab' => 'positions'])
            ->with('success', "{$member->full_name} was assigned as {$position->name}.{$emailWarning}")
            ->with('settings_tab', 'positions');
    }

    /**
     * Remove a member from a managed position and restore the Member/student
     * state when the account no longer holds an officer position.
     */
    public function removePositionMember(OrganizationPosition $position, User $user): RedirectResponse
    {
        $organizationId = $this->authorizedOrganizationId();
        $position = OrganizationPosition::where('organization_id', $organizationId)
            ->findOrFail($position->id);

        $membership = OrganizationUser::where('organization_id', $organizationId)
            ->where('user_id', $user->id)
            ->where('role', $position->name)
            ->firstOrFail();

        DB::transaction(function () use ($membership, $user) {
            $membership->role = 'Member';
            $membership->save();

            $hasAnotherOfficerPosition = DB::table('organization_user')
                ->where('user_id', $user->id)
                ->where('membership_status', 'Active')
                ->whereRaw("LOWER(role) <> 'member'")
                ->exists();

            if (!$hasAnotherOfficerPosition && $user->role === 'admin') {
                $user->role = 'student';
                $user->active_view = null;
                $user->save();
            }
        });

        $this->permissionService->invalidateCache($user->id, $organizationId);

        $emailWarning = null;
        try {
            $user->notify(new PositionRemovedNotification(
                organization: \App\Models\Organization::findOrFail($organizationId),
                position: $position->name,
                assignedBy: auth()->user()->full_name,
            ));
        } catch (\Throwable $exception) {
            $emailWarning = ' The in-app notification was saved, but the email could not be sent.';
            Log::error('Position removal email notification failed', [
                'user_id' => $user->id,
                'organization_id' => $organizationId,
                'position' => $position->name,
                'error' => $exception->getMessage(),
            ]);
        }

        return redirect()
            ->route('admin.settings.index', ['tab' => 'positions'])
            ->with('success', "{$user->full_name} was removed from {$position->name}.{$emailWarning}")
            ->with('settings_tab', 'positions');
    }

    /**
     * Delete a custom organization position and remove its organization-scoped
     * permissions. Any assignees are safely returned to Member first.
     */
    public function destroyPosition(OrganizationPosition $position): RedirectResponse
    {
        $organizationId = $this->authorizedOrganizationId();
        $position = OrganizationPosition::where('organization_id', $organizationId)
            ->findOrFail($position->id);
        $positionName = $position->name;

        $affectedUsers = DB::transaction(function () use ($organizationId, $position, $positionName) {
            $memberships = OrganizationUser::query()
                ->where('organization_id', $organizationId)
                ->where('role', $positionName)
                ->lockForUpdate()
                ->get();

            $users = User::whereIn('id', $memberships->pluck('user_id'))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($memberships as $membership) {
                $membership->role = 'Member';
                $membership->save();

                $user = $users->get($membership->user_id);
                if (!$user || $user->role !== 'admin') {
                    continue;
                }

                $hasAnotherOfficerPosition = DB::table('organization_user')
                    ->where('user_id', $user->id)
                    ->where('membership_status', 'Active')
                    ->whereRaw("LOWER(role) <> 'member'")
                    ->exists();

                if (!$hasAnotherOfficerPosition) {
                    $user->role = 'student';
                    $user->active_view = null;
                    $user->save();
                }
            }

            OrganizationRolePermission::where('organization_id', $organizationId)
                ->where('role', $positionName)
                ->delete();

            $position->delete();

            return $users->values();
        });

        $this->permissionService->invalidateGrantCache($organizationId, $positionName);

        $emailWarning = false;
        foreach ($affectedUsers as $user) {
            $this->permissionService->invalidateCache($user->id, $organizationId);

            try {
                $user->notify(new PositionRemovedNotification(
                    organization: \App\Models\Organization::findOrFail($organizationId),
                    position: $positionName,
                    assignedBy: auth()->user()->full_name,
                ));
            } catch (\Throwable $exception) {
                $emailWarning = true;
                Log::error('Position deletion email notification failed', [
                    'user_id' => $user->id,
                    'organization_id' => $organizationId,
                    'position' => $positionName,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        $memberCount = $affectedUsers->count();
        $message = "Position '{$positionName}' was deleted.";
        if ($memberCount > 0) {
            $message .= ' ' . $memberCount . ' ' . Str::plural('member', $memberCount) . ' returned to Member.';
        }
        if ($emailWarning) {
            $message .= ' Some email notifications could not be sent.';
        }

        return redirect()
            ->route('admin.settings.index', ['tab' => 'positions'])
            ->with('success', $message)
            ->with('settings_tab', 'positions');
    }

    /**
     * Deactivate or reactivate one of the org's member accounts.
     *
     * Adviser-only. Deactivating flips `users.is_active` off and the member's
     * `organization_user.membership_status` to 'Inactive' while keeping their
     * role, so reactivation restores the exact previous access. Reactivating
     * reverses both flags.
     */
    public function updateAccountStatus(Request $request): JsonResponse
    {
        $currentOrgId = $this->authorizedAdviserOrganizationId();

        $validated = $request->validate([
            'user_id'   => ['required', 'integer', 'exists:users,id'],
            'is_active' => ['required', 'boolean'],
        ]);

        $targetId = (int) $validated['user_id'];
        $requestedActive = (bool) $validated['is_active'];

        if ($targetId === auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot change your own account status.',
            ], 422);
        }

        $membership = OrganizationUser::where('organization_id', $currentOrgId)
            ->where('user_id', $targetId)
            ->first();

        if (! $membership) {
            return response()->json([
                'success' => false,
                'message' => 'That account is not a member of this organization.',
            ], 404);
        }

        $orgRole = OrganizationUser::formatDisplayLabel((string) $membership->role);

        if ($this->isProtectedMembershipRole($orgRole)) {
            return response()->json([
                'success' => false,
                'message' => 'Adviser, Dean, and COMELEC accounts cannot be deactivated here.',
            ], 422);
        }

        if ($membership->membership_status === 'Pending') {
            return response()->json([
                'success' => false,
                'message' => 'Pending members must complete the organization approval flow before their account can be activated.',
            ], 422);
        }

        $target = User::query()->whereKey($targetId)->lockForUpdate()->first();

        if (! $target) {
            return response()->json([
                'success' => false,
                'message' => 'Account not found.',
            ], 404);
        }

        if ((bool) $target->is_active === $requestedActive) {
            return response()->json([
                'success' => true,
                'message' => $requestedActive
                    ? "{$target->full_name} is already active."
                    : "{$target->full_name} is already inactive.",
            ]);
        }

        try {
            DB::transaction(function () use ($target, $membership, $requestedActive, $currentOrgId) {
                $target->is_active = $requestedActive;
                $target->save();

                $membership->membership_status = $requestedActive ? 'Active' : 'Inactive';
                $membership->save();

                // membership_status change already invalidates via the observer;
                // this covers the account-level flag as well.
                $this->permissionService->invalidateCache($target->id, $currentOrgId);
            });
        } catch (\Throwable $exception) {
            Log::error('Account status update failed', [
                'user_id'         => $targetId,
                'organization_id' => $currentOrgId,
                'error'           => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update the account status. Please try again.',
            ], 500);
        }

        $emailWarning = null;
        try {
            $target->notify(new \App\Notifications\AccountStatusChangedNotification(
                organization: \App\Models\Organization::findOrFail($currentOrgId),
                deactivated: ! $requestedActive,
                changedBy: auth()->user()->full_name,
            ));
        } catch (\Throwable $exception) {
            $emailWarning = ' The in-app notification was saved, but the email could not be sent.';
            Log::error('Account status email notification failed', [
                'user_id'         => $target->id,
                'organization_id' => $currentOrgId,
                'error'           => $exception->getMessage(),
            ]);
        }

        Log::info('Account status changed by adviser', [
            'org_id'          => $currentOrgId,
            'target_user_id'  => $target->id,
            'is_active'       => $requestedActive,
            'changed_by'      => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => $requestedActive
                ? "{$target->full_name} was reactivated.{$emailWarning}"
                : "{$target->full_name} was deactivated.{$emailWarning}",
        ]);
    }

    /**
     * Approve officer transition from election winners. Adviser-only.
     * Supports immediate effect or scheduled effective date.
     */
    public function approveOfficerTransition(Request $request): JsonResponse|RedirectResponse
    {
        $currentOrgId = $this->authorizedAdviserOrganizationId();

        $validated = $request->validate([
            'transition_id' => 'required|integer|exists:election_officer_transitions,id',
            'scheduled_at' => 'nullable|date|after:now',
        ]);

        $transition = \App\Models\ElectionOfficerTransition::where('id', $validated['transition_id'])
            ->where('organization_id', $currentOrgId)
            ->firstOrFail();

        if (!in_array($transition->status, ['pending', 'scheduled'], true)) {
            $message = 'This transition can no longer be approved (status: '.$transition->status.').';
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $message], 422);
            }
            return back()->with('error', $message);
        }

        // Ensure winners snapshot has linked users
        $snapshot = $transition->winners_snapshot ?? [];
        $missingLinks = collect($snapshot)->filter(fn ($d) => empty($d['user_id']))->count();
        if ($missingLinks > 0) {
            $message = 'Cannot approve: '.$missingLinks.' winner(s) have no linked student account. Please link candidates to student accounts first.';
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $message], 422);
            }
            return back()->with('error', $message);
        }

        $scheduledAt = $validated['scheduled_at'] ?? null;
        if ($scheduledAt) {
            $transition->scheduled_at = $scheduledAt;
            $transition->approved_at = now();
            $transition->approved_by = auth()->id();
            $transition->status = 'scheduled';
            $transition->save();

            Log::info('Officer transition scheduled by adviser.', [
                'transition_id' => $transition->id,
                'organization_id' => $currentOrgId,
                'scheduled_at' => $scheduledAt,
                'approved_by' => auth()->id(),
            ]);

            $message = 'Officer transition scheduled for '. \Carbon\Carbon::parse($scheduledAt)->format('M j, Y g:i A') .'. It will execute automatically.';
            if ($request->expectsJson()) {
                return response()->json(['success' => true, 'message' => $message]);
            }
            return back()->with('success', $message);
        }

        // Immediate execution
        try {
            $transition->approved_at = now();
            $transition->approved_by = auth()->id();
            $transition->save();
            $this->transitionService->execute($transition);
        } catch (\Throwable $exception) {
            Log::error('Officer transition execution failed.', [
                'transition_id' => $transition->id,
                'organization_id' => $currentOrgId,
                'reason' => $exception->getMessage(),
            ]);
            $message = 'Failed to execute transition: '.$exception->getMessage();
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $message], 500);
            }
            return back()->with('error', $message);
        }

        $message = 'New officers have been approved and now have admin access. Winners have been notified.';
        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => $message]);
        }
        return back()->with('success', $message);
    }

    public function rescheduleOfficerTransition(Request $request): JsonResponse
    {
        $currentOrgId = $this->authorizedAdviserOrganizationId();

        $validated = $request->validate([
            'transition_id' => 'required|integer|exists:election_officer_transitions,id',
            'scheduled_at' => 'required|date|after:now',
        ]);

        $transition = \App\Models\ElectionOfficerTransition::where('id', $validated['transition_id'])
            ->where('organization_id', $currentOrgId)
            ->where('status', 'scheduled')
            ->first();

        if (!$transition) {
            return response()->json(['success' => false, 'message' => 'Only scheduled transitions can be rescheduled.'], 422);
        }

        $transition->scheduled_at = $validated['scheduled_at'];
        $transition->save();

        Log::info('Officer transition rescheduled.', [
            'transition_id' => $transition->id,
            'new_scheduled_at' => $validated['scheduled_at'],
            'by' => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Transition rescheduled to '.\Carbon\Carbon::parse($validated['scheduled_at'])->format('M j, Y g:i A').'.',
        ]);
    }

    public function cancelOfficerTransition(Request $request): JsonResponse
    {
        $currentOrgId = $this->authorizedAdviserOrganizationId();

        $validated = $request->validate([
            'transition_id' => 'required|integer|exists:election_officer_transitions,id',
        ]);

        $transition = \App\Models\ElectionOfficerTransition::where('id', $validated['transition_id'])
            ->where('organization_id', $currentOrgId)
            ->where('status', 'scheduled')
            ->first();

        if (!$transition) {
            return response()->json(['success' => false, 'message' => 'Only scheduled transitions can be cancelled.'], 422);
        }

        $transition->status = 'pending';
        $transition->scheduled_at = null;
        $transition->approved_at = null;
        $transition->approved_by = null;
        $transition->save();

        Log::info('Officer transition cancelled (reverted to pending).', [
            'transition_id' => $transition->id,
            'by' => auth()->id(),
        ]);

        return response()->json(['success' => true, 'message' => 'Scheduled transition cancelled. It is now pending approval again.']);
    }

    public function lookupMember(Request $request, string $studentId): JsonResponse
    {
        $currentOrgId = (int) session('current_organization_id');
        if (!$currentOrgId) {
            return response()->json(['success' => false, 'message' => 'No active organization.'], 400);
        }

        $raw = trim($studentId);
        $digits = preg_replace('/\D/', '', $raw);
        $user = User::where('student_id', $raw)->first();
        if (!$user && $digits) {
            $user = User::whereRaw("REPLACE(REPLACE(student_id, '-', ''), ' ', '') = ?", [$digits])->first();
            if (!$user) {
                $user = User::where('student_id', $digits)->first();
            }
        }

        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Student not found.'], 404);
        }

        $isMember = DB::table('organization_user')
            ->where('organization_id', $currentOrgId)
            ->where('user_id', $user->id)
            ->where('membership_status', 'Active')
            ->exists();

        if (!$isMember) {
            return response()->json(['success' => false, 'message' => 'Student is not an active member of this organization.'], 422);
        }

        return response()->json([
            'success' => true,
            'student' => [
                'id' => $user->id,
                'student_id' => $user->student_id,
                'full_name' => $user->full_name,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'photo_url' => $user->photo ? storage_url($user->photo) : null,
                'year_level' => $user->year_level,
            ],
        ]);
    }

    private function authorizedOrganizationId(): int
    {
        $organizationId = (int) session('current_organization_id');
        $userPosition = $organizationId ? auth()->user()->getPositionInOrganization($organizationId) : null;

        abort_unless(in_array($userPosition, self::ALLOWED_POSITIONS, true), 403);

        return $organizationId;
    }

    /**
     * Whether the given organization position belongs to an Adviser.
     */
    private function isAdviserPosition(?string $position): bool
    {
        return in_array(strtolower((string) $position), [
            'adviser',
            'organization adviser',
            'senior adviser',
            'junior adviser',
        ], true);
    }

    /**
     * Organization roles that can never be deactivated from Manage Accounts.
     */
    private function isProtectedMembershipRole(string $role): bool
    {
        $lower = strtolower($role);

        return str_contains($lower, 'adviser')
            || str_contains($lower, 'dean')
            || strtoupper($role) === 'COMELEC';
    }

    /**
     * Organization id gate for adviser-only account management.
     */
    private function authorizedAdviserOrganizationId(): int
    {
        $organizationId = (int) session('current_organization_id');
        $userPosition = $organizationId ? auth()->user()->getPositionInOrganization($organizationId) : null;

        abort_unless($organizationId > 0 && $this->isAdviserPosition($userPosition), 403);

        return $organizationId;
    }

}
