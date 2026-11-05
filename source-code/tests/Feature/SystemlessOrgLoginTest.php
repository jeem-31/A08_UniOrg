<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Organization;
use App\Models\User;
use App\Support\SystemlessOrgFallbackResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Covers the "Systemless Org Login Fallback" spec
 * (docs/systemless-org-login-spec.md): users whose active organizations all
 * lack a system are routed to their department's eligible Mother Org in the
 * Student View, and blocked with a clear error when no destination exists or
 * when they hold staff-only memberships (Adviser/Dean/COMELEC).
 */
class SystemlessOrgLoginTest extends TestCase
{
    use RefreshDatabase;

    private function makeDepartment(int $id): Department
    {
        return Department::create([
            'id' => $id,
            'department_name' => "Department {$id}",
            'department_acronym' => "DEPT{$id}",
        ]);
    }

    private function makeMotherOrg(int $departmentId, bool $hasSystem = true): Organization
    {
        return Organization::create([
            'name' => "Mother Org {$departmentId}",
            'org_acronym' => "MO{$departmentId}",
            'type' => 'Mother Org',
            'department_id' => $departmentId,
            'status' => 'Approved',
            'has_system' => $hasSystem,
        ]);
    }

    private function makeSystemlessOrg(string $role = 'Member'): Organization
    {
        return Organization::create([
            'name' => 'Non-Academic Org',
            'org_acronym' => 'NAO',
            'type' => 'Non-Academic',
            'status' => 'Approved',
            'has_system' => false,
        ]);
    }

    private function makeUser(array $attributes = []): User
    {
        return User::create(array_merge([
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'student_id' => 'TEST-' . fake()->unique()->numerify('####'),
            'role' => 'student',
            'is_active' => true,
        ], $attributes));
    }

    private function attach(User $user, Organization $org, string $role = 'Member'): void
    {
        $user->organizations()->attach($org->id, [
            'role' => $role,
            'membership_status' => 'Active',
        ]);
    }

    /** 1. Systemless org member (student) is routed to the department Mother Org. */
    public function test_systemless_student_is_routed_to_department_mother_org_in_student_view(): void
    {
        $this->makeDepartment(7); // CCS id used by the course→department map
        $motherOrg = $this->makeMotherOrg(7);

        $user = $this->makeUser([
            'role' => 'student',
            'course' => 'BSIT',
            'department_id' => null, // exercises the course→department fallback
        ]);
        $this->attach($user, $this->makeSystemlessOrg());

        $response = $this->postJson('/org/login', [
            'student_id' => $user->student_id,
            'password' => 'password',
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'redirect' => url('/dashboard'),
        ]);

        $this->assertEquals($motherOrg->id, session('current_organization_id'));
        $this->assertTrue(session('systemless_fallback'));
        $this->assertSame('student', $user->fresh()->active_view);
        $this->assertTrue($user->fresh()->isEffectivelyStudent());
    }

    /** 2. Systemless org member (admin/officer) is forced into the Student View. */
    public function test_systemless_admin_officer_is_forced_into_student_view(): void
    {
        $this->makeDepartment(7);
        $this->makeMotherOrg(7);

        $user = $this->makeUser([
            'role' => 'admin',
            'course' => 'BSIT',
            'department_id' => null,
        ]);
        $this->attach($user, $this->makeSystemlessOrg(), 'President');

        $response = $this->postJson('/org/login', [
            'student_id' => $user->student_id,
            'password' => 'password',
        ]);

        $response->assertOk();
        $this->assertTrue(session('systemless_fallback'));
        $this->assertSame('student', $user->fresh()->active_view);
        $this->assertTrue($user->fresh()->isEffectivelyStudent());
        $this->assertFalse($user->fresh()->canSwitchView());

        // The dashboard renders the student dashboard for the fallback user.
        $this->actingAs($user->fresh());
        session(['current_organization_id' => session('current_organization_id'), 'systemless_fallback' => true]);
        $this->get(route('dashboard'))->assertOk();
    }

