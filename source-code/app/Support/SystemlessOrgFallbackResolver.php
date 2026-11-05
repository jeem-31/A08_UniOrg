<?php

namespace App\Support;

use App\Http\Controllers\Auth\OrgAuthController;
use App\Models\Organization;
use App\Models\User;

/**
 * Resolves where a user whose active organizations all lack a system
 * (has_system = false) should be routed at login: the department's eligible
 * Mother Org, in Student View. Also produces the clear error messages for the
 * cases where no such destination exists.
 *
 * A user is a "fallback candidate" when NONE of their active organizations
 * has a system. For those users the login flow either:
 *   - routes them to the department Mother Org student page (fallback), or
 *   - blocks them with a clear message (no department, no eligible Mother
 *     Org, or a staff-only Adviser/Dean/COMELEC membership).
 */
class SystemlessOrgFallbackResolver
{
    /** Error A — the user's department could not be determined. */
    public const ERROR_NO_DEPARTMENT = 'Unable to determine your department. Please contact OSAS to update your account before logging in.';

    /** Error B — the department has no eligible Mother Org (with a system). */
    public const ERROR_NO_ELIGIBLE_MOTHER_ORG = 'Your organization does not have a system yet. Please wait until your organization\'s system has been created before logging in.';

    /** Error C — staff-only membership (Adviser / Dean / COMELEC). */
    public const ERROR_STAFF_ONLY_MEMBERSHIP = 'Your account is linked to an organization that does not have a system yet. Once your organization\'s system is created, you can log in. Please contact OSAS if you need access.';

    /**
     * Resolve the fallback destination for a user.
     *
     * @return array{fallback: Organization|null, error: string|null}
     *         - `fallback` set when the user should be routed to that org's
     *           Student View (all active orgs are systemless and an eligible
     *           department Mother Org exists).
     *         - `error` set when the user is a fallback candidate but must be
     *           blocked (no department, or no eligible Mother Org).
     *         - both null when normal login behavior applies (the user has an
     *           active org with a system, or no active orgs at all).
     */
    public static function resolve(User $user): array
    {
        // 1. Fallback candidates are users whose active organizations are ALL
        //    systemless. Users with no active organizations are not fallback
        //    candidates (the login flow reports that case separately).
        $activeOrgs = $user->activeOrganizations()->get();

        if ($activeOrgs->isEmpty()) {
            return ['fallback' => null, 'error' => null];
        }

        // 2. If any active organization has a system, the user logs in
        //    normally — no fallback (an officer is usually tied to one org,
        //    but a member may belong to several).
        if ($activeOrgs->contains(fn (Organization $org) => $org->has_system)) {
            return ['fallback' => null, 'error' => null];
        }

        // 3. Determine the target department: the course → department keyword
        //    mapping wins because the course column is the source of truth for
        //    a student's college (the stored department_id may point to the
        //    org's own department, e.g. Non-Academic, rather than the
        //    student's real college). The stored department_id is only used
        //    when the course cannot be resolved.
        $candidateDepartmentIds = [];

        if ($courseDepartmentId = OrgAuthController::courseToDepartmentId($user->course)) {
            $candidateDepartmentIds[] = (int) $courseDepartmentId;
        }

        if ($user->department_id) {
            $candidateDepartmentIds[] = (int) $user->department_id;
        }

        $candidateDepartmentIds = array_values(array_unique($candidateDepartmentIds));

        if (empty($candidateDepartmentIds)) {
            return ['fallback' => null, 'error' => self::ERROR_NO_DEPARTMENT];
        }

        // 4. The department's eligible Mother Org: Mother Org + Approved +
        //    has a system. Anything else means there is nowhere to route the
        //    user, so they are blocked (students included).
        foreach ($candidateDepartmentIds as $departmentId) {
            $motherOrg = Organization::query()
                ->where('department_id', $departmentId)
                ->where('type', 'Mother Org')
                ->where('status', 'Approved')
                ->whereRaw('has_system IS TRUE')
                ->first();

            if ($motherOrg) {
                return ['fallback' => $motherOrg, 'error' => null];
            }
        }

        return ['fallback' => null, 'error' => self::ERROR_NO_ELIGIBLE_MOTHER_ORG];
    }
}
