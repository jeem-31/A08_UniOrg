<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixBooleanColumns extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fix:boolean-columns';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix is_published columns to proper boolean type for PostgreSQL';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Fixing boolean columns for PostgreSQL...');
        
        $tables = [
            'announcements',
            'events',
            'library_resources',
            'faqs'
        ];
        
        foreach ($tables as $table) {
            try {
                $this->info("Checking {$table} table...");
                
                // Check if table exists
                $exists = DB::select("SELECT to_regclass('public.{$table}')");
                if (!$exists[0]->to_regclass) {
                    $this->warn("Table {$table} does not exist. Skipping...");
                    continue;
                }
                
                // Check current column type
                $columnInfo = DB::select("
                    SELECT data_type 
                    FROM information_schema.columns 
                    WHERE table_name = '{$table}' 
                    AND column_name = 'is_published'
                ");
                
                if (empty($columnInfo)) {
                    $this->warn("Column is_published does not exist in {$table}. Skipping...");
                    continue;
                }
                
                $currentType = $columnInfo[0]->data_type;
                $this->info("Current type: {$currentType}");
                
                if ($currentType !== 'boolean') {
                    $this->info("Converting {$table}.is_published to boolean...");
                    
                    // First, update any NULL values to false
                    DB::statement("UPDATE {$table} SET is_published = 0 WHERE is_published IS NULL");
                    
                    // Convert the column type
                    DB::statement("ALTER TABLE {$table} ALTER COLUMN is_published TYPE BOOLEAN USING CASE WHEN is_published = 0 THEN false ELSE true END");
                    
                    $this->info("✓ {$table}.is_published converted to boolean");
                } else {
                    $this->info("✓ {$table}.is_published is already boolean");
                }
                
            } catch (\Exception $e) {
                $this->error("Error fixing {$table}: " . $e->getMessage());
            }
        }
        
        $this->info('');
        $this->info('Done! All boolean columns have been fixed.');
        $this->info('Please run: php artisan cache:clear');
        
        return 0;
    }
}
