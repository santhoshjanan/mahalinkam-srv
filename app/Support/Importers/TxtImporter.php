<?php

namespace App\Support\Importers;

use RuntimeException;

final class TxtImporter implements Importer
{
    public static function supports(string $extension): bool
    {
        return strtolower($extension) === 'txt';
    }

    public function rows(string $path): iterable
    {
        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException("Unable to open file: {$path}");
        }

        try {
            while (($line = fgets($handle)) !== false) {
                $line = trim($line);

                if ($line === '' || str_starts_with($line, '#')) {
                    continue;
                }

                yield new ParsedBookmark(url: $line);
            }
        } finally {
            fclose($handle);
        }
    }
}
