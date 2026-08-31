<?php

namespace App\Support\Importers;

use InvalidArgumentException;

final class ImporterFactory
{
    /**
     * @var array<int, class-string<Importer>>
     */
    private const IMPORTERS = [
        TxtImporter::class,
        CsvImporter::class,
        JsonImporter::class,
        HtmlImporter::class,
    ];

    public static function for(string $format): Importer
    {
        $format = strtolower(trim($format));

        foreach (self::IMPORTERS as $importer) {
            if ($importer::supports($format)) {
                return new $importer;
            }
        }

        throw new InvalidArgumentException("Unsupported import format: {$format}");
    }
}
