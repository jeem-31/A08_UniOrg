<?php

namespace App\Services;

use App\Models\Election;
use App\Models\ElectionOfficerTransition;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\User;
use App\Notifications\OfficerAccessApprovedNotification;
use App\Notifications\OfficerAccessRemovedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ElectionOfficerTransitionService
{
    public const POSITION_TO_OFFICER_MAP = [
        'president' => 'President',
        'vice_president' => 'Vice President',
        'treasurer' => 'Treasurer',
        'auditor' => 'Auditor',
        'public_information_officer' => 'PIO',
        'business_manager' => 'Business Manager',
        'event_coordinator' => 'Event Coordinator',
        '1st_year_rep' => '1st Year Representative',
        '2nd_year_rep' => '2nd Year Representative',
        '3rd_year_rep' => '3rd Year Representative',
        '4th_year_rep' => '4th Year Representative',
    ];

    private array $pendingPromoted = [];
    private array $pendingDemoted = [];

    public function __construct(
        private OrganizationPermissionService $permissionService,
        private PermissionMatrix $matrix,
    ) {}

    /**
     * Execute the transition atomically: demote losers, promote winners, notify winners.
     */
    public function execute(ElectionOfficerTransition $transition): void
    {
        $this->pendingPromoted = [];
        $this->pendingDemoted = [];
        $election = $transition->election()->with('organization')->firstOrFail();
        $organizationId = (int) $transition->organization_id;
        $organization = Organization::findOrFail($organizationId);
        $snapshot = $transition->winners_snapshot ?? [];

        if (empty($snapshot)) {
            throw new \RuntimeException('Transition snapshot is empty.');
        }

        // Map canonical officer role => winner user id
        $winnersByOfficerRole = [];
        foreach ($snapshot as $posKey => $data) {
            $officerRole = self::POSITION_TO_OFFICER_MAP[$posKey] ?? null;
            if (!$officerRole) {
                continue;
            }
            $userId = $data['user_id'] ?? null;
            if (!$userId) {
                Log::warning('Winner has no linked user; skipping promotion.', [
                    'election_id' => $election->id,
                    'position' => $posKey,
                    'candidate_id' => $data['candidate_id'] ?? null,
                ]);
                continue;
            }
            // canonicalize for grouping (PIO alias etc.)
            $canonical = $this->matrix->canonicalize(OrganizationUser::formatDisplayLabel($officerRole));
            $winnersByOfficerRole[$canonical] = (int) $userId;
        }

        // Collect all officer role canonicals that are part of election
        $electionCanonicalRoles = collect(self::POSITION_TO_OFFICER_MAP)
            ->map(fn ($role) => $this->matrix->canonicalize(OrganizationUser::formatDisplayLabel($role)))
            ->unique()
            ->values()
            ->all();

        DB::transaction(function () use ($organizationId, $organization, $election, $transition, $winnersByOfficerRole, $electionCanonicalRoles, $snapshot) {
            // Lock all relevant organization_user rows for this org
            $allAssignments = OrganizationUser::where('organization_id', $organizationId)
                ->lockForUpdate()
                ->get();

            // Group current holders by canonical role (election) and collect non-election officers to vacate (Secretary etc.)
            $holdersByCanonical = [];
            $nonElectionHolders = [];
            foreach ($allAssignments as $assignment) {
                $canonical = $this->matrix->canonicalize(OrganizationUser::formatDisplayLabel((string) $assignment->role));
                // Only active
                if ($assignment->membership_status !== 'Active') {
                    continue;
                }
                $lower = strtolower((string) $assignment->role);
                if (str_contains($lower, 'adviser') || str_contains($lower, 'dean') || strtoupper((string) $assignment->role) === 'COMELEC') {
                    continue;
                }
                if (in_array($canonical, $electionCanonicalRoles, true)) {
                    $holdersByCanonical[$canonical][] = $assignment;
                } else {
                    // Non-election officer (Secretary, Assistant Secretary etc.) -> vacate on handover
                    if (strtolower((string) $assignment->role) === 'member') {
                        continue;
                    }
                    $nonElectionHolders[] = $assignment;
                }
            }

            // Also lock users that will be affected (both holders and winners)
            $affectedUserIds = collect($holdersByCanonical)->flatten(1)->pluck('user_id')->merge(collect($nonElectionHolders)->pluck('user_id'))->merge(array_values($winnersByOfficerRole))->unique()->values();
            $usersById = User::whereIn('id', $affectedUserIds)->lockForUpdate()->get()->keyBy('id');

            // Determine who to demote: holders whose user_id != winner for that role
            $usersToDemote = [];
            foreach ($electionCanonicalRoles as $canonical) {
                $winnerId = $winnersByOfficerRole[$canonical] ?? null;
                $holders = $holdersByCanonical[$canonical] ?? [];
                foreach ($holders as $holder) {
                    if ($winnerId && (int) $holder->user_id === (int) $winnerId) {
                        // Winner retains same position, even if role string differs slightly (PIO vs Public Information Officer)
                        // Update role to canonical exact spelling to normalize
                        if ($holder->role !== $this->canonicalDisplayRole($canonical)) {
                            $holder->role = $this->canonicalDisplayRole($canonical);
                            $holder->save();
                        }
                        continue;
                    }
                    $usersToDemote[] = $holder;
                }
            }

            // Also vacate all non-election officers (Secretary etc.) — no winners, always demote; renew process will reassign
            foreach ($nonElectionHolders as $holder) {
                $usersToDemote[] = $holder;
            }

            // Capture demoted snapshot before changing roles (for email + in-app)
            $demotedSnapshot = [];
            foreach ($usersToDemote as $assignment) {
                $demotedSnapshot[] = [
                    'user_id' => (int) $assignment->user_id,
                    'previous_role' => (string) $assignment->role,
                ];
            }

            // Demote losers to Member
            foreach ($usersToDemote as $assignment) {
                $user = $usersById->get($assignment->user_id);
                $assignment->role = 'Member';
                $assignment->save();

                if ($user && $user->role === 'admin') {
                    $hasAnotherOfficer = DB::table('organization_user')
                        ->where('user_id', $user->id)
                        ->where('membership_status', 'Active')
                        ->whereRaw("LOWER(role) <> 'member'")
                        ->whereRaw("LOWER(role) NOT LIKE '%adviser%'")
                        ->whereRaw("LOWER(role) NOT LIKE '%dean%'")
                        ->whereRaw("UPPER(role) <> 'COMELEC'")
                        ->exists();
                    if (!$hasAnotherOfficer) {
                        $user->role = 'student';
                        $user->active_view = null;
                        $user->save();
                    }
                }
                $this->permissionService->invalidateCache($assignment->user_id, $organizationId);
            }

            // Store demoted for after-commit notification dispatch
            $this->pendingDemoted = [
                'demoted' => $demotedSnapshot,
                'organization' => $organization,
                'election' => $election,
            ];

            // Promote winners
            $promotedUserIds = [];
            foreach ($winnersByOfficerRole as $canonical => $winnerId) {
                $officerDisplay = $this->canonicalDisplayRole($canonical);
                $user = $usersById->get($winnerId);
                if (!$user) {
                    // Fetch if not in locked set (e.g., winner wasn't a holder before)
                    $user = User::whereKey($winnerId)->lockForUpdate()->first();
                    if (!$user) {
                        continue;
                    }
                    $usersById[$winnerId] = $user;
                }

                $existingAssignment = $allAssignments->firstWhere('user_id', $winnerId);
                if ($existingAssignment) {
                    // If winner was previously demoted in this transaction (was a holder of another role), its assignment object may have been updated to Member.
                    // Refresh from collection: find assignment for this user (could be the same object we just demoted)
                    // In that case, just update role to new officer role.
                    $existingAssignment->role = $officerDisplay;
                    $existingAssignment->membership_status = 'Active';
                    $existingAssignment->save();
                } else {
                    // No assignment in this org - create one (should rarely happen because winners are members)
                    OrganizationUser::createAssignment($user, $organization, $officerDisplay, 'Active');
                    // Refresh assignments collection
                    $allAssignments->push(OrganizationUser::where('organization_id', $organizationId)->where('user_id', $winnerId)->first());
                }

                $wasStudent = $user->role === 'student';
                $user->role = 'admin';
                if ($wasStudent) {
                    $user->active_view = 'student';
                }
                $user->save();

                $this->permissionService->invalidateCache($user->id, $organizationId);
                $this->permissionService->invalidateGrantCache($organizationId, $officerDisplay);
                $promotedUserIds[] = $winnerId;
            }

            // Mark transition completed
            $transition->status = 'completed';
            $transition->save();

            // Notify promoted winners (outside transaction would be safer, but we are inside; queue will handle after commit)
            // Store for after-commit notification dispatch
            $this->pendingPromoted = [
                'userIds' => array_unique($promotedUserIds),
                'organization' => $organization,
                'election' => $election,
                'snapshot' => $snapshot,
            ];
        });

        // After transaction commit, send notifications
        $pending = $this->pendingPromoted ?? null;
        if ($pending) {
            $org = $pending['organization'];
            $election = $pending['election'];
            $snapshot = $pending['snapshot'];
            // Build lookup position for each winner user
            $userIdToPosition = [];
            foreach ($snapshot as $posKey => $data) {
                $uid = $data['user_id'] ?? null;
                if ($uid) {
                    $officerRole = self::POSITION_TO_OFFICER_MAP[$posKey] ?? $posKey;
                    $userIdToPosition[(int) $uid] = $this->canonicalDisplayRole($this->matrix->canonicalize($officerRole));
                }
            }
            foreach (array_unique($pending['userIds']) as $uid) {
                $user = User::find($uid);
                if (!$user) {
                    continue;
                }
                $pos = $userIdToPosition[$uid] ?? 'Officer';
                try {
                    $user->notify(new OfficerAccessApprovedNotification($org, $pos, $election));
                } catch (\Throwable $exception) {
                    Log::error('Failed to notify promoted officer.', [
                        'user_id' => $uid,
                        'organization_id' => $org->id,
                        'position' => $pos,
                        'reason' => $exception->getMessage(),
                    ]);
                }
            }
        }

        // Notify demoted losers (in-app + email) — exclude those who were also promoted (role swap)
        $pendingDemoted = $this->pendingDemoted ?? null;
        if ($pendingDemoted && !empty($pendingDemoted['demoted'])) {
            $orgD = $pendingDemoted['organization'];
            $electionD = $pendingDemoted['election'];
            $promotedIds = isset($pending['userIds']) ? array_map('intval', (array) $pending['userIds']) : [];
            foreach ($pendingDemoted['demoted'] as $item) {
                $uid = (int) ($item['user_id'] ?? 0);
                if (!$uid || in_array($uid, $promotedIds, true)) {
                    continue;
                }
                $user = User::find($uid);
                if (!$user) {
                    continue;
                }
                $prevRole = $item['previous_role'] ?? 'Officer';
                try {
                    $user->notify(new OfficerAccessRemovedNotification($orgD, $prevRole, $electionD));
                } catch (\Throwable $exception) {
                    Log::error('Failed to notify demoted officer.', [
                        'user_id' => $uid,
                        'organization_id' => $orgD->id,
                        'previous_position' => $prevRole,
                        'reason' => $exception->getMessage(),
                    ]);
                }
            }
        }
    }

    private function canonicalDisplayRole(string $canonical): string
    {
        // Keep canonical as stored in PERMISSIONS keys; those are already display-ready.
        return $canonical;
    }
}
