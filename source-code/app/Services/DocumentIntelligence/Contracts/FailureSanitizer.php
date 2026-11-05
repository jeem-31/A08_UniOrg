<?php

namespace App\Services\DocumentIntelligence\Contracts;

use App\Services\DocumentIntelligence\Data\SanitizedFailure;
use Throwable;

interface FailureSanitizer
{
    public function reviewerFailure(Throwable $failure): SanitizedFailure;
}
