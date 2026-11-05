<?php

return [
    'deletion' => [
        /*
         * Zero preserves the current policy. A positive value prevents deletion
         * until the account has been retained for the configured interval.
         */
        'minimum_account_retention_seconds' => (int) env(
            'ACCOUNT_MANAGEMENT_MINIMUM_ACCOUNT_RETENTION_SECONDS',
            0,
        ),
    ],
];
