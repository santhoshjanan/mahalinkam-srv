<?php // config/mahalinkam.php
return [
    'signups_enabled' => env('SIGNUPS_ENABLED', true),
    'metadata' => [
        'enabled'   => env('METADATA_FETCH_ENABLED', true),
        'timeout'   => (int) env('METADATA_FETCH_TIMEOUT', 8),
        'max_bytes' => (int) env('METADATA_FETCH_MAX_BYTES', 524288),
    ],
    'import' => [
        'max_file_mb' => (int) env('IMPORT_MAX_FILE_MB', 20),
    ],
];
