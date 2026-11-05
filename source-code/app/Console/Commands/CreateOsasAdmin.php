<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateOsasAdmin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'osas:create-admin 
                            {email? : The email address for the OSAS admin}
                            {password? : The password for the OSAS admin}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create an OSAS Administrator account';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email') ?? $this->ask('Enter email address', 'osas@lspu.edu.ph');
        
        // Check if user already exists
        if (User::where('email', $email)->exists()) {
            $this->error("User with email {$email} already exists!");
            return 1;
        }

        $password = $this->argument('password') ?? $this->secret('Enter a password of at least 12 characters');
        
        if (empty($password) || strlen($password) < 12) {
            $this->error('A password of at least 12 characters is required.');
            return 1;
        }

        $firstName = $this->ask('Enter first name', 'OSAS');
        $lastName = $this->ask('Enter last name', 'Administrator');
        $studentId = $this->ask('Enter student ID (format: 00-0000-0000)', '00-0000-0000');

        // Create OSAS Admin
        $user = User::create([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'student_id' => $studentId,
            'password' => Hash::make($password),
            'role' => 'osas_admin',
            'is_active' => true,
        ]);

        $this->info('✅ OSAS Admin account created successfully!');
        $this->table(
            ['Field', 'Value'],
            [
                ['Name', $user->full_name],
                ['Email', $user->email],
                ['Role', $user->role],
                ['Password', $password],
            ]
        );
        
        $this->warn('⚠️  Please change the password after first login!');
        
        return 0;
    }
}
