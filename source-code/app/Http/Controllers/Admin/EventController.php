<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\EventSubmittedToOsas;
use App\Models\Event;
use App\Models\Organization;
use App\Models\User;
use App\Services\ContentCache;
use App\Services\MembershipFeeService;
use App\Services\OsasProposalQueueService;
use App\Services\WeatherSuggestionService;
use App\Notifications\EventRescheduleRequestedNotification;
use App\Notifications\EventSubmittedToOsasNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

class EventController extends Controller
{
    public function index(ContentCache $contentCache)
    {
        $user = auth()->user();
        $currentOrgId = session('current_organization_id');
        $currentOrg = $currentOrgId ? \App\Models\Organization::find($currentOrgId) : null;
        $orgAcronym = $currentOrg?->org_acronym ?? 'your organization';

        // Get user's role in current organization
        $userRole = null;
        if ($currentOrgId) {
            $membership = $user->organizations()->where('organizations.id', $currentOrgId)->first();
            $userRole = $membership ? $membership->pivot->role : null;
        }

        $page = request()->integer('page', 1);
        $events = $contentCache->rememberEventIndex($currentOrgId, $userRole, $page, function () use ($userRole, $currentOrgId, $page) {
            // Content Aggregation Logic for Mother Org Students
            if ($userRole === 'Student' && is_mother_org()) {
                // Get current organization
                $currentOrg = \App\Models\Organization::find($currentOrgId);

                if ($currentOrg && $currentOrg->department_id) {
                    // Get all Sub-Orgs with the same department_id
                    $subOrgs = \App\Models\Organization::where('department_id', $currentOrg->department_id)
                        ->where('type', 'Sub-Org')
                        ->pluck('id')
                        ->toArray();

                    // Combine Mother Org + Sub-Orgs
                    $organizationIds = array_merge([$currentOrgId], $subOrgs);

                    // Query events from Mother Org and all related Sub-Orgs
                    return Event::with(['user', 'organization'])
                        ->whereIn('organization_id', $organizationIds)
                        ->published()
                        ->orderBy('start_date', 'desc')
                        ->paginate(12, ['*'], 'page', $page);
                }

                // Fallback to current org only
                return Event::with('user')
                    ->published()
                    ->latest()
                    ->paginate(12, ['*'], 'page', $page);
            }

            // For non-students or Sub-Org users, show only current org events
            return Event::with('user')
                ->published()
                ->latest()
                ->paginate(12, ['*'], 'page', $page);
        });

        return view('student.events.index', compact('events', 'orgAcronym'));
    }

    public function calendar(ContentCache $contentCache)
    {
        $currentOrgId = session('current_organization_id');
        $currentOrg = $currentOrgId ? \App\Models\Organization::find($currentOrgId) : null;
        $orgAcronym = $currentOrg?->org_acronym ?? 'your organization';

        $events = $contentCache->rememberEventCalendar(function () {
            return Event::published()
                ->select('id', 'title', 'start_date', 'end_date')
                ->get()
                ->map(function ($event) {
                    return [
                        'id' => $event->id,
                        'title' => $event->title,
                        'start' => $event->start_date->format('Y-m-d H:i:s'),
                        'end' => $event->end_date->format('Y-m-d H:i:s'),
                        'color' => '#8238B3',
                        'url' => route('events.show', $event),
                    ];
                });
        });

        return view('student.events.calendar', compact('events', 'orgAcronym'));
    }

    public function show(Event $studentEvent, ContentCache $contentCache, MembershipFeeService $membershipFees)
    {
        $event = $studentEvent;
        $event = $contentCache->rememberEvent(
            $event->getKey(),
            fn () => Event::with('user')->findOrFail($event->getKey())
        );

        $membershipEligibility = null;
        $currentOrganizationId = session('current_organization_id');
        $motherOrganization = $currentOrganizationId ? Organization::find($currentOrganizationId) : null;

        if (auth()->user()?->isEffectivelyStudent()
            && $motherOrganization?->isMotherOrg()
            && $motherOrganization->canBeUsed()) {
            $membershipFees->ensureForOrganization($motherOrganization);
            $membershipEligibility = $membershipFees->eligibilityFor(auth()->user(), $motherOrganization);
        }

        return view('student.events.show', compact('event', 'membershipEligibility'));
    }

