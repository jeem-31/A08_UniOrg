<?php

namespace App\Policies;

use App\Models\ApplyOrg;
use App\Models\ProcessedDocument;
use App\Models\User;
use App\Services\DocumentIntelligence\CurrentDocumentAnalysisLocator;

class ProcessedDocumentPolicy
{
    public function __construct(private readonly CurrentDocumentAnalysisLocator $locator) {}

    public function view(User $user, ProcessedDocument $analysis, ApplyOrg $application): bool
    {
        return $this->canAccess($user, $analysis, $application);
    }

    public function viewSource(User $user, ProcessedDocument $analysis, ApplyOrg $application): bool
    {
        return $this->canAccess($user, $analysis, $application);
    }

    public function viewSummary(User $user, ProcessedDocument $analysis, ApplyOrg $application): bool
    {
        return $this->canAccess($user, $analysis, $application);
    }

    public function viewDetails(User $user, ProcessedDocument $analysis, ApplyOrg $application): bool
    {
        return $this->canAccess($user, $analysis, $application);
    }

    public function retry(User $user, ProcessedDocument $analysis, ApplyOrg $application): bool
    {
        return $this->canAccess($user, $analysis, $application);
    }

    public function resummarize(User $user, ProcessedDocument $analysis, ApplyOrg $application): bool
    {
        return $this->canAccess($user, $analysis, $application);
    }

    public function retag(User $user, ProcessedDocument $analysis, ApplyOrg $application): bool
    {
        return $this->canAccess($user, $analysis, $application);
    }

    private function canAccess(User $user, ProcessedDocument $analysis, ApplyOrg $application): bool
    {
        return $user->role === 'osas_admin'
            && $this->locator->isCurrent($application, $analysis);
    }
}
