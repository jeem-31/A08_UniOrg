<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Election;
use App\Models\Candidate;
use App\Models\Vote;
use App\Models\User;
use App\Models\ElectionOfficerTransition;
use App\Notifications\ElectionAnnouncedNotification;
use App\Notifications\VotingNowOpenNotification;
use App\Notifications\ElectionResultsPublishedNotification;
use App\Notifications\ElectionOfficerApprovalRequiredNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Notification;

class ElectionController extends Controller
{
    public function index()
    {
        $currentOrgId = session('current_organization_id');
        $elections = Election::where('organization_id', $currentOrgId)
            ->with(['candidates', 'votes'])
            ->whereRaw('is_posted = TRUE')
            ->orderByDesc('start_date')
            ->get();

        $now = now();
        $featuredElection = $elections->first(function (Election $election) use ($now) {
            return $election->is_active && $election->end_date->gte($now);
        }) ?? $elections->first();

        $historyElections = $elections->filter(function (Election $election) use ($featuredElection) {
            return !$featuredElection || $election->id !== $featuredElection->id;
        })->values();

        $resultsElection = $elections->first(fn (Election $election) => $election->results_published);
        $featuredHasVoted = $featuredElection
            ? $featuredElection->votes->contains('user_id', auth()->id())
            : false;

        return view('student.elections.index', [
            'elections' => $elections,
            'featuredElection' => $featuredElection,
            'historyElections' => $historyElections,
            'resultsElection' => $resultsElection,
            'featuredHasVoted' => $featuredHasVoted,
            'positions' => $this->positionDefinitions(),
        ]);
    }

    public function show(Election $election)
    {
        // Students may only view posted elections in their active organization.
        if (!$election->is_posted || $election->organization_id !== session('current_organization_id')) {
            abort(404);
        }

        $election->load('organization.settings');
        $candidates = $election->candidates()->with('election')->orderBy('position')->orderBy('name')->get();
        $hasVoted = Vote::where('election_id', $election->id)
            ->where('user_id', auth()->id())
            ->exists();

        $userVotes = $hasVoted
            ? Vote::where('election_id', $election->id)
                ->where('user_id', auth()->id())
                ->with('candidate')
                ->get()
            : collect();

        $positions = $this->positionDefinitions();
        $positionsWithCandidates = collect($positions)
            ->map(function (array $position, string $key) use ($candidates) {
                $positionCandidates = $candidates->where('position', $key)->values();

                return $positionCandidates->isNotEmpty()
                    ? ['key' => $key, 'data' => $position, 'candidates' => $positionCandidates]
                    : null;
            })
            ->filter()
            ->values();

        return view('student.elections.show', compact(
            'election',
            'candidates',
            'hasVoted',
            'userVotes',
            'positions',
            'positionsWithCandidates'
        ));
    }

