<?php

namespace App\Policies;

use App\Models\Folder;
use App\Models\User;

class FolderPolicy
{
    public function view(User $user, Folder $f): bool
    {
        return $f->user_id === $user->id;
    }

    public function update(User $user, Folder $f): bool
    {
        return $f->user_id === $user->id;
    }

    public function delete(User $user, Folder $f): bool
    {
        return $f->user_id === $user->id;
    }
}
