<?php

namespace App\Models;

use Database\Factories\ImportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Import extends Model
{
    /** @use HasFactory<ImportFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'format',
        'status',
        'total_rows',
        'processed_rows',
        'created_count',
        'duplicate_count',
        'error_count',
        'errors',
        'original_filename',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'errors' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
