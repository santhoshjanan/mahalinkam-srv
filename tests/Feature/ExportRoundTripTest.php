<?php

use App\Jobs\ProcessImport;
use App\Models\Bookmark;
use App\Models\Folder;
use App\Models\User;
use App\Services\ExportService;
use App\Services\ImportService;
use App\Services\TagService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

dataset('formats', ['html', 'csv', 'json']);

it('exports then re-imports to the same logical set', function (string $format) {
    Storage::fake('local');
    Queue::fake();

    $u = User::factory()->create();
    $reading = Folder::factory()->for($u)->create(['name' => 'Reading']);
    $tech = Folder::factory()->for($u)->create(['name' => 'Tech', 'parent_id' => $reading->id]);
    $b1 = Bookmark::factory()->for($u)->create([
        'url' => 'https://a.test/one',
        'normalized_url' => 'https://a.test/one',
        'folder_id' => $tech->id,
        'title' => 'One',
    ]);
    app(TagService::class)->syncForBookmark($b1, ['news']);
    Bookmark::factory()->for($u)->create([
        'url' => 'https://b.test/two',
        'normalized_url' => 'https://b.test/two',
        'title' => 'Two',
    ]);

    $payload = app(ExportService::class)->stream($u, $format);
    ob_start();
    $payload->sendContent();
    $body = ob_get_clean();

    expect($payload->headers->get('content-disposition'))
        ->toContain('attachment')
        ->toContain('mahalinkam-export-'.now()->format('Y-m-d').".{$format}");

    $fresh = User::factory()->create();
    $file = UploadedFile::fake()->createWithContent("e.{$format}", $body);
    $import = app(ImportService::class)->start($fresh, $file, $format);
    (new ProcessImport($import->id))->handle();

    expect($fresh->bookmarks()->pluck('normalized_url')->sort()->values()->all())
        ->toBe(['https://a.test/one', 'https://b.test/two']);

    expect(
        $fresh->bookmarks()->where('normalized_url', 'https://a.test/one')->first()->tags->pluck('name')->all()
    )->toBe(['news']);

    $one = $fresh->bookmarks()->where('normalized_url', 'https://a.test/one')->first();
    expect($one->folder->name)->toBe('Tech')
        ->and($one->folder->parent->name)->toBe('Reading');
})->with('formats');
