<?php

namespace App\Services;

use App\Models\User;
use App\Support\Exporters\CsvExporter;
use App\Support\Exporters\HtmlExporter;
use App\Support\Exporters\JsonExporter;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportService
{
    /**
     * Stream every bookmark the user owns, plus the folder structure needed to
     * reconstruct nesting, in the requested format. The body is byte-for-byte
     * what the matching importer consumes.
     *
     * @param  string  $format  one of html|csv|json
     */
    public function stream(User $user, string $format): StreamedResponse
    {
        $format = strtolower($format);

        /** @var array{0: HtmlExporter|CsvExporter|JsonExporter, 1: string, 2: string} $spec */
        $spec = match ($format) {
            'html' => [new HtmlExporter, 'text/html; charset=UTF-8', 'html'],
            'csv' => [new CsvExporter, 'text/csv; charset=UTF-8', 'csv'],
            'json' => [new JsonExporter, 'application/json', 'json'],
            default => throw new InvalidArgumentException("Unsupported export format: {$format}"),
        };

        [$exporter, $contentType, $extension] = $spec;

        $folders = $user->folders()
            ->orderBy('parent_id')
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        $bookmarks = $user->bookmarks()
            ->with('tags')
            ->orderBy('id')
            ->get();

        $filename = 'mahalinkam-export-'.now()->format('Y-m-d').'.'.$extension;

        return new StreamedResponse(
            function () use ($exporter, $folders, $bookmarks): void {
                $exporter->write($folders, $bookmarks);
            },
            200,
            [
                'Content-Type' => $contentType,
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            ],
        );
    }
}
