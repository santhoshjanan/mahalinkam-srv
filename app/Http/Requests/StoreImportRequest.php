<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxKb = (int) config('mahalinkam.import.max_file_mb', 20) * 1024;

        return [
            'file' => ['required', 'file', 'extensions:txt,csv,json,html,htm', "max:{$maxKb}"],
            'format' => ['nullable', 'in:txt,csv,json,html'],
        ];
    }
}
