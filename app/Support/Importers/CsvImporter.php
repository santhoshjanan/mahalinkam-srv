<?php

namespace App\Support\Importers;

use RuntimeException;

final class CsvImporter implements Importer
{
    public static function supports(string $extension): bool
    {
        return strtolower($extension) === 'csv';
    }

    public function rows(string $path): iterable
    {
        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException("Unable to open file: {$path}");
        }

        try {
            $header = fgetcsv($handle, escape: '');

            if ($header === false || $header === null) {
                throw new RuntimeException('CSV file is empty; a header row is required.');
            }

            $header = array_map(
                fn ($name) => strtolower(trim((string) $name)),
                $header
            );

            if (! in_array('url', $header, true)) {
                throw new RuntimeException('CSV header must contain a "url" column.');
            }

            while (($record = fgetcsv($handle, escape: '')) !== false) {
                if ($record === [null] || $record === []) {
                    continue;
                }

                $row = [];
                foreach ($header as $i => $key) {
                    $row[$key] = isset($record[$i]) ? trim((string) $record[$i]) : '';
                }

                $url = $row['url'] ?? '';

                if ($url === '') {
                    continue;
                }

                yield new ParsedBookmark(
                    url: $url,
                    title: ($row['title'] ?? '') !== '' ? $row['title'] : null,
                    description: ($row['description'] ?? '') !== '' ? $row['description'] : null,
                    tags: $this->splitList($row['tags'] ?? '', ';'),
                    folderPath: $this->splitList($row['folder'] ?? '', '/'),
                );
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * @return array<int, string>
     */
    private function splitList(string $value, string $delimiter): array
    {
        if (trim($value) === '') {
            return [];
        }

        return array_values(array_filter(
            array_map('trim', explode($delimiter, $value)),
            fn ($segment) => $segment !== '',
        ));
    }
}
