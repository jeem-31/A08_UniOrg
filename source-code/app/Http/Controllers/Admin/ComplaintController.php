<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ComplaintController extends Controller
{
    public function index()
    {
        // Get all complaints by current user (both anonymous and non-anonymous)
        // Since we now store user_id even for anonymous submissions, we can simply query by user_id
        $complaints = Complaint::where('user_id', auth()->user()->id)
            ->latest()
            ->paginate(10);
        
        return view('student.complaints.index', compact('complaints'));
    }

    public function create()
    {
        return view('student.complaints.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|in:complaint,suggestion,inquiry,feedback',
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
            'is_anonymous' => 'sometimes|boolean',
        ]);

        // Check if this is an anonymous submission
        $isAnonymous = $request->has('is_anonymous') && $request->input('is_anonymous');

        // Get organization_id
        $user = auth()->user();
        $organizationId = $user->organization_id ?? ($user->organizations->isNotEmpty() ? $user->organizations->first()->id : 1);

        // Use Query Builder with DB::raw() for boolean to bypass Eloquent casting
        $complaintId = DB::table('complaints')->insertGetId([
            'type' => $validated['type'],
            'subject' => $validated['subject'],
            'message' => $validated['message'],
            'user_id' => $user->id,
            'is_anonymous' => DB::raw($isAnonymous ? 'true' : 'false'),
            'organization_id' => $organizationId,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Get the created complaint for broadcasting
        $complaint = Complaint::find($complaintId);

        // Debug: Log before broadcasting
        \Log::info('🔵 About to broadcast ComplaintCreated', [
            'complaint_id' => $complaint->id,
            'organization_id' => $complaint->organization_id,
            'user_id' => $complaint->user_id,
            'channel' => 'complaints',
            'event_name' => 'complaint-created',
        ]);

        // Broadcast real-time update (synchronous - happens immediately)
        $event = new \App\Events\ComplaintCreated($complaint);
        broadcast($event)->toOthers();

        // Debug: Log after broadcasting
        \Log::info('🔵 ComplaintCreated broadcasted successfully', [
            'broadcast_channels' => $event->broadcastOn(),
            'broadcast_as' => $event->broadcastAs(),
            'broadcast_with' => $event->broadcastWith(),
        ]);

        // Return JSON for AJAX or redirect for regular form
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Your submission has been received. Thank you!',
                'complaint' => $complaint
            ]);
        }

        return redirect()->route('complaints.index')
            ->with('success', 'Your submission has been received. Thank you!');
    }

    public function show(Complaint $complaint)
    {
        if ($complaint->user_id !== auth()->id() && !auth()->user()->isAdmin()) {
            abort(403);
        }

        return view('student.complaints.show', compact('complaint'));
    }

    public function destroy(Request $request, Complaint $complaint)
    {
        abort_unless($complaint->user_id === auth()->id(), 403);

        $complaintId = $complaint->id;
        $complaint->delete();

        broadcast(new \App\Events\ComplaintDeleted($complaintId))->toOthers();

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Submission deleted successfully.',
                'complaint_id' => $complaintId,
            ]);
        }

        return redirect()->route('complaints.index')
            ->with('success', 'Submission deleted successfully.');
    }

    // Admin methods
    public function adminIndex(Request $request)
    {
        $currentOrgId = session('current_organization_id');
        // Fetch all complaints for current organization without caching for real-time updates
        $complaints = Complaint::where('organization_id', $currentOrgId)
            ->with('user:id,first_name,middle_name,last_name,suffix,email,student_id')
            ->select('id', 'user_id', 'type', 'subject', 'message', 'status', 'is_anonymous', 'admin_response', 'responded_by', 'responded_at', 'created_at', 'updated_at', 'organization_id')
            ->latest()
            ->get();

        return view('admin.complaints.index', compact('complaints'));
    }

    public function adminShow(Complaint $complaint)
    {
        return view('admin.complaints.show', compact('complaint'));
    }

    public function respond(Request $request, Complaint $complaint)
    {
        $validated = $request->validate([
            'admin_response' => 'required|string',
            'status' => 'required|in:pending,reviewed,resolved',
        ]);

        // Use the actual user ID from the user object, not auth()->id()
        $validated['responded_by'] = auth()->user()->id;
        $validated['responded_at'] = now();

        $complaint->update($validated);

        // Clear cache
        cache()->flush();

        // Debug: Log before broadcasting
        \Log::info('🟢 About to broadcast ComplaintUpdated', [
            'complaint_id' => $complaint->id,
            'organization_id' => $complaint->organization_id,
            'user_id' => $complaint->user_id,
            'channel' => 'complaints',
            'event_name' => 'complaint-updated',
        ]);

        // Broadcast real-time update (synchronous - happens immediately)
        $event = new \App\Events\ComplaintUpdated($complaint);
        broadcast($event)->toOthers();

        // Debug: Log after broadcasting
        \Log::info('🟢 ComplaintUpdated broadcasted successfully', [
            'broadcast_channels' => $event->broadcastOn(),
            'broadcast_as' => $event->broadcastAs(),
            'broadcast_with' => $event->broadcastWith(),
        ]);

        // Send the notification (DB row + email) AFTER the response is sent
        // (non-blocking). The queued notification creates both the in-app
        // database notification and the email, so no manual insert is needed.
        if ($validated['status'] === 'resolved' && $complaint->user) {
            $user = $complaint->user;
            $complaintData = $complaint->fresh();
            $adminResponse = $validated['admin_response'];
            
            register_shutdown_function(function() use ($user, $complaintData, $adminResponse) {
                try {
                    // Send email notification in background
                    $user->notify(new \App\Notifications\ComplaintResolvedNotification(
                        $complaintData,
                        $adminResponse
                    ));
                } catch (\Exception $e) {
                    \Log::error('Failed to send complaint email: ' . $e->getMessage());
                }
            });
        }

        // Create success message based on status
        $statusMessages = [
            'pending' => 'Complaint status updated to Pending.',
            'reviewed' => 'Complaint marked as Reviewed.',
            'resolved' => 'Complaint resolved successfully! Student will receive email notification.',
        ];

        $message = $statusMessages[$validated['status']] ?? 'Response submitted successfully.';

        // Return JSON for AJAX requests
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'complaint' => $complaint->fresh()->load('user:id,first_name,middle_name,last_name,suffix,email,student_id')
            ]);
        }

        return redirect()->back()->with('success', $message);
    }
}
