<?php

namespace App\Services\AccountManagement;

/**
 * Safe, presentation-ready reasons an audit filter submission was rejected.
 *
 * A rejection never corrects or reorders the submitted value: the OSAS
 * administrator must submit a corrected filter.
 */
enum AuditFilterRejection: string
{
    case InvalidAction = 'invalid_action';
    case InvalidActor = 'invalid_actor';
    case InvalidAffectedAccount = 'invalid_affected_account';
    case InvalidOrganization = 'invalid_organization';
    case InvalidStartTime = 'invalid_start_time';
    case InvalidEndTime = 'invalid_end_time';
    case ReversedTimeRange = 'reversed_time_range';

    /** The canonical request field the rejection message belongs to. */
    public function field(): string
    {
        return match ($this) {
            self::InvalidAction => AccountManagementAuditFilter::PARAM_ACTION,
            self::InvalidActor => AccountManagementAuditFilter::PARAM_ACTOR,
            self::InvalidAffectedAccount => AccountManagementAuditFilter::PARAM_ACCOUNT,
            self::InvalidOrganization => AccountManagementAuditFilter::PARAM_ORGANIZATION,
            self::InvalidStartTime, self::ReversedTimeRange => AccountManagementAuditFilter::PARAM_FROM,
            self::InvalidEndTime => AccountManagementAuditFilter::PARAM_TO,
        };
    }

    /** A non-sensitive message that describes only the submitted filter value. */
    public function message(): string
    {
        return match ($this) {
            self::InvalidAction => 'Select a listed account-management action.',
            self::InvalidActor => 'Select a listed OSAS administrator.',
            self::InvalidAffectedAccount => 'Select a listed account.',
            self::InvalidOrganization => 'Select a listed organization.',
            self::InvalidStartTime => 'Enter a valid start date and time.',
            self::InvalidEndTime => 'Enter a valid end date and time.',
            self::ReversedTimeRange => 'Enter an end date and time that is not before the start date and time.',
        };
    }

    public function isTimeRangeRejection(): bool
    {
        return in_array($this, [self::InvalidStartTime, self::InvalidEndTime, self::ReversedTimeRange], true);
    }
}
