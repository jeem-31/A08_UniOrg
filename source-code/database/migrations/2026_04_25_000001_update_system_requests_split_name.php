<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('system_requests', function (Blueprint $table) {
            // Add new columns
            $table->string('requester_first_name')->nullable()->after('organization_type');
            $table->string('requester_last_name')->nullable()->after('requester_first_name');
        });

        // Migrate existing data if any
        DB::table('system_requests')->get()->each(function ($request) {
            $nameParts = explode(' ', trim($request->requester_name));
            $firstName = $nameParts[0] ?? '';
            $lastName = end($nameParts) ?? '';
            
            DB::table('system_requests')
                ->where('id', $request->id)
                ->update([
                    'requester_first_name' => $firstName,
                    'requester_last_name' => $lastName,
                ]);
        });

        Schema::table('system_requests', function (Blueprint $table) {
            // Make columns required after migration
            $table->string('requester_first_name')->nullable(false)->change();
            $table->string('requester_last_name')->nullable(false)->change();
            
            // Drop old column
            $table->dropColumn('requester_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('system_requests', function (Blueprint $table) {
            // Add back old column
            $table->string('requester_name')->nullable()->after('organization_type');
        });

        // Migrate data back
        DB::table('system_requests')->get()->each(function ($request) {
            $fullName = trim($request->requester_first_name . ' ' . $request->requester_last_name);
            
            DB::table('system_requests')
                ->where('id', $request->id)
                ->update(['requester_name' => $fullName]);
        });

        Schema::table('system_requests', function (Blueprint $table) {
            $table->string('requester_name')->nullable(false)->change();
            $table->dropColumn(['requester_first_name', 'requester_last_name']);
        });
    }
};
