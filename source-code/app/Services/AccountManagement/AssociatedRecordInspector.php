<?php

namespace App\Services\AccountManagement;

use App\Models\User;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Determines whether a user can be permanently deleted without exposing the
 * persisted record that blocked the operation or a storage failure.
 */
final class AssociatedRecordInspector
{
    /** @var array<string, array{0: string, 1: string}> */
    private const REFERENCE_CHECKS = [
        'organization_membership' => ['organization_user', 'user_id'],
        'announcement_activity' => ['announcements', 'user_id'],
        'event_activity' => ['events', 'user_id'],
        'payment_activity' => ['payments', 'user_id'],
        'student_payment_activity' => ['student_payments', 'user_id'],
        'event_registration_activity' => ['event_registrations', 'user_id'],
        'vote_activity' => ['votes', 'user_id'],
        'student_document_ownership' => ['student_documents', 'user_id'],
        'shared_document_recipient' => ['shared_documents', 'student_id'],
        'library_resource_ownership' => ['library_resources', 'user_id'],
        'edushare_item_ownership' => ['edushare_items', 'user_id'],
        'edushare_folder_ownership' => ['edushare_folders', 'user_id'],
        'edushare_share_ownership' => ['edushare_shares', 'shared_by'],
        'admin_chat_participant' => ['admin_chats', 'user_id'],
        'admin_chat_responder' => ['admin_chats', 'admin_id'],
        'complaint_author' => ['complaints', 'user_id'],
        'complaint_responder' => ['complaints', 'responded_by'],
        'event_proposal_submitter' => ['event_proposals', 'submitted_by'],
        'event_proposal_reviewer' => ['event_proposals', 'reviewed_by'],
        'event_proposal_osas_reviewer' => ['event_proposals', 'osas_reviewed_by'],
        'event_proposal_reschedule_requester' => ['event_proposals', 'reschedule_requested_by'],
        'event_proposal_reschedule_reviewer' => ['event_proposals', 'reschedule_reviewed_by'],
        'event_financial_report_submitter' => ['event_financial_reports', 'submitted_by'],
        'event_financial_report_reviewer' => ['event_financial_reports', 'reviewed_by'],
        'event_approval' => ['events', 'approved_by'],
        'event_osas_approval' => ['events', 'osas_approved_by'],
        'student_document_verification' => ['student_documents', 'verified_by'],
        'student_payment_verification' => ['student_payments', 'verified_by'],
        'organization_application_review' => ['apply_orgs', 'reviewed_by'],
        'audit_actor' => ['account_management_audits', 'actor_user_id'],
        'audit_affected_account' => ['account_management_audits', 'affected_user_id'],
    ];

    public function __construct(private readonly DatabaseManager $database) {}

    public function inspect(User $user): AssociatedRecordInspection
    {
        $references = $this->associatedRecordState($user);
        $notifications = $this->notificationState($user);

        if ($references === null || $notifications === null) {
            return AssociatedRecordInspection::blocked(AssociatedRecordInspection::UNAVAILABLE);
        }

        if ($references || $notifications) {
            return AssociatedRecordInspection::blocked(AssociatedRecordInspection::ASSOCIATED_RECORDS);
        }

        return ($restriction = $this->deletionRestriction($user)) === null
            ? AssociatedRecordInspection::eligible()
            : AssociatedRecordInspection::blocked($restriction);
    }

    /** A database failure is conservatively treated as an associated record. */
    public function hasAssociatedRecords(User $user): bool
    {
        return $this->associatedRecordState($user) !== false || $this->notificationState($user) !== false;
    }

    /** @return AssociatedRecordInspection::RETENTION_ACTIVE|AssociatedRecordInspection::UNAVAILABLE|null */
    public function deletionRestriction(User $user): ?string
    {
        try {
            $seconds = config('account-management.deletion.minimum_account_retention_seconds', 0);
            if (! is_int($seconds) || $seconds < 0) {
                return AssociatedRecordInspection::UNAVAILABLE;
            }

            if ($seconds === 0) {
                return null;
            }

            $createdAt = $user->getAttribute('created_at');
            if ($createdAt === null) {
                return AssociatedRecordInspection::UNAVAILABLE;
            }

            $retentionEndsAt = Carbon::parse($createdAt)->addSeconds($seconds);

            return $retentionEndsAt->isFuture()
                ? AssociatedRecordInspection::RETENTION_ACTIVE
                : null;
        } catch (Throwable) {
            return AssociatedRecordInspection::UNAVAILABLE;
        }
    }

    /** @return bool|null True/false for a completed check; null when inspection failed. */
    private function associatedRecordState(User $user): ?bool
    {
        try {
            foreach (self::REFERENCE_CHECKS as [$table, $column]) {
                if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                    continue;
                }

                if ($this->database->table($table)->where($column, $user->getKey())->exists()) {
                    return true;
                }
            }

            return false;
        } catch (Throwable) {
            return null;
        }
    }

    /** @return bool|null True/false for a completed check; null when inspection failed. */
    private function notificationState(User $user): ?bool
    {
        try {
            if (! Schema::hasTable('notifications')
                || ! Schema::hasColumn('notifications', 'notifiable_id')
                || ! Schema::hasColumn('notifications', 'notifiable_type')) {
                return false;
            }

            return $this->database->table('notifications')
                ->where('notifiable_id', $user->getKey())
                ->where('notifiable_type', $user->getMorphClass())
                ->exists();
        } catch (Throwable) {
            return null;
        }
    }
}
