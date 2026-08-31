<?php

// tests/Unit/Importers/ImportersTest.php

use App\Support\Importers\ImporterFactory;

function importFixturePath(string $file): string
{
    return dirname(__DIR__, 3).'/tests/fixtures/import/'.$file;
}

function collectRows(string $format, string $file): array
{
    $imp = ImporterFactory::for($format);

    return collect($imp->rows(importFixturePath($file)))
        ->map(fn ($p) => [$p->url, $p->title, $p->tags, $p->folderPath])
        ->all();
}

it('parses bare txt', function () {
    expect(collectRows('txt', 'bare.txt'))->toBe([
        ['https://a.test/one', null, [], []],
        ['https://b.test/two', null, [], []],
    ]);
});

it('parses csv with tags and folder path', function () {
    $rows = collectRows('csv', 'sample.csv');
    expect($rows[0])->toBe(['https://a.test/one', 'One', ['news', 'tech'], ['Reading', 'Tech']])
        ->and($rows[1])->toBe(['https://b.test/two', 'Two', [], []]);
});

it('parses json bookmarks array', function () {
    $rows = collectRows('json', 'sample.json');
    expect($rows[0])->toBe(['https://a.test/one', 'One', ['news'], ['Reading', 'Tech']]);
});

it('parses netscape html with nested folders', function () {
    $rows = collectRows('html', 'netscape.html');
    expect($rows[0])->toBe(['https://a.test/one', 'One', ['news', 'tech'], ['Reading', 'Tech']])
        ->and($rows[1])->toBe(['https://b.test/two', 'Two', [], []]);
});
