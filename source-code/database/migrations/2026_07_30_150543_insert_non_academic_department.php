<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Only insert if ID 11 doesn't exist yet to keep this idempotent
        $exists = DB::table('departments')->where('id', 11)->exists();

        if (! $exists) {
            $timestamp = now();
            DB::table('departments')->insert([
                'id' => 11,
                'department_name' => 'Non-Academic',
                'department_acronym' => 'N/A',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);

            // Advance the sequence past 11 so future auto-increments don't collide
            if (DB::getDriverName() === 'pgsql') {
                DB::statement("
                    SELECT setval(pg_get_serial_sequence('departments', 'id'), GREATEST(11, (SELECT MAX(id) FROM departments)))
                ");
            }
        }
    }

    public function down(): void
    {
        DB::table('departments')->where('id', 11)->delete();
    }
};
