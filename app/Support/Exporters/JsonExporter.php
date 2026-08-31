<?php

namespace App\Support\Exporters;

use App\Models\Bookmark;
use App\Models\Folder;
use App\Support\Importers\JsonImporter;
use Illuminate\Support\Collection;

/**
 * Emits the mahalinkam JSON export shape, accepted verbatim by
 * {@see JsonImporter}:
 *
 *   { version, exported_at, folders: [{id,parent_id,name,position}],
 *     bookmarks: [{url,title,description,tags:[name],folder_id,folder_path:[...]}] }
 *
 * Both the id-based `folders` list and the per-bookmark `folder_path` array are
 * included so the importer resolves nesting whichever branch it takes.
 */
final class JsonExporter
{
    /**
     * @param  Collection<int, Folder>  $folders
     * @param  Collection<int, Bookmark>  $bookmarks
     */
    public function write(Collection $folders, Collection $bookmarks): void
    {
        $paths = FolderPaths::map($folders);

        $payload = [
            'version' => 1,
            'exported_at' => now()->toIso8601String(),
            'folders' => $folders->map(fn ($folder) => [
                'id' => $folder->id,
                'parent_id' => $folder->parent_id,
                'name' => $folder->name,
                'position' => $folder->position,
            ])->values()->all(),
            'bookmarks' => $bookmarks->map(fn ($bookmark) => [
                'url' => $bookmark->url,
                'title' => $bookmark->title,
                'description' => $bookmark->description,
                'tags' => $bookmark->tags->pluck('name')->values()->all(),
                'folder_id' => $bookmark->folder_id,
                'folder_path' => $bookmark->folder_id !== null
                    ? ($paths[$bookmark->folder_id] ?? [])
                    : [],
            ])->values()->all(),
        ];

        echo json_encode(
            $payload,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
    }
}
