<?php

namespace App\Models;

use App\Enums\MetadataStatus;
use Database\Factories\BookmarkFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Bookmark extends Model
{
    /** @use HasFactory<BookmarkFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['folder_id', 'url', 'normalized_url', 'title', 'description', 'favicon_url', 'metadata_status'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata_status' => MetadataStatus::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }
}
