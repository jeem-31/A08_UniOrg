<?php

namespace App\Http\Controllers\Dean;

use App\Http\Controllers\Controller;
use App\Mail\EventSubmittedToOsas;
use App\Models\Event;
use App\Models\EventFinancialReport;
use App\Models\EventProposal;
use App\Models\EventRegistration;
use App\Models\User;
use App\Notifications\EventDeanActionNotification;
use App\Notifications\EventSubmittedToOsasNotification;
use App\Services\OsasProposalQueueService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Dompdf\Dompdf;
use Dompdf\Options;

class DeanController extends Controller
{
    // Event Proposals (Event Requests from Admins)
    public function proposals()
    {
        $departmentId = auth()->user()->department_id;
        $orgIds = \App\Models\Organization::where('department_id', $departmentId)->pluck('id');

        // Aggressive caching - cache for 2 minutes, unique per department
        $cacheKey = 'dean.proposals.page.' . request('page', 1) . '.dept.' . ($departmentId ?? 0);
        
        $events = cache()->remember($cacheKey, 120, function () use ($orgIds) {
            return Event::with(['user:id,first_name,last_name,email', 'proposal'])
                ->select('id', 'user_id', 'title', 'description', 'start_date', 'end_date', 'venue', 'category', 'planned_budget', 'images', 'approval_status', 'approved_by', 'approved_at', 'dean_approved_at', 'osas_status', 'created_at', 'updated_at')
                ->whereIn('organization_id', $orgIds)
                ->whereIn('approval_status', ['pending', 'approved', 'rejected'])
                ->latest()
                ->paginate(15);
        });

        // Ensure proposal relation is available in JSON serialization
        $events->getCollection()->transform(function ($event) {
            if ($event->relationLoaded('proposal')) {
                $event->proposal = $event->proposal ? $event->proposal->toArray() : null;
            }
            return $event;
        });

        return response()
            ->view('dean.proposals.index', compact('events'))
            ->header('Cache-Control', 'private, max-age=120');
    }

    public function approveEvent(Event $event, OsasProposalQueueService $proposalQueue)
    {
        $this->eventInDepartment($event);

        try {
            if ($event->approval_status !== 'pending') {
                if (request()->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'This event has already been ' . $event->approval_status . '.'
                    ], 400);
                }
                return redirect()->route('dean.proposals.index')
                    ->with('error', 'This event has already been ' . $event->approval_status . '.');
            }

            $event->update([
                'approval_status' => 'approved',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
                'dean_approved_at' => now(),
                // Auto-submit to OSAS right away - no need for admin to manually resubmit
                // after the Dean has already approved the event.
                'osas_status' => 'pending',
            ]);

            // Broadcast real-time update
            safe_broadcast(new \App\Events\EventUpdated($event), true);

            // Notify event creator and org officers
            $event->loadMissing('organization');
            $notifiable = collect([$event->user]);
            if ($event->organization) {
                $notifiable = $notifiable->merge($event->organization->officers);
            }
            Notification::send($notifiable->unique('id'), new EventDeanActionNotification($event, 'approved'));

            // FYI-only: update the venue premise owner on the Dean decision.
            \App\Services\VenuePremisesNotifier::notifyOwnerDeans($event, 'dean_approved');

            // Notify all OSAS admins via email + database notification, same as the
            // manual "Submit to OSAS" action used to do.
            $osasAdmins = User::where('role', 'osas_admin')->get();
            foreach ($osasAdmins as $admin) {
                Mail::to($admin->email)->queue(new EventSubmittedToOsas($event));
                $admin->notify(new EventSubmittedToOsasNotification($event));
            }

            $proposalQueue->broadcastUpdated();

            // Clear cache
            cache()->flush();

