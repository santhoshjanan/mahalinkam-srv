<?php

namespace App\Support\Importers;

final readonly class ParsedBookmark
{
    /**
     * @param  array<int, string>  $tags
     * @param  array<int, string>  $folderPath  root -> leaf segment names
     */
    public function __construct(
        public string $url,
        public ?string $title = null,
        public ?string $description = null,
        public array $tags = [],
        public array $folderPath = [],
    ) {}
}
