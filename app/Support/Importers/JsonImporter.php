<?php

namespace App\Support\Importers;

use RuntimeException;

final class JsonImporter implements Importer
{
    public static function supports(string $extension): bool
    {
        return strtolower($extension) === 'json';
    }

    public function rows(string $path): iterable
    {
        $raw = @file_get_contents($path);

        if ($raw === false) {
            throw new RuntimeException("Unable to open file: {$path}");
        }

        $data = json_decode($raw, true);

        if (! is_array($data)) {
            throw new RuntimeException('Invalid JSON: expected an object or array.');
        }

        [$bookmarks, $pathResolver] = $this->normalize($data);

        foreach ($bookmarks as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $url = isset($entry['url']) ? trim((string) $entry['url']) : '';

            if ($url === '') {
                continue;
            }

            yield new ParsedBookmark(
                url: $url,
                title: $this->stringOrNull($entry['title'] ?? null),
                description: $this->stringOrNull($entry['description'] ?? null),
                tags: $this->tags($entry['tags'] ?? []),
                folderPath: $pathResolver($entry),
            );
        }
    }

    /**
     * @param  array<mixed>  $data
     * @return array{0: array<mixed>, 1: callable(array<mixed>): array<int, string>}
     */
    private function normalize(array $data): array
    {
        // mahalinkam export shape: { folders: [...], bookmarks: [...] }
        if (isset($data['folders']) && is_array($data['folders']) && isset($data['bookmarks']) && is_array($data['bookmarks'])) {
            $paths = $this->buildFolderPaths($data['folders']);

            return [
                $data['bookmarks'],
                function (array $entry) use ($paths): array {
                    $id = $entry['folder_id'] ?? $entry['folderId'] ?? null;

                    if ($id !== null && isset($paths[$id])) {
                        return $paths[$id];
                    }

                    return $this->folderPathFromEntry($entry);
                },
            ];
        }

        // { bookmarks: [...] }
        if (isset($data['bookmarks']) && is_array($data['bookmarks'])) {
            return [$data['bookmarks'], fn (array $entry) => $this->folderPathFromEntry($entry)];
        }

        // top-level array [ {...} ]
        return [$data, fn (array $entry) => $this->folderPathFromEntry($entry)];
    }

    /**
     * Resolve every folder id to a root->leaf array of segment names.
     *
     * @param  array<mixed>  $folders
     * @return array<int|string, array<int, string>>
     */
    private function buildFolderPaths(array $folders): array
    {
        $byId = [];
        foreach ($folders as $folder) {
            if (is_array($folder) && isset($folder['id'])) {
                $byId[$folder['id']] = $folder;
            }
        }

        $resolved = [];

        $resolve = function ($id, array $seen) use (&$resolve, $byId): array {
            if ($id === null || ! isset($byId[$id]) || in_array($id, $seen, true)) {
                return [];
            }

            $folder = $byId[$id];
            $seen[] = $id;
            $parentId = $folder['parent_id'] ?? $folder['parentId'] ?? null;
            $name = (string) ($folder['name'] ?? $folder['title'] ?? '');

            return array_merge($resolve($parentId, $seen), $name !== '' ? [$name] : []);
        };

        foreach ($byId as $id => $folder) {
            $resolved[$id] = $resolve($id, []);
        }

        return $resolved;
    }

    /**
     * @param  array<mixed>  $entry
     * @return array<int, string>
     */
    private function folderPathFromEntry(array $entry): array
    {
        if (isset($entry['folder_path']) && is_array($entry['folder_path'])) {
            return array_values(array_filter(
                array_map(fn ($s) => trim((string) $s), $entry['folder_path']),
                fn ($s) => $s !== '',
            ));
        }

        if (isset($entry['folder']) && is_string($entry['folder']) && trim($entry['folder']) !== '') {
            return array_values(array_filter(
                array_map('trim', explode('/', $entry['folder'])),
                fn ($s) => $s !== '',
            ));
        }

        return [];
    }

    /**
     * @param  mixed  $tags
     * @return array<int, string>
     */
    private function tags($tags): array
    {
        if (is_string($tags)) {
            $tags = explode(',', $tags);
        }

        if (! is_array($tags)) {
            return [];
        }

        return array_values(array_filter(
            array_map(fn ($t) => trim((string) $t), $tags),
            fn ($t) => $t !== '',
        ));
    }

    private function stringOrNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
