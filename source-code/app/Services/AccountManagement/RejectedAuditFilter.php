<?php

namespace App\Services\AccountManagement;

/**
 * A rejected audit filter submission.
 *
 * The rejected value is never corrected and no audit history is projected, so
 * an invalid request can never broaden the requested history. Every valid
 * filter and the raw submitted values are retained for correction.
 */
final readonly class RejectedAuditFilter implements AuditBrowseResult
{
    /**
     * @param  list<AuditFilterRejection>  $rejections
     * @param  array<string, string>  $submitted
     */
    public function __construct(
        private AccountManagementAuditFilter $retainedFilter,
        public array $rejections,
        public array $submitted,
    ) {}

    public function accepted(): bool
    {
        return false;
    }

    public function filter(): AccountManagementAuditFilter
    {
        return $this->retainedFilter;
    }

    /** @return array<string, string> Field-keyed non-sensitive messages. */
    public function messages(): array
    {
        $messages = [];

        foreach ($this->rejections as $rejection) {
            $messages[$rejection->field()] = $rejection->message();
        }

        return $messages;
    }

    public function rejectedTimeRange(): bool
    {
        foreach ($this->rejections as $rejection) {
            if ($rejection->isTimeRangeRejection()) {
                return true;
            }
        }

        return false;
    }
}
