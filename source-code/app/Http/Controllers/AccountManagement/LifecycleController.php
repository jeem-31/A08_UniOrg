<?php

namespace App\Http\Controllers\AccountManagement;

use App\Http\Requests\AccountManagement\ChangeLifecycleRequest;
use App\Services\AccountManagement\AccountManagementService;
use App\Services\AccountManagement\ActorAccountId;
use App\Services\AccountManagement\ManagedAccountId;
use Illuminate\Support\Facades\Auth;

class LifecycleController extends BaseAccountController
{
    public function __construct(private AccountManagementService $service) {}

    public function change(ChangeLifecycleRequest $request, int $account)
    {
        $snapshot = AccountManagementService::lifecycleFreshness(
            new ActorAccountId(Auth::id()),
            new ManagedAccountId($account),
            (int) $request->input('freshness')
        );

        $command = $request->toCommand(Auth::id(), $account, $snapshot);
        $result = $this->service->changeActivation($command);

        if (! $result->completed()) {
            return $this->handleRejection($result);
        }

        return redirect()->back()->with('success', 'Account status updated.');
    }
}