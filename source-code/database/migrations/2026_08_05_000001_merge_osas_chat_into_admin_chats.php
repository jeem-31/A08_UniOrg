<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Channel discriminator: 'student' = student <-> org admin chat,
        // 'osas' = OSAS <-> organization chat (shared inbox for all org admins).
        if (! Schema::hasColumn('admin_chats', 'channel')) {
            Schema::table('admin_chats', function (Blueprint $table) {
                $table->string('channel', 10)->default('student')->after('id');
            });
        }

        // Extend sender_type so OSAS conversations fit. Laravel's enum() on
        // PostgreSQL/SQLite creates a varchar column guarded by a CHECK.
        if (DB::getDriverName() === 'sqlite') {
            $this->rebuildSqliteSenderTypeConstraint("('student', 'admin', 'osas', 'org')");
        } elseif (DB::getDriverName() === 'pgsql') {
            $constraintName = 'admin_chats_sender_type_check';
            $constraintExists = DB::select(
                "SELECT 1 FROM pg_constraint WHERE conname = ? AND conrelid = 'admin_chats'::regclass",
                [$constraintName]
            );

            if ($constraintExists) {
                DB::statement("ALTER TABLE admin_chats DROP CONSTRAINT {$constraintName}");
                DB::statement(
                    "ALTER TABLE admin_chats ADD CONSTRAINT {$constraintName} "
                    . "CHECK (sender_type IN ('student', 'admin', 'osas', 'org'))"
                );
            }

            // Older schemas may define sender_type as a native enum instead.
            $enumType = 'admin_chats_sender_type_enum';
            $enumExists = DB::select("SELECT 1 FROM pg_type WHERE typname = ?", [$enumType]);

            if ($enumExists) {
                DB::statement("ALTER TYPE {$enumType} ADD VALUE IF NOT EXISTS 'osas'");
                DB::statement("ALTER TYPE {$enumType} ADD VALUE IF NOT EXISTS 'org'");
            }
        }

        Schema::table('admin_chats', function (Blueprint $table) {
            $table->index('channel', 'admin_chats_channel_idx');
            $table->index(['channel', 'organization_id'], 'admin_chats_channel_org_idx');
            $table->index(['channel', 'sender_type', 'is_read'], 'admin_chats_channel_sender_read_idx');
            $table->index(['channel', 'organization_id', 'created_at'], 'admin_chats_channel_org_created_idx');
        });

        // OSAS chat now lives inside admin_chats (channel = 'osas'). The old
        // feedback module was replaced by the chat and is removed as well.
        Schema::dropIfExists('osas_chats');
        Schema::dropIfExists('osas_feedback');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('admin_chats', function (Blueprint $table) {
            $table->dropIndex('admin_chats_channel_org_created_idx');
            $table->dropIndex('admin_chats_channel_sender_read_idx');
            $table->dropIndex('admin_chats_channel_org_idx');
            $table->dropIndex('admin_chats_channel_idx');
        });

        Schema::table('admin_chats', function (Blueprint $table) {
            $table->dropColumn('channel');
        });

        if (DB::getDriverName() === 'sqlite') {
            $this->rebuildSqliteSenderTypeConstraint("('student', 'admin')");
        } elseif (DB::getDriverName() === 'pgsql') {
            $constraintName = 'admin_chats_sender_type_check';
            DB::statement("ALTER TABLE admin_chats DROP CONSTRAINT IF EXISTS {$constraintName}");
            DB::statement(
                "ALTER TABLE admin_chats ADD CONSTRAINT {$constraintName} "
                . "CHECK (sender_type IN ('student', 'admin'))"
            );
        }

        Schema::create('osas_chats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->onDelete('cascade');
            $table->foreignId('sender_id')->constrained('users')->onDelete('cascade');
            $table->text('message');
            $table->boolean('is_from_osas')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('osas_feedback', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->foreignId('osas_id')->constrained('users')->onDelete('cascade');
            $blueprint->foreignId('organization_id')->constrained('organizations')->onDelete('cascade');
            $blueprint->string('subject');
            $blueprint->text('message');
            $blueprint->enum('priority', ['normal', 'urgent', 'critical'])->default('normal');
            $blueprint->enum('status', ['open', 'in_progress', 'resolved', 'closed'])->default('open');
            $blueprint->text('admin_response')->nullable();
            $blueprint->foreignId('responded_by')->nullable()->constrained('users')->onDelete('set null');
            $blueprint->timestamp('responded_at')->nullable();
            $blueprint->timestamps();
        });
    }

    /**
     * SQLite cannot alter check constraints, so rebuild the table with the
     * updated sender_type constraint while preserving rows and indexes.
     */
    private function rebuildSqliteSenderTypeConstraint(string $allowedValues): void
    {
        $ddl = DB::selectOne(
            "SELECT sql FROM sqlite_master WHERE type = 'table' AND name = 'admin_chats'"
        )?->sql;

        if (! $ddl) {
            return;
        }

        $indexes = DB::select(
            "SELECT sql FROM sqlite_master WHERE type = 'index' AND tbl_name = 'admin_chats' AND sql IS NOT NULL"
        );

        $replaced = preg_replace(
            '/sender_type"\s+in\s*\(\s*\'student\',\s*\'admin\'\s*\)/i',
            'sender_type" in ' . $allowedValues,
            $ddl
        );

        DB::statement('ALTER TABLE admin_chats RENAME TO admin_chats_old');
        DB::statement($replaced);
        DB::statement('INSERT INTO "admin_chats" SELECT * FROM "admin_chats_old"');
        DB::statement('DROP TABLE "admin_chats_old"');

        foreach ($indexes as $index) {
            DB::statement($index->sql);
        }
    }
};
