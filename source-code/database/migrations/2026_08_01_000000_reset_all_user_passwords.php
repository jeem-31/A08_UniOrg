<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    /**
     * Reset all existing account passwords for the current development setup.
     */
    public function up(): void
    {
        DB::transaction(function (): void {
            DB::table('users')
                ->select('id')
                ->orderBy('id')
                ->get()
                ->each(function (object $user): void {
                    DB::table('users')
                        ->where('id', $user->id)
                        ->update([
                            'password' => Hash::make('Pass1234'),
                            'password_changed_at' => now(),
                        ]);
                });
        });
    }

    public function down(): void
    {
        // Passwords cannot be restored because their previous values are not recoverable.
    }
};
