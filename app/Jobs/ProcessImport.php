<?php

namespace App\Jobs;

use App\DataTransfer\BookmarkInput;
use App\Exceptions\FolderDepthException;
use App\Exceptions\InvalidUrlException;
use App\Models\Import;
use App\Models\User;
use App\Services\BookmarkService;
use App\Services\FolderService;
use App\Support\Importers\ImporterFactory;
use App\Support\Importers\ParsedBookmark;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProcessImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const CHUNK = 200;

    private const ERROR_CAP = 200;

    public function __construct(public int $importId) {}

    public function handle(): void
    {
        $bookmarks = app(BookmarkService::class);
        $folders = app(FolderService::class);

        // Re-load rather than trust a serialized model: the row may have been
        // updated between dispatch and execution.
        $import = Import::findOrFail($this->importId);
        $user = $import->user;

        $import->update(['status' => 'processing']);

        $relativePath = "imports/{$import->id}/{$import->original_filename}";
        $absolutePath = Storage::disk('local')->path($relativePath);

        /** @var array<int, array<string, mixed>> $errors */
        $errors = [];
        $errorCount = 0;
        $created = 0;
        $duplicates = 0;
        $processed = 0;
        $total = 0;

        /** @var array<string, int> $folderCache  full "A/B/C" path => folder id */
        $folderCache = [];

        $appendError = function (array $entry) use (&$errors, &$errorCount): void {
            if (count($errors) < self::ERROR_CAP) {
                $errors[] = $entry;
            }
            $errorCount++;
        };

        try {
            $importer = ImporterFactory::for($import->format);

            // First pass: establish total_rows (and surface an unparseable file
            // before we start mutating the user's data).
            foreach ($importer->rows($absolutePath) as $_) {
                $total++;
            }
            $import->update(['total_rows' => $total]);

            $batch = 0;
            foreach ($importer->rows($absolutePath) as $row) {
                /** @var ParsedBookmark $row */
                try {
                    $result = $bookmarks->save($user, BookmarkInput::fromArray([
                        'url' => $row->url,
                        'title' => $row->title,
                        'description' => $row->description,
                        'tags' => $row->tags,
                    ]));

                    if ($result['alreadySaved']) {
                        $duplicates++;
                    } else {
                        $created++;

                        // Resolve/create the folder_path chain only after save()
                        // accepts the row. Doing it first meant an invalid-URL
                        // row (rejected below with InvalidUrlException) still
                        // left its folder segments behind as orphans.
                        $folderId = $this->resolveFolder($user, $folders, $row->folderPath, $folderCache, $appendError);
                        if ($folderId !== null) {
                            $result['bookmark']->update(['folder_id' => $folderId]);
                        }
                    }
                } catch (InvalidUrlException $e) {
                    $appendError([
                        'level' => 'error',
                        'row' => $processed + 1,
                        'url' => $row->url,
                        'message' => $e->getMessage(),
                    ]);
                }

                $processed++;
                $batch++;

                if ($batch === self::CHUNK) {
                    $import->update([
                        'processed_rows' => $processed,
                        'created_count' => $created,
                        'duplicate_count' => $duplicates,
                        'error_count' => $errorCount,
                        'errors' => $errors,
                    ]);
                    $batch = 0;
                }
            }
        } catch (Throwable $e) {
            $import->update([
                'status' => 'failed',
                'processed_rows' => $processed,
                'created_count' => $created,
                'duplicate_count' => $duplicates,
                'error_count' => $errorCount + 1,
                'errors' => array_slice(
                    [...$errors, ['level' => 'error', 'message' => 'Import failed: '.$e->getMessage()]],
                    0,
                    self::ERROR_CAP
                ),
            ]);
            $this->cleanup($import);

            return;
        }

        $import->update([
            'status' => 'completed',
            'total_rows' => $total,
            'processed_rows' => $processed,
            'created_count' => $created,
            'duplicate_count' => $duplicates,
            'error_count' => $errorCount,
            'errors' => $errors,
        ]);

        $this->cleanup($import);
    }

    /**
     * Resolve an array of folder segment names to a folder id, creating any
     * missing segments under the user's root. Results are cached by full path
     * string so sibling paths share ancestor nodes within a run.
     *
     * @param  array<int, string>  $segments
     * @param  array<string, int>  $cache
     */
    private function resolveFolder(
        User $user,
        FolderService $folders,
        array $segments,
        array &$cache,
        callable $appendError,
    ): ?int {
        $segments = array_values(array_filter(array_map('trim', $segments), fn ($s) => $s !== ''));

        if ($segments === []) {
            return null;
        }

        $parentId = null;
        $accumulated = [];

        foreach ($segments as $segment) {
            $accumulated[] = $segment;
            $key = implode('/', $accumulated);

            if (isset($cache[$key])) {
                $parentId = $cache[$key];

                continue;
            }

            $existing = $user->folders()
                ->where('parent_id', $parentId)
                ->where('name', $segment)
                ->first();

            if ($existing) {
                $cache[$key] = $existing->id;
                $parentId = $existing->id;

                continue;
            }

            try {
                $folder = $folders->create($user, $segment, $parentId);
            } catch (FolderDepthException $e) {
                $appendError([
                    'level' => 'warning',
                    'path' => implode('/', $segments),
                    'message' => 'Folder nesting limit reached; attached at "'.implode('/', array_slice($accumulated, 0, -1)).'".',
                ]);

                return $parentId;
            }

            $cache[$key] = $folder->id;
            $parentId = $folder->id;
        }

        return $parentId;
    }

    private function cleanup(Import $import): void
    {
        Storage::disk('local')->deleteDirectory("imports/{$import->id}");
    }
}