    /** 3. Adviser member of a systemless org is blocked (Error C, JSON). */
    public function test_adviser_of_systemless_org_is_blocked(): void
    {
        $this->makeDepartment(7);
        $this->makeMotherOrg(7);

        $user = $this->makeUser(['role' => 'admin', 'course' => 'BSIT']);
        $this->attach($user, $this->makeSystemlessOrg(), 'Adviser');

        $response = $this->postJson('/org/login', [
            'student_id' => $user->student_id,
            'password' => 'password',
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'errors' => ['student_id' => [SystemlessOrgFallbackResolver::ERROR_STAFF_ONLY_MEMBERSHIP]],
        ]);
        $this->assertNull(session('systemless_fallback'));
    }

    /** 4. COMELEC member of a systemless org is blocked (Error C). */
    public function test_comelec_of_systemless_org_is_blocked(): void
    {
        $this->makeDepartment(7);
        $this->makeMotherOrg(7);

        $user = $this->makeUser(['role' => 'admin', 'course' => 'BSIT']);
        $this->attach($user, $this->makeSystemlessOrg(), 'COMELEC');

        $response = $this->postJson('/org/login', [
            'student_id' => $user->student_id,
            'password' => 'password',
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'errors' => ['student_id' => [SystemlessOrgFallbackResolver::ERROR_STAFF_ONLY_MEMBERSHIP]],
        ]);
    }

    /** 5. Dean / Assoc. Dean member of a systemless org is blocked (Error C). */
    public function test_dean_of_systemless_org_is_blocked(): void
    {
        $this->makeDepartment(7);
        $this->makeMotherOrg(7);

        foreach (['Dean', 'Assoc. Dean', 'Dean/Assoc. Dean'] as $role) {
            $user = $this->makeUser(['role' => 'admin', 'course' => 'BSIT']);
            $this->attach($user, $this->makeSystemlessOrg(), $role);

            $this->postJson('/org/login', [
                'student_id' => $user->student_id,
                'password' => 'password',
            ])->assertStatus(422)->assertJson([
                'errors' => ['student_id' => [SystemlessOrgFallbackResolver::ERROR_STAFF_ONLY_MEMBERSHIP]],
            ]);
        }
    }

    /** 6. User with no department and an unmatchable course is blocked (Error A). */
    public function test_user_without_resolvable_department_is_blocked(): void
    {
        $this->makeDepartment(7);
        $this->makeMotherOrg(7);

        $user = $this->makeUser([
            'role' => 'student',
            'course' => 'Mysterious Unknown Course',
            'department_id' => null,
        ]);
        $this->attach($user, $this->makeSystemlessOrg());

        $response = $this->postJson('/org/login', [
            'student_id' => $user->student_id,
            'password' => 'password',
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'errors' => ['student_id' => [SystemlessOrgFallbackResolver::ERROR_NO_DEPARTMENT]],
        ]);
    }

    /** 7a. Department without any Mother Org is blocked (Error B), students included. */
    public function test_department_without_mother_org_is_blocked_for_students(): void
    {
        $this->makeDepartment(2);
        // No Mother Org created for department 2.

        $user = $this->makeUser(['role' => 'student', 'course' => 'BSCS', 'department_id' => 2]);
        $this->attach($user, $this->makeSystemlessOrg());

        $response = $this->postJson('/org/login', [
            'student_id' => $user->student_id,
            'password' => 'password',
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'errors' => ['student_id' => [SystemlessOrgFallbackResolver::ERROR_NO_ELIGIBLE_MOTHER_ORG]],
        ]);
    }

    /** 7b. Mother Org without a system yet is blocked (Error B). */
    public function test_department_with_systemless_mother_org_is_blocked(): void
    {
        $this->makeDepartment(2);
        $this->makeMotherOrg(2, hasSystem: false);

        $user = $this->makeUser(['role' => 'student', 'course' => 'BSCS', 'department_id' => 2]);
        $this->attach($user, $this->makeSystemlessOrg());

        $response = $this->postJson('/org/login', [
            'student_id' => $user->student_id,
            'password' => 'password',
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'errors' => ['student_id' => [SystemlessOrgFallbackResolver::ERROR_NO_ELIGIBLE_MOTHER_ORG]],
        ]);
    }

