<?php

namespace App\Http\Controllers\Osas;

use App\Http\Controllers\Controller;
use App\Models\AccreditationPeriodAudit;
use App\Models\AuthenticationEvent;
use Carbon\CarbonImmutable;
use App\Services\OsasProposalQueueService;
use App\Models\Event;
use App\Models\Organization;
use App\Models\ApplyOrg;
use App\ViewModels\CurrentDocumentAnalysisPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Barryvdh\DomPDF\Facade\Pdf;

class OsasController extends Controller
{
    /**
     * Show OSAS Dashboard
     */
    public function dashboard()
    {
        // Aggregate stats are cached for 60s (Redis). They only change when
        // organizations/requests are created or approved, so a minute of
        // staleness is fine and saves ~15 remote DB round-trips per load.
        $stats = Cache::remember('osas.dashboard.stats', 60, function () {
            // Basic stats (batched queries to reduce DB round-trips)
            $orgCounts = Organization::selectRaw("
                COUNT(*) as total,
                COUNT(*) FILTER (WHERE status = 'Approved') as active,
                COUNT(*) FILTER (WHERE status = 'Inactive') as inactive,
                COUNT(*) FILTER (WHERE type = 'Mother Org') as mother_orgs,
                COUNT(*) FILTER (WHERE type = 'Sub Org') as sub_orgs,
                COUNT(*) FILTER (WHERE type = 'Non-Academic') as non_academic_orgs
            ")->first();

            $applyCounts = ApplyOrg::selectRaw("
                COUNT(*) as total,
                COUNT(*) FILTER (WHERE status IN ('Pending', 'For-Checking')) as pending,
                COUNT(*) FILTER (WHERE status = 'Approved') as approved,
                COUNT(*) FILTER (WHERE status = 'Rejected') as rejected
            ")->first();

            $totalMembers = \DB::table('organization_user')->count();
            $registeredStudents = \App\Models\User::where('role', 'student')->count();
            $totalStudentPopulation = \App\Models\EnrolledStudent::count();

            return [
                'total_organizations' => $orgCounts->total,
                'active_organizations' => $orgCounts->active,
                'inactive_organizations' => $orgCounts->inactive,
                'mother_orgs' => $orgCounts->mother_orgs,
                'sub_orgs' => $orgCounts->sub_orgs,
                'non_academic_orgs' => $orgCounts->non_academic_orgs,
                'total_members' => $totalMembers,
                'pending_requests' => $applyCounts->pending,
                'approved_requests' => $applyCounts->approved,
                'rejected_requests' => $applyCounts->rejected,
                'total_requests' => $applyCounts->total,
                'registered_students' => $registeredStudents,
                'total_student_population' => $totalStudentPopulation,
            ];
        });

        // Recent activities (kept fresh - shows the latest apply requests).
        // Internal report-linker rows (application_type NULL) are excluded,
        // same as the accreditation workbench queue.
        $recentRequests = ApplyOrg::with(['reviewer', 'organization.settings'])
            ->whereNotNull('application_type')
            ->latest()
            ->take(5)
            ->get();

        // Approved organizations whose accreditation is expired or expiring
        // within 30 days, cached.
        $expiringOrganizations = Cache::remember('osas.dashboard.expiring', 60, function () {
            return Organization::where('status', 'Approved')
                ->whereNotNull('accreditation_expires_at')
                ->get()
                ->filter(fn (Organization $org) => in_array($org->accreditationStatus(), ['expired', 'expiring'], true))
                ->sortBy('accreditation_expires_at')
                ->take(10)
                ->values();
        });

        // Events this week
        $startOfWeek = now()->startOfWeek();
        $endOfWeek = now()->endOfWeek();
        
        $eventsThisWeek = \App\Models\Event::with(['organization', 'organization.settings'])
            ->where(function ($query) {
                $query->where('osas_status', 'approved')
                    ->orWhere(function ($query) {
                        $query->whereNull('osas_status')
                            ->where('is_published', \DB::raw('true'));
                    });
            })
            ->where(function($query) use ($startOfWeek, $endOfWeek) {
                $query->whereBetween('start_date', [$startOfWeek, $endOfWeek])
                      ->orWhereBetween('end_date', [$startOfWeek, $endOfWeek])
                      ->orWhere(function($q) use ($startOfWeek, $endOfWeek) {
                          $q->where('start_date', '<', $startOfWeek)
                            ->where('end_date', '>', $endOfWeek);
                      });
            })
            ->orderBy('start_date', 'asc')
            ->paginate(5, ['*'], 'events_page');

        return view('osas.dashboard', compact(
            'stats',
            'recentRequests',
            'eventsThisWeek',
            'expiringOrganizations'
        ));
    }

    /**
     * Show all notifications for the authenticated OSAS administrator.
     */
    public function notifications(Request $request)
    {
        $user = $request->user();
        $notifications = $user->notifications()->latest()->paginate(20);

        return view('osas.notifications.index', [
            'notifications' => $notifications,
            'totalCount' => $user->notifications()->count(),
            'unreadCount' => $user->unreadNotifications()->count(),
        ]);
    }

    /**
     * List all apply org requests
     */
    public function applyOrgs(Request $request, CurrentDocumentAnalysisPresenter $presenter)
    {
        $query = ApplyOrg::with(['reviewer', 'processedDocuments', 'organization.settings']);

        // Internal linker rows for OSAS required-document submissions carry a
        // NULL application_type and must never surface in accreditation queues.
        $query->whereNotNull('application_type');

        // Search functionality
        if ($request->filled('q')) {
            $search = $request->q;
            $query->where(function($q) use ($search) {
                $q->where('organization_name', 'like', "%{$search}%")
                  ->orWhere('organization_acronym', 'like', "%{$search}%")
                  ->orWhere('requester_first_name', 'like', "%{$search}%")
                  ->orWhere('requester_last_name', 'like', "%{$search}%")
                  ->orWhere('requester_email', 'like', "%{$search}%");
            });
        }

        // Filter by request type (Recognition or Renewal)
        if ($request->filled('type')) {
            $query->where('request_type', $request->type);
        }

        // Filter by status
        if ($request->filled('status')) {
            if ($request->status === 'Pending') {
                $query->whereIn('status', ['Pending', 'For-Checking']);
            } else {
                $query->where('status', $request->status);
            }
        }

        $requests = $query->orderBy('updated_at', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(20)
            ->withQueryString()
            ->appends($request->except(['fragment', 'table_fragment', 'page']));
        $requests->getCollection()->each(function (ApplyOrg $application) use ($presenter): void {
            $application->setAttribute('analysis_state', $presenter->aggregate($application));
        });

        // Get counts for badges (filtered by search if needed, or global)
        $pendingRecognition = ApplyOrg::where('status', 'Pending')
            ->where('request_type', 'apply')
            ->count();
        
        $pendingRenewal = ApplyOrg::where('status', 'For-Checking')
            ->where('request_type', 'request')
            ->count();

        $totalRequests = ApplyOrg::whereNotNull('application_type')->count();

        if ($request->boolean('table_fragment')) {
            return view('osas.apply-org.partials.table', compact('requests'));
        }

        return view('osas.apply-org.index', compact('requests', 'pendingRecognition', 'pendingRenewal', 'totalRequests'));
    }

    /**
     * Generate a pending renewal record for every approved organization whose
     * accreditation has already expired, so they surface in the Renewal filter.
     * Records already holding a pending renewal are skipped (safe to re-click).
     */
    public function generateExpiredRenewals(Request $request)
    {
        try {
            $result = app(\App\Services\ExpiredRenewalGenerator::class)->generate();

            if ($result['created'] === 0 && $result['skipped'] === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'No expired organizations found. Set School Year End to a past date via Settings → Accreditation Expiration → Apply first, then Start Renewal.',
                    'created' => $result['created'],
                    'skipped' => $result['skipped'],
                ], 422);
            }

            if ($result['created'] === 0 && $result['skipped'] > 0) {
                return response()->json([
                    'success' => true,
                    'message' => "No new renewals needed. {$result['skipped']} organization(s) already had a pending renewal.",
                    'created' => $result['created'],
                    'skipped' => $result['skipped'],
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => "Generated {$result['created']} renewal record(s) for expired organizations. {$result['skipped']} organization(s) already had a pending renewal.",
                'created' => $result['created'],
                'skipped' => $result['skipped'],
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('generateExpiredRenewals failed: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to start renewal: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Show apply org request details
     */
    public function showApplyOrg(
        ApplyOrg $applyOrg,
        CurrentDocumentAnalysisPresenter $presenter,
    ) {
        $applyOrg->load(['reviewer', 'processedDocuments']);

        return view('osas.apply-org.show', [
            'request' => $applyOrg,
            'documentAnalyses' => $presenter->forApplication($applyOrg),
            'analysisState' => $presenter->aggregate($applyOrg),
        ]);
    }

    /**
     * List all organizations
     */
    public function organizations(Request $request)
    {
        $query = Organization::with(['settings'])->withCount('users');

        // Filter by type
        if ($request->has('type') && $request->type != '') {
            $query->where('type', $request->type);
        }

        // Filter by status
        if ($request->has('status') && $request->status != '') {
            $query->where('status', $request->status);
        }

        // Search by name or acronym
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($searchQuery) use ($search) {
                $searchQuery->where('name', 'ilike', '%' . $search . '%')
                    ->orWhere('org_acronym', 'ilike', '%' . $search . '%');
            });
        }

        $organizations = $query->orderBy('created_at', 'desc')->paginate(15)->withQueryString();

        $stats = [
            'total' => Organization::count(),
            'mother_org' => Organization::where('type', 'Mother Org')->count(),
            'sub_org' => Organization::where('type', 'Sub Org')->count(),
            'non_academic' => Organization::where('type', 'Non-Academic')->count(),
            'active' => Organization::where('status', 'Approved')->count(),
        ];

        return view('osas.organizations.index', compact('organizations', 'stats'));
    }

    /**
     * Show organization details
     */
    public function showOrganization(Organization $organization)
    {
        $organization->load(['settings', 'users' => function($query) {
            $query->withPivot('role', 'membership_status');
        }]);

        return view('osas.organizations.show', compact('organization'));
    }

    /**
     * Toggle organization status (Archive/Unarchive)
     */
    public function toggleOrganizationStatus(Organization $organization)
    {
        if ($organization->status === 'Archived' || !is_null($organization->archived_at)) {
            // Unarchive
            $organization->update([
                'status' => 'Approved',
                'archived_at' => null
            ]);
            $message = "Organization unarchived successfully.";
            $newStatus = 'Approved';
        } else {
            // Archive
            $organization->update([
                'status' => 'Archived',
                'archived_at' => now()
            ]);
            $message = "Organization archived successfully.";
            $newStatus = 'Archived';
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'status' => $newStatus
        ]);
    }

    /**
     * Export organizations report as PDF
     */
    public function exportOrganizationsPDF()
    {
        $organizations = Organization::withCount('users')->get();
        
        $stats = [
            'total' => Organization::count(),
            'mother_org' => Organization::where('type', 'Mother Org')->count(),
            'sub_org' => Organization::where('type', 'Sub Org')->count(),
            'non_academic' => Organization::where('type', 'Non-Academic')->count(),
            'active' => Organization::where('status', 'Approved')->count(),
            'total_members' => \DB::table('organization_user')->count(),
        ];

        $pdf = Pdf::loadView('osas.reports.organizations-pdf', compact('organizations', 'stats'));
        
        return $pdf->download('organizations-report-' . now()->format('Y-m-d') . '.pdf');
    }

    /**
     * Export organization details as PDF
     */
    public function exportOrganizationPDF(Organization $organization)
    {
        $organization->load(['users' => function($query) {
            $query->withPivot('role', 'membership_status');
        }]);

        $pdf = Pdf::loadView('osas.reports.organization-detail-pdf', compact('organization'));
        
        return $pdf->download($organization->name . '-report-' . now()->format('Y-m-d') . '.pdf');
    }

    /**
     * Show analytics page
     */
    public function analytics(Request $request)
    {
        // School year filter. Convention: Aug 1 – Jul 31, matching the
        // accreditation period labels used elsewhere in the system.
        $schoolYears = $this->schoolYearOptions();

        $selectedSy = null;
        if ($request->filled('sy')) {
            foreach ($schoolYears as $option) {
                if ($option['key'] === $request->query('sy')) {
                    $selectedSy = $option['key'];
                    break;
                }
            }
        }

        if ($selectedSy) {
            [$startYear] = explode('-', $selectedSy);
            $windowStart = \Illuminate\Support\Carbon::create((int) $startYear, 8, 1)->startOfDay();
            $windowEnd = \Illuminate\Support\Carbon::create((int) $startYear + 1, 7, 31)->endOfDay();
        } else {
            $windowStart = null;
            $windowEnd = null;
        }

        $periodLabel = $selectedSy
            ? 'SY ' . str_replace('-', '-', $selectedSy)
            : 'All Time';

        // ---- Events & Engagement ----
        // Monthly event pipeline, bucketed by event start date. Mirrors the
        // dashboard's event visibility rules (approved by OSAS, or dean-approved
        // when no OSAS review is required).
        $eventMonthly = \DB::table('events')
            ->selectRaw("
                CONCAT(EXTRACT(YEAR FROM start_date), '-', LPAD(EXTRACT(MONTH FROM start_date)::text, 2, '0')) as month_key,
                COUNT(*) FILTER (WHERE osas_status = 'approved' OR (osas_status IS NULL AND approval_status = 'approved')) as approved,
                COUNT(*) FILTER (WHERE osas_status = 'pending' OR approval_status = 'pending') as pending,
                COUNT(*) FILTER (WHERE osas_status = 'rejected' OR approval_status = 'rejected') as rejected
            ")
            ->when($windowStart, fn ($q) => $q->where('start_date', '>=', $windowStart))
            ->when($windowEnd, fn ($q) => $q->where('start_date', '<=', $windowEnd))
            ->groupByRaw('EXTRACT(YEAR FROM start_date), EXTRACT(MONTH FROM start_date)')
            ->get()
            ->keyBy('month_key');

        $eventMonths = $this->analyticsMonthAxis($windowStart, $windowEnd, $eventMonthly);
        $eventTrend = [];
        foreach ($eventMonths as $m) {
            $row = $eventMonthly[$m['key']] ?? null;
            $eventTrend[] = [
                'month' => $m['month'],
                'approved' => (int) ($row->approved ?? 0),
                'pending' => (int) ($row->pending ?? 0),
                'rejected' => (int) ($row->rejected ?? 0),
            ];
        }

        $eventTotals = [
            'total' => array_sum(array_map(fn ($m) => $m['approved'] + $m['pending'] + $m['rejected'], $eventTrend)),
            'approved' => array_sum(array_column($eventTrend, 'approved')),
            'pending' => array_sum(array_column($eventTrend, 'pending')),
            'rejected' => array_sum(array_column($eventTrend, 'rejected')),
        ];

        // Top organizations by approved events hosted (within the period)
        $topEventOrgs = \DB::table('events')
            ->join('organizations', 'organizations.id', '=', 'events.organization_id')
            ->select('organizations.id', 'organizations.name', 'organizations.org_acronym', \DB::raw('COUNT(*) as total'))
            ->where(function ($q) {
                $q->where('events.osas_status', 'approved')
                  ->orWhere(function ($q2) {
                      $q2->whereNull('events.osas_status')
                         ->where('events.approval_status', 'approved');
                  });
            })
            ->when($windowStart, fn ($q) => $q->where('events.start_date', '>=', $windowStart))
            ->when($windowEnd, fn ($q) => $q->where('events.start_date', '<=', $windowEnd))
            ->groupBy('organizations.id', 'organizations.name', 'organizations.org_acronym')
            ->orderByDesc('total')
            ->take(8)
            ->get()
            ->map(fn ($row) => [
                'label' => $row->org_acronym ?: $row->name,
                'total' => (int) $row->total,
            ])
            ->values();

        // Average OSAS approval turnaround (submitted -> approved), in days
        $avgApprovalDays = \DB::table('events')
            ->whereNotNull('osas_approved_at')
            ->when($windowStart, fn ($q) => $q->where('osas_approved_at', '>=', $windowStart))
            ->when($windowEnd, fn ($q) => $q->where('osas_approved_at', '<=', $windowEnd))
            ->avg(\DB::raw("EXTRACT(EPOCH FROM (osas_approved_at - created_at)) / 86400"));
        $avgApprovalDays = $avgApprovalDays !== null ? round((float) $avgApprovalDays, 1) : 0;

        // Approval turnaround trend (month of approval, within the period)
        $turnaroundMonthly = \DB::table('events')
            ->selectRaw("
                CONCAT(EXTRACT(YEAR FROM osas_approved_at), '-', LPAD(EXTRACT(MONTH FROM osas_approved_at)::text, 2, '0')) as month_key,
                AVG(EXTRACT(EPOCH FROM (osas_approved_at - created_at)) / 86400) as avg_days
            ")
            ->whereNotNull('osas_approved_at')
            ->when($windowStart, fn ($q) => $q->where('osas_approved_at', '>=', $windowStart))
            ->when($windowEnd, fn ($q) => $q->where('osas_approved_at', '<=', $windowEnd))
            ->groupByRaw('EXTRACT(YEAR FROM osas_approved_at), EXTRACT(MONTH FROM osas_approved_at)')
            ->get()
            ->keyBy('month_key');

        $turnaroundMonths = $this->analyticsMonthAxis($windowStart, $windowEnd, $turnaroundMonthly);
        $turnaroundTrend = [];
        foreach ($turnaroundMonths as $m) {
            $row = $turnaroundMonthly[$m['key']] ?? null;
            $turnaroundTrend[] = [
                'month' => $m['month'],
                'days' => $row ? round((float) $row->avg_days, 1) : null,
            ];
        }

        // Venue utilization (most-used venues, within the period)
        $venueUsage = \DB::table('events')
            ->whereNotNull('venue')
            ->where('venue', '!=', '')
            ->when($windowStart, fn ($q) => $q->where('start_date', '>=', $windowStart))
            ->when($windowEnd, fn ($q) => $q->where('start_date', '<=', $windowEnd))
            ->selectRaw('venue, COUNT(*) as total')
            ->groupBy('venue')
            ->orderByDesc('total')
            ->take(6)
            ->get()
            ->map(fn ($row) => [
                'label' => $row->venue,
                'total' => (int) $row->total,
            ])
            ->values();

        return view('osas.analytics', compact(
            'schoolYears',
            'selectedSy',
            'periodLabel',
            'eventTrend',
            'eventTotals',
            'topEventOrgs',
            'avgApprovalDays',
            'turnaroundTrend',
            'venueUsage'
        ));
    }

    /**
     * School years that contain event data (newest first), following the
     * Aug 1 – Jul 31 convention used by the accreditation period labels.
     *
     * @return array<int, array{key: string, label: string}>
     */
    private function schoolYearOptions(): array
    {
        $range = \DB::table('events')
            ->selectRaw('MIN(EXTRACT(YEAR FROM start_date)) as min_year, MAX(EXTRACT(YEAR FROM start_date)) as max_year')
            ->first();

        // An event in Jan 2026 belongs to SY 2025-2026, so start one year earlier.
        $minYear = (int) ($range->min_year ?? now()->year) - 1;
        $maxYear = max((int) ($range->max_year ?? now()->year), now()->year);

        $options = [];
        for ($year = $maxYear; $year >= $minYear; $year--) {
            $options[] = ['key' => "{$year}-" . ($year + 1), 'label' => "SY {$year}-" . ($year + 1)];
        }

        return $options;
    }

    /**
     * Month axis for the analytics time series. For a selected school year the
     * axis spans its full Aug-Jul window (clamped to the current month while the
     * year is ongoing); for "All Time" it spans the months that actually have data.
     *
     * @param  \Illuminate\Support\Carbon|null  $windowStart
     * @param  \Illuminate\Support\Carbon|null  $windowEnd
     * @param  \Illuminate\Support\Collection  $rows  monthly rows keyed by 'Y-m'
     * @return array<int, array{month: string, key: string}>
     */
    private function analyticsMonthAxis($windowStart, $windowEnd, $rows): array
    {
        if ($windowStart && $windowEnd) {
            $start = $windowStart->copy()->startOfMonth();
            $end = $windowEnd->copy()->startOfMonth()->min(now()->startOfMonth());
        } else {
            $keys = $rows->keys();
            if ($keys->isEmpty()) {
                $start = now()->startOfMonth();
                $end = now()->startOfMonth();
            } else {
                $start = \Illuminate\Support\Carbon::createFromFormat('Y-m', $keys->min())->startOfMonth();
                $end = \Illuminate\Support\Carbon::createFromFormat('Y-m', $keys->max())->startOfMonth();
            }
        }

        $months = [];
        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            $months[] = ['month' => $cursor->format('M Y'), 'key' => $cursor->format('Y-m')];
            $cursor->addMonth();
        }

        return $months;
    }

    /**
     * Show settings page
     */
    public function settings(?Request $request = null)
    {
        $request = $request ?? request();
        $user = auth()->user();

        $recentActions = $this->recentAuditActions();

        $authenticationFilters = [
            'security_event' => $request->query('security_event') ?? '',
            'security_from' => $request->query('security_from') ?? '',
            'security_to' => $request->query('security_to') ?? '',
        ];
        $authenticationValidator = Validator::make($authenticationFilters, [
            'security_event' => ['bail', 'nullable', 'string', Rule::in(array_keys(AuthenticationEvent::EVENT_LABELS))],
            'security_from' => ['bail', 'nullable', 'string', 'date_format:Y-m-d\TH:i'],
            'security_to' => ['bail', 'nullable', 'string', 'date_format:Y-m-d\TH:i'],
        ], [
            'security_event.in' => 'Choose a valid activity type.',
            'security_from.date_format' => 'Enter a valid From date and time.',
            'security_to.date_format' => 'Enter a valid To date and time.',
        ]);
        $authenticationValidator->after(function ($validator) use ($authenticationFilters): void {
            if ($validator->errors()->isEmpty()
                && $authenticationFilters['security_from'] !== ''
                && $authenticationFilters['security_to'] !== ''
                && strcmp($authenticationFilters['security_from'], $authenticationFilters['security_to']) > 0) {
                $validator->errors()->add('security_to', 'To date and time must be on or after From.');
            }
        });

        $authenticationQuery = AuthenticationEvent::query();
        if ($authenticationValidator->fails()) {
            $authenticationQuery->whereRaw('1 = 0');
        } else {
            if ($authenticationFilters['security_event'] !== '') {
                $authenticationQuery->where('event', $authenticationFilters['security_event']);
            }
            if ($authenticationFilters['security_from'] !== '') {
                $from = CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $authenticationFilters['security_from'], config('app.timezone'));
                $authenticationQuery->where('occurred_at', '>=', $from);
            }
            if ($authenticationFilters['security_to'] !== '') {
                $to = CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $authenticationFilters['security_to'], config('app.timezone'));
                $authenticationQuery->where('occurred_at', '<=', $to->endOfMinute());
            }
        }
        $authenticationFilterErrors = $authenticationValidator->errors();
        $authenticationFilters = array_map(static fn ($value): string => is_string($value) ? $value : '', $authenticationFilters);
        $authenticationEvents = $authenticationQuery->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        $currentPeriod = Organization::currentAccreditationPeriod();
        $approvedOrgCount = Organization::where('status', 'Approved')->count();

        // Account Management data (org-filtered, deactivate-only)
        $accountQuery = app(\App\Services\AccountManagement\AccountDirectoryQuery::class);
        $validity = app(\App\Services\AccountManagement\StudentAccountValidity::class);

        $accountFilter = \App\Services\AccountManagement\AccountDirectoryFilter::fromQuery($request->all());

        // Dean accounts live in the separate Dean Setup tab, so the normal
        // Manage Accounts table always excludes them. A direct Dean role
        // filter on this tab is redirected to Dean Setup instead.
        $requestedSettingsTab = $request->input('tab');
        $isDeanSetupTab = $requestedSettingsTab === 'dean-setup';
        if (! $isDeanSetupTab && $accountFilter->role === \App\Services\AccountManagement\SystemAccessRole::SuperAdmin) {
            return redirect()->route('osas.settings', array_merge(
                $request->except(['role', 'page', 'tab']),
                ['tab' => 'dean-setup']
            ));
        }
        if (! $isDeanSetupTab) {
            $accountFilter = $accountFilter->withExcludedRole(\App\Services\AccountManagement\SystemAccessRole::SuperAdmin);
        }

        $accounts = $accountQuery->paginate($accountFilter);

        $deleteTokens = [];

        $accountStatus = [];
        $now = now(config('app.timezone'));
        foreach ($accounts as $account) {
            $accountStatus[(int) $account->getKey()] = $validity->statusFor(
                (string) $account->role,
                (bool) $account->is_active,
                $account->expires_at,
                $now
            );
        }

        $graceCutoff = $now->copy()->subMonths(\App\Services\AccountManagement\StudentAccountValidity::GRACE_MONTHS);
        $studentQuery = \App\Models\User::query()->where('role', 'student')->whereNotNull('expires_at');
        $validitySummary = [
            'expired' => (clone $studentQuery)->where('expires_at', '<=', $graceCutoff)->count(),
            'expiring' => (clone $studentQuery)->where('expires_at', '<=', $now)->where('expires_at', '>', $graceCutoff)->count(),
        ];

        $departments = \App\Models\Department::query()->orderBy('department_name')->get(['id', 'department_name', 'department_acronym']);
        $organizations = \App\Models\Organization::orderBy('name')->get(['id', 'name', 'org_acronym']);
        // Dean Setup tab: Dean per college (one row per department).
        // Includes inactive Deans so existing-but-inactive ones (e.g. created
        // via Apply/Renew) surface with an Activate action. Active wins per
        // department; latest record wins among the rest. Always queried —
        // Settings inner tabs switch client-side without a new request, so
        // gating this on ?tab= would leave the table empty (all Vacant).
        $collegeDeans = \App\Models\User::query()
            ->where('role', 'super_admin')
            ->whereNotNull('department_id')
            ->orderByRaw('CAST(users.is_active AS INTEGER) ASC')
            ->orderBy('users.id', 'ASC')
            ->get()
            ->keyBy('department_id');

        return view('osas.settings', compact(
            'user', 'recentActions', 'currentPeriod', 'approvedOrgCount',
            'accounts', 'deleteTokens', 'accountStatus', 'validitySummary', 'departments', 'organizations', 'accountFilter',
            'collegeDeans', 'authenticationEvents', 'authenticationFilters', 'authenticationFilterErrors'
        ));
    }

    /**
     * Set the accreditation school year period (start + end) for all approved organizations.
     */
    public function setAccreditationPeriod(Request $request)
    {
        $validated = $request->validate([
            'accreditation_start_date' => 'required|date',
            'accreditation_end_date' => 'required|date|after_or_equal:accreditation_start_date',
        ]);

        $startedAt = \Illuminate\Support\Carbon::parse($validated['accreditation_start_date']);
        $expiresAt = \Illuminate\Support\Carbon::parse($validated['accreditation_end_date']);

        $affected = Organization::where('status', 'Approved')
            ->update([
                'accreditation_started_at' => $startedAt,
                'accreditation_expires_at' => $expiresAt,
            ]);

        $schoolYear = Organization::accreditationSchoolYear($startedAt, $expiresAt);
        if ($schoolYear) {
            ApplyOrg::whereIn('status', ['Pending', 'For-Checking'])
                ->update(['accreditation_year' => $schoolYear]);
        }

        try {
            AccreditationPeriodAudit::create([
                'actor_user_id' => auth()->id(),
                'actor_display' => auth()->user()->full_name ?? 'Unknown',
                'start_date' => $startedAt,
                'end_date' => $expiresAt,
                'affected_count' => $affected,
                'occurred_at' => now(),
            ]);
        } catch (\Throwable $auditError) {
            \Log::error('Failed to record accreditation period audit: '.$auditError->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => "Accreditation school year set to {$startedAt->format('M d, Y')} - {$expiresAt->format('M d, Y')} for {$affected} approved organization(s).",
            'start_date' => $startedAt->toDateString(),
            'end_date' => $expiresAt->toDateString(),
            'affected' => $affected,
        ]);
    }

    /**
     * Update profile
     */
    public function updateProfile(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . auth()->id(),
        ]);

        auth()->user()->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
        ]);
    }

    /**
     * Update profile photo
     */
    public function updatePhoto(Request $request)
    {
        $user = auth()->user();

        $request->validate([
            'photo' => 'required|image|max:5120',
        ]);

        if ($user->photo) {
            \Storage::disk(storage_disk())->delete($user->photo);
        }

        $path = $request->file('photo')->store('profiles', storage_disk());
        $user->update(['photo' => $path]);

        return response()->json([
            'success' => true,
            'message' => 'Profile photo updated successfully!',
            'photo_url' => storage_url($path),
        ]);
    }

    /**
     * Update password
     */
    public function updatePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => 'required',
            'new_password' => 'required|min:8|confirmed',
        ]);

        if (!\Hash::check($validated['current_password'], auth()->user()->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Current password is incorrect',
            ], 400);
        }

        auth()->user()->update([
            'password' => \Hash::make($validated['new_password']),
            'password_changed_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Password updated successfully',
        ]);
    }

    /**
     * Show audit logs
     */
    public function auditLogs()
    {
        $recentActions = $this->recentAuditActions();

        return view('osas.audit-logs', compact('recentActions'));
    }

    /**
     * Build the newest-first audit feed shown on the audit-logs page and the
     * settings "recent activity" panel. Combines accreditation request reviews
     * with accreditation school-year period changes.
     */
    private function recentAuditActions()
    {
        $requestActions = ApplyOrg::with('reviewer')
            ->whereNotNull('reviewed_by')
            ->whereNotNull('reviewed_at')
            ->orderBy('reviewed_at', 'desc')
            ->take(50)
            ->get()
            ->map(function ($request) {
                return [
                    'action' => $request->status === 'Approved' ? 'Approved Accreditation Request' : 'Rejected Accreditation Request',
                    'description' => "Accreditation request for {$request->organization_name}",
                    'user' => $request->reviewer->full_name ?? 'Unknown',
                    'timestamp' => $request->reviewed_at,
                    'type' => $request->status === 'Approved' ? 'success' : 'danger',
                ];
            });

        $periodActions = AccreditationPeriodAudit::latest('occurred_at')
            ->take(50)
            ->get()
            ->map(function (AccreditationPeriodAudit $audit) {
                return [
                    'action' => 'Set Accreditation Period',
                    'description' => "Set accreditation school year to {$audit->start_date->format('M d, Y')} - {$audit->end_date->format('M d, Y')} for {$audit->affected_count} approved organization(s)",
                    'user' => $audit->actor_display,
                    'timestamp' => $audit->occurred_at,
                    'type' => 'info',
                ];
            });

        return $requestActions
            ->concat($periodActions)
            ->sortByDesc(fn (array $action) => $action['timestamp'])
            ->take(50)
            ->values();
    }

    /**
     * Show announcements page
     */
    public function announcements()
    {
        // Kept as a compatibility entry point for any older callers.
        return app(\App\Http\Controllers\Admin\AnnouncementController::class)->adminIndex();
    }
    /**
     * Show Master Calendar for all organizations
     */
    public function masterCalendar()
    {
        $allEvents = \App\Models\Event::allOrganizations()->with(['organization.settings', 'proposal'])
            ->where('start_date', '>=', now()->subYear())
            ->where('start_date', '<=', now()->addYear())
            ->where(function ($q) {
                $q->where('osas_status', 'approved')
                  ->orWhere(function ($q2) {
                      $q2->whereNull('osas_status')->where('is_published', \DB::raw('true'));
                  });
            })
            ->get();
        
        $events = $allEvents->map(function ($event) use ($allEvents) {
            $orgName = $event->organization->org_acronym ?? $event->organization->name ?? 'Unknown Org';
            $title = "[{$orgName}] {$event->title}";
            
            // Check for conflicts (Same venue, overlapping time)
            $hasConflict = $allEvents->where('id', '!=', $event->id)
                ->filter(function ($other) use ($event) {
                    // Check venue (case-insensitive)
                    $sameVenue = strtolower(trim($event->venue)) === strtolower(trim($other->venue));
                    
                    // Check time overlap
                    $overlaps = ($event->start_date < $other->end_date && $event->end_date > $other->start_date);
                    
                    return $sameVenue && $overlaps;
                })->count() > 0;

            // Set color based on status or conflict
            $color = $event->organization->settings->primary_color ?? '#26488C';
            
            if ($hasConflict) {
                $color = '#000000'; // Black for conflicts to stand out
                $title = "⚠️ [CONFLICT] " . $title;
            } elseif ($event->approval_status === 'pending') {
                $color = '#F59E0B'; // Amber for pending
            } elseif ($event->approval_status === 'rejected') {
                $color = '#EF4444'; // Red for rejected
            }

            return [
                'id' => $event->id,
                'title' => $title,
                'start' => $event->start_date->format('Y-m-d H:i:s'),
                'end' => $event->end_date ? $event->end_date->format('Y-m-d H:i:s') : $event->start_date->format('Y-m-d H:i:s'),
                'color' => $color,
                'extendedProps' => [
                    'organization' => $orgName,
                    'organization_full_name' => $event->organization->name ?? $orgName,
                    'status' => $event->approval_status,
                    'osas_status' => $event->osas_status,
                    'venue' => $event->venue,
                    'category_label' => $event->category_label,
                    'hasConflict' => $hasConflict,
                    'organization_id' => $event->organization_id,
                    'description' => $event->description,
                    'images' => $event->images ?? [],
                    'planned_budget' => $event->planned_budget,
                    'start_date_formatted' => $event->start_date->format('M d, Y h:i A'),
                    'end_date_formatted' => $event->end_date ? $event->end_date->format('M d, Y h:i A') : null,
                    'org_logo' => $event->organization->settings->logo_url ?? null,
                    'org_primary_color' => $event->organization->settings->primary_color ?? '#26488C',
                    'proposal_id' => $event->proposal?->id,
                    'has_request_letter' => !empty($event->proposal?->request_letter),
                    'request_letter_path' => $event->proposal?->request_letter,
                    'request_letter_url' => $event->proposal?->request_letter ? storage_url($event->proposal->request_letter) : null,
                ],
                'url' => route('osas.proposals.show', $event->id),
            ];
        });

        $venues = collect(\App\Models\Event::VENUES)
            ->merge($allEvents->pluck('venue'))
            ->filter()
            ->unique()
            ->sort()
            ->values();
        $organizations = \App\Models\Organization::with('settings')->orderBy('name')->get();

        return view('osas.calendar', compact('events', 'venues', 'organizations'));
    }

    /**
     * Approve an organization event
     */
    public function approveEvent(Event $event)
    {
        try {
            if ($event->approval_status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'This event has already been ' . $event->approval_status . '.'
                ], 400);
            }

            $event->update([
                'approval_status' => 'approved',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);

            // Broadcast real-time update
            safe_broadcast(new \App\Events\EventUpdated($event), true);

            // Clear cache
            cache()->flush();

            return response()->json([
                'success' => true,
                'message' => 'Event approved successfully.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve event. Please try again.'
            ], 500);
        }
    }

    /**
     * Reject an organization event
     */
    public function rejectEvent(Event $event)
    {
        try {
            if ($event->approval_status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'This event has already been ' . $event->approval_status . '.'
                ], 400);
            }

            $event->update([
                'approval_status' => 'rejected',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);

            // Broadcast real-time update
            safe_broadcast(new \App\Events\EventUpdated($event), true);

            // Clear cache
            cache()->flush();

            return response()->json([
                'success' => true,
                'message' => 'Event rejected successfully.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reject event. Please try again.'
            ], 500);
        }
    }    public function downloadRequestLetter(Event $event)
    {
        if (!$event->proposal || !$event->proposal->request_letter) {
            return redirect()->back()->with('error', 'No request letter found.');
        }

        $extension = pathinfo($event->proposal->request_letter, PATHINFO_EXTENSION);
        $filename = $event->title . ' - Request Letter.' . $extension;

        return Storage::disk(storage_disk())->download($event->proposal->request_letter, $filename);
    }

    public function proposals()
    {
        $proposals = Event::with(['organization', 'proposal', 'user'])
            ->whereNotNull('osas_status')
            ->latest()
            ->paginate(15);

        $pendingCount = Event::where('osas_status', 'pending')->count();
        $approvedCount = Event::where('osas_status', 'approved')->whereNull('hardcopy_received_at')->count();
        $rejectedCount = Event::where('osas_status', 'rejected')->count();
        $hardcopyReceivedCount = Event::where('osas_status', 'approved')->whereNotNull('hardcopy_received_at')->count();

        return view('osas.proposals.index', compact('proposals', 'pendingCount', 'approvedCount', 'rejectedCount', 'hardcopyReceivedCount'));
    }

    public function showProposal($id)
    {
        $event = \App\Models\Event::allOrganizations()->findOrFail($id);
        $event->load([
            'organization.settings',
            'organization.department',
            'proposal.submitter',
            'proposal.reviewer',
            'proposal.osasReviewer',
            'proposal.rescheduleRequester',
            'proposal.rescheduleReviewer',
            'user',
            'approver',
            'osasApprover',
            'registrations',
        ]);
        return view('osas.proposals.show', compact('event'));
    }

    public function approveProposal(Event $event, OsasProposalQueueService $proposalQueue)
    {
        try {
            if ($event->osas_status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'This proposal has already been ' . $event->osas_status . '.'
                ], 400);
            }

            $event->update([
                'osas_status' => 'approved',
                'osas_approved_by' => auth()->id(),
                'osas_approved_at' => now(),
            ]);

            $proposalQueue->broadcastUpdated();

            safe_broadcast(new \App\Events\EventUpdated($event), true);

            // FYI-only: update the venue premise owner on the OSAS decision.
            \App\Services\VenuePremisesNotifier::notifyOwnerDeans($event, 'osas_approved');
            cache()->flush();

            if (request()->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Proposal approved. Event can now be posted.'
                ]);
            }

            return redirect()->route('osas.proposals.index')
                ->with('success', 'Proposal approved. Event can now be posted.');
        } catch (\Exception $e) {
            if (request()->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to approve proposal. Please try again.'
                ], 500);
            }
            return redirect()->route('osas.proposals.index')
                ->with('error', 'Failed to approve proposal. Please try again.');
        }
    }

    public function rejectProposal(Request $request, Event $event, OsasProposalQueueService $proposalQueue)
    {
        try {
            if ($event->osas_status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'This proposal has already been ' . $event->osas_status . '.'
                ], 400);
            }

            $validated = $request->validate([
                'rejection_reason' => 'required|string|max:1000',
            ]);

            $event->update([
                'osas_status' => 'rejected',
            ]);

            $proposalQueue->broadcastUpdated();

            // Save OSAS rejection reason to event_proposals
            if ($event->proposal) {
                $event->proposal->update([
                    'osas_rejection_reason' => $validated['rejection_reason'],
                    'osas_reviewed_by' => auth()->id(),
                    'osas_reviewed_at' => now(),
                ]);
            }

            safe_broadcast(new \App\Events\EventUpdated($event), true);

            // FYI-only: update the venue premise owner on the OSAS decision.
            \App\Services\VenuePremisesNotifier::notifyOwnerDeans($event, 'osas_rejected');
            cache()->flush();

            if (request()->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Proposal rejected.'
                ]);
            }

            return redirect()->route('osas.proposals.index')
                ->with('success', 'Proposal rejected.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            if (request()->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->validator->errors()->first('rejection_reason') ?? 'Please provide a rejection reason.'
                ], 422);
            }
            throw $e;
        } catch (\Exception $e) {
            if (request()->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to reject proposal. Please try again.'
                ], 500);
            }
            return redirect()->route('osas.proposals.index')
                ->with('error', 'Failed to reject proposal. Please try again.');
        }
    }

    /**
     * Approve a pending reschedule request. This grants the org's admin a
     * one-time window to edit the event's date/time/venue/budget.
     */
    public function approveReschedule(Event $event)
    {
        try {
            if (!$event->proposal || $event->proposal->reschedule_status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'There is no pending reschedule request for this event.'
                ], 400);
            }

            $event->proposal->update([
                'reschedule_status' => 'approved',
                'reschedule_reviewed_by' => auth()->id(),
                'reschedule_reviewed_at' => now(),
                'reschedule_rejection_reason' => null,
            ]);

            // Notify the event creator and org officers that they can now edit the event.
            $event->loadMissing('organization', 'user');
            $notifiable = collect([$event->user]);
            if ($event->organization) {
                $notifiable = $notifiable->merge($event->organization->officers);
            }
            \Illuminate\Support\Facades\Notification::send(
                $notifiable->unique('id'),
                new \App\Notifications\EventRescheduleReviewedNotification($event, 'approved')
            );

            safe_broadcast(new \App\Events\EventUpdated($event), true);
            cache()->flush();

            if (request()->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Reschedule request approved. The organization can now update the event.'
                ]);
            }

            return redirect()->route('osas.proposals.index')
                ->with('success', 'Reschedule request approved.');
        } catch (\Exception $e) {
            if (request()->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to approve reschedule request. Please try again.'
                ], 500);
            }
            return redirect()->route('osas.proposals.index')
                ->with('error', 'Failed to approve reschedule request. Please try again.');
        }
    }

    /**
     * Reject a pending reschedule request. The event keeps its current
     * date/time/venue and remains locked.
     */
    public function rejectReschedule(Request $request, Event $event)
    {
        try {
            if (!$event->proposal || $event->proposal->reschedule_status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'There is no pending reschedule request for this event.'
                ], 400);
            }

            $validated = $request->validate([
                'rejection_reason' => 'required|string|max:1000',
            ]);

            $event->proposal->update([
                'reschedule_status' => 'rejected',
                'reschedule_reviewed_by' => auth()->id(),
                'reschedule_reviewed_at' => now(),
                'reschedule_rejection_reason' => $validated['rejection_reason'],
            ]);

            // Notify the event creator and org officers about the rejection.
            $event->loadMissing('organization', 'user');
            $notifiable = collect([$event->user]);
            if ($event->organization) {
                $notifiable = $notifiable->merge($event->organization->officers);
            }
            \Illuminate\Support\Facades\Notification::send(
                $notifiable->unique('id'),
                new \App\Notifications\EventRescheduleReviewedNotification($event, 'rejected', $validated['rejection_reason'])
            );

            safe_broadcast(new \App\Events\EventUpdated($event), true);
            cache()->flush();

            if (request()->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Reschedule request rejected.'
                ]);
            }

            return redirect()->route('osas.proposals.index')
                ->with('success', 'Reschedule request rejected.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            if (request()->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->validator->errors()->first('rejection_reason') ?? 'Please provide a rejection reason.'
                ], 422);
            }
            throw $e;
        } catch (\Exception $e) {
            if (request()->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to reject reschedule request. Please try again.'
                ], 500);
            }
            return redirect()->route('osas.proposals.index')
                ->with('error', 'Failed to reject reschedule request. Please try again.');
        }
    }

    public function markHardcopyReceived(Event $event)
    {
        try {
            if ($event->osas_status !== 'approved') {
                return response()->json([
                    'success' => false,
                    'message' => 'Event must be approved by OSAS first.'
                ], 400);
            }

            if ($event->hardcopy_received_at) {
                return response()->json([
                    'success' => false,
                    'message' => 'Hardcopy already marked as received.'
                ], 400);
            }

            $event->update([
                'hardcopy_received_at' => now(),
            ]);

            safe_broadcast(new \App\Events\EventUpdated($event), true);
            cache()->flush();

            if (request()->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Hardcopy marked as received.'
                ]);
            }

            return redirect()->route('osas.proposals.index')
                ->with('success', 'Hardcopy marked as received.');
        } catch (\Exception $e) {
            if (request()->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to mark hardcopy as received. Please try again.'
                ], 500);
            }
            return redirect()->route('osas.proposals.index')
                ->with('error', 'Failed to mark hardcopy as received. Please try again.');
        }
    }
}

