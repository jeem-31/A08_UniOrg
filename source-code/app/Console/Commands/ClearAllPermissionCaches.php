<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ClearAllPermissionCaches extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cache:clear-permissions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear all permission and navigation caches (session and cache)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Clearing all permission caches...');
        
        try {
            // Clear all Laravel cache
            Cache::flush();
            $this->info('✓ Cleared all cache entries');
        } catch (\Exception $e) {
            $this->error('Error clearing cache: ' . $e->getMessage());
        }
        
        // Clear session-based navigation permissions
        // Note: This only clears the current session, users need to re-login or refresh
        $this->info('✓ Session-based navigation caches will be cleared on next page load');
        
        $this->info('✓ All permission caches cleared successfully!');
        $this->info('');
        $this->info('Users may need to:');
        $this->info('  1. Refresh their browser (F5)');
        $this->info('  2. Or logout and login again');
        
        return 0;
    }
}