    /** A systemless member whose stored department has no eligible Mother Org falls back to the course department. */
    public function test_stored_department_without_mother_org_falls_back_to_course_department(): void
    {
        $this->makeDepartment(2);
        $this->makeDepartment(7);
        $motherOrg = $this->makeMotherOrg(7); // eligible Mother Org for CCS (course BSIT → dept 7)

        $user = $this->makeUser([
            'role' => 'student',
            'course' => 'BSIT',
            'department_id' => 2, // Non-academic/unknown dept with no eligible Mother Org
        ]);
        $this->attach($user, $this->makeSystemlessOrg());

        $response = $this->postJson('/org/login', [
            'student_id' => $user->student_id,
            'password' => 'password',
        ]);

        $response->assertOk();
        $this->assertEquals($motherOrg->id, session('current_organization_id'));
        $this->assertTrue(session('systemless_fallback'));
        $this->assertSame('student', $user->fresh()->active_view);
    }

    /** 8. User with at least one org that has a system logs in normally (regression). */
    public function test_user_with_system_org_logs_in_normally_without_fallback(): void
    {
        $systemOrg = Organization::create([
            'name' => 'System Org',
            'org_acronym' => 'SO',
            'type' => 'Mother Org',
            'status' => 'Approved',
            'has_system' => true,
        ]);
        $systemlessOrg = $this->makeSystemlessOrg();

        $user = $this->makeUser(['role' => 'admin', 'course' => 'BSIT']);
        $this->attach($user, $systemOrg, 'President');
        $this->attach($user, $systemlessOrg, 'Member');

        $response = $this->postJson('/org/login', [
            'student_id' => $user->student_id,
            'password' => 'password',
        ]);

        $response->assertOk();
        $this->assertNull(session('systemless_fallback'));
        $this->assertContains(session('current_organization_id'), [$systemOrg->id, $systemlessOrg->id]);
        $this->assertNull($user->fresh()->active_view);
    }

    /** 9. Fallback users cannot switch views (403). */
    public function test_fallback_user_cannot_switch_view(): void
    {
        $this->makeDepartment(7);
        $motherOrg = $this->makeMotherOrg(7);

        $user = $this->makeUser(['role' => 'admin', 'course' => 'BSIT']);
        $this->attach($user, $this->makeSystemlessOrg(), 'President');
        $user->active_view = 'student';
        $user->save();

        $this->actingAs($user);
        session(['current_organization_id' => $motherOrg->id, 'systemless_fallback' => true]);

        $this->post(route('view.switch'))->assertStatus(403);
    }

    /** 10. Fallback users cannot switch organizations. */
    public function test_fallback_user_cannot_switch_organization(): void
    {
        $this->makeDepartment(7);
        $motherOrg = $this->makeMotherOrg(7);
        $systemlessOrg = $this->makeSystemlessOrg();

        $user = $this->makeUser(['role' => 'admin', 'course' => 'BSIT']);
        $this->attach($user, $systemlessOrg, 'President');

        $this->actingAs($user);
        session(['current_organization_id' => $motherOrg->id, 'systemless_fallback' => true]);

        $this->from(route('dashboard'))
            ->post(route('organization.switch'), ['organization_id' => $systemlessOrg->id])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('error');
    }

    /** 10b. Even a non-fallback admin cannot switch into a systemless org. */
    public function test_switching_into_a_systemless_org_is_blocked(): void
    {
        $systemOrg = Organization::create([
            'name' => 'System Org',
            'type' => 'Mother Org',
            'status' => 'Approved',
            'has_system' => true,
        ]);
        $systemlessOrg = $this->makeSystemlessOrg();

        $user = $this->makeUser(['role' => 'admin', 'course' => 'BSIT']);
        $this->attach($user, $systemOrg, 'President');
        $this->attach($user, $systemlessOrg, 'Member');

        $this->actingAs($user);
        session(['current_organization_id' => $systemOrg->id]);

        $this->from(route('dashboard'))
            ->post(route('organization.switch'), ['organization_id' => $systemlessOrg->id])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('error');
    }

