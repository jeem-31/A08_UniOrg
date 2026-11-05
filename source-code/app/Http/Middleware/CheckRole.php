<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        if (!$request->user()) {
            return redirect()->route('login');
        }

        if (!in_array($request->user()->role, $roles)) {
            $message = $this->getUnauthorizedMessage($request->user()->role, $roles);
            
            // For AJAX requests, return JSON response
            if ($request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
                return response()->json([
                    'message' => $message,
                    'error' => 'Unauthorized'
                ], 403);
            }
            
            abort(403, $message);
        }

        return $next($request);
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
