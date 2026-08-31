<?php

// tests/Unit/UrlNormalizerTest.php
use App\Exceptions\InvalidUrlException;
use App\Services\UrlNormalizer;

dataset('urls', array_map(
    fn (array $case) => [$case],
    require dirname(__DIR__, 2).'/app/Support/url-normalizer-fixtures.php'
));

it('normalizes per the fixture table', function (array $case) {
    $n = new UrlNormalizer;
    if ($case['throws'] ?? false) {
        expect(fn () => $n->normalize($case['in']))->toThrow(InvalidUrlException::class);
    } else {
        expect($n->normalize($case['in']))->toBe($case['out']);
    }
})->with('urls');

it('rejects an over-long normalized result', function () {
    $long = 'https://example.com/'.str_repeat('a', 900);
    expect(fn () => (new UrlNormalizer)->normalize($long))->toThrow(InvalidUrlException::class);
});
