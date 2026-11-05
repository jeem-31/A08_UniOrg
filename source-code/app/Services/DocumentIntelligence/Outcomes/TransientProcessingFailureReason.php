<?php

namespace App\Services\DocumentIntelligence\Outcomes;

enum TransientProcessingFailureReason: string
{
    case RateLimited = 'rate_limited';
    case TimedOut = 'timed_out';
    case ProviderUnavailable = 'provider_unavailable';
    case NotConfigured = 'ai_not_configured';
}
