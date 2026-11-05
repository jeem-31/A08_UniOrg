<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\Complaint;
use App\Models\Event;
use App\Models\Payment;
use App\Models\User;
use App\Support\NotificationVisibility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        // Redirect OSAS admin to their dashboard
        if ($user->role === 'osas_admin') {
            return redirect()->route('osas.dashboard');
        }

        // Deans belong to the department area, not the org-admin dashboard.
        if ($user->role === 'super_admin') {
            return redirect()->route('dean.dashboard');
        }

        // Systemless-org fallback users are always shown the Student View of
        // their department Mother Org, regardless of account role or view.
        if ($user->isSystemlessFallback()) {
            return $this->studentDashboard();
        }

        // Zero-module admins automatically fall back to Student View instead of being blocked
        if ($user->isAdmin() && !$user->isAdviser() && !$user->isComelec() && !$user->isSystemlessFallback()) {
            $orgIdForCheck = session('current_organization_id') ?? $user->organizations()->first()?->id;
            if ($orgIdForCheck && !app(\App\Services\OrganizationPermissionService::class)->hasAnyModuleAccess($user->id, (int) $orgIdForCheck)) {
                if ($user->getActiveView() !== 'student') {
                    $user->active_view = 'student';
                    $user->save();
                }
                session()->flash('error', 'Wala ka pang module access para sa posisyon na ito. Makipag-ugnayan sa iyong Adviser sa Settings → Admins para mabigyan ng access. Nasa Student View ka ngayon.');
                return $this->studentDashboard();
            }
        }

        if ($user->role === 'admin' && $user->getActiveView() === 'student') {
            return $this->studentDashboard();
        }

        if ($user->isAdmin()) {
            return $this->adminDashboard();
        }

        return $this->studentDashboard();
    }

    public function adminDashboard()
    {
        $user = auth()->user();
        
        // Get organization from session (current organization context)
        $organizationId = session('current_organization_id');
        $organization = $organizationId ? \App\Models\Organization::find($organizationId) : null;

        // Zero-module admins auto-fallback to Student View (not hard 403) so they remain usable
        if ($organization && !app(\App\Services\OrganizationPermissionService::class)->hasAnyModuleAccess($user->id, $organization->id)) {
            if ($user->active_view !== 'student') {
                $user->active_view = 'student';
                $user->save();
            }
            return redirect()->route('dashboard')->with('error', 'Wala ka pang module access para sa posisyon na ito. Makipag-ugnayan sa iyong Adviser sa Settings → Admins para mabigyan ng access. Ikaw ay nasa Student View ngayon.');
        }

        // Aggregate stats are cached for 60s per organization (Redis). The
        // payment/revenue queries are expensive against the remote DB, and
        // these totals only change when payments are made or content is added.
        $stats = $organization
            ? Cache::remember('admin.dashboard.stats.'.$organization->id, 60, function () use ($organization) {
                return [
                    'total_students' => $organization->users()->where('users.role', 'student')->count(),
                    'total_announcements' => Announcement::where('organization_id', $organization->id)->count(),
                    'total_events' => Event::where('organization_id', $organization->id)->count(),
                    'pending_payments' => \App\Models\StudentPayment::whereHas('user', function($q) use ($organization) {
                        $q->whereHas('organizations', function($q2) use ($organization) {
                            $q2->where('organizations.id', $organization->id);
                        });
                    })->where('status', 'pending')->count(),
                    'total_revenue' => \App\Models\StudentPayment::whereHas('user', function($q) use ($organization) {
                        $q->whereHas('organizations', function($q2) use ($organization) {
                            $q2->where('organizations.id', $organization->id);
                        });
                    })->where('status', 'completed')->sum('amount'),
                ];
            })
            : [
                'total_students' => 0,
                'total_announcements' => 0,
                'total_events' => 0,
                'pending_payments' => 0,
                'total_revenue' => 0,
            ];

        // Eager load user relationships - use published() to filter by is_published = true
        $recentAnnouncements = $organization ? Announcement::with('user')->where('organization_id', $organization->id)->published()->recent()->take(5)->get() : collect();
        $upcomingEvents = $organization ? Event::with('user')->where('organization_id', $organization->id)->published()->recent()->take(5)->get() : collect();
        $recentPayments = $organization ? \App\Models\StudentPayment::with('user', 'payment')->whereHas('user', function($q) use ($organization) {
            $q->whereHas('organizations', function($q2) use ($organization) {
                $q2->where('organizations.id', $organization->id);
            });
        })->where('status', 'completed')->latest()->take(5)->get() : collect();

        // Use admin.dashboard view for admin users
        return view('admin.dashboard', compact(
            'stats',
            'recentAnnouncements',
            'upcomingEvents',
            'recentPayments',
            'organization'
        ));
    }

    private function studentDashboard()
    {
        $user = auth()->user();
        
        // Prefer the organization context in the session — this is what every
        // other student page uses, and what the systemless-org fallback sets
        // to the department Mother Org. Falls back to the user's first usable
        // active organization for safety.
        $organization = null;
        $currentOrgId = session('current_organization_id');
        if ($currentOrgId) {
            $candidate = \App\Models\Organization::find($currentOrgId);
            if ($candidate && $candidate->canBeUsed()) {
                $organization = $candidate;
            }
        }
        if (!$organization) {
            $organization = $user->organizations()
                ->wherePivot('membership_status', 'Active')
                ->get()
                ->filter(fn($org) => $org->canBeUsed())
                ->first();
        }
        
        // Get department acronym from organization's department
        if ($organization && $organization->department) {
            $orgAcronym = $organization->department->department_acronym;
        } else {
            $orgAcronym = 'CCS'; // Default fallback
        }
        
        // Content visibility mirrors the student Announcements/Events pages so posts
        // don't disappear from the dashboard while visible on /announcements.
        // $organization above stays canBeUsed-gated for display/acronym only.
        $contentOrgId = $currentOrgId ?: $user->organizations()->wherePivot('membership_status', 'Active')->value('organizations.id');
        $contentOrg = $contentOrgId ? \App\Models\Organization::find($contentOrgId) : null;
        $organizationIds = $contentOrgId ? [$contentOrgId] : [];
        if ($contentOrg && $contentOrg->type === 'Mother Org' && $contentOrg->department_id) {
            $organizationIds = array_merge($organizationIds, \App\Models\Organization::where('department_id', $contentOrg->department_id)
                ->where('type', 'Sub-Org')
                ->pluck('id')
                ->all());
        }

        $announcements = Announcement::allOrganizations()
            ->with('user')
            ->where(function ($query) use ($organizationIds) {
                $query->whereNull('organization_id');

                if (!empty($organizationIds)) {
                    $query->orWhereIn('organization_id', $organizationIds);
                }
            })
            ->published()
            ->recent()
            ->take(5)
            ->get();
        $upcomingEvents = !empty($organizationIds)
            ? Event::allOrganizations()->with('user')->whereIn('organization_id', $organizationIds)->published()->upcoming()->orderBy('start_date')->take(5)->get()
            : Event::with('user')->published()->upcoming()->orderBy('start_date')->take(5)->get();
        $recentPayments = $user->studentPayments()
            ->with('payment')
            ->latest()
            ->take(5)
            ->get();
        $paymentAnnouncements = Payment::with('user')
            ->latest()
            ->take(4)
            ->get();
        $studentPaymentStatuses = $user->studentPayments()
            ->whereIn('payment_id', $paymentAnnouncements->pluck('id'))
            ->latest()
            ->get()
            ->unique('payment_id')
            ->keyBy('payment_id');
        $visibleUnreadNotifications = NotificationVisibility::forUser(
            $user,
            $user->unreadNotifications()->latest()->get()
        );

        return view('student.dashboard', compact(
            'announcements',
            'upcomingEvents',
            'recentPayments',
            'paymentAnnouncements',
            'studentPaymentStatuses',
            'visibleUnreadNotifications',
            'orgAcronym'
        ));
    }

    public function deanDashboard()
    {
        $departmentId = auth()->user()->department_id;
        
        // Get all organization IDs belonging to the Dean's department
        $orgIds = \App\Models\Organization::where('department_id', $departmentId)->pluck('id');

        // Calculate pending event proposals for this department
        $pendingProposalsCount = Event::withoutGlobalScope('organization')
            ->whereIn('organization_id', $orgIds)
            ->where('approval_status', 'pending')
            ->count();
        
        $stats = [
            'total_students' => User::where('role', 'student')->where('department_id', $departmentId)->count(),
            'pending_approvals' => $pendingProposalsCount,
            'total_announcements' => Announcement::withoutGlobalScope('organization')->whereIn('organization_id', $orgIds)->count(),
            'total_complaints' => Complaint::withoutGlobalScope('organization')->whereIn('organization_id', $orgIds)->count(),
            'total_soccs_admins' => User::where('role', 'admin')->where('department_id', $departmentId)->count(),
        ];

        // Eager load user relationships scoped to this department's organizations
        $recentAnnouncements = Announcement::withoutGlobalScope('organization')
            ->with('user')
            ->whereIn('organization_id', $orgIds)
            ->published()
            ->recent()
            ->take(5)
            ->get();
            
        $recentEvents = Event::withoutGlobalScope('organization')
            ->with('user')
            ->whereIn('organization_id', $orgIds)
            ->published()
            ->latest()
            ->take(5)
            ->get();
            
        $pendingComplaints = Complaint::withoutGlobalScope('organization')
            ->with('user')
            ->whereIn('organization_id', $orgIds)
            ->where('status', 'pending')
            ->latest()
            ->take(5)
            ->get();

        // Premise-use advisory (FYI-only): campus-wide events using this
        // department's owned premise(s). Intentionally NOT scoped to own-dept
        // orgs — cross-dept usage is the point. withoutGlobalScope matches
        // the other queries above (BelongsToOrganization session scoping).
        $departmentAcronym = $departmentId
            ? \App\Models\Department::where('id', $departmentId)->value('department_acronym')
            : null;
        $ownedVenues = [];
        if ($departmentAcronym) {
            foreach (Event::VENUE_OWNER_MAP as $venue => $acronym) {
                if (strcasecmp($acronym, $departmentAcronym) === 0) {
                    $ownedVenues[] = $venue;
                }
            }
        }
        $venueNotices = collect();
        if (! empty($ownedVenues)) {
            $venueNotices = Event::withoutGlobalScope('organization')
                ->with(['organization:id,name,org_acronym', 'user:id,first_name,last_name'])
                ->select('id', 'organization_id', 'user_id', 'title', 'description', 'images', 'start_date', 'end_date', 'venue', 'approval_status', 'osas_status', 'created_at')
                ->whereIn('venue', $ownedVenues)
                ->latest()
                ->take(8)
                ->get();
        }

        // Use dean.dashboard view for super_admin users
        return view('dean.dashboard', compact('stats', 'recentAnnouncements', 'recentEvents', 'pendingComplaints', 'venueNotices', 'ownedVenues'));
    }

    public function contentOverview()
    {
        $departmentId = auth()->user()->department_id;
        $orgIds = \App\Models\Organization::where('department_id', $departmentId)->pluck('id');

        $announcements = Announcement::withoutGlobalScope('organization')
            ->with('user')
            ->whereIn('organization_id', $orgIds)
            ->latest()
            ->paginate(12);
        $events = Event::withoutGlobalScope('organization')
            ->with('user')
            ->whereIn('organization_id', $orgIds)
            ->where('approval_status', 'approved')
            ->latest()
            ->paginate(12);
        $complaints = Complaint::withoutGlobalScope('organization')
            ->with('user')
            ->whereIn('organization_id', $orgIds)
            ->latest()
            ->paginate(15);

        return view('dean.content-overview', compact('announcements', 'events', 'complaints'));
    }

    public function deanReports()
    {
        $departmentId = auth()->user()->department_id;
        $orgIds = \App\Models\Organization::where('department_id', $departmentId)->pluck('id');

        // Generate college-scoped reports for dean
        $stats = [
            'total_students' => User::where('role', 'student')->where('department_id', $departmentId)->count(),
            'total_payments' => \App\Models\StudentPayment::whereIn('organization_id', $orgIds)->where('status', 'completed')->count(),
            'total_revenue' => \App\Models\StudentPayment::whereIn('organization_id', $orgIds)->where('status', 'completed')->sum('amount'),
            'pending_payments' => \App\Models\StudentPayment::whereIn('organization_id', $orgIds)->where('status', 'pending')->count(),
            'total_events' => Event::withoutGlobalScope('organization')->whereIn('organization_id', $orgIds)->count(),
            'total_announcements' => Announcement::withoutGlobalScope('organization')->whereIn('organization_id', $orgIds)->count(),
            'total_complaints' => Complaint::withoutGlobalScope('organization')->whereIn('organization_id', $orgIds)->count(),
        ];

        return view('dean.reports', compact('stats'));
    }

    public function guestDashboard()
    {
        // Eager load user relationships
        $announcements = Announcement::with('user')->published()->recent()->take(4)->get();
        $upcomingEvents = Event::with('user')->published()->upcoming()->take(4)->get();

        return view('guest.dashboard', compact('announcements', 'upcomingEvents'));
    }
}