    /** 11. Once the org gains a system, the fallback clears and switching returns. */
    public function test_fallback_clears_once_org_gains_a_system(): void
    {
        $this->makeDepartment(7);
        $motherOrg = $this->makeMotherOrg(7);
        $systemlessOrg = $this->makeSystemlessOrg();

        $user = $this->makeUser(['role' => 'admin', 'course' => 'BSIT']);
        $this->attach($user, $systemlessOrg, 'President');

        // Log in through the fallback path.
        $this->postJson('/org/login', [
            'student_id' => $user->student_id,
            'password' => 'password',
        ])->assertOk();
        $this->assertTrue(session('systemless_fallback'));

        // The org finally gains a system.
        $systemlessOrg->update(['has_system' => true]);

        $fresh = $user->fresh();
        $this->assertFalse($fresh->isSystemlessFallback());
        $this->assertNull(session('systemless_fallback'));

        // View switching is allowed again and the last view (student) is kept
        // until the user switches.
        $fresh->active_view = 'student';
        $fresh->save();

        $this->actingAs($fresh);
        session(['current_organization_id' => $motherOrg->id]);
        $this->post(route('view.switch'))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('success');
        $this->assertSame('admin', $fresh->fresh()->active_view);
    }

    /** 12. Non-JS login path shows the error through back()->withErrors(). */
    public function test_non_js_login_path_returns_server_side_error(): void
    {
        $this->makeDepartment(7);
        $this->makeMotherOrg(7);

        $user = $this->makeUser(['role' => 'admin', 'course' => 'BSIT']);
        $this->attach($user, $this->makeSystemlessOrg(), 'Adviser');

        $this->from('/org/login')
            ->post('/org/login', [
                'student_id' => $user->student_id,
                'password' => 'password',
            ])
            ->assertRedirect('/org/login')
            ->assertSessionHasErrors(['student_id' => SystemlessOrgFallbackResolver::ERROR_STAFF_ONLY_MEMBERSHIP]);
    }

    /** The legacy /login flow applies the same fallback. */
    public function test_legacy_login_flow_applies_the_same_fallback(): void
    {
        $this->makeDepartment(7);
        $motherOrg = $this->makeMotherOrg(7);

        $user = $this->makeUser(['role' => 'student', 'course' => 'BSIT', 'department_id' => null]);
        $this->attach($user, $this->makeSystemlessOrg());

        $response = $this->postJson('/login', [
            'student_id' => $user->student_id,
            'password' => 'password',
        ]);

        $response->assertOk();
        $this->assertEquals($motherOrg->id, session('current_organization_id'));
        $this->assertTrue(session('systemless_fallback'));
        $this->assertSame('student', $user->fresh()->active_view);
    }

    /** A fallback admin is blocked from admin URLs even by direct navigation. */
    public function test_fallback_admin_is_blocked_from_admin_routes(): void
    {
        $this->makeDepartment(7);
        $motherOrg = $this->makeMotherOrg(7);

        $user = $this->makeUser(['role' => 'admin', 'course' => 'BSIT']);
        $this->attach($user, $this->makeSystemlessOrg(), 'President');

        $this->actingAs($user);
        session(['current_organization_id' => $motherOrg->id, 'systemless_fallback' => true]);

        $this->get(route('admin.dashboard'))->assertStatus(403);
    }

    /** A non-fallback admin is still allowed into admin routes (regression). */
    public function test_normal_admin_still_reaches_admin_routes(): void
    {
        $this->makeDepartment(7);
        $systemOrg = Organization::create([
            'name' => 'System Org',
            'org_acronym' => 'SO',
            'type' => 'Mother Org',
            'status' => 'Approved',
            'has_system' => true,
            'department_id' => 7,
        ]);

        $user = $this->makeUser(['role' => 'admin', 'course' => 'BSIT']);
        $this->attach($user, $systemOrg, 'President');

        $this->actingAs($user);
        session(['current_organization_id' => $systemOrg->id]);

        $this->get(route('admin.dashboard'))->assertOk();
    }

    /** Fallback login ignores a pre-login intended URL (never lands on an admin page). */
    public function test_fallback_login_ignores_intended_url(): void
    {
        $this->makeDepartment(7);
        $this->makeMotherOrg(7);

        $user = $this->makeUser(['role' => 'admin', 'course' => 'BSIT']);
        $this->attach($user, $this->makeSystemlessOrg(), 'President');

        // Simulate a guest who tried to reach an admin page before logging in.
        session(['url.intended' => url('/admin/dashboard')]);

        $this->post('/org/login', [
            'student_id' => $user->student_id,
            'password' => 'password',
        ])->assertRedirect(url('/dashboard'));

        $this->assertTrue(session('systemless_fallback'));
        $this->assertSame('student', $user->fresh()->active_view);
    }
}
