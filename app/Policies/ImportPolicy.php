<?php

namespace App\Policies;

use App\Models\Import;
use App\Models\User;

class ImportPolicy
{
    public function view(User $user, Import $i): bool
    {
        return $i->user_id === $user->id;
    }

    public function update(User $user, Import $i): bool
    {
        return $i->user_id === $user->id;
    }

    public function delete(User $user, Import $i): bool
    {
        return $i->user_id === $user->id;
    }
}
