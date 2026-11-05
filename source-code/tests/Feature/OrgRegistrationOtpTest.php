<?php

namespace Tests\Feature;

use App\Mail\OtpVerificationMail;
use App\Models\Department;
use App\Models\EnrolledStudent;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

final class OrgRegistrationOtpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    private function registrationData(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Juan',
            'middle_name' => 'Dela',
            'middle_initial' => 'D.',
            'last_name' => 'Cruz',
            'suffix' => '',
            'student_number' => '2024-0001',
            'course' => 'BSCS',
            'year_level' => '1st Year',
            'school_year' => '2026-2027',
            'section' => 'A',
            'department_id' => 7,
            'email' => 'juan.cruz@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ], $overrides);
    }

    private function setupMotherOrg(int $departmentId = 7): Organization
    {
        return Organization::create([
            'name' => 'CCS Society',
            'type' => 'Mother Org',
            'status' => 'Approved',
            'department_id' => $departmentId,
            'has_system' => true,
        ]);
    }

    private function seedVerification(array $overrides = [], string $email = 'juan.cruz@example.com'): void
    {
        $values = array_merge([
            'email' => $email,
            'otp_hash' => Hash::make('123456'),
            'data' => json_encode($this->registrationData(['email' => $email])),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(10),
            'last_sent_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides);

        DB::table('registration_verifications')->insert($values);
    }

    public function test_register_sends_otp_and_does_not_create_account_yet(): void
    {
        $this->setupMotherOrg();
        EnrolledStudent::create(['student_id_number' => '2024-0001']);

        $response = $this->postJson('/org/register', $this->registrationData());

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('needs_otp', true);

        // OTP email was sent
        Mail::assertSent(OtpVerificationMail::class, function (OtpVerificationMail $mail) {
            return $mail->hasTo('juan.cruz@example.com');
        });

        // Pending verification is stored
        $this->assertDatabaseHas('registration_verifications', [
            'email' => 'juan.cruz@example.com',
            'attempts' => 0,
        ]);

        // No account exists yet - the student can NOT login yet
        $this->assertDatabaseMissing('users', ['email' => 'juan.cruz@example.com']);
        $this->assertDatabaseMissing('users', ['student_id' => '2024-0001']);
    }

    public function test_register_still_blocks_duplicate_email_and_student_id_before_sending_otp(): void
    {
        $this->setupMotherOrg();
        EnrolledStudent::create(['student_id_number' => '2024-0001']);

        User::factory()->create([
            'email' => 'juan.cruz@example.com',
            'student_id' => '2024-0002',
        ]);

        // Duplicate email
        $this->postJson('/org/register', $this->registrationData())
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'The email has already been taken.');

        // Duplicate student ID
        User::factory()->create([
            'email' => 'other@example.com',
            'student_id' => '2024-0001',
        ]);

        $this->postJson('/org/register', $this->registrationData())
            ->assertUnprocessable()
            ->assertJsonPath('errors.student_number.0', 'The student number has already been taken.');

        // No OTP was sent and no verification row exists for blocked attempts
        Mail::assertNothingSent();
        $this->assertDatabaseCount('registration_verifications', 0);
    }

    public function test_register_still_requires_enrollment_and_approved_system(): void
    {
        $this->setupMotherOrg();
        // Not enrolled
        $this->postJson('/org/register', $this->registrationData())
            ->assertUnprocessable()
            ->assertJsonPath('success', false);

        $this->assertDatabaseCount('registration_verifications', 0);

        // Enrolled but the mother org has no system yet
        EnrolledStudent::create(['student_id_number' => '2024-0001']);
        Organization::query()->update(['has_system' => false]);

        $this->postJson('/org/register', $this->registrationData())
            ->assertUnprocessable()
            ->assertJsonPath('success', false);

        $this->assertDatabaseCount('registration_verifications', 0);
    }

    public function test_register_blocks_department_that_does_not_match_course(): void
    {
        $this->setupMotherOrg();
        EnrolledStudent::create(['student_id_number' => '2024-0001']);

        // BSCS belongs to CCS (7), but the registrant picked CTE (6).
        $this->postJson('/org/register', $this->registrationData([
            'department_id' => 6,
        ]))
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.department_id.0', 'Department does not match this course. It belongs to College of Computer Studies. Please select the correct department.');

        // No OTP is sent and no verification row is created for a blocked attempt.
        Mail::assertNothingSent();
        $this->assertDatabaseCount('registration_verifications', 0);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_register_allows_department_matching_the_course(): void
    {
        $this->setupMotherOrg();
        EnrolledStudent::create(['student_id_number' => '2024-0001']);

        $this->postJson('/org/register', $this->registrationData())
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('needs_otp', true);

        Mail::assertSent(OtpVerificationMail::class);
    }

    public function test_register_allows_any_department_for_unknown_course(): void
    {
        $this->setupMotherOrg();
        $this->setupMotherOrg(6);
        EnrolledStudent::create(['student_id_number' => '2024-0001']);

        // A course without a keyword match skips the department check entirely.
        $this->postJson('/org/register', $this->registrationData([
            'course' => 'Bachelor of Science in Environmental Science',
            'department_id' => 6,
        ]))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('needs_otp', true);

        Mail::assertSent(OtpVerificationMail::class);
    }

    public function test_verify_otp_with_wrong_code_fails_and_increments_attempts(): void
    {
        $this->seedVerification();

        $response = $this->postJson('/org/register/verify-otp', [
            'email' => 'juan.cruz@example.com',
            'otp' => '999999',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('registration_verifications', [
            'email' => 'juan.cruz@example.com',
            'attempts' => 1,
        ]);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_verify_otp_with_correct_code_creates_account_and_joins_mother_org(): void
    {
        $motherOrg = $this->setupMotherOrg();
        EnrolledStudent::create(['student_id_number' => '2024-0001']);
        $this->seedVerification();

        $response = $this->postJson('/org/register/verify-otp', [
            'email' => 'juan.cruz@example.com',
            'otp' => '123456',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('redirect', route('org.login'));

        // Account created with the submitted data
        $this->assertDatabaseHas('users', [
            'first_name' => 'Juan',
            'middle_name' => 'Dela',
            'middle_initial' => 'D.',
            'last_name' => 'Cruz',
            'email' => 'juan.cruz@example.com',
            'student_id' => '2024-0001',
            'role' => 'student',
            'department_id' => 7,
        ]);

        $user = User::where('email', 'juan.cruz@example.com')->firstOrFail();
        $this->assertTrue(Hash::check('password123', $user->password));

        // Auto-joined the Mother Org
        $this->assertDatabaseHas('organization_user', [
            'user_id' => $user->id,
            'organization_id' => $motherOrg->id,
            'role' => 'Member',
            'membership_status' => 'Active',
        ]);

        // Verification record is consumed
        $this->assertDatabaseCount('registration_verifications', 0);
    }

    public function test_only_middle_initial_with_dot_is_stored_derived_from_full_middle_name(): void
    {
        $this->setupMotherOrg();
        EnrolledStudent::create(['student_id_number' => '2024-0001']);
        $this->seedVerification([
            'data' => json_encode($this->registrationData([
                'middle_initial' => '',
                'middle_name' => 'Rocamora',
            ])),
        ]);

        $this->postJson('/org/register/verify-otp', [
            'email' => 'juan.cruz@example.com',
            'otp' => '123456',
        ])->assertOk();

        // The full middle name stays in middle_name; the initial with a dot
        // (derived from the full name) goes to middle_initial.
        $this->assertDatabaseHas('users', [
            'email' => 'juan.cruz@example.com',
            'middle_name' => 'Rocamora',
            'middle_initial' => 'R.',
        ]);

        $user = User::where('email', 'juan.cruz@example.com')->firstOrFail();
        $this->assertSame('Juan Rocamora Cruz', $user->full_name);
        $this->assertSame('Juan R. Cruz', $user->full_name_with_middle_initial);
    }

    public function test_middle_initial_without_dot_still_stores_with_dot_for_display(): void
    {
        $this->setupMotherOrg();
        EnrolledStudent::create(['student_id_number' => '2024-0001']);
        $this->seedVerification([
            'data' => json_encode($this->registrationData([
                'middle_initial' => 'R',
                'middle_name' => 'Rocamora',
            ])),
        ]);

        $this->postJson('/org/register/verify-otp', [
            'email' => 'juan.cruz@example.com',
            'otp' => '123456',
        ])->assertOk();

        // Even without a dot in the input, the DB stores "R." in middle_initial
        // while middle_name keeps the full name.
        $this->assertDatabaseHas('users', [
            'email' => 'juan.cruz@example.com',
            'middle_name' => 'Rocamora',
            'middle_initial' => 'R.',
        ]);

        $user = User::where('email', 'juan.cruz@example.com')->firstOrFail();
        $this->assertSame('Juan Rocamora Cruz', $user->full_name);
        $this->assertSame('Juan R. Cruz', $user->full_name_with_middle_initial);
    }

    public function test_verify_otp_with_expired_code_fails(): void
    {
        $this->seedVerification([
            'expires_at' => now()->subMinutes(11),
        ]);

        $this->postJson('/org/register/verify-otp', [
            'email' => 'juan.cruz@example.com',
            'otp' => '123456',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('success', false);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_verify_otp_after_too_many_attempts_is_locked(): void
    {
        $this->seedVerification(['attempts' => 5]);

        $this->postJson('/org/register/verify-otp', [
            'email' => 'juan.cruz@example.com',
            'otp' => '123456',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('success', false);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_verify_otp_without_pending_registration_fails(): void
    {
        $this->postJson('/org/register/verify-otp', [
            'email' => 'nobody@example.com',
            'otp' => '123456',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('success', false);
    }

    public function test_resend_otp_sends_a_new_code_and_resets_attempts(): void
    {
        $this->seedVerification([
            'attempts' => 3,
            'last_sent_at' => now()->subMinutes(2),
        ]);

        $response = $this->postJson('/org/register/resend-otp', [
            'email' => 'juan.cruz@example.com',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true);

        Mail::assertSent(OtpVerificationMail::class);

        $this->assertDatabaseHas('registration_verifications', [
            'email' => 'juan.cruz@example.com',
            'attempts' => 0,
        ]);
    }

    public function test_resend_otp_within_cooldown_is_rejected(): void
    {
        $this->seedVerification(['last_sent_at' => now()]);

        $this->postJson('/org/register/resend-otp', [
            'email' => 'juan.cruz@example.com',
        ])
            ->assertStatus(429);
    }

    public function test_registered_account_can_login_after_otp_verification(): void
    {
        $this->setupMotherOrg();
        EnrolledStudent::create(['student_id_number' => '2024-0001']);

        // Register -> OTP flow
        $this->postJson('/org/register', $this->registrationData())->assertOk();

        // Grab the OTP that was "sent" and verify with it
        $otp = OtpVerificationMail::class;
        $mail = Mail::sent($otp)->first();
        $this->assertNotNull($mail, 'OTP email should have been sent');

        // Extract the code from the email body
        $code = $this->extractCodeFromMailable($mail);

        $this->postJson('/org/register/verify-otp', [
            'email' => 'juan.cruz@example.com',
            'otp' => $code,
        ])
            ->assertOk()
            ->assertJsonPath('success', true);

        // Now the student can login
        $this->postJson('/org/login', [
            'student_id' => '2024-0001',
            'password' => 'password123',
        ])
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    private function extractCodeFromMailable(OtpVerificationMail $mail): string
    {
        $body = $mail->render();
        preg_match('/verification code is:\s*(\d{6})/', $body, $matches);

        return $matches[1] ?? '';
    }
}
