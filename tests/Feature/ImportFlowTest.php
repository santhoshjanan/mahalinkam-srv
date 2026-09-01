<?php

use App\Jobs\ProcessImport;
use App\Models\Bookmark;
use App\Models\Folder;
use App\Models\Import;
use App\Models\User;
use App\Services\ImportService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;

it('accepts an upload and queues processing', function () {
    Queue::fake();
    Storage::fake('local');
    $u = User::factory()->create(['email_verified_at' => now()]);

    actingAs($u)->post('/import', [
        'file' => UploadedFile::fake()->createWithContent('b.txt', "https://a.test/1\nhttps://b.test/2\n"),
    ])->assertRedirect();

    expect(Import::where('user_id', $u->id)->count())->toBe(1);
    $import = Import::where('user_id', $u->id)->first();
    expect($import->status)->toBe('pending')
        ->and($import->format)->toBe('txt')
        ->and($import->original_filename)->toBe('b.txt');
    Storage::disk('local')->assertExists("imports/{$import->id}/b.txt");
    Queue::assertPushed(ProcessImport::class);
});

it('processes rows: creates, dedupes, records errors, builds folders', function () {
    Queue::fake();
    Storage::fake('local');
    $u = User::factory()->create();
    $content = "url,title,tags,folder\n"
        .'https://a.test/1,One,news,Reading/Tech'."\n"
        .'https://a.test/1,Dupe,,'."\n"          // duplicate
        .'not-a-url,Bad,,'."\n";                 // error
    $file = UploadedFile::fake()->createWithContent('x.csv', $content);

    $import = app(ImportService::class)->start($u, $file, null);
    (new ProcessImport($import->id))->handle();

    $import->refresh();
    expect($import->status)->toBe('completed')
        ->and($import->created_count)->toBe(1)
        ->and($import->duplicate_count)->toBe(1)
        ->and($import->error_count)->toBe(1)
        ->and($import->total_rows)->toBe(3)
        ->and($import->processed_rows)->toBe(3)
        ->and(Bookmark::where('user_id', $u->id)->count())->toBe(1)
        ->and(Folder::where('user_id', $u->id)->orderBy('id')->pluck('name')->all())->toBe(['Reading', 'Tech']);

    Storage::disk('local')->assertMissing("imports/{$import->id}/x.csv");
});

it('does not create folders for an invalid-URL row (T19)', function () {
    Queue::fake();
    Storage::fake('local');
    $u = User::factory()->create();
    $content = "url,folder\n".'not-a-url,Orphan/Nested'."\n";
    $file = UploadedFile::fake()->createWithContent('bad.csv', $content);

    $import = app(ImportService::class)->start($u, $file, null);
    (new ProcessImport($import->id))->handle();

    $import->refresh();
    expect($import->status)->toBe('completed')
        ->and($import->created_count)->toBe(0)
        ->and($import->error_count)->toBe(1)
        ->and(Folder::where('user_id', $u->id)->count())->toBe(0);
});

it('caps folder depth: deep folder_path still saves the bookmark at the deepest allowed level', function () {
    Queue::fake();
    Storage::fake('local');
    $u = User::factory()->create();

    $segments = collect(range(1, 12))->map(fn ($n) => "L{$n}")->implode('/');
    $content = "url,folder\n".'https://deep.test/1,'.$segments."\n";
    $file = UploadedFile::fake()->createWithContent('d.csv', $content);

    $import = app(ImportService::class)->start($u, $file, null);
    (new ProcessImport($import->id))->handle();

    $import->refresh();
    expect($import->status)->toBe('completed')
        ->and($import->created_count)->toBe(1)
        ->and($import->error_count)->toBe(1)
        ->and(Folder::where('user_id', $u->id)->count())->toBe(10);

    expect($import->errors)->toHaveCount(1);
    expect($import->errors[0]['level'])->toBe('warning');

    $bookmark = Bookmark::where('user_id', $u->id)->firstOrFail();
    $deepest = Folder::where('user_id', $u->id)->where('name', 'L10')->firstOrFail();
    expect($bookmark->folder_id)->toBe($deepest->id);
});

it('marks the import failed and cleans up when the file cannot be parsed', function () {
    Queue::fake();
    Storage::fake('local');
    $u = User::factory()->create();
    // CSV with no url column -> importer throws.
    $file = UploadedFile::fake()->createWithContent('bad.csv', "name,note\nfoo,bar\n");

    $import = app(ImportService::class)->start($u, $file, null);
    (new ProcessImport($import->id))->handle();

    $import->refresh();
    expect($import->status)->toBe('failed');
    Storage::disk('local')->assertMissing("imports/{$import->id}/bad.csv");
});

it('detects html from a .htm extension and honours a format override', function () {
    Queue::fake();
    Storage::fake('local');
    $u = User::factory()->create();

    $htm = app(ImportService::class)->start(
        $u,
        UploadedFile::fake()->createWithContent('links.htm', '<a href="https://x.test/1">x</a>'),
        null,
    );
    expect($htm->format)->toBe('html');

    $override = app(ImportService::class)->start(
        $u,
        UploadedFile::fake()->createWithContent('links.dat', "https://y.test/1\n"),
        'txt',
    );
    expect($override->format)->toBe('txt');
});

it('forbids viewing another user\'s import', function () {
    $u = User::factory()->create(['email_verified_at' => now()]);
    $foreign = Import::factory()->create();
    actingAs($u)->get("/import/{$foreign->id}")->assertForbidden();
});

it('renders the import index for polling', function () {
    Queue::fake();
    Storage::fake('local');
    $u = User::factory()->create(['email_verified_at' => now()]);
    $import = Import::factory()->create(['user_id' => $u->id, 'status' => 'processing']);

    actingAs($u)->get('/import')
        ->assertInertia(fn ($page) => $page
            ->component('Import/Index')
            ->where('activeImport.id', $import->id)
            ->where('activeImport.status', 'processing')
            ->where('formats', ['txt', 'csv', 'json', 'html']));

    actingAs($u)->get("/import/{$import->id}")
        ->assertInertia(fn ($page) => $page
            ->component('Import/Index')
            ->where('activeImport.id', $import->id));
});
