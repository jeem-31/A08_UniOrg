<?php

namespace Tests\Unit\AccountManagement;

use App\Models\User;
use App\Services\AccountManagement\AssociatedRecordInspection;
use App\Services\AccountManagement\AssociatedRecordInspector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** **Validates: Requirements 4.4, 4.5, 4.6, 4.8** */
final class AssociatedRecordInspectorTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        config(['account-management.deletion.minimum_account_retention_seconds' => 0]);

        parent::tearDown();
    }

    public function test_it_marks_an_account_without_associations_or_restrictions_as_eligible(): void
    {
        $result = $this->inspector()->inspect(User::factory()->create());

        self::assertTrue($result->canBeDeleted());
        self::assertSame(AssociatedRecordInspection::ELIGIBLE, $result->reasonCode());
    }

    public function test_it_returns_a_generic_associated_record_code_for_notification_references(): void
    {
        $user = User::factory()->create();
        DB::table('notifications')->insert([
            'id' => '710df527-1f28-4ebc-8d8b-77e76df3ce67',
            'type' => 'App\\Notifications\\AccountNotice',
            'notifiable_type' => $user->getMorphClass(),
            'notifiable_id' => $user->getKey(),
            'data' => '{}',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $result = $this->inspector()->inspect($user);

        self::assertFalse($result->canBeDeleted());
        self::assertSame(AssociatedRecordInspection::ASSOCIATED_RECORDS, $result->reasonCode());
        self::assertStringNotContainsString('notification', $result->reasonCode());
    }

    public function test_it_returns_the_same_generic_code_for_audit_references(): void
    {
        if (! Schema::hasTable('account_management_audits')) {
            Schema::create('account_management_audits', function ($table): void {
                $table->id();
                $table->unsignedBigInteger('actor_user_id')->nullable();
                $table->unsignedBigInteger('affected_user_id')->nullable();
            });
        }

        $user = User::factory()->create();
        $columns = array_flip(Schema::getColumnListing('account_management_audits'));
        $values = ['affected_user_id' => $user->getKey()];
        foreach ([
            'action' => 'account.deleted',
            'actor_display' => 'OSAS administrator',
            'affected_display' => 'Deleted account',
            'changes' => '{}',
            'occurred_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ] as $column => $value) {
            if (isset($columns[$column])) {
                $values[$column] = $value;
            }
        }
        DB::table('account_management_audits')->insert($values);

        $result = $this->inspector()->inspect($user);

        self::assertFalse($result->canBeDeleted());
        self::assertSame(AssociatedRecordInspection::ASSOCIATED_RECORDS, $result->reasonCode());
        self::assertStringNotContainsString('audit', $result->reasonCode());
    }

    public function test_it_evaluates_the_configured_account_retention_period_without_exposing_dates(): void
    {
        config(['account-management.deletion.minimum_account_retention_seconds' => 3600]);
        $user = User::factory()->create(['created_at' => now()->subMinutes(30)]);

        $result = $this->inspector()->inspect($user);

        self::assertFalse($result->canBeDeleted());
        self::assertSame(AssociatedRecordInspection::RETENTION_ACTIVE, $result->reasonCode());
        self::assertSame(AssociatedRecordInspection::RETENTION_ACTIVE, $this->inspector()->deletionRestriction($user));
    }

    private function inspector(): AssociatedRecordInspector
    {
        return $this->app->make(AssociatedRecordInspector::class);
    }
}
