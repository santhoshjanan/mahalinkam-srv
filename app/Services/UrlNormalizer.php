<?php

namespace App\Services;

use App\Exceptions\InvalidUrlException;

class UrlNormalizer
{
    public const MAX_LENGTH = 768;

    public const TRACKING_PARAMS = [
        'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'utm_id',
        'gclid', 'gbraid', 'wbraid', 'fbclid', 'msclkid', 'mc_cid', 'mc_eid',
        'ref', 'ref_src', 'igshid', 'si',
    ];

    public function normalize(string $url): string
    {
        $url = trim($url);                                    // 1
        $parts = parse_url($url);
        if ($parts === false || empty($parts['scheme']) || ! isset($parts['host']) || $parts['host'] === '') {
            throw new InvalidUrlException("Unparseable URL: {$url}");
        }

        $scheme = strtolower($parts['scheme']);               // 2 + 3
        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new InvalidUrlException("Unsupported scheme: {$scheme}");
        }
        $host = strtolower($parts['host']);

        $port = $parts['port'] ?? null;                       // 3 strip default port
        if (($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443)) {
            $port = null;
        }

        // 4 drop fragment (parse_url already separates it; we simply never re-add it)
        // 7 + 8 applied as "empty -> '/' first, then strip trailing slash"; result is order-independent.

        $path = $parts['path'] ?? '/';
        if ($path === '') {
            $path = '/';                                      // 8 empty path -> '/'
        }
        if ($path !== '/' && str_ends_with($path, '/')) {     // 7 strip trailing slash
            $path = rtrim($path, '/');
        }

        $query = '';
        if (isset($parts['query']) && $parts['query'] !== '') { // 5 + 6
            $kept = [];
            // preserve original order: re-split raw rather than trusting parse_str order
            foreach (explode('&', $parts['query']) as $seg) {
                if ($seg === '') {
                    continue;
                }
                [$k] = array_pad(explode('=', $seg, 2), 2, '');
                if (in_array(strtolower(urldecode($k)), self::TRACKING_PARAMS, true)) {
                    continue;
                }
                $kept[] = $seg;
            }
            $query = implode('&', $kept);
        }

        $result = $scheme.'://'.$host.($port ? ":{$port}" : '').$path;
        if ($query !== '') {
            $result .= '?'.$query;
        }

        if (mb_strlen($result) > self::MAX_LENGTH) {
            throw new InvalidUrlException('Normalized URL exceeds '.self::MAX_LENGTH.' characters');
        }

        return $result;
    }
}
