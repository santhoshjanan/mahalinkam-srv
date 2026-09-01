<?php

// tests/Unit/Importers/ImportersTest.php

use App\Support\Importers\HtmlImporter;
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

it('parses a csv with a leading UTF-8 BOM identically', function () {
    expect(collectRows('csv', 'sample-bom.csv'))->toBe(collectRows('csv', 'sample.csv'));
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

it('parses a realistic chrome export', function () {
    $rows = collectRows('html', 'chrome-export.html');

    // Deep bookmark: 3-level path, entity-decoded title, TAGS attr.
    expect($rows[0])->toBe([
        'https://benandjerry.test/',
        'Ben & Jerry',
        ['lang', 'systems'],
        ['Bookmarks bar', 'Dev', 'Rust'],
    ]);

    // Empty <H3>Empty</H3> folder yields no row and does not corrupt the
    // path of the bookmark that follows it (still Bookmarks bar / Dev).
    // TAGS appears before HREF in this tag.
    expect($rows[1])->toBe([
        'https://tagsfirst.test/y',
        'Tags First',
        ['tools', 'cli'],
        ['Bookmarks bar', 'Dev'],
    ]);

    // Single-quoted HREF is captured; back up one level to Bookmarks bar.
    expect($rows[2])->toBe([
        'https://single.test/x',
        'Single Quoted',
        [],
        ['Bookmarks bar'],
    ]);

    expect($rows)->toHaveCount(3);
});

it('yields nothing (and does not throw) for a valid HTML file with zero bookmark matches', function () {
    // preg_match_all returns 0 here, not false — the importer must treat that
    // as "empty file", distinct from the `=== false` PCRE-failure branch which
    // raises RuntimeException.
    $tmp = tempnam(sys_get_temp_dir(), 'imp');
    file_put_contents($tmp, '<html><body>no bookmarks here</body></html>');

    try {
        $rows = iterator_to_array((new HtmlImporter)->rows($tmp));
        expect($rows)->toBe([]);
    } finally {
        @unlink($tmp);
    }
});
