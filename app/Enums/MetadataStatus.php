<?php

namespace App\Enums;

enum MetadataStatus: string
{
    case Pending = 'pending';
    case Done = 'done';
    case Failed = 'failed';
    case Skipped = 'skipped';
}
