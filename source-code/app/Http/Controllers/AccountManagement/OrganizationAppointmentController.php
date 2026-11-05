<?php

namespace App\Http\Controllers\AccountManagement;

use App\Http\Requests\AccountManagement\OrganizationAppointmentRequest;
use App\Services\AccountManagement\ActorAccountId;
use App\Services\AccountManagement\ManagedAccountId;
use App\Services\AccountManagement\OrganizationAppointmentService;
use App\Services\AccountManagement\OrganizationId;
use App\Services\AccountManagement\OrganizationPositionRegistry;
use Illuminate\Support\Facades\Auth;

class OrganizationAppointmentController extends BaseAccountController
{
    public function __construct(private OrganizationAppointmentService $service) {}

    public function appoint(OrganizationAppointmentRequest $request, int $account, int $organization)
    {
        $positionStr = $request->validated('position');
        $position = OrganizationPositionRegistry::adviserForRoute($positionStr) 
                 ?? OrganizationPositionRegistry::officerForRoute($positionStr);

        if (!$position) {
            return back()->with('error', 'Invalid position.');
        }

        $snapshot = $this->service->currentFreshness(
            new ActorAccountId(Auth::id()),
            new OrganizationId($organization),
            new ManagedAccountId((int) $request->validated('candidate_id')),
            $position
        );

        $command = $request->toCommand(Auth::id(), $organization, $snapshot);
        $result = $position instanceof \App\Services\AccountManagement\AdviserPosition 
            ? $this->service->appointAdviser($command) 
            : $this->service->appointOfficer($command);

        if (! $result->completed()) {
            return $this->handleRejection($result);
        }

        return redirect()->back()->with('success', 'Organization appointment updated.');
    }
}