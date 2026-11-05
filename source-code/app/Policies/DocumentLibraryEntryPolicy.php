<?php

namespace App\Policies;

use App\Models\ApplyOrg;
use App\Models\DocumentLibraryEntry;
use App\Models\User;
use App\Services\DocumentLibrary\CurrentDocumentLibraryEntryLocator;
use Illuminate\Auth\Access\Response;

final readonly class DocumentLibraryEntryPolicy
{
    private const DENIAL_MESSAGE = 'The requested document is unavailable.';

    public function __construct(private CurrentDocumentLibraryEntryLocator $locator) {}

    public function viewSource(
        User $user,
        DocumentLibraryEntry $entry,
        ApplyOrg $application,
    ): Response {
        if ($user->role !== 'osas_admin') {
            return $this->denied();
        }

        return $this->locator->find($application, $entry) === null
            ? $this->denied()
            : Response::allow();
    }

    private function denied(): Response
    {
        return Response::denyAsNotFound(self::DENIAL_MESSAGE);
    }
}
