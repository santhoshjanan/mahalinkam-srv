<?php

namespace App\Support\Exporters;

use App\Models\Bookmark;
use App\Models\Folder;
use App\Support\Importers\CsvImporter;
use Illuminate\Support\Collection;

/**
 * Emits the CSV shape consumed by {@see CsvImporter}:
 * header `url,title,description,tags,folder`; `tags` joined with `;`; `folder`
 * as a `/`-separated path. fputcsv() quotes any field containing a comma,
 * double-quote or newline.
 */
final class CsvExporter
{
    /**
     * @param  Collection<int, Folder>  $folders
     * @param  Collection<int, Bookmark>  $bookmarks
     */
    public function write(Collection $folders, Collection $bookmarks): void
    {
        $paths = FolderPaths::map($folders);

        $out = fopen('php://output', 'wb');

        fputcsv($out, ['url', 'title', 'description', 'tags', 'folder'], ',', '"', '');

        foreach ($bookmarks as $bookmark) {
            $folderPath = $bookmark->folder_id !== null
                ? implode('/', $paths[$bookmark->folder_id] ?? [])
                : '';

            fputcsv($out, [
                $bookmark->url,
                $bookmark->title ?? '',
                $bookmark->description ?? '',
                $bookmark->tags->pluck('name')->implode(';'),
                $folderPath,
            ], ',', '"', '');
        }

        fclose($out);
    }
}
