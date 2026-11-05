<?php

namespace App\Http\Controllers\AccountManagement;

use App\Http\Requests\AccountManagement\CreateAccountRequest;
use App\Http\Requests\AccountManagement\DeleteAccountRequest;
use App\Http\Requests\AccountManagement\EditProfileRequest;
use App\Http\Requests\AccountManagement\UpdateRoleRequest;
use App\Models\User;
use App\Services\AccountManagement\AccountManagementService;
use App\Services\AccountManagement\ActorAccountId;
use App\Services\AccountManagement\DestructiveConfirmation;
use App\Services\AccountManagement\DestructiveConfirmationVerifier;
use App\Services\AccountManagement\DirectoryContext;
use App\Services\AccountManagement\ManagedAccountDetailsQuery;
use App\Services\AccountManagement\ManagedAccountId;
use App\Services\AccountManagement\NonDeanOutcome;
use App\Services\AccountManagement\ResolvedAccountDetails;
use App\Services\AccountManagement\StudentAccountValidity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AccountController extends BaseAccountController
{
    private const DEAN_ROLE = 'super_admin';

    public function __construct(
        private AccountManagementService $service,
        private ManagedAccountDetailsQuery $detailsQuery,
        private DestructiveConfirmationVerifier $confirmations,
        private StudentAccountValidity $validity
    ) {}

    public function create()
    {
        $departments = \App\Models\Department::query()
            ->orderBy('department_name')
            ->get(['id', 'department_name', 'department_acronym']);

        return view('osas.accounts.create', compact('departments'));
    }

    public function store(CreateAccountRequest $request)
    {
        $command = $request->toCommand(Auth::id());
        $result = $this->service->create($command);

        if (! $result->completed()) {
            return $this->handleRejection($result);
        }

        return redirect()->route('osas.accounts.show', $result->affectedAccount->value)
            ->with('success', 'Account created successfully.');
    }

    public function show(Request $request, int $account)
    {
        $details = $this->resolveDetails($request, $account);

        $actorId = (int) Auth::id();
        $deleteToken = $this->confirmations->issue(DestructiveConfirmation::forAccountDeletion(
            $actorId,
            $account,
            $details->managementVersion,
        ));

        $deanContext = $this->deanAppointmentContext($details);

        $accountExpiry = $this->validity->statusFor(
            $details->role,
            $details->active(),
            $details->expiresAt,
        );

        $rawDepartmentId = $details->profileValues['department_id'] ?? null;
        $departmentName = is_numeric($rawDepartmentId)
            ? \App\Models\Department::query()
                ->whereKey((int) $rawDepartmentId)
                ->value('department_name')
            : null;

        return view('osas.accounts.show', [
            'details' => $details,
            'deleteConfirmation' => $deleteToken,
            'deanAppointment' => $deanContext,
            'accountExpiry' => $accountExpiry,
            'departmentName' => $departmentName,
        ]);
    }

    public function edit(Request $request, int $account)
    {
        return view('osas.accounts.edit', [
            'details' => $this->resolveDetails($request, $account),
        ]);
    }

    public function updateProfile(EditProfileRequest $request, int $account)
    {
        $snapshot = AccountManagementService::profileFreshness(
            new ActorAccountId(Auth::id()),
            new ManagedAccountId($account),
            (int) $request->input('freshness')
        );

        $command = $request->toCommand(Auth::id(), $account, $snapshot);
        $result = $this->service->updateProfile($command);

        if (! $result->completed()) {
            return $this->handleRejection($result);
        }

        return redirect()->route('osas.accounts.show', $account)
            ->with('success', 'Profile updated successfully.');
    }

    public function updateRole(UpdateRoleRequest $request, int $account)
    {
        $snapshot = AccountManagementService::roleFreshness(
            new ActorAccountId(Auth::id()),
            new ManagedAccountId($account),
            (int) $request->input('freshness')
        );

        $command = $request->toCommand(Auth::id(), $account, $snapshot);
        $result = $this->service->changeNonDeanRole($command);

        if (! $result->completed()) {
            return $this->handleRejection($result);
        }

        return redirect()->route('osas.accounts.show', $account)
            ->with('success', 'Role updated successfully.');
    }

    public function destroy(DeleteAccountRequest $request, int $account)
    {
        $snapshot = AccountManagementService::deletionFreshness(
            new ActorAccountId(Auth::id()),
            new ManagedAccountId($account),
            (int) $request->input('freshness')
        );

        $command = $request->toCommand(Auth::id(), $account, $snapshot);
        $result = $this->service->delete($command);

        if (! $result->completed()) {
            return $this->handleRejection($result);
        }

        return redirect()->route('osas.accounts.index')
            ->with('success', 'Account permanently deleted.');
    }

    private function resolveDetails(Request $request, int $account): ResolvedAccountDetails
    {
        $result = $this->detailsQuery->findDetails(
            $account,
            DirectoryContext::fromParameters($request->all()),
        );

        if (! $result->resolved()) {
            abort(404);
        }

        return $result;
    }

    /**
     * Freshness and confirmation facts the Dean appointment form needs. A
     * replacement binds the current active Dean of the candidate's college and
     * demotes them to OSAS Admin; an initial appointment (no Dean for that
     * college) needs no confirmation.
     */
    private function deanAppointmentContext(ResolvedAccountDetails $details): array
    {
        $rawDepartmentId = $details->profileValues['department_id'] ?? null;
        $departmentId = is_numeric($rawDepartmentId) ? (int) $rawDepartmentId : null;

        $currentDean = User::query()
            ->where('role', self::DEAN_ROLE)
            ->whereRaw('CAST(users.is_active AS INTEGER) = 1')
            ->whereKeyNot($details->accountId)
            ->when(
                $departmentId === null || $departmentId <= 0,
                static fn ($query) => $query->whereNull('department_id'),
                static fn ($query) => $query->where('department_id', $departmentId),
            )
            ->first();

        $confirmation = null;

        if ($currentDean !== null) {
            $confirmation = $this->confirmations->issue(DestructiveConfirmation::forDeanReplacement(
                (int) Auth::id(),
                $details->accountId,
                $details->managementVersion,
                (int) $currentDean->getKey(),
                (int) $currentDean->management_version,
                NonDeanOutcome::reassign('osas_admin'),
            ));
        }

        return [
            'candidateVersion' => $details->managementVersion,
            'confirmation' => $confirmation,
            'replacesDean' => $currentDean !== null,
            'departmentId' => $departmentId,
        ];
    }
}