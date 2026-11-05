<?php

namespace App\Policies;

use App\Models\EdushareItem;
use App\Models\User;

class EdushareItemPolicy
{
    public function view(User $user, EdushareItem $item): bool
    {
        return $item->is_public || $user->id === $item->user_id || $user->isAdmin();
    }

    public function update(User $user, EdushareItem $item): bool
    {
        return $user->id === $item->user_id || $user->isAdmin();
    }

    public function delete(User $user, EdushareItem $item): bool
    {
        return $user->id === $item->user_id || $user->isAdmin();
    }
}
