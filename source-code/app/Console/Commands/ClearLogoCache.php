<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\OrganizationSettings;

class ClearLogoCache extends Command
{
    protected $signature = 'logo:clear';
    protected $description = 'Clear logo URL from database and session cache';

    public function handle()
    {
        // Clear all organization settings logo URLs
        $settings = OrganizationSettings::all();
        
        foreach ($settings as $setting) {
            if ($setting->logo_url && str_contains($setting->logo_url, 'soccsyncLogo-removebg-preview.png')) {
                $this->info("Clearing logo for organization ID: {$setting->organization_id}");
                $this->info("Current logo_url: {$setting->logo_url}");
                
                $setting->logo_url = null;
                $setting->save();
                
                $this->info("✅ Logo URL cleared");
            }
        }
        
        $this->info("✅ All default logos cleared from database");
        $this->info("Now using local asset for default logo");
        
        return 0;
    }
}
