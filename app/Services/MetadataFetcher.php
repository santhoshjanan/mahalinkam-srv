<?php

namespace App\Services;

use App\Support\PrivateNetworkGuard;
use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;

class MetadataFetcher
{
    public function __construct(private PrivateNetworkGuard $guard) {}

    /**
     * @return array{title: ?string, description: ?string, favicon_url: ?string}
     */
    public function fetch(string $url): array
    {
        $this->guardUrl($url);

        $response = Http::withOptions([
            'allow_redirects' => [
                'max' => 3,
                'on_redirect' => function ($request, $response, $uri) {
                    // Re-check every redirect hop to prevent SSRF via redirect.
                    $this->guard->assertHostAllowed($uri->getHost());
                },
            ],
        ])
            ->timeout((int) config('mahalinkam.metadata.timeout'))
            ->withHeaders([
                'User-Agent' => 'mahalinkam-metadata/1.0 (+https://github.com/santhoshj/mahalinkam)',
            ])
            ->get($url);

        $response->throw();

        $body = substr($response->body(), 0, (int) config('mahalinkam.metadata.max_bytes'));
        $crawler = new Crawler($body);

        $effectiveUri = (string) $response->effectiveUri();

        return [
            'title' => $this->title($crawler),
            'description' => $this->description($crawler),
            'favicon_url' => $this->favicon($crawler, $effectiveUri),
        ];
    }

    private function guardUrl(string $url): void
    {
        $host = parse_url($url, PHP_URL_HOST) ?: '';
        $this->guard->assertHostAllowed($host);
    }

    private function title(Crawler $crawler): ?string
    {
        $title = $this->firstMatch($crawler, 'title');
        if ($title === null) {
            $title = $this->firstAttr($crawler, 'meta[property="og:title"]', 'content');
        }

        return $title === null ? null : mb_substr($title, 0, 1024);
    }

    private function description(Crawler $crawler): ?string
    {
        $value = $this->firstAttr($crawler, 'meta[name="description"]', 'content')
            ?? $this->firstAttr($crawler, 'meta[property="og:description"]', 'content');

        // Cap at the `description` column / FormRequest limit so a page with a
        // giant meta description can't fail the DB write on MySQL/Postgres.
        return $value === null ? null : mb_substr($value, 0, 5000);
    }

    private function firstMatch(Crawler $crawler, string $selector): ?string
    {
        $node = $crawler->filter($selector);
        if ($node->count() === 0) {
            return null;
        }

        $value = trim($node->first()->text(''));

        return $value === '' ? null : $value;
    }

    private function firstAttr(Crawler $crawler, string $selector, string $attr): ?string
    {
        $node = $crawler->filter($selector);
        if ($node->count() === 0) {
            return null;
        }

        $value = trim((string) ($node->first()->attr($attr) ?? ''));

        return $value === '' ? null : $value;
    }

    private function favicon(Crawler $crawler, string $baseUrl): ?string
    {
        $scheme = parse_url($baseUrl, PHP_URL_SCHEME) ?: 'https';
        $host = parse_url($baseUrl, PHP_URL_HOST) ?: '';
        $origin = "{$scheme}://{$host}";

        $href = null;
        $crawler->filter('link[rel~="icon"]')->each(function (Crawler $node) use (&$href) {
            if ($href === null) {
                $value = trim((string) ($node->attr('href') ?? ''));
                if ($value !== '') {
                    $href = $value;
                }
            }
        });

        if ($href === null) {
            return "{$origin}/favicon.ico";
        }

        // A data: URI is the icon bytes inline, not a location — building an
        // absolute URL from it yields a bogus link, so drop it.
        if (str_starts_with(strtolower($href), 'data:')) {
            return null;
        }

        $resolved = match (true) {
            str_starts_with($href, 'http://'), str_starts_with($href, 'https://') => $href,
            str_starts_with($href, '//') => "{$scheme}:{$href}",
            str_starts_with($href, '/') => $origin.$href,
            default => $this->resolveRelative($baseUrl, $origin, $href),
        };

        // Reject anything that would overflow the favicon_url column (2048).
        return mb_strlen($resolved) > 2048 ? null : $resolved;
    }

    private function resolveRelative(string $baseUrl, string $origin, string $href): string
    {
        $path = parse_url($baseUrl, PHP_URL_PATH) ?: '/';
        $dir = rtrim(substr($path, 0, strrpos($path, '/') ?: 0), '/');

        return "{$origin}{$dir}/{$href}";
    }
}
