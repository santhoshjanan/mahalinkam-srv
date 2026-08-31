<?php

namespace App\Support\Exporters;

use App\Models\Bookmark;
use App\Models\Folder;
use App\Support\Importers\HtmlImporter;
use Illuminate\Support\Collection;

/**
 * Emits a Netscape bookmark file with nested <DL> blocks mirroring the folder
 * tree. Round-trips through {@see HtmlImporter}, which
 * tracks folder depth by <H3> (push) / </DL> (pop) and reads the comma-joined
 * TAGS attribute. All text and attribute values are HTML-escaped.
 */
final class HtmlExporter
{
    /**
     * @param  Collection<int, Folder>  $folders
     * @param  Collection<int, Bookmark>  $bookmarks
     */
    public function write(Collection $folders, Collection $bookmarks): void
    {
        $childFolders = $folders->groupBy(fn ($folder) => $folder->parent_id ?? 0);
        $bookmarksByFolder = $bookmarks->groupBy(fn ($bookmark) => $bookmark->folder_id ?? 0);

        echo "<!DOCTYPE NETSCAPE-Bookmark-file-1>\n";
        echo '<META HTTP-EQUIV="Content-Type" CONTENT="text/html; charset=UTF-8">'."\n";
        echo "<TITLE>Bookmarks</TITLE>\n";
        echo "<H1>Bookmarks</H1>\n";
        echo "<DL><p>\n";

        $this->renderLevel(0, 1, $childFolders, $bookmarksByFolder);

        echo "</DL><p>\n";
    }

    /**
     * @param  Collection<int|string, Collection<int, Folder>>  $childFolders
     * @param  Collection<int|string, Collection<int, Bookmark>>  $bookmarksByFolder
     */
    private function renderLevel(
        int $folderId,
        int $depth,
        Collection $childFolders,
        Collection $bookmarksByFolder,
    ): void {
        $indent = str_repeat('    ', $depth);

        foreach ($childFolders->get($folderId, collect()) as $folder) {
            echo $indent.'<DT><H3>'.$this->esc((string) $folder->name)."</H3>\n";
            echo $indent."<DL><p>\n";
            $this->renderLevel($folder->id, $depth + 1, $childFolders, $bookmarksByFolder);
            echo $indent."</DL><p>\n";
        }

        foreach ($bookmarksByFolder->get($folderId, collect()) as $bookmark) {
            $attrs = ' HREF="'.$this->esc($bookmark->url).'"';

            $tags = $bookmark->tags->pluck('name')->implode(',');
            if ($tags !== '') {
                $attrs .= ' TAGS="'.$this->esc($tags).'"';
            }

            $label = $this->esc($bookmark->title ?? $bookmark->url);

            echo $indent.'<DT><A'.$attrs.'>'.$label."</A>\n";
        }
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
