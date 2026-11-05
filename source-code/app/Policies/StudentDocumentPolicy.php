<?php

namespace App\Policies;

use App\Models\StudentDocument;
use App\Models\User;

class StudentDocumentPolicy
{
    public function view(User $user, StudentDocument $document): bool
    {
        return $user->id === $document->user_id || $user->isAdmin();
    }

    public function update(User $user, StudentDocument $document): bool
    {
        return $user->id === $document->user_id;
    }

    public function delete(User $user, StudentDocument $document): bool
    {
        return $user->id === $document->user_id || $user->isAdmin();
    }
}
