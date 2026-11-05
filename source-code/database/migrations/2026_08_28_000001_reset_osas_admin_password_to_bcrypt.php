<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    /**
     * Ensure OSAS admin can login with bcrypt Pass1234.
     * Targets role='osas_admin' OR email='osas@lspu.edu.ph' to cover
     * live drift 00-0000-0000 vs seeder 02-0000-0001. Idempotent, no destructive.
     */
    public function up(): void
    {
        DB::transaction(function (): void {
            // Update any existing OSAS admin(s) to bcrypt Pass1234
            DB::table('users')
                ->where(function ($q) {
                    $q->where('role', 'osas_admin')
                      ->orWhere('email', 'osas@lspu.edu.ph');
                })
                ->update([
                    'password' => Hash::make('Pass1234'),
                    'password_changed_at' => now(),
                ]);

            // Ensure at least one OSAS admin exists if table was empty (idempotent)
            if (!DB::table('users')->where('role', 'osas_admin')->exists()) {
                DB::table('users')->insert([
                    'first_name' => 'OSAS',
                    'last_name' => 'Administrator',
                    'email' => 'osas@lspu.edu.ph',
                    'student_id' => '00-0000-0000',
                    'password' => Hash::make('Pass1234'),
                    'role' => 'osas_admin',
                    'is_active' => true,
                    'password_changed_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }

    public function down(): void
    {
        // Cannot restore previous plain text / OSASAdmin2026! hash
    }
};