    public function register(Request $request, Event $studentEvent, MembershipFeeService $membershipFees)
    {
        $event = $studentEvent;
        try {
            $user = $request->user();
            $currentOrganizationId = session('current_organization_id');
            $motherOrganization = $currentOrganizationId ? Organization::find($currentOrganizationId) : null;
            $hasActiveMembership = $motherOrganization && $user?->organizations()
                ->whereKey($motherOrganization->getKey())
                ->wherePivot('membership_status', 'Active')
                ->exists();

            if (! $user?->isEffectivelyStudent()
                || ! $motherOrganization?->isMotherOrg()
                || ! $motherOrganization->canBeUsed()
                || ! $hasActiveMembership) {
                return $this->registrationFailure(
                    $request,
                    $event,
                    'membership_fee_context_required',
                    'Switch to your active Mother Organization student page before registering for an event.',
                    403,
                );
            }

            $eventOrganization = $event->organization_id ? Organization::find($event->organization_id) : null;
            $eventIsVisibleToMotherOrganization = $eventOrganization
                && ($eventOrganization->getKey() === $motherOrganization->getKey()
                    || ($eventOrganization->isSubOrg()
                        && $motherOrganization->department_id
                        && $eventOrganization->department_id === $motherOrganization->department_id));

            if (! $eventIsVisibleToMotherOrganization) {
                return $this->registrationFailure(
                    $request,
                    $event,
                    'event_not_available',
                    'This event is not available in your Mother Organization student page.',
                    403,
                );
            }

            $membershipFees->ensureForOrganization($motherOrganization);
            $eligibility = $membershipFees->eligibilityFor($user, $motherOrganization);

            if (! $eligibility['eligible']) {
                $details = match ($eligibility['status']) {
                    'pending' => [
                        'membership_fee_pending',
                        'Your Membership Fee payment is still awaiting verification. You can register after it is approved.',
                        403,
                    ],
                    'rejected' => [
                        'membership_fee_rejected',
                        'Your Membership Fee payment was rejected. Please resubmit it from the Payments page.',
                        403,
                    ],
                    'unconfigured' => [
                        'membership_fee_unconfigured',
                        'Membership Fee payment details are still being set up. Please contact your organization officer.',
                        409,
                    ],
                    'unavailable' => [
                        'membership_fee_unavailable',
                        'The current Membership Fee is unavailable. Please contact your organization officer.',
                        409,
                    ],
                    default => [
                        'membership_fee_unpaid',
                        'Pay your current Membership Fee before registering for this event.',
                        403,
                    ],
                };

                return $this->registrationFailure(
                    $request,
                    $event,
                    $details[0],
                    $details[1],
                    $details[2],
                    $eligibility['payments_url'],
                );
            }

            // Check if already registered
            $existingRegistration = \App\Models\EventRegistration::where('event_id', $event->id)
                ->where('user_id', $user->getKey())
                ->first();

            if ($existingRegistration) {
                return $this->registrationFailure(
                    $request,
                    $event,
                    'already_registered',
                    'You are already registered for this event.',
                    409,
                );
            }

            // Create registration
            $registration = \App\Models\EventRegistration::create([
                'event_id' => $event->id,
                'user_id' => $user->getKey(),
                'status' => 'registered',
                'registered_at' => now(),
            ]);

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Successfully registered for the event!',
                    'registration' => [
                        'id' => $registration->getKey(),
                        'status' => $registration->status,
                        'registered_at' => $registration->registered_at?->toIso8601String(),
                    ],
                ], 201);
            }

            return redirect()->route('events.show', $event)
                ->with('success', 'Successfully registered for the event!');
        } catch (\Throwable $e) {
            \Log::error('Failed to register for event.', [
                'event_id' => $event->getKey(),
                'user_id' => $request->user()?->getKey(),
                'exception' => $e->getMessage(),
            ]);

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'code' => 'registration_failed',
                    'message' => 'Unable to register for this event right now. Please try again.',
                ], 500);
            }

            return redirect()->route('events.show', $event)
                ->with('error', 'Failed to register for the event. Please try again.');
        }
    }

    private function registrationFailure(
        Request $request,
        Event $event,
        string $code,
        string $message,
        int $status,
        ?string $paymentsUrl = null,
    ) {
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => false,
                'code' => $code,
                'message' => $message,
                'payments_url' => $paymentsUrl,
            ], $status);
        }

        return redirect()->route('events.show', $event)->with('error', $message);
    }

    public function create()
    {
        return view('admin.events.create');
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'required|string',
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
                'venue' => ['required', 'string', function ($attribute, $value, $fail) use ($request) {
                    // Check across ALL organizations since venues/schedule are shared campus-wide
                    $conflict = Event::allOrganizations()
                        ->where('start_date', '<', $request->end_date)
                        ->where('end_date', '>', $request->start_date)
                        ->where('venue', $value)
                        ->exists();

                    if ($conflict) {
                        $fail('This venue is already booked for the selected time period.');
                    }
                }],
                'category' => 'required|string|in:general_on,general_off,outreach_on,outreach_off',
                'planned_budget' => 'required|numeric|min:0',
                'request_letter' => 'required|file|mimes:pdf,doc,docx|max:10240',
                'images' => 'required|array|min:1',
                'images.*' => 'required|image|mimes:jpeg,png,jpg,gif|max:5120',
            ]);

            $validated['user_id'] = auth()->user()->id;
            $validated['approval_status'] = 'pending';
            $validated['is_published'] = \DB::raw('FALSE');
            $validated['status'] = 'draft';
            $validated['organization_id'] = session('current_organization_id');

            // Handle multiple image uploads
            if ($request->hasFile('images')) {
                $imagePaths = [];
                foreach ($request->file('images') as $image) {
                    $path = $image->store('events', storage_disk());
                    $imagePaths[] = $path;
                }
                $validated['images'] = $imagePaths;
            }

            $event = Event::create($validated);


            // Create EventProposal with request letter
            if ($request->hasFile('request_letter')) {
                $path = $request->file('request_letter')->store('proposals/letters', storage_disk());
                $event->proposal()->create([
                    'organization_id' => session('current_organization_id'),
                    'submitted_by' => auth()->id(),
                    'request_letter' => $path,
                    'status' => 'pending',
                ]);
            }

            // Load user relationship
            $event->load('user');

            // FYI-only: notify the dean owning the venue's premises (if any).
            // Never breaks proposal creation on failure.
            \App\Services\VenuePremisesNotifier::notifyOwnerDeans($event, 'proposed');

            // Broadcast real-time update
            safe_broadcast(new \App\Events\EventCreated($event));

            // Return JSON for AJAX requests
            if ($request->expectsJson() || $request->ajax()) {
                $eventData = [
                    'id' => $event->id,
                    'title' => $event->title,
                    'description' => $event->description,
                    'start_date' => $event->start_date->toISOString(),
                    'end_date' => $event->end_date->toISOString(),
                    'venue' => $event->venue,
                    'planned_budget' => $event->planned_budget,
                    'category' => $event->category,
                    'category_label' => Event::CATEGORIES[$event->category] ?? $event->category,
                    'approval_status' => $event->approval_status,
                    'status' => $event->status,
                    'images' => $event->images ?? [],
                    'images_urls' => $event->images ? array_map(function($path) {
                        return storage_url($path);
                    }, $event->images) : [],
                    'created_at' => $event->created_at->toISOString(),
                    'user' => [
                        'id' => $event->user->id,
                        'name' => $event->user->name,
                    ],
                    'isNew' => true,
                ];

                return response()->json([
                    'success' => true,
                    'message' => 'Event request submitted successfully. Waiting for Dean approval.',
                    'event' => $eventData
                ]);
            }

            return redirect()->route('admin.events.index')
                ->with('success', 'Event request submitted successfully. Waiting for Dean approval.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()
                ->withErrors($e->errors())
                ->withInput()
                ->with('error', 'Please check the form for errors.');
        } catch (\Exception $e) {
            return redirect()->route('admin.events.index')
                ->with('error', 'Failed to submit event request. Please try again.');
        }
    }

    public function edit(Event $event)
    {
        return view('admin.events.edit', compact('event'));
    }

    public function update(Request $request, Event $event)
    {
        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'required|string',
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
                'venue' => ['required', 'string', function ($attribute, $value, $fail) use ($request, $event) {
                    // Check across ALL organizations since venues/schedule are shared campus-wide
                    $conflict = Event::allOrganizations()
                        ->where('start_date', '<', $request->end_date)
                        ->where('end_date', '>', $request->start_date)
                        ->where('venue', $value)
                        ->where('id', '!=', $event->id)
                        ->exists();

                    if ($conflict) {
                        $fail('This venue is already booked for the selected time period.');
                    }
                }],
                'category' => 'required|string|in:general_on,general_off,outreach_on,outreach_off',
                'planned_budget' => 'nullable|numeric|min:0',
                'request_letter' => 'nullable|file|mimes:pdf,doc,docx|max:10240',
                'images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120',
                'existing_images' => 'nullable|array',
                'is_published' => 'boolean',
                'scheduled_at' => 'nullable|date',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()
                ->withErrors($e->errors())
                ->withInput()
                ->with('error', 'Please check the form for errors.');
        }

        // Once the Dean has approved an event, the schedule/venue/budget are
        // locked and can only be changed after OSAS approves a reschedule
        // request. This is enforced server-side too, not just in the UI.
        $rescheduleApproved = $event->approval_status === 'approved'
            && $event->proposal
            && $event->proposal->reschedule_status === 'approved';

        if ($event->approval_status === 'approved' && !$rescheduleApproved) {
            $validated['start_date'] = $event->start_date;
            $validated['end_date'] = $event->end_date;
            $validated['venue'] = $event->venue;
            $validated['category'] = $event->category;
            $validated['planned_budget'] = $event->planned_budget;
        }

        // Handle hide/unhide functionality
        if ($request->has('hide_event') && $request->hide_event == '1') {
            $validated['status'] = 'hidden';
        } elseif ($request->has('unhide_event') && $request->unhide_event == '1') {
            $validated['status'] = 'active';
        }

        // Handle images only if images are being uploaded
        $newImages = [];
        if ($request->hasFile('images') || $request->has('existing_images')) {
            $existingImages = $request->input('existing_images', []);

            // Upload new images
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $image) {
                    $path = $image->store('events', storage_disk());
                    $newImages[] = $path;
                }
            }

            // Combine existing and new images
            $allImages = array_merge($existingImages, $newImages);
            $validated['images'] = $allImages;

            // Delete removed images from storage
            if ($event->images) {
                $removedImages = array_diff($event->images, $existingImages);
                foreach ($removedImages as $removedImage) {
                    Storage::disk(storage_disk())->delete($removedImage);
                }
            }
        } else {
            // Keep existing images if no image changes
            $validated['images'] = $event->images;
        }

        // Handle request letter replacement
        if ($request->hasFile('request_letter')) {
            // Delete old request letter if exists
            if ($event->proposal && $event->proposal->request_letter) {
                Storage::disk(storage_disk())->delete($event->proposal->request_letter);
            }
            $path = $request->file('request_letter')->store('proposals/letters', storage_disk());
            $event->proposal()->updateOrCreate(
                ['event_id' => $event->id],
                [
                    'request_letter' => $path,
                    'submitted_by' => auth()->id(),
                ]
            );
        }

        // For AJAX requests, skip the change detection and update directly
        if ($request->expectsJson()) {
            $oldVenue = $event->venue;
            $event->update($validated);


            // The reschedule window has been used - lock the schedule again.
            if ($rescheduleApproved) {
                $event->proposal->update(['reschedule_status' => 'completed']);
            }

            // FYI-only: venue changed (e.g. after approved reschedule) — inform premise owner.
            if (isset($validated['venue']) && $validated['venue'] !== $oldVenue) {
                \App\Services\VenuePremisesNotifier::notifyOwnerDeans($event->refresh(), 'proposed');
            }

            return response()->json([
                'success' => true,
                'message' => 'Event updated successfully.'
            ]);
        }

        // Check if any actual changes were made (for regular form submissions)
        $hasChanges = false;

        // Check text field changes
        if ($event->title !== $validated['title'] || 
            $event->description !== $validated['description'] ||
            $event->venue !== $validated['venue'] ||
            ($event->category ?? null) !== ($validated['category'] ?? null)) {
            $hasChanges = true;
        }

        // Check datetime field changes using Carbon comparison
        $newStartDate = \Carbon\Carbon::parse($validated['start_date']);
        $newEndDate = \Carbon\Carbon::parse($validated['end_date']);

        if (!$event->start_date->eq($newStartDate) || !$event->end_date->eq($newEndDate)) {
            $hasChanges = true;
        }

        // Check scheduled_at changes (safe comparison with Carbon)
        if (isset($validated['scheduled_at']) && $validated['scheduled_at']) {
            $newScheduled = \Carbon\Carbon::parse($validated['scheduled_at']);
            if (!$event->scheduled_at || !$event->scheduled_at->eq($newScheduled)) {
                $hasChanges = true;
            }
        } elseif ($event->scheduled_at) {
            $hasChanges = true;
        }

        // Check planned_budget changes
        if (isset($validated['planned_budget']) && $event->planned_budget != $validated['planned_budget']) {
            $hasChanges = true;
        }

        // Check status changes
        if (isset($validated['status']) && $event->status !== $validated['status']) {
            $hasChanges = true;
        }

        // Check if images changed
        if (count($newImages) > 0) {
            $hasChanges = true;
        }

        $oldImages = $event->images ?? [];
        $newImagesArray = $validated['images'] ?? [];

        if (count($oldImages) !== count($newImagesArray)) {
            $hasChanges = true;
        }

        if (!$hasChanges) {
            foreach ($oldImages as $oldImage) {
                if (!in_array($oldImage, $newImagesArray)) {
                    $hasChanges = true;
                    break;
                }
            }
        }

        // If no changes were made, return with info message
        if (!$hasChanges) {
            return redirect()->route('admin.events.index')
                ->with('info', 'No changes were made to the event.');
        }

        $oldVenue = $event->venue;
        $event->update($validated);

        // FYI-only: venue changed — inform premise owner of the new venue.
        if (isset($validated['venue']) && $validated['venue'] !== $oldVenue) {
            \App\Services\VenuePremisesNotifier::notifyOwnerDeans($event->refresh(), 'proposed');
        }

        // The reschedule window has been used - lock the schedule again.
        if ($rescheduleApproved) {
            $event->proposal->update(['reschedule_status' => 'completed']);
        }

        // Broadcast real-time update
        safe_broadcast(new \App\Events\EventUpdated($event));



        return redirect()->route('admin.events.index')
            ->with('success', 'Event updated successfully.');
    }

    public function destroy(Event $event)
    {
        try {
            $eventId = $event->id;

            // Delete all images from storage
            if ($event->images) {
                foreach ($event->images as $image) {
                    Storage::disk(storage_disk())->delete($image);
                }
            }

            $event->delete();

            // Broadcast real-time update
            safe_broadcast(new \App\Events\EventDeleted($eventId));



            return redirect()->route('admin.events.index')
                ->with('success', 'Event deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->route('admin.events.index')
                ->with('error', 'Failed to delete event. Please try again.');
        }
    }

    public function adminIndex()
    {
        $currentOrgId = session('current_organization_id');
        $events = Event::where('organization_id', $currentOrgId)
            ->with(['user', 'proposal'])
            ->withCount('registrations')
            ->latest()
            ->paginate(15);

        // Transform images to full URLs for edit modal
        $events->getCollection()->transform(function ($event) {
            if ($event->images) {
                $event->images_urls = \App\Helpers\StorageHelper::transformImages($event->images);
            }
            return $event;
        });

        $venues = Event::VENUES;
        $categories = Event::CATEGORIES;

        return view('admin.events.index', compact('events', 'venues', 'categories'));
    }

    public function submitToOsas(Event $event, OsasProposalQueueService $proposalQueue)
    {
        try {
            if ($event->approval_status !== 'approved') {
                if (request()->expectsJson()) {
                    return response()->json(['success' => false, 'message' => 'Event must be approved by dean first.'], 400);
                }
                return redirect()->route('admin.events.index')->with('error', 'Event must be approved by dean first.');
            }

            if ($event->osas_status === 'pending') {
                if (request()->expectsJson()) {
                    return response()->json(['success' => false, 'message' => 'Event already submitted to OSAS.'], 400);
                }
                return redirect()->route('admin.events.index')->with('error', 'Event already submitted to OSAS.');
            }

            $event->update(['osas_status' => 'pending']);

            safe_broadcast(new \App\Events\EventUpdated($event));



            // Notify all OSAS admins via email + database notification
            $osasAdmins = User::where('role', 'osas_admin')->get();
            foreach ($osasAdmins as $admin) {
                Mail::to($admin->email)->queue(new EventSubmittedToOsas($event));
                $admin->notify(new EventSubmittedToOsasNotification($event));
            }

            $proposalQueue->broadcastUpdated();

            if (request()->expectsJson()) {
                return response()->json(['success' => true, 'message' => 'Event submitted to OSAS for review.']);
            }

            return redirect()->route('admin.events.index')->with('success', 'Event submitted to OSAS for review.');
        } catch (\Exception $e) {
            if (request()->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Failed to submit event. Please try again.'], 500);
            }
            return redirect()->route('admin.events.index')->with('error', 'Failed to submit event. Please try again.');
        }
    }

    public function postEvent(Event $event)
    {
        try {
            if ($event->approval_status !== 'approved') {
                if (request()->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Only approved events can be posted.'
                    ], 400);
                }
                return redirect()->route('admin.events.index')
                    ->with('error', 'Only approved events can be posted.');
            }

            if ($event->osas_status !== 'approved') {
                if (request()->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Event must be approved by OSAS before posting.'
                    ], 400);
                }
                return redirect()->route('admin.events.index')
                    ->with('error', 'Event must be approved by OSAS before posting.');
            }

            if ($event->is_published) {
                if (request()->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'This event has already been posted.'
                    ], 400);
                }
                return redirect()->route('admin.events.index')
                    ->with('error', 'This event has already been posted.');
            }

            $event->update([
                'is_published' => \DB::raw('TRUE'),
                'status' => 'active',
            ]);


            // Broadcast real-time update when event is published
            safe_broadcast(new \App\Events\EventCreated($event));

            if (request()->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Event posted successfully!'
                ]);
            }

            return redirect()->route('admin.events.index')
                ->with('success', 'Event posted successfully!');
        } catch (\Exception $e) {
            if (request()->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to post event. Please try again.'
                ], 500);
            }
            return redirect()->route('admin.events.index')
                ->with('error', 'Failed to post event. Please try again.');
        }
    }

    // Get event as JSON for real-time updates
    public function getEventJson(Event $studentEvent)
    {
        $event = $studentEvent;
        // Transform images to full URLs
        if ($event->images) {
            $event->images_urls = \App\Helpers\StorageHelper::transformImages($event->images);
        }

        return response()->json([
            'success' => true,
            'event' => $event
        ]);
    }

    public function checkVenueAvailability(Request $request)
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $excludeEventId = $request->input('exclude_event_id');

        // Check across ALL organizations since venues/schedule are shared campus-wide
        $conflictingEvents = Event::allOrganizations()
            ->with('organization')
            ->where('start_date', '<', $endDate)
            ->where('end_date', '>', $startDate)
            ->when($excludeEventId, function ($q) use ($excludeEventId) {
                $q->where('id', '!=', $excludeEventId);
            })
            ->get(['id', 'title', 'start_date', 'end_date', 'venue', 'organization_id']);

        $conflictingVenues = $conflictingEvents->pluck('venue')->unique()->toArray();
        $allVenues = Event::VENUES;
        $availableVenues = array_values(array_diff($allVenues, $conflictingVenues));

        $conflictingEventsData = $conflictingEvents->map(function ($event) {
            return [
                'id' => $event->id,
                'title' => $event->title,
                'start_date' => $event->start_date->format('Y-m-d H:i'),
                'end_date' => $event->end_date->format('Y-m-d H:i'),
                'venue' => $event->venue,
                'organization' => $event->organization ? ($event->organization->org_acronym ?? $event->organization->name) : 'N/A',
            ];
        });

        return response()->json([
            'available_venues' => $availableVenues,
            'unavailable_venues' => $conflictingVenues,
            'conflicting_events' => $conflictingEventsData,
        ]);
    }

    /**
     * Smart scheduler: weather-aware day suggestions inside one month.
     * Read-only — no database writes, safe to call repeatedly.
     */
    public function suggestEventDates(Request $request, WeatherSuggestionService $weather)
    {
        $validated = $request->validate([
            'month' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'venue' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'location_label' => 'nullable|string|max:255',
            'exclude_event_id' => 'nullable|integer|exists:events,id',
            'start_time' => ['nullable', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'end_time' => ['nullable', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
        ]);

        [$year, $month] = array_map('intval', explode('-', $validated['month']));

        $result = $weather->suggest(
            $year,
            $month,
            $validated['venue'] ?? null,
            isset($validated['latitude']) ? (float) $validated['latitude'] : null,
            isset($validated['longitude']) ? (float) $validated['longitude'] : null,
            $validated['location_label'] ?? null,
            $validated['exclude_event_id'] ?? null,
            $validated['start_time'] ?? '09:00',
            $validated['end_time'] ?? '11:00',
        );

        return response()->json(['success' => true, ...$result]);
    }

    /**
     * Geocode proxy for "Outside of School" locations (Open-Meteo, free).
     */
    public function geocodeLocation(Request $request, WeatherSuggestionService $weather)
    {
        $validated = $request->validate([
            'q' => 'required|string|min:3|max:120',
        ]);

        return response()->json([
            'success' => true,
            'results' => $weather->geocode($validated['q']),
        ]);
    }

    public function resubmitEvent(Event $event, OsasProposalQueueService $proposalQueue)
    {
        try {
            $deanRejected = $event->approval_status === 'rejected';
            $osasRejected = $event->approval_status === 'approved' && $event->osas_status === 'rejected';

            if (!$deanRejected && !$osasRejected) {
                if (request()->expectsJson()) {
                    return response()->json(['success' => false, 'message' => 'Only rejected events can be resubmitted.'], 400);
                }
                return redirect()->route('admin.events.index')->with('error', 'Only rejected events can be resubmitted.');
            }

            if ($deanRejected) {
                // Was rejected by the Dean - restart the approval process from the Dean.
                $event->update([
                    'approval_status' => 'pending',
                    'approved_by' => null,
                    'approved_at' => null,
                ]);

                if ($event->proposal) {
                    $event->proposal->update([
                        'status' => 'pending',
                        'rejection_reason' => null,
                        'reviewed_by' => null,
                        'reviewed_at' => null,
                    ]);
                }
            } else {
                // Was already approved by the Dean and only rejected by OSAS -
                // send it straight back to OSAS instead of re-approving with the Dean.
                $event->update([
                    'osas_status' => 'pending',
                ]);

                if ($event->proposal) {
                    $event->proposal->update([
                        'osas_rejection_reason' => null,
                        'osas_reviewed_by' => null,
                        'osas_reviewed_at' => null,
                    ]);
                }

                // Notify OSAS admins again, same as a fresh submission.
                $osasAdmins = User::where('role', 'osas_admin')->get();
                foreach ($osasAdmins as $admin) {
                    Mail::to($admin->email)->queue(new EventSubmittedToOsas($event));
                    $admin->notify(new EventSubmittedToOsasNotification($event));
                }

                $proposalQueue->broadcastUpdated();
            }

            safe_broadcast(new \App\Events\EventUpdated($event));


            $message = $deanRejected ? 'Event resubmitted for review.' : 'Event resubmitted to OSAS for review.';

            if (request()->expectsJson()) {
                return response()->json(['success' => true, 'message' => $message]);
            }

            return redirect()->route('admin.events.index')->with('success', $message);
        } catch (\Exception $e) {
            if (request()->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Failed to resubmit event. Please try again.'], 500);
            }
            return redirect()->route('admin.events.index')->with('error', 'Failed to resubmit event. Please try again.');
        }
    }

    /**
     * Request a reschedule for an event that has already been approved by
     * OSAS (e.g. due to a typhoon or venue issue). This only records the
     * request and reason - the actual date/venue/etc. can only be changed
     * once OSAS approves the request.
     */
    public function requestReschedule(Request $request, Event $event)
    {
        try {
            if ($event->approval_status !== 'approved' || $event->osas_status !== 'approved') {
                if (request()->expectsJson()) {
                    return response()->json(['success' => false, 'message' => 'Only events already approved by OSAS can be rescheduled.'], 400);
                }
                return redirect()->route('admin.events.index')->with('error', 'Only events already approved by OSAS can be rescheduled.');
            }

            if ($event->end_date->isPast()) {
                if (request()->expectsJson()) {
                    return response()->json(['success' => false, 'message' => 'This event has already ended and cannot be rescheduled.'], 400);
                }
                return redirect()->route('admin.events.index')->with('error', 'This event has already ended and cannot be rescheduled.');
            }

            if ($event->proposal && $event->proposal->reschedule_status === 'pending') {
                if (request()->expectsJson()) {
                    return response()->json(['success' => false, 'message' => 'A reschedule request is already pending for this event.'], 400);
                }
                return redirect()->route('admin.events.index')->with('error', 'A reschedule request is already pending for this event.');
            }

            $validated = $request->validate([
                'reschedule_reason' => 'required|string|max:1000',
            ]);

            $event->proposal()->updateOrCreate(
                ['event_id' => $event->id],
                [
                    'reschedule_status' => 'pending',
                    'reschedule_reason' => $validated['reschedule_reason'],
                    'reschedule_requested_by' => auth()->id(),
                    'reschedule_requested_at' => now(),
                    'reschedule_reviewed_by' => null,
                    'reschedule_reviewed_at' => null,
                    'reschedule_rejection_reason' => null,
                ]
            );

            // Notify OSAS admins about the reschedule request.
            $osasAdmins = User::where('role', 'osas_admin')->get();
            Notification::send($osasAdmins, new EventRescheduleRequestedNotification($event, $validated['reschedule_reason']));

            safe_broadcast(new \App\Events\EventUpdated($event));


            $message = 'Reschedule request sent to OSAS for approval.';

            if (request()->expectsJson()) {
                return response()->json(['success' => true, 'message' => $message]);
            }

            return redirect()->route('admin.events.index')->with('success', $message);
        } catch (\Illuminate\Validation\ValidationException $e) {
            if (request()->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->validator->errors()->first('reschedule_reason') ?? 'Please provide a reason for the reschedule request.'
                ], 422);
            }
            throw $e;
        } catch (\Exception $e) {
            if (request()->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Failed to submit reschedule request. Please try again.'], 500);
            }
            return redirect()->route('admin.events.index')->with('error', 'Failed to submit reschedule request. Please try again.');
        }
    }

    // Guest methods
    public function guestIndex()
    {
        $events = Event::published()->latest()->paginate(12);
        return view('guest.events.index', compact('events'));
    }

    public function guestShow(Event $event)
    {
        return view('guest.events.show', compact('event'));
    }
}
