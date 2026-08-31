<?php

namespace App\Http\Requests;

use App\DataTransfer\BookmarkInput;
use App\Rules\OwnedFolder;
use Illuminate\Foundation\Http\FormRequest;

class StoreBookmarkRequest extends FormRequest
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
            'url' => ['required', 'string', 'max:2048'],
            'title' => ['nullable', 'string', 'max:1024'],
            'description' => ['nullable', 'string', 'max:5000'],
            'folder_id' => ['nullable', 'integer', new OwnedFolder($this->user()->id)],
            'tags' => ['array', 'max:50'],
            'tags.*' => ['string', 'max:80'],
        ];
    }

    public function toBookmarkInput(): BookmarkInput
    {
        return BookmarkInput::fromArray($this->validated());
    }
}
