<?php

namespace App\Support\Importers;

use RuntimeException;

/**
 * Parser for Netscape bookmark files.
 *
 * These files are notoriously not well-formed (unclosed <DT>, <p> with no
 * closing tag, inconsistent nesting), and every browser's HTML repair mode
 * flattens the <DL> nesting differently. Rather than trust a repaired DOM
 * tree, this scans the markup in source order and tracks folder depth with a
 * stack: <H3> pushes a level, </DL> pops one, <A> emits at the current path.
 */
final class HtmlImporter implements Importer
{
    public static function supports(string $extension): bool
    {
        return in_array(strtolower($extension), ['html', 'htm'], true);
    }

    public function rows(string $path): iterable
    {
        $raw = @file_get_contents($path);

        if ($raw === false) {
            throw new RuntimeException("Unable to open file: {$path}");
        }

        $pattern = '~<h3\b[^>]*>(?<h3>.*?)</h3>|<a\b(?<attrs>[^>]*)>(?<text>.*?)</a>|</dl\s*>~is';

        if (! preg_match_all($pattern, $raw, $matches, PREG_SET_ORDER)) {
            return;
        }

        /** @var array<int, string> $stack */
        $stack = [];

        foreach ($matches as $match) {
            $token = strtolower(ltrim($match[0]));

            if (str_starts_with($token, '</dl')) {
                array_pop($stack);

                continue;
            }

            if (str_starts_with($token, '<a')) {
                $bookmark = $this->anchor($match['attrs'] ?? '', $match['text'] ?? '', $stack);

                if ($bookmark !== null) {
                    yield $bookmark;
                }

                continue;
            }

            // <h3> — opens a new folder level.
            $name = $this->decode($match['h3'] ?? '');

            // Cap depth so a truncated file (missing trailing </DL>s) cannot
            // grow folderPath without bound. Task 19 re-clamps to the real limit.
            if ($name !== '' && count($stack) < 20) {
                $stack[] = $name;
            }
        }
    }

    /**
     * @param  array<int, string>  $stack
     */
    private function anchor(string $attrs, string $text, array $stack): ?ParsedBookmark
    {
        $href = $this->attr($attrs, 'href');

        if ($href === null || trim($href) === '') {
            return null;
        }

        $title = $this->decode($text);

        return new ParsedBookmark(
            url: trim($href),
            title: $title === '' ? null : $title,
            tags: $this->parseTags($this->attr($attrs, 'tags') ?? ''),
            folderPath: array_values($stack),
        );
    }

    private function attr(string $attrs, string $name): ?string
    {
        if (preg_match('~\b'.preg_quote($name, '~').'\s*=\s*("|\')(.*?)\1~is', $attrs, $m)) {
            return html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5);
        }

        if (preg_match('~\b'.preg_quote($name, '~').'\s*=\s*([^\s"\'>]+)~is', $attrs, $m)) {
            return html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5);
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function parseTags(string $value): array
    {
        if (trim($value) === '') {
            return [];
        }

        return array_values(array_filter(
            array_map('trim', explode(',', $value)),
            fn ($tag) => $tag !== '',
        ));
    }

    private function decode(string $html): string
    {
        $text = preg_replace('~<[^>]*>~', '', $html) ?? $html;

        return trim(html_entity_decode($text, ENT_QUOTES | ENT_HTML5));
    }
}
