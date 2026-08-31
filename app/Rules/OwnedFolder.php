<?php

namespace App\Rules;

use App\Models\Folder;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class OwnedFolder implements ValidationRule
{
    public function __construct(private int $userId) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null) {
            return;
        }

        $ok = Folder::where('id', $value)->where('user_id', $this->userId)->exists();

        if (! $ok) {
            $fail('The selected folder is invalid.');
        }
    }
}
