<?php

namespace App\Http\Controllers\AccountManagement;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\User;
use App\Services\AccountManagement\AccountDirectoryQuery;
use App\Services\AccountManagement\AccountDirectoryFilter;
use App\Services\AccountManagement\StudentAccountValidity;
use Illuminate\Http\Request;

class DirectoryController extends Controller
{
    public function index(
        Request $request,
        AccountDirectoryQuery $query,
        StudentAccountValidity $validity,
    ) {
        $filter = AccountDirectoryFilter::fromQuery($request->all());
        $accounts = $query->paginate($filter);

        $organizations = Organization::orderBy('name')->get(['id', 'name', 'org_acronym']);

        return view('osas.accounts.index', [
            'accounts' => $accounts,
            'filter' => $filter,
            'organizations' => $organizations,
            'deleteTokens' => [],
            'accountStatus' => $this->accountStatus($accounts, $validity),
            'validitySummary' => $this->validitySummary(),
        ]);
    }

    /**
     * Per-row display status combining the manual lifecycle flag with the fixed
     * time-based validity window. Only students carry an expiry, so non-students
     * keep the existing Active/Inactive mapping.
     *
     * @param  iterable<\App\Models\User>  $accounts
     * @return array<int, array{state: string, expiresAt: ?\Illuminate\Support\Carbon, daysLeft: ?int}>
     */
    private function accountStatus(iterable $accounts, StudentAccountValidity $validity): array
    {
        $status = [];
        $now = now(config('app.timezone'));

        foreach ($accounts as $account) {
            $status[(int) $account->getKey()] = $validity->statusFor(
                (string) $account->role,
                (bool) $account->is_active,
                $account->expires_at,
                $now,
            );
        }

        return $status;
    }

    /**
     * Whole-directory counts of students in the expiring (grace) window or fully
     * expired, surfaced as quick links above the table.
     *
     * @return array{expiring: int, expired: int}
     */
    private function validitySummary(): array
    {
        $now = now(config('app.timezone'));
        $graceCutoff = $now->copy()->subMonths(StudentAccountValidity::GRACE_MONTHS);

        $students = User::query()
            ->where('role', 'student')
            ->whereNotNull('expires_at');

        $expired = (clone $students)->where('expires_at', '<=', $graceCutoff)->count();
        $expiring = (clone $students)
            ->where('expires_at', '<=', $now)
            ->where('expires_at', '>', $graceCutoff)
            ->count();

        return ['expiring' => $expiring, 'expired' => $expired];
    }
}
