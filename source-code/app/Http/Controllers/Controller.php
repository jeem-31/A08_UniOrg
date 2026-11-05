<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    /**
     * Allow only the effective Student View on mobile devices.
     *
     * This is intentionally checked before Auth::login() so a mobile request
     * cannot create an authenticated admin/staff session. Plain students and
     * admin-role users whose active_view is student may still log in.
     */
    protected function mobileAdminLoginError(Request $request, User $user): mixed
    {
        if (!$this->isMobileDevice($request) || $user->isEffectivelyStudent()) {
            return null;
        }

        $message = 'Admin access is available on desktop only. Please use a desktop or laptop to log in.';

        if ($request->wantsJson() || $request->ajax() || $request->expectsJson()) {
            return response()->json([
                'success' => false,
                'errors' => ['student_id' => [$message]],
            ], 422);
        }

        return back()->withErrors([
            'student_id' => $message,
        ])->onlyInput('student_id');
    }

    /**
     * Detect common mobile and tablet User-Agent identifiers.
     */
    protected function isMobileDevice(Request $request): bool
    {
        $userAgent = strtolower((string) $request->header('User-Agent'));

        return $userAgent !== '' && preg_match(
            '/android|webos|iphone|ipad|ipod|blackberry|iemobile|opera mini|mobile|tablet|silk|kindle|playbook|windows phone/i',
            $userAgent
        ) === 1;
    }
}
