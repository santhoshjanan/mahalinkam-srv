<?php

namespace App\Services;

use App\Jobs\ProcessImport;
use App\Models\Import;
use App\Models\User;
use Illuminate\Http\UploadedFile;

class ImportService
{
    /**
     * Extensions that map onto a different importer format key.
     *
     * @var array<string, string>
     */
    private const EXTENSION_ALIASES = [
        'htm' => 'html',
    ];

    public function start(User $user, UploadedFile $file, ?string $formatOverride): Import
    {
        $format = $this->detectFormat($file, $formatOverride);

        $original = $file->getClientOriginalName();

        $import = $user->imports()->create([
            'format' => $format,
            'status' => 'pending',
            'original_filename' => $original,
        ]);

        $file->storeAs("imports/{$import->id}", $original, 'local');

        ProcessImport::dispatch($import->id);

        return $import;
    }

    private function detectFormat(UploadedFile $file, ?string $formatOverride): string
    {
        if ($formatOverride !== null && $formatOverride !== '') {
            return strtolower($formatOverride);
        }

        $ext = strtolower($file->getClientOriginalExtension());

        return self::EXTENSION_ALIASES[$ext] ?? $ext;
    }
}
