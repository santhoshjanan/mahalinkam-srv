<?php

namespace App\Support\Importers;

interface Importer
{
    /**
     * @return iterable<int, ParsedBookmark>
     */
    public function rows(string $path): iterable;

    public static function supports(string $extension): bool;
}
