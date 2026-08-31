<?php

use App\Exceptions\BlockedHostException;
use App\Services\MetadataFetcher;
use App\Support\PrivateNetworkGuard;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

function metadataFetcherWithNoopGuard(): MetadataFetcher
{
    $guard = Mockery::mock(PrivateNetworkGuard::class);
    $guard->shouldReceive('assertHostAllowed')->andReturnNull();

    return new MetadataFetcher($guard);
}

it('pulls title and description from standard tags', function () {
    Http::fake(['*' => Http::response(
        '<html><head><title>  Page Title  </title>'
        .'<meta name="description" content="A plain description">'
        .'<link rel="icon" href="/favicon-32.png"></head></html>',
        200,
        ['Content-Type' => 'text/html'],
    )]);

    $meta = metadataFetcherWithNoopGuard()->fetch('https://example.com/page');

    expect($meta['title'])->toBe('Page Title')
        ->and($meta['description'])->toBe('A plain description')
        ->and($meta['favicon_url'])->toBe('https://example.com/favicon-32.png');
});

it('falls back to og:title, og:description and /favicon.ico', function () {
    Http::fake(['*' => Http::response(
        '<html><head>'
        .'<meta property="og:title" content="OG Title">'
        .'<meta property="og:description" content="OG Description">'
        .'</head><body>no icon link here</body></html>',
        200,
        ['Content-Type' => 'text/html'],
    )]);

    $meta = metadataFetcherWithNoopGuard()->fetch('https://example.com/deep/path');

    expect($meta['title'])->toBe('OG Title')
        ->and($meta['description'])->toBe('OG Description')
        ->and($meta['favicon_url'])->toBe('https://example.com/favicon.ico');
});

it('resolves an absolute favicon href unchanged', function () {
    Http::fake(['*' => Http::response(
        '<html><head><title>T</title>'
        .'<link rel="shortcut icon" href="https://cdn.example.net/i.ico"></head></html>',
        200,
        ['Content-Type' => 'text/html'],
    )]);

    $meta = metadataFetcherWithNoopGuard()->fetch('https://example.com/');

    expect($meta['favicon_url'])->toBe('https://cdn.example.net/i.ico');
});

it('caps the title at 1024 characters', function () {
    Http::fake(['*' => Http::response(
        '<html><head><title>'.str_repeat('x', 2000).'</title></head></html>',
        200,
        ['Content-Type' => 'text/html'],
    )]);

    $meta = metadataFetcherWithNoopGuard()->fetch('https://example.com/');

    expect(mb_strlen($meta['title']))->toBe(1024);
});

it('throws on a non-2xx response', function () {
    Http::fake(['*' => Http::response('nope', 500)]);

    expect(fn () => metadataFetcherWithNoopGuard()->fetch('https://example.com/'))
        ->toThrow(RequestException::class);
});

it('re-checks the host on each redirect hop and blocks an internal target', function () {
    $guard = Mockery::mock(PrivateNetworkGuard::class);
    $guard->shouldReceive('assertHostAllowed')->with('start.test')->andReturnNull();
    $guard->shouldReceive('assertHostAllowed')->with('169.254.169.254')
        ->andThrow(new BlockedHostException('link-local metadata endpoint'));

    Http::fake([
        'start.test/*' => Http::response('', 302, ['Location' => 'http://169.254.169.254/latest/meta-data/']),
        '*' => Http::response('<html><head><title>secret</title></head></html>', 200),
    ]);

    expect(fn () => (new MetadataFetcher($guard))->fetch('http://start.test/go'))
        ->toThrow(BlockedHostException::class);
});
