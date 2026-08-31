<?php

namespace App\Policies;

use App\Models\Bookmark;
use App\Models\User;

class BookmarkPolicy
{
    public function view(User $user, Bookmark $b): bool
    {
        return $b->user_id === $user->id;
    }

    public function update(User $user, Bookmark $b): bool
    {
        return $b->user_id === $user->id;
    }

    public function delete(User $user, Bookmark $b): bool
    {
        return $b->user_id === $user->id;
    }
}
