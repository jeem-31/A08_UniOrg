<?php

namespace App\Http\Controllers\Dean;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Event;
use App\Models\Payment;
use App\Models\User;
use App\Models\Complaint;
use App\Models\Organization;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $departmentId = auth()->user()->department_id;
        
        // Aggressive caching - cache for 2 minutes, unique per department
        $cacheKey = 'dean.dashboard.dept.' . ($departmentId ?? 0);
        
        $data = cache()->remember($cacheKey, 120, function () use ($departmentId) {
            // Get all organization IDs belonging to the Dean's department
            $orgIds = Organization::where('department_id', $departmentId)->pluck('id');

            $stats = [
                'total_students' => User::where('role', 'student')->where('department_id', $departmentId)->count(),
                'total_soccs_admins' => User::where('role', 'admin')->where('department_id', $departmentId)->count(),
                'total_announcements' => Announcement::whereIn('organization_id', $orgIds)->count(),
                'total_events' => Event::whereIn('organization_id', $orgIds)->count(),
                'pending_approvals' => Event::whereIn('organization_id', $orgIds)->where('approval_status', 'pending')->count(),
                'total_complaints' => Complaint::whereIn('organization_id', $orgIds)->count(),
            ];

            $recentAnnouncements = Announcement::with('user:id,first_name,last_name,email')
                ->whereIn('organization_id', $orgIds)
                ->select('id', 'user_id', 'title', 'content', 'type', 'created_at')
                ->published()
                ->recent()
                ->take(5)
                ->get();
            $upcomingEvents = Event::with('user:id,first_name,last_name,email')
                ->whereIn('organization_id', $orgIds)
                ->select('id', 'user_id', 'title', 'description', 'start_date', 'end_date', 'venue', 'created_at')
                ->published()
                ->upcoming()
                ->take(5)
                ->get();
            $pendingComplaints = Complaint::with('user:id,first_name,last_name,email')
                ->whereIn('organization_id', $orgIds)
                ->select('id', 'user_id', 'type', 'subject', 'message', 'status', 'created_at')
                ->where('status', 'pending')
                ->latest()
                ->take(5)
                ->get();

            return compact('stats', 'recentAnnouncements', 'upcomingEvents', 'pendingComplaints');
        });

        return response()
            ->view('dean.dashboard', $data)
            ->header('Cache-Control', 'private, max-age=120');
    }
}
