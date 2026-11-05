<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_proposals', function (Blueprint $table) {
            $table->dropColumn([
                'letter_of_communication',
                'plan_of_activity',
                'program_of_activity',
                'budget_proposal',
                'proposed_budget',
            ]);
        });

        Schema::table('event_proposals', function (Blueprint $table) {
            $table->string('request_letter')->nullable()->after('submitted_by');
        });
    }

    public function down(): void
    {
        Schema::table('event_proposals', function (Blueprint $table) {
            $table->dropColumn('request_letter');
        });

        Schema::table('event_proposals', function (Blueprint $table) {
            $table->string('letter_of_communication')->nullable()->after('submitted_by');
            $table->string('plan_of_activity')->nullable()->after('letter_of_communication');
            $table->string('program_of_activity')->nullable()->after('plan_of_activity');
            $table->string('budget_proposal')->nullable()->after('program_of_activity');
            $table->decimal('proposed_budget', 10, 2)->nullable()->after('budget_proposal');
        });
    }
};