    public function vote(Request $request, Election $election)
    {
        // Organization officers with legacy election visibility are
        // read-only; only students may vote (COMELEC manages the election).
        if (auth()->user()?->isAdmin()) {
            abort(403, 'This account has view-only access to Elections.');
        }

        if (!$election->is_posted || $election->organization_id !== session('current_organization_id')) {
            abort(404);
        }

        $validated = $request->validate([
            'votes' => 'required|array',
            'votes.*' => 'required|integer|exists:candidates,id',
        ]);

        $redirect = DB::transaction(function () use ($validated, $election) {
            $lockedElection = Election::whereKey($election->id)->lockForUpdate()->firstOrFail();
            $now = now();

            if (
                !$lockedElection->is_posted
                || !$lockedElection->is_active
                || $now->lt($lockedElection->start_date)
                || $now->gt($lockedElection->end_date)
            ) {
                return back()->with('error', 'This election is not currently active.');
            }

            $existingVote = Vote::where('election_id', $lockedElection->id)
                ->where('user_id', auth()->id())
                ->exists();

            if ($existingVote) {
                return back()->with('error', 'You have already voted in this election.');
            }

            $availablePositions = Candidate::where('election_id', $lockedElection->id)
                ->pluck('position')
                ->filter()
                ->unique()
                ->values();
            $submittedVotes = collect($validated['votes']);
            $submittedPositions = $submittedVotes->keys()->values();

            if (
                $availablePositions->sort()->values()->all() !== $submittedPositions->sort()->values()->all()
                || $submittedVotes->count() !== $availablePositions->count()
            ) {
                return back()->with('error', 'Please select one candidate for every available position.');
            }

            $candidates = Candidate::where('election_id', $lockedElection->id)
                ->whereIn('id', $submittedVotes->values()->all())
                ->get()
                ->keyBy('id');

            foreach ($submittedVotes as $position => $candidateId) {
                $candidate = $candidates->get((int) $candidateId);

                if (!$candidate || $candidate->position !== $position) {
                    return back()->with('error', 'One or more ballot selections are invalid. Please review your ballot.');
                }
            }

            foreach ($submittedVotes as $candidateId) {
                Vote::create([
                    'organization_id' => $lockedElection->organization_id,
                    'election_id' => $lockedElection->id,
                    'user_id' => auth()->id(),
                    'candidate_id' => $candidateId,
                ]);

                $candidates->get((int) $candidateId)->increment('vote_count');
            }

            safe_broadcast(new \App\Events\ElectionUpdated($lockedElection));

            return redirect()->route('elections.show', $lockedElection)
                ->with('success', 'Your ballot has been recorded successfully.');
        });

        return $redirect;
    }

    public function results(Election $election)
    {
        // Redirect to elections index - results are displayed there
        return redirect()->route('elections.index');
    }

    private function positionDefinitions(): array
    {
        return [
            'president' => ['name' => 'President', 'icon' => 'ph-crown'],
            'vice_president' => ['name' => 'Vice President', 'icon' => 'ph-user'],
            'treasurer' => ['name' => 'Treasurer', 'icon' => 'ph-coins'],
            'auditor' => ['name' => 'Auditor', 'icon' => 'ph-clipboard-text'],
            'public_information_officer' => ['name' => 'Public Information Officer', 'icon' => 'ph-megaphone'],
            'business_manager' => ['name' => 'Business Manager', 'icon' => 'ph-briefcase'],
            'event_coordinator' => ['name' => 'Event Coordinator', 'icon' => 'ph-calendar-blank'],
            '1st_year_rep' => ['name' => '1st Year Representative', 'icon' => 'ph-student'],
            '2nd_year_rep' => ['name' => '2nd Year Representative', 'icon' => 'ph-student'],
            '3rd_year_rep' => ['name' => '3rd Year Representative', 'icon' => 'ph-student'],
            '4th_year_rep' => ['name' => '4th Year Representative', 'icon' => 'ph-student'],
        ];
    }

    // Admin methods
    public function adminIndex(Request $request)
    {
        $currentOrgId = session('current_organization_id');
        $query = Election::where('organization_id', $currentOrgId)
            ->withCount(['candidates', 'votes as total_votes']);
        
        // Filter by status
        if ($request->status && $request->status !== 'all') {
            if ($request->status === 'upcoming') {
                $query->where('start_date', '>', now());
            } elseif ($request->status === 'ongoing') {
                $query->where('start_date', '<=', now())
                      ->where('end_date', '>=', now());
            } elseif ($request->status === 'completed') {
                $query->where('end_date', '<', now());
            }
        }
        
        $elections = $query->latest()->paginate(15);
        
        return view('admin.elections.index', compact('elections'));
    }

    public function create()
    {
        return view('admin.elections.create');
    }

