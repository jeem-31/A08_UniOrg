<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class OsasAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Check if OSAS admin already exists
        $existingOsasAdmin = User::where('email', 'osas@lspu.edu.ph')->first();
        
        if ($existingOsasAdmin) {
            $this->command->info('OSAS Admin already exists!');
            return;
        }

        // Create OSAS Admin account
        $osasAdmin = User::create([
            'first_name' => 'OSAS',
            'last_name' => 'Administrator',
            'email' => 'osas@lspu.edu.ph',
            'student_id' => '02-0000-0001',
            'password' => Hash::make(\Illuminate\Support\Str::random(40)),
            'role' => 'osas_admin',
            'is_active' => true,
        ]);

        $this->command->info('OSAS Admin account created successfully!');
        $this->command->info('Student ID: 02-0000-0001');
        $this->command->warn('⚠️  Please change the password after first login!');
    }
}
