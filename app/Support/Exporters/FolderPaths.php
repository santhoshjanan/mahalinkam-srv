<?php

namespace App\Support\Exporters;

use App\Models\Folder;
use Illuminate\Support\Collection;

/**
 * Resolves every folder id to a root -> leaf array of segment names, matching
 * the shape the importers consume (ParsedBookmark::$folderPath).
 */
final class FolderPaths
{
    /**
     * @param  Collection<int, Folder>  $folders
     * @return array<int, array<int, string>>
     */
    public static function map(Collection $folders): array
    {
        $byId = $folders->keyBy('id');

        /** @var array<int, array<int, string>> $cache */
        $cache = [];

        $resolve = function ($id, array $seen) use (&$resolve, $byId, &$cache): array {
            if ($id === null || ! $byId->has($id) || in_array($id, $seen, true)) {
                return [];
            }

            if (isset($cache[$id])) {
                return $cache[$id];
            }

            $folder = $byId->get($id);
            $seen[] = $id;

            return $cache[$id] = array_merge(
                $resolve($folder->parent_id, $seen),
                [(string) $folder->name],
            );
        };

        $out = [];

        foreach ($byId as $id => $folder) {
            $out[$id] = $resolve($id, []);
        }

        return $out;
    }
}
