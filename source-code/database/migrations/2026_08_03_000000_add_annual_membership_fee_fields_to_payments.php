<?php

use App\Models\Organization;
use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
            $table->boolean('is_required_membership_fee')->default(false)->after('amount');
            $table->date('membership_cycle_start')->nullable()->after('is_required_membership_fee');
            $table->unique(['organization_id', 'membership_cycle_start'], 'payments_organization_membership_cycle_unique');
        });

        $today = now(config('app.timezone'));
        $year = $today->month >= 8 ? $today->year : $today->subYear()->year;
        $cycleStart = CarbonImmutable::create($year, 8, 1, 0, 0, 0, config('app.timezone'))->toDateString();
        $timestamp = now();

        Organization::query()
            ->where('type', 'Mother Org')
            ->get()
            ->filter(fn (Organization $organization): bool => $organization->canBeUsed())
            ->each(function (Organization $organization) use ($cycleStart, $timestamp): void {
                DB::table('payments')->updateOrInsert(
                    [
                        'organization_id' => $organization->getKey(),
                        'membership_cycle_start' => $cycleStart,
                    ],
                    [
                        'user_id' => null,
                        'title' => 'Membership Fee',
                        'description' => null,
                        'due_date' => $cycleStart,
                        'category' => 'membership_fee',
                        'amount' => 250.00,
                        'gcash_number' => null,
                        'payment_instructions' => null,
                        'is_required_membership_fee' => DB::raw('TRUE'),
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ],
                );
            });
    }

    public function down(): void
    {
        if (DB::table('payments')->whereRaw('is_required_membership_fee = TRUE')->exists()) {
            throw new LogicException('Cannot roll back while protected annual Membership Fee records exist.');
        }

        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique('payments_organization_membership_cycle_unique');
            $table->dropColumn(['is_required_membership_fee', 'membership_cycle_start']);
            $table->foreignId('user_id')->nullable(false)->change();
        });
    }
};
