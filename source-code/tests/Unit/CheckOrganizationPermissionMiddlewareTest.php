<?php

namespace Tests\Unit;

use App\Http\Middleware\CheckOrganizationPermission;
use App\Models\Organization;
use App\Models\OrganizationSettings;
use App\Models\User;
use App\Services\OrganizationPermissionService;
use App\Services\PermissionAuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class CheckOrganizationPermissionMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    private CheckOrganizationPermission $middleware;
    private OrganizationPermissionService $permissionService;
    private PermissionAuditLogger $auditLogger;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->permissionService = app(OrganizationPermissionService::class);
        $this->auditLogger = app(PermissionAuditLogger::class);
        $this->middleware = new CheckOrganizationPermission(
            $this->permissionService,
            $this->auditLogger
        );
    }

    /**
     * Test middleware uses session organization context when available
     */
    public function test_middleware_uses_session_organization_context(): void
    {
        // Create organization
        $organization = Organization::create([
            'name' => 'Test Organization',
            'type' => 'Mother Org',
            'status' => 'Approved',
        ]);

        // Create admin user
        $user = User::create([
            'first_name' => 'Test',
            'last_name' => 'Admin',
            'email' => 'admin@test.com',
            'password' => Hash::make('password'),
            'student_id' => 'TEST001',
            'course' => 'BSCS',
            'year_level' => '4',
            'section' => 'A',
            'role' => 'admin',
            'is_active' => true,
        ]);

        // Attach user to organization with President role
        $user->organizations()->attach($organization->id, [
            'role' => 'President',
            'membership_status' => 'Active',
        ]);
        OrganizationSettings::create(['organization_id' => $organization->id]);

        // Set session organization context
        session(['current_organization_id' => $organization->id]);

        // Create request
        $request = Request::create('/admin/announcements', 'GET');
        $request->setUserResolver(fn() => $user);

        // Execute middleware
        $response = $this->middleware->handle(
            $request,
            fn($req) => response('OK'),
            'announcements',
            'full'
        );

        // Assert request was allowed
        $this->assertEquals('OK', $response->getContent());
    }

    /**
     * Test middleware falls back to first organization when session is not set
     */
    public function test_middleware_falls_back_to_first_organization(): void
    {
        // Create organization
        $organization = Organization::create([
            'name' => 'Test Organization',
            'type' => 'Mother Org',
            'status' => 'Approved',
        ]);

        // Create admin user
        $user = User::create([
            'first_name' => 'Test',
            'last_name' => 'Admin',
            'email' => 'admin@test.com',
            'password' => Hash::make('password'),
            'student_id' => 'TEST002',
            'course' => 'BSCS',
            'year_level' => '4',
            'section' => 'A',
            'role' => 'admin',
            'is_active' => true,
        ]);

        // Attach user to organization with President role
        $user->organizations()->attach($organization->id, [
            'role' => 'President',
            'membership_status' => 'Active',
        ]);
        OrganizationSettings::create(['organization_id' => $organization->id]);

        // Do NOT set session organization context

        // Create request
        $request = Request::create('/admin/announcements', 'GET');
        $request->setUserResolver(fn() => $user);

        // Execute middleware
        $response = $this->middleware->handle(
            $request,
            fn($req) => response('OK'),
            'announcements',
            'full'
        );

        // Assert request was allowed
        $this->assertEquals('OK', $response->getContent());
    }

    /**
     * Test middleware handles missing organization context gracefully
     */
    public function test_middleware_handles_missing_organization_context_gracefully(): void
    {
        // Create admin user WITHOUT any organization membership
        $user = User::create([
            'first_name' => 'Test',
            'last_name' => 'Admin',
            'email' => 'admin@test.com',
            'password' => Hash::make('password'),
            'student_id' => 'TEST003',
            'course' => 'BSCS',
            'year_level' => '4',
            'section' => 'A',
            'role' => 'admin',
            'is_active' => true,
        ]);

        // Do NOT attach user to any organization
        // Do NOT set session organization context

        // Create request
        $request = Request::create('/admin/announcements', 'GET');
        $request->setUserResolver(fn() => $user);

        // Expect 403 exception with appropriate message
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('No organization context available');

        // Execute middleware
        $this->middleware->handle(
            $request,
            fn($req) => response('OK'),
            'announcements',
            'full'
        );
    }

    /**
     * Test super_admin bypasses organization context check
     */
    public function test_super_admin_without_org_context_is_rejected(): void
    {
        // Create super admin user WITHOUT any organization membership
        $user = User::create([
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'email' => 'superadmin@test.com',
            'password' => Hash::make('password'),
            'student_id' => 'SUPER001',
            'course' => 'BSCS',
            'year_level' => '4',
            'section' => 'A',
            'role' => 'super_admin',
            'is_active' => true,
        ]);

        // Do NOT attach user to any organization
        // Do NOT set session organization context

        // Create request
        $request = Request::create('/admin/announcements', 'GET');
        $request->setUserResolver(fn() => $user);

        // Without an org context the middleware rejects the request; there is
        // no role bypass anymore.
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);

        $this->middleware->handle(
            $request,
            fn($req) => response('OK'),
            'announcements',
            'full'
        );
    }

    /**
     * Test osas_admin without org context is rejected
     */
    public function test_osas_admin_without_org_context_is_rejected(): void
    {
        // Create OSAS admin user WITHOUT any organization membership
        $user = User::create([
            'first_name' => 'OSAS',
            'last_name' => 'Admin',
            'email' => 'osas@test.com',
            'password' => Hash::make('password'),
            'student_id' => 'OSAS001',
            'course' => 'BSCS',
            'year_level' => '4',
            'section' => 'A',
            'role' => 'osas_admin',
            'is_active' => true,
        ]);

        // Do NOT attach user to any organization
        // Do NOT set session organization context

        // Create request
        $request = Request::create('/admin/announcements', 'GET');
        $request->setUserResolver(fn() => $user);

        // Without an org context the middleware rejects the request; there is
        // no role bypass anymore.
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);

        $this->middleware->handle(
            $request,
            fn($req) => response('OK'),
            'announcements',
            'full'
        );
    }
}
