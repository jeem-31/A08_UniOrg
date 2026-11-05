<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const CATEGORIES = "'tour_fee', 'membership_fee', 'event_fee', 'document_fee', 'medical_fee', 'other'";

    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE payments DROP CONSTRAINT IF EXISTS payments_category_check');
            DB::statement('ALTER TABLE payments ADD CONSTRAINT payments_category_check CHECK (category IN (' . self::CATEGORIES . '))');
            DB::statement('ALTER TABLE student_payments DROP CONSTRAINT IF EXISTS student_payments_category_check');
            DB::statement('ALTER TABLE student_payments ADD CONSTRAINT student_payments_category_check CHECK (category IN (' . self::CATEGORIES . '))');

            return;
        }

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE payments MODIFY category ENUM(' . self::CATEGORIES . ') NOT NULL');
            DB::statement('ALTER TABLE student_payments MODIFY category ENUM(' . self::CATEGORIES . ') NOT NULL');
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if (
            DB::table('payments')->where('category', 'other')->exists()
            || DB::table('student_payments')->where('category', 'other')->exists()
        ) {
            throw new \LogicException('Cannot roll back payment categories while records use the Others category. Reclassify those records first.');
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE payments DROP CONSTRAINT IF EXISTS payments_category_check');
            DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_category_check CHECK (category IN ('tour_fee', 'membership_fee', 'event_fee', 'document_fee', 'medical_fee'))");
            DB::statement('ALTER TABLE student_payments DROP CONSTRAINT IF EXISTS student_payments_category_check');
            DB::statement("ALTER TABLE student_payments ADD CONSTRAINT student_payments_category_check CHECK (category IN ('tour_fee', 'membership_fee', 'event_fee', 'document_fee', 'medical_fee'))");

            return;
        }

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE payments MODIFY category ENUM('tour_fee', 'membership_fee', 'event_fee', 'document_fee', 'medical_fee') NOT NULL");
            DB::statement("ALTER TABLE student_payments MODIFY category ENUM('tour_fee', 'membership_fee', 'event_fee', 'document_fee', 'medical_fee') NOT NULL");
        }
    }
};
