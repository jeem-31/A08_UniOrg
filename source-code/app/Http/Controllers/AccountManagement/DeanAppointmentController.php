<?php

namespace App\Http\Controllers\AccountManagement;

use App\Http\Requests\AccountManagement\DeanAppointmentRequest;
use App\Models\User;
use App\Services\AccountManagement\ActorAccountId;
use App\Services\AccountManagement\DeanAppointmentService;
use App\Services\AccountManagement\ManagedAccountId;
use Illuminate\Support\Facades\Auth;

class DeanAppointmentController extends BaseAccountController
{
    private const DEAN_ROLE = 'super_admin';

    public function __construct(private DeanAppointmentService $service) {}

    public function appoint(DeanAppointmentRequest $request, int $account)
    {
        $candidate = User::query()->find((int) $request->validated('candidate_id'));

        if ($candidate === null) {
            abort(404);
        }

        // Scope to the candidate's college: one active Dean per department.
        $currentDean = User::query()
            ->where('role', self::DEAN_ROLE)
            ->whereRaw('CAST(users.is_active AS INTEGER) = 1')
            ->whereKeyNot((int) $candidate->getKey())
            ->when(
                $candidate->department_id === null,
                static fn ($query) => $query->whereNull('department_id'),
                static fn ($query) => $query->where('department_id', $candidate->department_id),
            )
            ->first();

        $snapshot = DeanAppointmentService::appointmentFreshness(
            new ActorAccountId(Auth::id()),
            new ManagedAccountId((int) $candidate->getKey()),
            (int) $request->validated('freshness'),
            $currentDean === null ? null : (int) $currentDean->getKey(),
            $currentDean === null ? null : (int) $currentDean->management_version,
        );

        $command = $request->toCommand(Auth::id(), $snapshot);
        $result = $this->service->appoint($command);

        if (! $result->completed()) {
            return $this->handleRejection($result);
        }

        return redirect()->back()->with('success', 'Dean appointment updated.');
    }
}