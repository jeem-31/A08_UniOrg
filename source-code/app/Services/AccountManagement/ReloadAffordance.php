<?php

namespace App\Services\AccountManagement;

/** Safe presentation data for a stale submission; it exposes no versions or record identifiers. */
final readonly class ReloadAffordance
{
    public function __construct(
        public string $label = 'Reload and review the latest changes',
        public string $message = 'This form is out of date. Reload and try again.',
    ) {}
}
