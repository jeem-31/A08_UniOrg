<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Console-based unit tests for the org approval flow logic.
 * No database, no HTTP — pure PHP logic simulation.
 *
 * Tests the following scenarios:
 *  1. New org application approved → org created, requester + officers attached
 *  2. Renewal approved → existing org updated, old officers detached, new attached
 *  3. Student registers after org is Approved → auto-joined to Mother Org
 *  4. Student registers but NO Approved Mother Org exists → not attached (gap)
 *  5. Student registers for Sub Org type → not auto-joined (only Mother Org triggers join)
 */
class OrgApprovalFlowTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Simulated in-memory state (replaces the database)
    // -------------------------------------------------------------------------

    private array $organizations   = [];
    private array $users           = [];
    private array $organizationUser = [];
    private int   $orgIdSeq        = 1;
    private int   $userIdSeq       = 1;

    protected function setUp(): void
    {
        $this->organizations    = [];
        $this->users            = [];
        $this->organizationUser = [];
        $this->orgIdSeq         = 1;
        $this->userIdSeq        = 1;
    }

    // -------------------------------------------------------------------------
    // Helpers that mirror the actual controller/model logic
    // -------------------------------------------------------------------------

    private function createOrFindOrg(string $name, string $type, string $status, int $departmentId): array
    {
        foreach ($this->organizations as &$org) {
            if ($org['name'] === $name) {
                return $org;
            }
        }
        $org = [
            'id'            => $this->orgIdSeq++,
            'name'          => $name,
            'type'          => $type,
            'status'        => $status,
            'department_id' => $departmentId,
        ];
        $this->organizations[] = $org;
        return $org;
    }

    private function createUser(string $studentId, string $role, ?int $departmentId = null): array
    {
        $user = [
            'id'            => $this->userIdSeq++,
            'student_id'    => $studentId,
            'role'          => $role,
            'department_id' => $departmentId,
            'temp_position' => null,
        ];
        $this->users[] = $user;
        return $user;
    }

    /** Mirror of Organization::users()->attach() */
    private function attachUserToOrg(int $orgId, int $userId, string $role, string $status): void
    {
        $this->organizationUser[] = [
            'organization_id'   => $orgId,
            'user_id'           => $userId,
            'role'              => $role,
            'membership_status' => $status,
        ];
    }

    /** Mirror of Organization::users()->where('user_id')->exists() */
    private function isUserInOrg(int $orgId, int $userId): bool
    {
        foreach ($this->organizationUser as $row) {
            if ($row['organization_id'] === $orgId && $row['user_id'] === $userId) {
                return true;
            }
        }
        return false;
    }

    /** Mirror of Organization::users()->wherePivot('role', ...)->detach() */
    private function detachOfficers(int $orgId): void
    {
        $this->organizationUser = array_values(array_filter(
            $this->organizationUser,
            fn($row) => !($row['organization_id'] === $orgId
                && in_array($row['role'], ['President', 'Adviser']))
        ));
    }

    /**
     * Mirror of the approve() controller logic (condensed).
     *
     * @param array $applyOrg   Simulated ApplyOrg record
     * @param array $requester  Simulated User record
     * @param array $officers   Simulated officer User records with temp_position set
     */
    private function runApproveLogic(array $applyOrg, array $requester, array $officers): array
    {
        $organization = null;

        if ($applyOrg['request_type'] === 'apply') {
            // firstOrCreate equivalent
            $organization = $this->createOrFindOrg(
                $applyOrg['organization_name'],
                $applyOrg['organization_type'],
                'Approved',
                $applyOrg['department_id']
            );
        } else {
            // Renewal: find existing org and update status
            foreach ($this->organizations as &$org) {
                if ($org['name'] === $applyOrg['organization_name']) {
                    $org['status'] = 'Approved';
                    $organization = $org;
                    break;
                }
            }
            unset($org);

            if ($organization) {
                // Detach old President/Adviser
                $this->detachOfficers($organization['id']);
            }
        }

        if ($organization) {
            // Attach requester
            if (!$this->isUserInOrg($organization['id'], $requester['id'])) {
                $role = str_contains(strtolower($applyOrg['requester_position']), 'president')
                    ? 'President' : 'Adviser';
                $this->attachUserToOrg($organization['id'], $requester['id'], $role, 'Active');
            }

            // Attach officers
            foreach ($officers as $officer) {
                if (!$this->isUserInOrg($organization['id'], $officer['id'])) {
                    $role = $officer['temp_position'] ?? 'Member';
                    $this->attachUserToOrg($organization['id'], $officer['id'], $role, 'Active');
                }
            }
        }

        return $organization ?? [];
    }

    /**
     * Mirror of OrgAuthController::register() auto-join logic.
     */
    private function runRegisterAutoJoin(array $user, int $departmentId): bool
    {
        $motherOrg = null;
        foreach ($this->organizations as $org) {
            if ($org['department_id'] === $departmentId
                && $org['type']          === 'Mother Org'
                && $org['status']        === 'Approved') {
                $motherOrg = $org;
                break;
            }
        }

        if ($motherOrg) {
            $this->attachUserToOrg($motherOrg['id'], $user['id'], 'Member', 'Active');
            return true;
        }

        return false;
    }

    // =========================================================================
    // TESTS
    // =========================================================================

    /** Test 1: New org approved → org created + requester & officers attached */
    public function test_new_org_approval_creates_org_and_attaches_people(): void
    {
        $requester = $this->createUser('2021-0001', 'student', 7);
        $officer1  = $this->createUser('2021-0002', 'student', 7);
        $officer1['temp_position'] = 'Vice_president';
        $officer2  = $this->createUser('2021-0003', 'student', 7);
        $officer2['temp_position'] = 'Secretary';

        $applyOrg = [
            'request_type'      => 'apply',
            'organization_name' => 'CSS Society',
            'organization_type' => 'Mother Org',
            'department_id'     => 7,
            'requester_position' => 'Organization President',
        ];

        $org = $this->runApproveLogic($applyOrg, $requester, [$officer1, $officer2]);

        $this->assertNotEmpty($org, 'Organization should be created');
        $this->assertSame('Approved', $org['status']);
        $this->assertSame('Mother Org', $org['type']);
        $this->assertCount(3, $this->organizationUser, 'Requester + 2 officers = 3 rows');
        $this->assertTrue($this->isUserInOrg($org['id'], $requester['id']));
        $this->assertTrue($this->isUserInOrg($org['id'], $officer1['id']));
        $this->assertTrue($this->isUserInOrg($org['id'], $officer2['id']));

        echo "\n  ✅ Test 1 passed: New org approval creates org and attaches 3 people";
    }

    /** Test 2: Renewal → old officers detached, new ones attached */
    public function test_renewal_detaches_old_officers_and_attaches_new(): void
    {
        // Seed an existing org with old officers
        $oldOrg = $this->createOrFindOrg('CSS Society', 'Mother Org', 'Expired', 7);
        $oldPresident = $this->createUser('2020-0001', 'student', 7);
        $this->attachUserToOrg($oldOrg['id'], $oldPresident['id'], 'President', 'Active');
        $this->assertCount(1, $this->organizationUser);

        // New requester for renewal
        $newPresident = $this->createUser('2021-0001', 'student', 7);
        $newOfficer   = $this->createUser('2021-0002', 'student', 7);
        $newOfficer['temp_position'] = 'Secretary';

        $applyOrg = [
            'request_type'       => 'renew',
            'organization_name'  => 'CSS Society',
            'organization_type'  => 'Mother Org',
            'department_id'      => 7,
            'requester_position' => 'Organization President',
        ];

        $org = $this->runApproveLogic($applyOrg, $newPresident, [$newOfficer]);

        $this->assertSame('Approved', $org['status']);
        // Old president should be gone, new president + new officer should be in
        $this->assertFalse($this->isUserInOrg($org['id'], $oldPresident['id']), 'Old president detached');
        $this->assertTrue($this->isUserInOrg($org['id'], $newPresident['id']), 'New president attached');
        $this->assertTrue($this->isUserInOrg($org['id'], $newOfficer['id']), 'New officer attached');
        $this->assertCount(2, $this->organizationUser);

        echo "\n  ✅ Test 2 passed: Renewal detaches old officers, attaches new ones";
    }

    /** Test 3: Student registers AFTER org is Approved → auto-joined to Mother Org */
    public function test_student_register_after_approval_joins_mother_org(): void
    {
        // Approved Mother Org already exists (from a prior approval)
        $this->createOrFindOrg('CSS Society', 'Mother Org', 'Approved', 7);

        $student = $this->createUser('2022-0001', 'student', 7);
        $joined  = $this->runRegisterAutoJoin($student, 7);

        $this->assertTrue($joined, 'Student should be auto-joined');
        $this->assertCount(1, $this->organizationUser);
        $this->assertSame('Member', $this->organizationUser[0]['role']);
        $this->assertSame('Active', $this->organizationUser[0]['membership_status']);

        echo "\n  ✅ Test 3 passed: Student auto-joined to approved Mother Org on registration";
    }

    /** Test 4: Student registers but NO Approved Mother Org → NOT attached (the gap) */
    public function test_student_register_without_approved_org_is_not_attached(): void
    {
        // No orgs at all (just like the current state of the system)
        $student = $this->createUser('2022-0001', 'student', 7);
        $joined  = $this->runRegisterAutoJoin($student, 7);

        $this->assertFalse($joined, 'No org = no auto-join');
        $this->assertEmpty($this->organizationUser, 'organization_user should remain empty');

        echo "\n  ✅ Test 4 passed: No approved org = student NOT attached (confirmed gap)";
    }

    /** Test 5: Student registers under Sub Org department → NOT auto-joined */
    public function test_student_register_does_not_join_sub_org(): void
    {
        // Only a Sub Org exists, not a Mother Org
        $this->createOrFindOrg('CSS Dev Club', 'Sub Org', 'Approved', 7);

        $student = $this->createUser('2022-0001', 'student', 7);
        $joined  = $this->runRegisterAutoJoin($student, 7);

        $this->assertFalse($joined, 'Sub Org should not trigger auto-join');
        $this->assertEmpty($this->organizationUser, 'organization_user should remain empty');

        echo "\n  ✅ Test 5 passed: Sub Org does not trigger auto-join on registration";
    }

    // =========================================================================
    // Enrollment gate tests (OrgAuthController::register() check)
    // =========================================================================

    /** Mirror of the enrollment check added to OrgAuthController::register() */
    private function runEnrollmentCheck(string $studentNumber, array $enrolledStudents): bool
    {
        return in_array($studentNumber, $enrolledStudents);
    }

    /** Test 6: Student ID is in enrolled_students → allowed to proceed */
    public function test_enrolled_student_passes_enrollment_gate(): void
    {
        $enrolledStudents = ['2021-0001', '2021-0002', '2021-0003'];

        $this->assertTrue(
            $this->runEnrollmentCheck('2021-0001', $enrolledStudents),
            'Enrolled student should pass the check'
        );

        echo "\n  ✅ Test 6 passed: Enrolled student ID passes enrollment gate";
    }

    /** Test 7: Student ID NOT in enrolled_students → blocked */
    public function test_non_enrolled_student_is_blocked(): void
    {
        $enrolledStudents = ['2021-0001', '2021-0002'];

        $this->assertFalse(
            $this->runEnrollmentCheck('2022-9999', $enrolledStudents),
            'Non-enrolled student should be blocked'
        );

        echo "\n  ✅ Test 7 passed: Non-enrolled student ID is blocked from registration";
    }

    /** Test 8: Full happy path — enrolled + approved org → registers + auto-joined */
    public function test_full_happy_path_enrolled_student_registers_and_joins_org(): void
    {
        $enrolledStudents = ['2021-0001'];
        $studentNumber    = '2021-0001';

        // Step 1: enrollment gate passes
        $this->assertTrue($this->runEnrollmentCheck($studentNumber, $enrolledStudents));

        // Step 2: approved Mother Org already exists
        $this->createOrFindOrg('CSS Society', 'Mother Org', 'Approved', 7);

        // Step 3: student registers and gets auto-joined
        $student = $this->createUser($studentNumber, 'student', 7);
        $joined  = $this->runRegisterAutoJoin($student, 7);

        $this->assertTrue($joined);
        $this->assertCount(1, $this->organizationUser);
        $this->assertSame('Member', $this->organizationUser[0]['role']);
        $this->assertSame('Active', $this->organizationUser[0]['membership_status']);

        echo "\n  ✅ Test 8 passed: Full happy path — enrolled student registers and auto-joins approved org";
    }
}
