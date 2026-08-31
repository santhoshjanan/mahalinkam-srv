<?php

namespace App\DataTransfer;

readonly class BookmarkInput
{
    /**
     * @param  array<int, string>  $tags
     */
    public function __construct(
        public string $url,
        public ?string $title = null,
        public ?string $description = null,
        public ?int $folderId = null,
        public array $tags = [],
        public bool $tagsProvided = false,
        public bool $folderIdProvided = false,
    ) {}

    /**
     * @param  array<string, mixed>  $d
     */
    public static function fromArray(array $d): self
    {
        $folderIdProvided = array_key_exists('folder_id', $d) || array_key_exists('folderId', $d);

        $folderId = null;
        if (isset($d['folderId'])) {
            $folderId = (int) $d['folderId'];
        } elseif (isset($d['folder_id'])) {
            $folderId = (int) $d['folder_id'];
        }

        return new self(
            url: $d['url'] ?? '',
            title: $d['title'] ?? null,
            description: $d['description'] ?? null,
            folderId: $folderId,
            tags: array_values($d['tags'] ?? []),
            tagsProvided: array_key_exists('tags', $d),
            folderIdProvided: $folderIdProvided,
        );
    }
}
