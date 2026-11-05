<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // PostgreSQL-only type conversion; SQLite exposes no information_schema
        // and stores booleans as integers already.
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        // Check current data type and convert if needed
        $columnType = DB::selectOne("
            SELECT data_type 
            FROM information_schema.columns 
            WHERE table_name = 'complaints' 
            AND column_name = 'is_anonymous'
        ")->data_type;
        
        echo "Current is_anonymous type: {$columnType}\n";
        
        if ($columnType !== 'boolean') {
            echo "Converting to boolean...\n";
            // Convert integer/tinyint to boolean
            DB::statement('ALTER TABLE complaints ALTER COLUMN is_anonymous TYPE boolean USING (is_anonymous::int::boolean)');
            echo "Conversion complete!\n";
        } else {
            echo "Already boolean type, no conversion needed.\n";
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No need to reverse - boolean is the correct type
    }
};
