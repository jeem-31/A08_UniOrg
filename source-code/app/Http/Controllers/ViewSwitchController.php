<?php

namespace App\Http\Controllers;

use App\Services\OrganizationPermissionService;
use Illuminate\Http\RedirectResponse;

class ViewSwitchController extends Controller
{
    public function __construct(
        private OrganizationPermissionService $permissionService,
    ) {}

    /**
     * Toggle the authenticated user's active_view between 'admin' and 'student'.
     * Only users with role === 'admin' are allowed to switch.
     */
    public function switch(): RedirectResponse
    {
        $user = auth()->user();

        // Systemless-org fallback users are forced into the Student View, so
        // `canSwitchView()` returns false while their org has no system. The
        // marker clears automatically once the org gains a system, which
        // restores view switching.
        abort_unless($user->canSwitchView(), 403);

        $newView = $user->getActiveView() === 'student' ? 'admin' : 'student';

        // Zero-module admins auto-stay in Student View (not hard 403) — keep system usable
        if ($newView === 'admin') {
            $orgId = session('current_organization_id') ?? $user->organizations()->first()?->id;
            if ($orgId && !$this->permissionService->hasAnyModuleAccess($user->id, (int) $orgId)) {
                return redirect()->route('dashboard')->with('error', 'Wala ka pang module access para sa posisyon na ito. Makipag-ugnayan sa iyong Adviser sa Settings → Admins para mabigyan ng access. Nasa Student View ka pa rin.');
            }
        }

        $user->active_view = $newView;
        $user->save();

        return redirect()->route('dashboard')
            ->with('success', $newView === 'student'
                ? 'Switched to Student View.'
                : 'Switched to Admin View.');
    }
}
