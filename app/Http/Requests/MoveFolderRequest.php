<?php

namespace App\Http\Requests;

use App\Rules\OwnedFolder;
use Illuminate\Foundation\Http\FormRequest;

class MoveFolderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'parent_id' => ['nullable', 'integer', new OwnedFolder($this->user()->id)],
        ];
    }
}
