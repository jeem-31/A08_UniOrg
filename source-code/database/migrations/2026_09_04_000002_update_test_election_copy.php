<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('elections')
            ->where('title', 'Pure Web Flow Election')
            ->orWhere('description', 'like', '%Testing pure web flow%')
            ->update([
                'title' => 'SOCCS General Election 2026',
                'description' => 'Official election of the Student Organization of the College of Computer Studies (SOCCS) for A.Y. 2026-2027',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('elections')
            ->where('title', 'SOCCS General Election 2026')
            ->update([
                'title' => 'Pure Web Flow Election',
                'description' => 'Testing pure web flow without script bypass',
                'updated_at' => now(),
            ]);
    }
};