            if (request()->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Event approved and automatically submitted to OSAS for review.'
                ]);
            }

            return redirect()->route('dean.proposals.index')
                ->with('success', 'Event approved and automatically submitted to OSAS for review.');
        } catch (\Exception $e) {
            if (request()->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to approve event. Please try again.'
                ], 500);
            }
            return redirect()->route('dean.proposals.index')
                ->with('error', 'Failed to approve event. Please try again.');
        }
    }

    public function rejectEvent(Request $request, Event $event)
    {
        $this->eventInDepartment($event);

        try {
            if ($event->approval_status !== 'pending') {
                if (request()->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'This event has already been ' . $event->approval_status . '.'
                    ], 400);
                }
                return redirect()->route('dean.proposals.index')
                    ->with('error', 'This event has already been ' . $event->approval_status . '.');
            }

            $request->validate([
                'rejection_reason' => 'required|string|max:1000',
            ]);

            $event->update([
                'approval_status' => 'rejected',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);

            // Save rejection reason to event_proposals
            if ($event->proposal) {
                $event->proposal->update([
                    'status' => 'rejected',
                    'rejection_reason' => $request->rejection_reason,
                    'reviewed_by' => auth()->id(),
                    'reviewed_at' => now(),
                ]);
            }

            // Broadcast real-time update
            safe_broadcast(new \App\Events\EventUpdated($event), true);

            // Notify event creator and org officers
            $event->loadMissing('organization');
            $notifiable = collect([$event->user]);
            if ($event->organization) {
                $notifiable = $notifiable->merge($event->organization->officers);
            }
            Notification::send($notifiable->unique('id'), new EventDeanActionNotification($event, 'rejected', $request->rejection_reason));

            // FYI-only: update the venue premise owner on the Dean decision.
            \App\Services\VenuePremisesNotifier::notifyOwnerDeans($event, 'dean_rejected');

            // Clear cache
            cache()->flush();

            if (request()->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Event rejected successfully.'
                ]);
            }

            return redirect()->route('dean.proposals.index')
                ->with('success', 'Event rejected successfully.');
        } catch (\Exception $e) {
            if (request()->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to reject event. Please try again.'
                ], 500);
            }
            return redirect()->route('dean.proposals.index')
                ->with('error', 'Failed to reject event. Please try again.');
        }
    }

    public function showProposal(EventProposal $proposal)
    {
        $this->proposalInDepartment($proposal);

        $proposal->load(['event', 'submitter', 'reviewer']);
        return view('dean.proposals.show', compact('proposal'));
    }

    public function approveProposal(Request $request, EventProposal $proposal)
    {
        $this->proposalInDepartment($proposal);

        $proposal->update([
            'status' => 'approved',
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        return redirect()->route('dean.proposals.show', $proposal)
            ->with('success', 'Event proposal approved successfully.');
    }

    public function rejectProposal(Request $request, EventProposal $proposal)
    {
        $this->proposalInDepartment($proposal);

        $request->validate([
            'rejection_reason' => 'required|string',
        ]);

        $proposal->update([
            'status' => 'rejected',
            'rejection_reason' => $request->rejection_reason,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        return redirect()->route('dean.proposals.show', $proposal)
            ->with('success', 'Event proposal rejected.');
    }

    // Financial Reports
    public function financialReports(Request $request)
    {
        $departmentId = auth()->user()->department_id;
        $orgIds = \App\Models\Organization::where('department_id', $departmentId)->pluck('id');

        // Calculate summary statistics
        $totalPayments = \App\Models\StudentPayment::whereIn('organization_id', $orgIds)->where('status', 'completed')->count();
        $totalEventBudgets = Event::whereIn('organization_id', $orgIds)->whereNotNull('planned_budget')
            ->where('approval_status', 'approved')
            ->sum('planned_budget');
        
        // Load all payments (for client-side filtering)
        $payments = \App\Models\StudentPayment::with(['user', 'payment'])
            ->whereIn('organization_id', $orgIds)
            ->where('status', 'completed')
            ->latest('paid_at')
            ->get();
        
        // Load all event budgets (for client-side filtering)
        $eventBudgets = Event::with(['user'])
            ->whereIn('organization_id', $orgIds)
            ->whereNotNull('planned_budget')
            ->latest()
            ->get();
        
        return view('dean.financial-reports.index', compact(
            'payments',
            'eventBudgets',
            'totalPayments',
            'totalEventBudgets'
        ));
    }

    public function exportFinancialReports(Request $request)
    {
        $departmentId = auth()->user()->department_id;
        $orgIds = \App\Models\Organization::where('department_id', $departmentId)->pluck('id');

        $tab = $request->get('tab', 'payments');
        $category = $request->get('category', 'all');
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');
        
        // Configure DomPDF
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $dompdf = new Dompdf($options);
        
        // Load data based on tab
        if ($tab === 'budgets') {
            $eventBudgets = Event::with(['user'])
                ->whereIn('organization_id', $orgIds)
                ->whereNotNull('planned_budget')
                ->latest()
                ->get();
            
            $html = view('dean.financial-reports.pdf-budgets', compact('eventBudgets'))->render();
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'landscape');
            $dompdf->render();
            
            return response()->streamDownload(function() use ($dompdf) {
                echo $dompdf->output();
            }, 'event-budgets-report-' . now()->format('Y-m-d') . '.pdf');
        }
        
        // Default: payments with filters
        $query = \App\Models\StudentPayment::with(['user', 'payment'])
            ->whereIn('organization_id', $orgIds)
            ->where('status', 'completed');
        
        // Apply category filter
        if ($category !== 'all') {
            $query->where('category', $category);
        }
        
        // Apply date range filter
        if ($startDate) {
            $query->whereDate('paid_at', '>=', $startDate);
        }
        if ($endDate) {
            $query->whereDate('paid_at', '<=', $endDate);
        }
        
        $payments = $query->latest('paid_at')->get();
        $html = view('dean.financial-reports.pdf-payments', compact('payments', 'category', 'startDate', 'endDate'))->render();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();
        
        $filename = 'student-payments-report';
        if ($category !== 'all') {
            $filename .= '-' . $category;
        }
        $filename .= '-' . now()->format('Y-m-d') . '.pdf';
        
        return response()->streamDownload(function() use ($dompdf) {
            echo $dompdf->output();
        }, $filename);
    }

    public function showFinancialReport(EventFinancialReport $report)
    {
        $this->reportInDepartment($report);

        $report->load(['event', 'submitter', 'reviewer']);
        return view('dean.financial-reports.show', compact('report'));
    }

    public function approveFinancialReport(Request $request, EventFinancialReport $report)
    {
        $this->reportInDepartment($report);

        $report->update([
            'status' => 'approved',
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        return redirect()->route('dean.financial-reports.show', $report)
            ->with('success', 'Financial report approved successfully.');
    }

    public function rejectFinancialReport(Request $request, EventFinancialReport $report)
    {
        $this->reportInDepartment($report);

        $request->validate([
            'remarks' => 'required|string',
        ]);

        $report->update([
            'status' => 'rejected',
            'remarks' => $request->remarks,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        return redirect()->route('dean.financial-reports.show', $report)
            ->with('success', 'Financial report rejected.');
    }

    // Delete Event (for rejected events)
    public function downloadRequestLetter(Event $event)
    {
        $this->eventInDepartment($event);

        if (!$event->proposal || !$event->proposal->request_letter) {
            return redirect()->back()->with('error', 'No request letter found.');
        }

        $extension = pathinfo($event->proposal->request_letter, PATHINFO_EXTENSION);
        $filename = $event->title . ' - Request Letter.' . $extension;

        return Storage::disk(storage_disk())->download($event->proposal->request_letter, $filename);
    }

    public function deleteEvent(Event $event)
    {
        $this->eventInDepartment($event);

        // Only allow deletion of rejected events
        if ($event->approval_status !== 'rejected') {
            if (request()->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only rejected events can be deleted.'
                ], 422);
            }

            return redirect()->back()->with('error', 'Only rejected events can be deleted.');
        }

        // Delete event images if they exist
        if ($event->images && is_array($event->images)) {
            foreach ($event->images as $image) {
                Storage::disk(storage_disk())->delete($image);
            }
        }

        $event->delete();

        // Broadcast real-time update
        safe_broadcast(new \App\Events\EventDeleted($event->id), true);

        // Clear cache
        cache()->flush();

        if (request()->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Event deleted successfully.'
            ]);
        }

        return redirect()->route('dean.proposals.index')
            ->with('success', 'Event deleted successfully.');
    }

    // Registration Reports
    public function registrationReports()
    {
        $departmentId = auth()->user()->department_id;
        $orgIds = \App\Models\Organization::where('department_id', $departmentId)->pluck('id');

        $totalRegistrations = User::where('role', 'student')->where('department_id', $departmentId)->count();
        $totalParticipants = EventRegistration::whereHas('event', function ($query) use ($orgIds) {
            $query->whereIn('organization_id', $orgIds)
                ->where('approval_status', 'approved');
        })->count();
        
        $eventRegistrations = Event::withCount('registrations')
            ->with(['registrations' => function($query) {
                $query->with(['payment', 'user']);
            }])
            ->whereIn('organization_id', $orgIds)
            ->where('approval_status', 'approved')
            ->latest()
            ->paginate(15);

        return view('dean.registration-reports', compact('totalRegistrations', 'totalParticipants', 'eventRegistrations'));
    }

    public function eventRegistrationDetails(Event $event)
    {
        $this->eventInDepartment($event);

        $registrations = EventRegistration::where('event_id', $event->id)
            ->with(['user', 'payment'])
            ->latest()
            ->paginate(20);

        $stats = [
            'total_registrations' => $registrations->total(),
            'paid_registrations' => EventRegistration::where('event_id', $event->id)
                ->where('status', 'paid')
                ->count(),
            'total_payments' => EventRegistration::where('event_id', $event->id)
                ->whereHas('payment')
                ->with('payment')
                ->get()
                ->sum(fn($reg) => $reg->payment?->amount ?? 0),
        ];

        return view('dean.event-registration-details', compact('event', 'registrations', 'stats'));
    }

    public function exportRegistrationReports()
    {
        $departmentId = auth()->user()->department_id;
        $orgIds = \App\Models\Organization::where('department_id', $departmentId)->pluck('id');

        $totalRegistrations = User::where('role', 'student')->where('department_id', $departmentId)->count();

        $eventRegistrations = Event::withCount('registrations')
            ->with(['registrations' => function($query) {
                $query->with('user');
            }])
            ->whereIn('organization_id', $orgIds)
            ->where('approval_status', 'approved')
            ->latest()
            ->get();
        $totalParticipants = (int) $eventRegistrations->sum('registrations_count');

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $dompdf = new Dompdf($options);

        $html = view('dean.registration-reports-pdf', compact('totalRegistrations', 'totalParticipants', 'eventRegistrations'))->render();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return response()->streamDownload(function() use ($dompdf) {
            echo $dompdf->output();
        }, 'registration-reports-' . now()->format('Y-m-d') . '.pdf');
    }

    public function exportSystemReports()
    {
        $departmentId = auth()->user()->department_id;
        $orgIds = \App\Models\Organization::where('department_id', $departmentId)->pluck('id');

        $stats = [
            'total_students' => User::where('role', 'student')->where('department_id', $departmentId)->count(),
            'total_payments' => \App\Models\StudentPayment::whereIn('organization_id', $orgIds)->where('status', 'completed')->count(),
            'total_revenue' => \App\Models\StudentPayment::whereIn('organization_id', $orgIds)->where('status', 'completed')->sum('amount'),
            'pending_payments' => \App\Models\StudentPayment::whereIn('organization_id', $orgIds)->where('status', 'pending')->count(),
            'total_events' => Event::whereIn('organization_id', $orgIds)->count(),
            'total_announcements' => \App\Models\Announcement::whereIn('organization_id', $orgIds)->count(),
            'total_complaints' => \App\Models\Complaint::whereIn('organization_id', $orgIds)->count(),
        ];

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $dompdf = new Dompdf($options);

        $html = view('dean.reports-pdf', compact('stats'))->render();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return response()->streamDownload(function() use ($dompdf) {
            echo $dompdf->output();
        }, 'system-reports-' . now()->format('Y-m-d') . '.pdf');
    }
    /**
     * User Management for Dean
     */
    public function users(Request $request)
    {
        $departmentId = auth()->user()->department_id;
        $search = trim((string) $request->input('search', ''));

        $studentQuery = User::where('role', 'student')
            ->where('department_id', $departmentId);
        $departmentOrganizationIds = \App\Models\Organization::where('department_id', $departmentId)->pluck('id');
        $positionPrioritySql = "CASE LOWER(REPLACE(TRIM(organization_user.role), '_', ' '))
            WHEN 'president' THEN 0
            WHEN 'organization president' THEN 0
            WHEN 'vice president' THEN 1
            WHEN 'secretary' THEN 2
            WHEN 'treasurer' THEN 3
            WHEN 'auditor' THEN 4
            WHEN 'business manager' THEN 5
            WHEN 'pio' THEN 6
            WHEN 'event coordinator' THEN 7
            WHEN '1st yr representative' THEN 8
            WHEN '1st year representative' THEN 8
            WHEN '2nd yr representative' THEN 9
            WHEN '2nd year representative' THEN 9
            WHEN '3rd yr representative' THEN 10
            WHEN '3rd year representative' THEN 10
            WHEN '4th yr representative' THEN 11
            WHEN '4th year representative' THEN 11
            WHEN 'adviser' THEN 12
            WHEN 'organization adviser' THEN 12
            WHEN 'senior adviser' THEN 12
            WHEN 'junior adviser' THEN 12
            ELSE 999
        END";
        $adminQuery = User::where('users.role', 'admin')
            ->where('users.department_id', $departmentId)
            ->leftJoinSub(
                DB::table('organization_user')
                    ->whereIn('organization_id', $departmentOrganizationIds)
                    ->where('membership_status', 'Active')
                    ->select('user_id')
                    ->selectRaw("MIN({$positionPrioritySql}) AS position_priority")
                    ->groupBy('user_id'),
                'admin_position_order',
                'admin_position_order.user_id',
                '=',
                'users.id'
            )
            ->select('users.*')
            ->orderByRaw('COALESCE(admin_position_order.position_priority, 999)')
            ->orderBy('users.last_name')
            ->orderBy('users.first_name');

        if ($search !== '') {
            $like = '%' . $search . '%';
            $applySearch = function ($query, bool $includeAcademicFields = false) use ($like) {
                $query->where(function ($searchQuery) use ($like, $includeAcademicFields) {
                    $searchQuery->where('first_name', 'like', $like)
                        ->orWhere('last_name', 'like', $like)
                        ->orWhere('email', 'like', $like)
                        ->orWhere('student_id', 'like', $like);

                    if ($includeAcademicFields) {
                        $searchQuery->orWhere('course', 'like', $like)
                            ->orWhere('year_level', 'like', $like);
                    }
                });
            };

            $applySearch($studentQuery, true);
            $applySearch($adminQuery);
        }

        $studentStatsQuery = clone $studentQuery;
        $adminStatsQuery = clone $adminQuery;
        $studentCount = $studentStatsQuery->count();
        $adminCount = $adminStatsQuery->count();
        $activeLiteral = DB::connection()->getDriverName() === 'pgsql' ? 'TRUE' : '1';
        $activeCount = (clone $studentQuery)->whereRaw("is_active = {$activeLiteral}")->count()
            + (clone $adminQuery)->whereRaw("is_active = {$activeLiteral}")->count();

        $students = $studentQuery->latest()->get();
        $admins = $adminQuery
            ->with(['organizations' => function ($query) use ($departmentId) {
                $query->where('organizations.department_id', $departmentId)
                    ->wherePivot('membership_status', 'Active')
                    ->withPivot('role', 'membership_status');
            }])
            ->get();

        $positionPriority = [
            'president' => 0,
            'organization president' => 0,
            'vice president' => 1,
            'secretary' => 2,
            'treasurer' => 3,
            'auditor' => 4,
            'business manager' => 5,
            'pio' => 6,
            'event coordinator' => 7,
            '1st yr representative' => 8,
            '1st year representative' => 8,
            '2nd yr representative' => 9,
            '2nd year representative' => 9,
            '3rd yr representative' => 10,
            '3rd year representative' => 10,
            '4th yr representative' => 11,
            '4th year representative' => 11,
            'adviser' => 12,
            'organization adviser' => 12,
            'senior adviser' => 12,
            'junior adviser' => 12,
        ];
        $admins->each(function ($admin) use ($positionPriority) {
            $rawPosition = $admin->organizations
                ->pluck('pivot.role')
                ->filter()
                ->sortBy(function ($position) use ($positionPriority) {
                    $normalized = strtolower(str_replace('_', ' ', trim($position)));
                    return $positionPriority[$normalized] ?? 999;
                })
                ->first() ?? 'Organization admin';

            // Roles are stored in canonical display form, so the front-end
            // shows the exact position label (e.g. "Business Manager").
            $admin->primary_position = \App\Models\OrganizationUser::formatDisplayLabel($rawPosition);
        });
        $userStats = [
            'students' => $studentCount,
            'admins' => $adminCount,
            'total' => $studentCount + $adminCount,
            'active' => $activeCount,
        ];

        return view('dean.users', compact('students', 'admins', 'userStats', 'search'));
    }

    public function deleteUser(User $user)
    {
        $this->userInDepartment($user);

        // Dean is deactivate-only: students are view-only, admins are toggled via lifecycle.
        if ($user->role === 'student') {
            abort(403, 'Students are view-only for the Dean. Deactivation is not allowed.');
        }

        if ($user->role !== 'admin') {
            abort(403, 'Only organization admins can be deactivated by the Dean.');
        }

        // Legacy DELETE endpoint now behaves as deactivate-only for backward compat.
        return $this->toggleUserStatus(request(), $user);
    }

    public function toggleUserStatus(Request $request, User $user)
    {
        $this->userInDepartment($user);

        if ($user->role === 'student') {
            abort(403, 'Students are view-only for the Dean.');
        }

        if ($user->role !== 'admin') {
            abort(403, 'Only organization admins can be deactivated by the Dean.');
        }

        $desiredActive = $request->input('action') === 'activate' ? true : ($request->input('action') === 'deactivate' ? false : !$user->is_active);
        if ($request->has('is_active')) {
            $desiredActive = (bool) $request->boolean('is_active');
        }

        if ((bool) $user->is_active === $desiredActive) {
            return redirect()->route('dean.users.index')
                ->with('success', $user->full_name . ' is already ' . ($desiredActive ? 'active' : 'inactive') . '.');
        }

        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($user, $desiredActive): void {
                $user->is_active = $desiredActive;
                $user->save();
            });

            cache()->flush();

            return redirect()->route('dean.users.index')
                ->with('success', $user->full_name . ' was ' . ($desiredActive ? 'reactivated' : 'deactivated') . '.');
        } catch (\Exception $e) {
            return redirect()->route('dean.users.index')
                ->with('error', 'Failed to update status: ' . $e->getMessage());
        }
    }

    /**
     * A Dean may only act on records that belong to their own college, so every
     * action endpoint re-checks the target against the Dean's department before
     * reading or mutating anything.
     */
    private function eventInDepartment(Event $event): void
    {
        $this->requireDepartmentOrganization($event->organization_id);
    }

    private function proposalInDepartment(EventProposal $proposal): void
    {
        $this->requireDepartmentOrganization($proposal->event?->organization_id);
    }

    private function reportInDepartment(EventFinancialReport $report): void
    {
        $this->requireDepartmentOrganization($report->event?->organization_id);
    }

    private function requireDepartmentOrganization(?int $organizationId): void
    {
        $departmentId = (int) auth()->user()->department_id;
        $belongs = $organizationId !== null
            && $departmentId > 0
            && \App\Models\Organization::query()
                ->where('department_id', $departmentId)
                ->whereKey($organizationId)
                ->exists();

        if (! $belongs) {
            abort(403, 'This record belongs to another college.');
        }
    }

    private function userInDepartment(User $user): void
    {
        $departmentId = (int) auth()->user()->department_id;

        if ($departmentId <= 0 || (int) $user->department_id !== $departmentId) {
            abort(403, 'This account belongs to another college.');
        }
    }
}
