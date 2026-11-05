<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OrganizationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $organizations = [
            // Mother Org
            [
                'name' => 'Supreme Student Council (SSC)',
                'type' => 'Mother Org',
                'status' => 'Approved',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            
            // Academic Organizations
            [
                'name' => 'College of Computer Studies Council (CCSC)',
                'type' => 'Sub-Org',
                'status' => 'Approved',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'College of Engineering Council',
                'type' => 'Sub-Org',
                'status' => 'Approved',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'College of Business Administration Council',
                'type' => 'Sub-Org',
                'status' => 'Approved',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            
            // Non-Academic Organizations
            [
                'name' => 'Dance Troupe',
                'type' => 'Non-Academic',
                'status' => 'Approved',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Debate Society',
                'type' => 'Non-Academic',
                'status' => 'Approved',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Photography Club',
                'type' => 'Non-Academic',
                'status' => 'Pending',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('organizations')->insert($organizations);

        // Create default settings for each organization
        $orgIds = DB::table('organizations')->pluck('id');
        
        foreach ($orgIds as $orgId) {
            DB::table('organization_settings')->insert([
                'organization_id' => $orgId,
                'logo_url' => null,
                'has_announcements' => DB::raw('true'),
                'has_events' => DB::raw('true'),
                'has_payments' => DB::raw('true'),
                'has_elections' => DB::raw('true'),
                'has_elibrary' => DB::raw('true'),
                'has_edushare' => DB::raw('true'),
                'welcome_text' => 'Welcome to our organization!',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
