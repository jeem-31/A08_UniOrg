<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the optional name components without rebuilding the users table.
     * Physical column order is not part of the relational schema, and rebuilding
     * the table would discard foreign keys from every table that references users.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'middle_name')) {
                $table->string('middle_name')->nullable()->after('first_name');
            }
            if (! Schema::hasColumn('users', 'middle_initial')) {
                $table->string('middle_initial', 10)->nullable()->after('middle_name');
            }
            if (! Schema::hasColumn('users', 'suffix')) {
                $table->string('suffix', 50)->nullable()->after('last_name');
            }
        });
    }

    public function down(): void
    {
        $columns = array_values(array_filter(
            ['middle_name', 'middle_initial', 'suffix'],
            static fn (string $column): bool => Schema::hasColumn('users', $column),
        ));

        if ($columns !== []) {
            Schema::table('users', static function (Blueprint $table) use ($columns): void {
                $table->dropColumn($columns);
            });
        }
    }
};