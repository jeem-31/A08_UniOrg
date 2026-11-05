<?php

namespace App\Http\Middleware;

use App\Services\OrganizationPermissionService;
use App\Services\PermissionAuditLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckOrganizationPermission
{
    public function __construct(
        private OrganizationPermissionService $permissionService,
        private PermissionAuditLogger $auditLogger
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @param string $module - Module name (announcements, events, documents, complaints, payments, elections)
     * @param string $accessLevel - Required access level (full, read)
     * @return Response
     */
    public function handle(Request $request, Closure $next, string $module, string $accessLevel = 'full'): Response
    {
        $user = $request->user();
        
        // Get current organization context
        $organizationId = $this->getCurrentOrganizationId($request);
        
        // Handle missing organization context gracefully (Requirement 9.4)
        if ($organizationId === null) {
            return $this->handleUnauthorized($request, 'No organization context available. Please contact your administrator.');
        }

        // Check if organization is archived or expired (Requirement: restricted use for archived/expired orgs)
        $organization = \App\Models\Organization::find($organizationId);
        if ($organization && ($organization->isArchived() || $organization->isExpired())) {
            $reason = $organization->isArchived() ? 'archived' : 'expired (accreditation period ended)';
            return $this->handleUnauthorized($request, "This organization is currently {$reason}. Access is restricted until accreditation is renewed.");
        }
        
        // Check permission
        $hasAccess = $this->permissionService->hasAccess(
            $user->id,
            $organizationId,
            $module,
            $accessLevel
        );
        
        // Get user's position for audit logging
        $position = $this->permissionService->getPosition($user->id, $organizationId);
        
        // Log access attempt
        $this->auditLogger->log(
            $user->id,
            $organizationId,
            $module,
            $accessLevel,
            $hasAccess,
            $position
        );
        
        if (!$hasAccess) {
            $message = $this->getAccessDeniedMessage($module, $accessLevel, $position);
            return $this->handleUnauthorized($request, $message);
        }
        
        return $next($request);
    }
    
    /**
     * Handle unauthorized access based on request type
     *
     * @param Request $request
     * @param string $message
     * @return Response
     */
    private function handleUnauthorized(Request $request, string $message): Response
    {
        // For AJAX requests, return JSON response
        if ($request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return response()->json([
                'message' => $message,
                'error' => 'Unauthorized'
            ], 403);
        }
        
        // For regular requests, abort with 403
        abort(403, $message);
    }
    
    /**
     * Get user-friendly access denied message
     *
     * @param string $module
     * @param string $accessLevel
     * @param string|null $position
     * @return string
     */
    private function getAccessDeniedMessage(string $module, string $accessLevel, ?string $position): string
    {
        $moduleNames = [
            'announcements' => 'Announcements',
            'events' => 'Events',
            'documents' => 'Documents',
            'complaints' => 'Complaints & Suggestions',
            'payments' => 'Payments',
            'elections' => 'Elections',
            'library' => 'e-Library',
            'edushare' => 'EduShare',
            'chatbot' => 'Chatbot/FAQ'
        ];
        
        $moduleName = $moduleNames[$module] ?? ucfirst($module);
        $positionText = $position ? " (Position: $position)" : '';
        
        if ($accessLevel === 'read') {
            return "You do not have permission to view the $moduleName module$positionText. Please contact your organization administrator.";
        }
        
        return "You do not have permission to manage the $moduleName module$positionText. Please contact your organization administrator.";
    }
    
    /**
     * Get current organization ID from session or user's first organization
     *
     * @param Request $request
     * @return int|null
     */
    private function getCurrentOrganizationId(Request $request): ?int
    {
        // Get from session first
        $organizationId = session('current_organization_id');
        
        if ($organizationId) {
            return $organizationId;
        }
        
        // Fall back to user's first organization
        $firstOrganization = $request->user()->organizations()->first();
        
        return $firstOrganization?->id;
    }
}