    public function store(Request $request)
    {
        // Server guard: only one current/upcoming election at a time (match UI disabled state)
        $currentOrgId = session('current_organization_id');
        $hasActiveElection = Election::where('organization_id', $currentOrgId)
            ->where('end_date', '>', now())
            ->exists();
        if ($hasActiveElection) {
            $message = 'Only one current or upcoming election can be scheduled at a time.';
            if ($request->expectsJson()) {
                return response()->json(['message' => $message, 'errors' => ['title' => [$message]]], 422);
            }
            return back()->with('error', $message);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'is_active' => 'boolean',
        ]);

        $validated['organization_id'] = $currentOrgId;
        $election = Election::create($validated);

        // Broadcast real-time update
        safe_broadcast(new \App\Events\ElectionCreated($election));

        // Clear cache
        cache()->flush();

        return redirect()->route('admin.elections.index')
            ->with('success', 'Election created successfully.');
    }

    public function edit(Election $election)
    {
        return view('admin.elections.edit', compact('election'));
    }

    public function update(Request $request, Election $election)
    {
        // Prevent editing completed elections
        if ($election->end_date->isPast()) {
            $message = 'Cannot edit a completed election.';
            if ($request->expectsJson()) {
                return response()->json(['message' => $message, 'errors' => ['election' => [$message]]], 422);
            }
            return back()->with('error', $message);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'is_active' => 'boolean',
            'results_published' => 'boolean',
        ]);

        // Dates are optional in the edit form. Keep the persisted schedule when
        // an administrator only changes the title or description.
        $startDate = $request->filled('start_date')
            ? $validated['start_date']
            : $election->start_date;
        $endDate = $request->filled('end_date')
            ? $validated['end_date']
            : $election->end_date;

        if (\Carbon\Carbon::parse($endDate)->lte(\Carbon\Carbon::parse($startDate))) {
            $message = 'The end date must be after the start date.';
            if ($request->expectsJson()) {
                return response()->json(['message' => $message, 'errors' => ['end_date' => [$message]]], 422);
            }
            return back()
                ->withInput()
                ->withErrors(['end_date' => $message]);
        }

        $validated['start_date'] = $startDate;
        $validated['end_date'] = $endDate;

        $election->update($validated);

        // Broadcast real-time update
        safe_broadcast(new \App\Events\ElectionUpdated($election));

        // Clear cache
        cache()->flush();

        return redirect()->route('admin.elections.index')
            ->with('success', 'Election updated successfully.');
    }

    public function destroy(Election $election)
    {
        $electionId = $election->id;
        
        $election->delete();

        // Broadcast real-time update
        safe_broadcast(new \App\Events\ElectionDeleted($electionId));

        // Clear cache
        cache()->flush();

        return redirect()->route('admin.elections.index')
            ->with('success', 'Election deleted successfully.');
    }

    public function manageCandidates(Election $election)
    {
        $candidates = $election->candidates()->orderBy('position')->get();
        return view('admin.elections.candidates', compact('election', 'candidates'));
    }

    public function storeCandidate(Request $request, Election $election)
    {
        // Prevent adding candidates to completed or ongoing elections (match UI disabled state)
        $isOngoing = $election->is_posted && now()->between($election->start_date, $election->end_date);
        if ($election->end_date->isPast() || $isOngoing) {
            $message = $election->end_date->isPast() ? 'Cannot add candidates to a completed election.' : 'Cannot add candidates to an ongoing election.';
            if ($request->expectsJson()) {
                return response()->json(['message' => $message, 'errors' => ['position' => [$message]]], 422);
            }
            return back()->with('error', $message);
        }

        $validated = $request->validate([
            'student_id' => 'required|string|max:50',
            'position' => 'required|string|max:255',
            'photo' => 'nullable|image|max:2048',
        ]);

        // Resolve student by student_id (handles dash variations like 00-0000-0000 vs 0000-0000)
        $rawStudentId = trim((string) $validated['student_id']);
        $digits = preg_replace('/\D/', '', $rawStudentId);
        $user = User::where('student_id', $rawStudentId)->first();
        if (!$user && $digits) {
            $user = User::whereRaw("REPLACE(REPLACE(student_id, '-', ''), ' ', '') = ?", [$digits])->first();
            if (!$user) {
                $user = User::where('student_id', $digits)->first();
            }
        }

        if (!$user) {
            $message = 'Student ID not found. Only accounts with an existing student record can be added as candidates.';
            if ($request->expectsJson()) {
                return response()->json(['message' => $message, 'errors' => ['student_id' => [$message]]], 422);
            }
            return back()->with('error', $message)->withInput();
        }

        // Must be an active member of the election's organization
        $isMember = DB::table('organization_user')
            ->where('organization_id', $election->organization_id)
            ->where('user_id', $user->id)
            ->where('membership_status', 'Active')
            ->exists();
        if (!$isMember) {
            $message = 'Selected student is not an active member of this organization. Only org members can be candidates.';
            if ($request->expectsJson()) {
                return response()->json(['message' => $message, 'errors' => ['student_id' => [$message]]], 422);
            }
            return back()->with('error', $message)->withInput();
        }

        // Prevent duplicate candidate (same student in same election)
        $alreadyCandidate = Candidate::where('election_id', $election->id)
            ->where(function ($q) use ($user, $rawStudentId) {
                $q->where('user_id', $user->id)
                  ->orWhere('student_id', $rawStudentId);
            })
            ->exists();
        if ($alreadyCandidate) {
            $message = 'This student is already a candidate in this election.';
            if ($request->expectsJson()) {
                return response()->json(['message' => $message, 'errors' => ['student_id' => [$message]]], 422);
            }
            return back()->with('error', $message)->withInput();
        }

        $data = [
            'election_id' => $election->id,
            'organization_id' => $election->organization_id,
            'user_id' => $user->id,
            'student_id' => $user->student_id ?? $rawStudentId,
            'name' => $user->full_name ?? trim($user->first_name . ' ' . $user->last_name),
            'position' => $validated['position'],
            'year_level' => $user->year_level ?? null,
        ];

        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo')->store('candidates', storage_disk());
        } elseif ($user->photo) {
            // Use existing user photo if no upload
            $data['photo'] = $user->photo;
        }

        Candidate::create($data);

        return back()->with('success', 'Candidate added successfully.');
    }

    public function postElection(Election $election)
    {
        // Validate that all positions have at least one candidate
        $requiredPositions = [
            'president' => 'President',
            'vice_president' => 'Vice President',
            'treasurer' => 'Treasurer',
            'auditor' => 'Auditor',
            'public_information_officer' => 'Public Information Officer',
            'business_manager' => 'Business Manager',
            'event_coordinator' => 'Event Coordinator',
            '1st_year_rep' => '1st Year Representative',
            '2nd_year_rep' => '2nd Year Representative',
            '3rd_year_rep' => '3rd Year Representative',
            '4th_year_rep' => '4th Year Representative',
        ];

        $missingPositions = [];
        
        foreach ($requiredPositions as $posKey => $posName) {
            $candidateCount = $election->candidates()->where('position', $posKey)->count();
            if ($candidateCount === 0) {
                $missingPositions[] = $posName;
            }
        }

        if (!empty($missingPositions)) {
            return back()->with('error', 'Cannot post election! All positions need at least one candidate.');
        }

        // Use the model casts instead of raw boolean expressions so the
        // PostgresBoolean cast does not turn DB::raw('TRUE') into false.
        $election->is_posted = true;
        $election->is_active = true;
        $election->save();

        // Broadcast real-time update
        safe_broadcast(new \App\Events\ElectionCreated($election));

        // Election notifications are private to the organization that owns the election.
        // Do not include other organizations from the same department.
        $orgIds = [$election->organization_id];
        $students = User::eligibleForElectionNotifications($orgIds)->get();

        // A malformed email must not prevent election notifications from being
        // sent to everyone else. The provider validates every recipient and rejects
        // the entire send when one address is invalid.
        $invalidRecipients = $students->filter(function (User $student): bool {
            return filter_var(trim((string) $student->email), FILTER_VALIDATE_EMAIL) === false;
        });

        if ($invalidRecipients->isNotEmpty()) {
            Log::warning('Skipping users with invalid email addresses while sending election notifications.', [
                'election_id' => $election->id,
                'user_ids' => $invalidRecipients->pluck('id')->values()->all(),
            ]);
        }

        $students = $students->reject(function (User $student): bool {
            return filter_var(trim((string) $student->email), FILTER_VALIDATE_EMAIL) === false;
        })->values();
        $studentCount = $students->count();
        
        // Check if election has already started (start_date is now or in the past)
        if ($election->start_date <= now()) {
            // Election is starting now - send "Voting Now Open" notification
            Log::info("Sending {$studentCount} 'Voting Now Open' emails for election: {$election->title}");

            $failedRecipients = [];
            foreach ($students as $student) {
                try {
                    $student->notify(new VotingNowOpenNotification($election));
                } catch (\Throwable $exception) {
                    $failedRecipients[] = $student->id;
                    Log::warning('Skipping failed voting-open email.', [
                        'election_id' => $election->id,
                        'user_id' => $student->id,
                        'reason' => $exception->getMessage(),
                    ]);
                }
            }
            
            $election->start_notified = true;
            $election->save();
            $sentCount = $studentCount - count($failedRecipients);
            $message = "Election posted successfully! {$sentCount} of {$studentCount} email notifications were sent.";
            if (request()->expectsJson()) return response()->json(['success' => true, 'message' => $message, 'student_count' => $studentCount]);
            return back()->with('success', $message);
        } else {
            // Election starts in the future - send "Election Announced" notification
            Log::info("Sending {$studentCount} 'Election Announced' emails for election: {$election->title}");

            $failedRecipients = [];
            foreach ($students as $student) {
                try {
                    $student->notify(new ElectionAnnouncedNotification($election));
                } catch (\Throwable $exception) {
                    $failedRecipients[] = $student->id;
                    Log::warning('Skipping failed election announcement email.', [
                        'election_id' => $election->id,
                        'user_id' => $student->id,
                        'reason' => $exception->getMessage(),
                    ]);
                }
            }
            
            $sentCount = $studentCount - count($failedRecipients);
            $message = "Election posted successfully! {$sentCount} of {$studentCount} email notifications were sent.";
            if (request()->expectsJson()) return response()->json(['success' => true, 'message' => $message, 'student_count' => $studentCount]);
            return back()->with('success', $message);
        }
    }

    public function unpostElection(Election $election)
    {
        // Use the model casts instead of a database-specific raw boolean
        // expression so this works consistently on PostgreSQL and MySQL.
        $election->is_posted = false;
        $election->is_active = false;
        $election->save();

        return redirect()->route('admin.elections.index')
            ->with('success', 'Election unposted successfully. Students can no longer view or vote in this election.');
    }

    public function getSecretaryCandidates(Request $request, Election $election)
    {
        $president = Candidate::find($request->president);
        $vicePresident = Candidate::find($request->vice);

        return response()->json([
            'president' => $president,
            'vicePresident' => $vicePresident,
        ]);
    }

    public function assignSecretary(Request $request, Election $election)
    {
        $validated = $request->validate([
            'secretary_id' => 'required|exists:candidates,id',
        ]);

        // Ensure candidate belongs to this election (prevent cross-election assignment)
        $candidate = Candidate::where('id', $validated['secretary_id'])
            ->where('election_id', $election->id)
            ->first();
        if (!$candidate) {
            $message = 'Selected candidate does not belong to this election.';
            if ($request->expectsJson()) {
                return response()->json(['message' => $message, 'errors' => ['secretary_id' => [$message]]], 422);
            }
            return back()->with('error', $message);
        }

        $election->update(['secretary_id' => $validated['secretary_id']]);

        return back()->with('success', 'Secretary assigned successfully!');
    }

    public function adminResults(Election $election)
    {
        $positions = [
            'president' => 'President',
            'vice_president' => 'Vice President',
            'treasurer' => 'Treasurer',
            'auditor' => 'Auditor',
            'public_information_officer' => 'Public Information Officer',
            'business_manager' => 'Business Manager',
            'event_coordinator' => 'Event Coordinator',
            '1st_year_rep' => '1st Year Representative',
            '2nd_year_rep' => '2nd Year Representative',
            '3rd_year_rep' => '3rd Year Representative',
            '4th_year_rep' => '4th Year Representative',
        ];

        // Load all candidates once, then group them in memory instead of querying for each position.
        // Setting the relation also prevents the view from triggering a separate lazy-load query.
        $candidates = $election->candidates()
            ->orderBy('position')
            ->orderByDesc('vote_count')
            ->get();
        $election->setRelation('candidates', $candidates);
        $candidatesByPosition = $candidates->groupBy('position');

        $results = [];
        foreach ($positions as $key => $name) {
            $results[$key] = [
                'name' => $name,
                'candidates' => $candidatesByPosition->get($key, collect()),
            ];
        }

        $totalVotes = $election->votes()->count();
        $totalVoters = $election->votes()->distinct('user_id')->count('user_id');
        $registeredVoters = User::eligibleForElectionNotifications([$election->organization_id])->count();

        return view('admin.elections.results', compact('election', 'results', 'totalVotes', 'totalVoters', 'registeredVoters'));
    }

    public function publishResults(Election $election)
    {
        if (!$election->end_date->isPast()) {
            return back()->with('error', 'Cannot publish results before the election ends.');
        }

        if ($election->results_published) {
            return request()->expectsJson()
                ? response()->json(['success' => false, 'message' => 'Election results have already been published.'], 422)
                : back()->with('error', 'Election results have already been published.');
        }

        $election->results_published = true;
        $election->save();

        // Create officer transition record for adviser approval (if not already exists)
        try {
            $existingTransition = ElectionOfficerTransition::where('election_id', $election->id)->first();
            if (!$existingTransition) {
                $winnersSnapshot = $this->buildWinnersSnapshot($election);
                ElectionOfficerTransition::create([
                    'election_id' => $election->id,
                    'organization_id' => $election->organization_id,
                    'status' => 'pending',
                    'winners_snapshot' => $winnersSnapshot,
                ]);
                // Notify all advisers in the organization
                $adviserIds = DB::table('organization_user')
                    ->where('organization_id', $election->organization_id)
                    ->where('membership_status', 'Active')
                    ->whereRaw("LOWER(role) LIKE ?", ['%adviser%'])
                    ->pluck('user_id');
                if ($adviserIds->isNotEmpty()) {
                    $advisers = User::whereIn('id', $adviserIds)->get();
                    foreach ($advisers as $adviser) {
                        if (filter_var(trim((string) $adviser->email), FILTER_VALIDATE_EMAIL) === false) {
                            Log::warning('Skipping adviser with invalid email for officer transition notification.', [
                                'election_id' => $election->id,
                                'user_id' => $adviser->id,
                            ]);
                            continue;
                        }
                        try {
                            $adviser->notify(new ElectionOfficerApprovalRequiredNotification($election));
                        } catch (\Throwable $exception) {
                            Log::warning('Failed to notify adviser for officer transition.', [
                                'election_id' => $election->id,
                                'user_id' => $adviser->id,
                                'reason' => $exception->getMessage(),
                            ]);
                        }
                    }
                }
            }
        } catch (\Throwable $exception) {
            Log::error('Failed to create officer transition after publishing results.', [
                'election_id' => $election->id,
                'reason' => $exception->getMessage(),
            ]);
        }

        // Broadcast real-time update
        safe_broadcast(new \App\Events\ElectionUpdated($election));

        // Election notifications are private to the organization that owns the election.
        // Do not include other organizations from the same department.
        $orgIds = [$election->organization_id];
        $students = User::eligibleForElectionNotifications($orgIds)->get();

        // A malformed email must not prevent the election results from being
        // published for everyone else. The provider validates every recipient and
        // rejects the entire send when one address is invalid.
        $invalidRecipients = $students->filter(function (User $student): bool {
            return filter_var(trim((string) $student->email), FILTER_VALIDATE_EMAIL) === false;
        });

        if ($invalidRecipients->isNotEmpty()) {
            Log::warning('Skipping users with invalid email addresses while publishing election results.', [
                'election_id' => $election->id,
                'user_ids' => $invalidRecipients->pluck('id')->values()->all(),
            ]);
        }

        $students = $students->reject(function (User $student): bool {
            return filter_var(trim((string) $student->email), FILTER_VALIDATE_EMAIL) === false;
        })->values();
        $studentCount = $students->count();
        
        Log::info("Sending {$studentCount} 'Results Published' emails for election: {$election->title}");
        
        $failedRecipients = [];

        foreach ($students as $student) {
            try {
                // Send per recipient so one malformed address cannot abort the
                // remaining notifications when the sync queue is enabled.
                $student->notify(new ElectionResultsPublishedNotification($election));
            } catch (\Throwable $exception) {
                $failedRecipients[] = $student->id;
                Log::warning('Skipping failed election results email.', [
                    'election_id' => $election->id,
                    'user_id' => $student->id,
                    'reason' => $exception->getMessage(),
                ]);
            }
        }

        if ($failedRecipients !== []) {
            Log::warning('Some election results emails were skipped.', [
                'election_id' => $election->id,
                'user_ids' => $failedRecipients,
            ]);
        }

        $sentCount = $studentCount - count($failedRecipients);
        $message = "Election results have been published! {$sentCount} of {$studentCount} email notifications were sent.";

        if (request()->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'student_count' => $studentCount,
            ]);
        }

        return redirect()->route('admin.elections.results', $election)->with('success', $message);
    }

    /**
     * Build winners snapshot grouped by position. Winners are highest vote_count per position.
     */
    private function buildWinnersSnapshot(Election $election): array
    {
        $positions = array_keys($this->positionDefinitions());
        $candidates = $election->candidates()->get()->groupBy('position');
        $snapshot = [];
        foreach ($positions as $posKey) {
            $group = $candidates->get($posKey, collect());
            if ($group->isEmpty()) {
                continue;
            }
            // Highest votes first, tie-break by earliest id
            $winner = $group->sort(function ($a, $b) {
                if ($a->vote_count !== $b->vote_count) {
                    return $b->vote_count <=> $a->vote_count;
                }
                return $a->id <=> $b->id;
            })->first();
            $snapshot[$posKey] = [
                'candidate_id' => $winner->id,
                'user_id' => $winner->user_id,
                'student_id' => $winner->student_id,
                'name' => $winner->name,
                'position' => $winner->position,
                'vote_count' => $winner->vote_count,
                'photo' => $winner->photo,
            ];
        }
        return $snapshot;
    }

    public function destroyCandidate(Candidate $candidate)
    {
        // Prevent removing candidates from completed or ongoing elections (match UI disabled state)
        $isOngoing = $candidate->election->is_posted && now()->between($candidate->election->start_date, $candidate->election->end_date);
        if ($candidate->election->end_date->isPast() || $isOngoing) {
            $message = $candidate->election->end_date->isPast() ? 'Cannot remove candidates from a completed election.' : 'Cannot remove candidates from an ongoing election.';
            if (request()->expectsJson()) {
                return response()->json(['message' => $message, 'errors' => ['candidate' => [$message]]], 422);
            }
            return back()->with('error', $message);
        }

        if ($candidate->photo) {
            Storage::disk(storage_disk())->delete($candidate->photo);
        }

        $candidate->delete();

        return back()->with('success', 'Candidate removed successfully.');
    }
}
