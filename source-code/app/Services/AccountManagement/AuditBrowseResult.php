<?php

namespace App\Services\AccountManagement;

/** Typed read-only audit browsing outcome; there is no mutation counterpart. */
interface AuditBrowseResult
{
    /** True when the submitted filter was accepted and audit history was projected. */
    public function accepted(): bool;

    /** The retained filter state, including after a rejected time range. */
    public function filter(): AccountManagementAuditFilter;
}
