<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Users table indexes
        Schema::table('users', function (Blueprint $table) {
            $table->index('role', 'users_role_idx');
            $table->index('student_id', 'users_student_id_idx');
            $table->index(['role', 'created_at'], 'users_role_created_idx');
        });

        // Payments table indexes
        Schema::table('payments', function (Blueprint $table) {
            $table->index(['category', 'created_at'], 'payments_category_created_idx');
            $table->index('user_id', 'payments_user_id_idx');
            $table->index('due_date', 'payments_due_date_idx');
        });

        // Student Payments table indexes
        Schema::table('student_payments', function (Blueprint $table) {
            $table->index(['status', 'created_at'], 'student_payments_status_created_idx');
            $table->index(['user_id', 'payment_id'], 'student_payments_user_payment_idx');
            $table->index(['payment_method', 'created_at'], 'student_payments_method_created_idx');
            $table->index('paid_at', 'student_payments_paid_at_idx');
        });

        // Elections table indexes
        Schema::table('elections', function (Blueprint $table) {
            $table->index('is_active', 'elections_posted_active_idx');
            $table->index(['start_date', 'end_date'], 'elections_dates_idx');
            $table->index('results_published', 'elections_results_published_idx');
        });

        // Candidates table indexes
        Schema::table('candidates', function (Blueprint $table) {
            $table->index(['election_id', 'position'], 'candidates_election_position_idx');
            $table->index('vote_count', 'candidates_vote_count_idx');
        });

        // Votes table indexes
        Schema::table('votes', function (Blueprint $table) {
            $table->index(['election_id', 'user_id'], 'votes_election_user_idx');
            $table->index('candidate_id', 'votes_candidate_id_idx');
        });

        // Library Resources table indexes
        Schema::table('library_resources', function (Blueprint $table) {
            $table->index(['is_published', 'created_at'], 'library_resources_published_created_idx');
            $table->index(['category', 'created_at'], 'library_resources_category_created_idx');
            $table->index(['course', 'created_at'], 'library_resources_course_created_idx');
            $table->index('user_id', 'library_resources_user_id_idx');
        });

        // Complaints table indexes
        Schema::table('complaints', function (Blueprint $table) {
            $table->index(['user_id', 'created_at'], 'complaints_user_created_idx');
            $table->index(['status', 'created_at'], 'complaints_status_created_idx');
            $table->index('type', 'complaints_type_idx');
            $table->index('is_anonymous', 'complaints_anonymous_idx');
        });

        // Edushare Items table indexes
        Schema::table('edushare_items', function (Blueprint $table) {
            $table->index(['user_id', 'created_at'], 'edushare_items_user_created_idx');
            $table->index('is_public', 'edushare_items_public_idx');
        });

        // Notifications table indexes (if exists)
        if (Schema::hasTable('notifications')) {
            Schema::table('notifications', function (Blueprint $table) {
                $table->index(['notifiable_id', 'notifiable_type', 'read_at'], 'notifications_notifiable_read_idx');
                $table->index('created_at', 'notifications_created_at_idx');
            });
        }

        // Student Documents table indexes (if exists)
        if (Schema::hasTable('student_documents')) {
            Schema::table('student_documents', function (Blueprint $table) {
                $table->index(['user_id', 'document_type'], 'student_documents_user_type_idx');
            });
        }

        // Shared Documents table indexes (if exists)
        if (Schema::hasTable('shared_documents')) {
            Schema::table('shared_documents', function (Blueprint $table) {
                $table->index('student_id', 'shared_documents_student_id_idx');
                $table->index('student_document_id', 'shared_documents_doc_id_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_role_idx');
            $table->dropIndex('users_student_id_idx');
            $table->dropIndex('users_role_created_idx');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('payments_category_created_idx');
            $table->dropIndex('payments_user_id_idx');
            $table->dropIndex('payments_due_date_idx');
        });

        Schema::table('student_payments', function (Blueprint $table) {
            $table->dropIndex('student_payments_status_created_idx');
            $table->dropIndex('student_payments_user_payment_idx');
            $table->dropIndex('student_payments_method_created_idx');
            $table->dropIndex('student_payments_paid_at_idx');
        });

        Schema::table('elections', function (Blueprint $table) {
            $table->dropIndex('elections_posted_active_idx');
            $table->dropIndex('elections_dates_idx');
            $table->dropIndex('elections_results_published_idx');
        });

        Schema::table('candidates', function (Blueprint $table) {
            $table->dropIndex('candidates_election_position_idx');
            $table->dropIndex('candidates_vote_count_idx');
        });

        Schema::table('votes', function (Blueprint $table) {
            $table->dropIndex('votes_election_user_idx');
            $table->dropIndex('votes_candidate_id_idx');
        });

        Schema::table('library_resources', function (Blueprint $table) {
            $table->dropIndex('library_resources_published_created_idx');
            $table->dropIndex('library_resources_category_created_idx');
            $table->dropIndex('library_resources_course_created_idx');
            $table->dropIndex('library_resources_user_id_idx');
        });

        Schema::table('complaints', function (Blueprint $table) {
            $table->dropIndex('complaints_user_created_idx');
            $table->dropIndex('complaints_status_created_idx');
            $table->dropIndex('complaints_type_idx');
            $table->dropIndex('complaints_anonymous_idx');
        });

        Schema::table('edushare_items', function (Blueprint $table) {
            $table->dropIndex('edushare_items_user_created_idx');
            $table->dropIndex('edushare_items_public_idx');
        });

        if (Schema::hasTable('notifications')) {
            Schema::table('notifications', function (Blueprint $table) {
                $table->dropIndex('notifications_notifiable_read_idx');
                $table->dropIndex('notifications_created_at_idx');
            });
        }

        if (Schema::hasTable('student_documents')) {
            Schema::table('student_documents', function (Blueprint $table) {
                $table->dropIndex('student_documents_user_type_idx');
            });
        }

        if (Schema::hasTable('shared_documents')) {
            Schema::table('shared_documents', function (Blueprint $table) {
                $table->dropIndex('shared_documents_student_id_idx');
                $table->dropIndex('shared_documents_doc_id_idx');
            });
        }
    }
};
