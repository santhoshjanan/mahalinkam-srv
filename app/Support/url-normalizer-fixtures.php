<?php

// app/Support/url-normalizer-fixtures.php
// Shared expectation table. The browser extension keeps an identical copy.
return [
    ['in' => 'HTTP://Example.COM:80/Path/', 'out' => 'http://example.com/Path'],
    ['in' => 'https://example.com:443/', 'out' => 'https://example.com/'],
    ['in' => 'https://example.com/a/b/', 'out' => 'https://example.com/a/b'],
    ['in' => 'https://example.com/#section', 'out' => 'https://example.com/'],
    ['in' => 'https://example.com/p?utm_source=x&id=7&fbclid=abc', 'out' => 'https://example.com/p?id=7'],
    ['in' => 'https://example.com/p?b=2&a=1', 'out' => 'https://example.com/p?b=2&a=1'], // order preserved, not sorted
    ['in' => 'https://example.com/p?utm_source=x', 'out' => 'https://example.com/p'],   // trailing ? dropped
    ['in' => 'https://example.com', 'out' => 'https://example.com/'],                   // empty path stays '/'
    ['in' => 'ftp://example.com/x', 'throws' => true],
    ['in' => 'not a url', 'throws' => true],
    ['in' => 'javascript:alert(1)', 'throws' => true],
];
