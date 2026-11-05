<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        $user = auth()->user();

        // Systemless-org fallback users (routed to their department Mother Org
        // Student View because their own org has no system yet) are locked out
        // of admin pages entirely. Their account role is unchanged, but they
        // must not reach admin URLs until their organization gains a system.
        if (in_array('admin', $roles, true) && $user->isSystemlessFallback()) {
            $message = 'Your organization does not have a system yet. You can access its admin pages once its system is created.';

            if ($request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
                return response()->json([
                    'message' => $message,
                    'error' => 'Unauthorized'
                ], 403);
            }

            abort(403, $message);
        }

        foreach ($roles as $role) {
            if ($user->role === $role) {
                return $next($request);
            }
        }

        $message = $this->getUnauthorizedMessage($user->role, $roles);
        
        // For AJAX requests, return JSON response
        if ($request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return response()->json([
                'message' => $message,
                'error' => 'Unauthorized'
            ], 403);
        }
        
        abort(403, $message);
    }
    
    /**
     * Get user-friendly unauthorized message
     *
     * @param string $userRole
     * @param array $allowedRoles
     * @return string
     */
    private function getUnauthorizedMessage(string $userRole, array $allowedRoles): string
    {
        $roleNames = [
            'super_admin' => 'Super Administrator',
            'osas_admin' => 'OSAS Administrator',
            'admin' => 'Organization Administrator',
            'dean' => 'Dean',
            'student' => 'Student'
        ];
        
        $currentRole = $roleNames[$userRole] ?? ucfirst(str_replace('_', ' ', $userRole));
        $requiredRoles = array_map(fn($role) => $roleNames[$role] ?? ucfirst(str_replace('_', ' ', $role)), $allowedRoles);
        
        if (count($requiredRoles) === 1) {
            return "This page is only accessible to {$requiredRoles[0]}s. Your current role: $currentRole";
        }
        
        $lastRole = array_pop($requiredRoles);
        $rolesText = implode(', ', $requiredRoles) . ' or ' . $lastRole;
        
        return "This page is only accessible to $rolesText. Your current role: $currentRole";
    }
}
