<?php

namespace Tests\Feature;

use App\Http\Controllers\Osas\OsasController;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class OsasSettingsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createSchema();
        $this->seedUsers();
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('organization_user');
        Schema::dropIfExists('authentication_events');
        Schema::dropIfExists('accreditation_period_audits');
        Schema::dropIfExists('apply_orgs');
        Schema::dropIfExists('organizations');
        Schema::dropIfExists('departments');
        Schema::dropIfExists('users');
        parent::tearDown();
    }

    public function test_password_update_records_password_changed_at(): void
    {
        $user = User::find(1);
        $this->actingAs($user);
        self::assertNull($user->password_changed_at);

        $request = Request::create('/osas/settings/password', 'POST', [
            'current_password' => 'Pass1234',
            'new_password' => 'NewPass1234!',
            'new_password_confirmation' => 'NewPass1234!',
        ]);
        $response = app(OsasController::class)->updatePassword($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertTrue($response->getData()->success);
        self::assertNotNull(User::find(1)->password_changed_at);
    }

    public function test_audit_feed_skips_reviews_without_a_review_timestamp(): void
    {
        DB::table('apply_orgs')->insert([
            'id' => 1,
            'organization_id' => 1,
            'organization_name' => 'Alpha Society',
            'organization_acronym' => 'AS',
            'status' => 'Approved',
            'reviewed_by' => 1,
            'reviewed_at' => '2026-08-01 10:00:00',
        ]);
        DB::table('apply_orgs')->insert([
            'id' => 2,
            'organization_id' => 1,
            'organization_name' => 'Incomplete Review',
            'organization_acronym' => 'IR',
            'status' => 'Pending',
            'reviewed_by' => 1,
            'reviewed_at' => null,
        ]);

        $this->actingAs(User::find(1));
        $view = app(OsasController::class)->settings();

        self::assertSame('osas.settings', $view->name());
        $recentActions = $view->getData()['recentActions'];
        self::assertCount(1, $recentActions);
        self::assertSame('Approved Accreditation Request', $recentActions->first()['action']);
        self::assertStringContainsString('Alpha Society', $recentActions->first()['description']);
    }

    public function test_accreditation_period_update_applies_to_approved_organizations_and_writes_audit(): void
    {
        $user = User::find(1);
        $this->actingAs($user);
        DB::table('organizations')->insert([
            ['id' => 1, 'name' => 'Alpha Society', 'org_acronym' => 'AS', 'status' => 'Approved'],
            ['id' => 2, 'name' => 'Pending Guild', 'org_acronym' => 'PG', 'status' => 'Pending'],
        ]);

        $request = Request::create('/osas/settings/accreditation', 'POST', [
            'accreditation_start_date' => '2026-08-01',
            'accreditation_end_date' => '2027-07-31',
        ]);
        $response = app(OsasController::class)->setAccreditationPeriod($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(1, $response->getData()->affected);
        $approved = DB::table('organizations')->where('id', 1)->first();
        self::assertSame('2026-08-01 00:00:00', $approved->accreditation_started_at);
        self::assertSame('2027-07-31 00:00:00', $approved->accreditation_expires_at);
        $pending = DB::table('organizations')->where('id', 2)->first();
        self::assertNull($pending->accreditation_started_at);
        self::assertSame(1, DB::table('accreditation_period_audits')->count());
    }

    public function test_accreditation_period_rejects_end_before_start(): void
    {
        $this->actingAs(User::find(1));
        DB::table('organizations')->insert([
            ['id' => 1, 'name' => 'Alpha Society', 'org_acronym' => 'AS', 'status' => 'Approved'],
        ]);

        $response = $this->postJson('/osas/settings/accreditation', [
            'accreditation_start_date' => '2027-01-01',
            'accreditation_end_date' => '2026-01-01',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('accreditation_end_date');
        self::assertNull(DB::table('organizations')->where('id', 1)->value('accreditation_started_at'));
        self::assertSame(0, DB::table('accreditation_period_audits')->count());
    }

    private function createSchema(): void
    {
        (require database_path('migrations/2026_10_21_000000_create_authentication_events_table.php'))->up();

        Schema::create('users', function (Blueprint $table): void {
            $table->temporary();
            $table->id();
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('middle_initial')->nullable();
            $table->string('last_name');
            $table->string('suffix')->nullable();
            $table->string('student_id')->nullable();
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role');
            $table->boolean('is_active')->default(true);
            $table->timestamp('expires_at')->nullable();
            $table->unsignedInteger('management_version')->default(1);
            $table->timestamp('password_changed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('organization_user', function (Blueprint $table): void {
            $table->temporary();
            $table->id();
            $table->foreignId('organization_id');
            $table->foreignId('user_id');
            $table->string('role')->default('member');
            $table->string('membership_status')->default('Active');
            $table->timestamps();
        });
        Schema::create('departments', function (Blueprint $table): void {
            $table->temporary();
            $table->id();
            $table->string('department_name');
            $table->string('department_acronym')->nullable();
            $table->timestamps();
        });
        Schema::create('organizations', function (Blueprint $table): void {
            $table->temporary();
            $table->id();
            $table->string('name');
            $table->string('org_acronym')->nullable();
            $table->string('status');
            $table->timestamp('accreditation_started_at')->nullable();
            $table->timestamp('accreditation_expires_at')->nullable();
            $table->timestamps();
        });
        Schema::create('apply_orgs', function (Blueprint $table): void {
            $table->temporary();
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->string('organization_name')->nullable();
            $table->string('organization_acronym')->nullable();
            $table->string('accreditation_year')->nullable();
            $table->string('application_type')->nullable();
            $table->string('request_type')->nullable();
            $table->string('status');
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('accreditation_period_audits', function (Blueprint $table): void {
            $table->temporary();
            $table->id();
            $table->unsignedBigInteger('actor_user_id');
            $table->string('actor_display');
            $table->date('start_date');
            $table->date('end_date');
            $table->integer('affected_count');
            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->nullable();
        });
    }

    private function seedUsers(): void
    {
        DB::table('users')->insert([
            'id' => 1,
            'first_name' => 'OSAS',
            'last_name' => 'Administrator',
            'email' => 'osas@test.test',
            'password' => Hash::make('Pass1234'),
            'role' => 'osas_admin',
            'is_active' => true,
            'management_version' => 1,
        ]);
    }
}
