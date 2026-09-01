<?php

namespace App\Queries;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class BookmarkListQuery
{
    public const PER_PAGE = 50;

    public static function for(User $user, array $filters): LengthAwarePaginator
    {
        $q = $user->bookmarks()->with('tags');

        if (filled($filters['q'] ?? null)) {
            foreach (preg_split('/\s+/', trim($filters['q'])) as $term) {
                $like = '%'.Str::lower($term).'%';
                // lower() is a deliberate ANSI-SQL exception to the "no raw SQL" rule — portable across sqlite/mysql/pgsql, verified by the 3-DB CI matrix.
                $q->where(function (Builder $sub) use ($like) {
                    $sub->whereRaw('lower(title) like ?', [$like])
                        ->orWhereRaw('lower(url) like ?', [$like])
                        ->orWhereRaw('lower(description) like ?', [$like])
                        ->orWhereHas('tags', fn (Builder $t) => $t->whereRaw('lower(name) like ?', [$like]));
                });
            }
        }

        $folder = $filters['folder_id'] ?? null;
        if ($folder === 'unfiled') {
            $q->whereNull('folder_id');
        } elseif (is_numeric($folder)) {
            $q->where('folder_id', (int) $folder);
        }

        foreach ($filters['tags'] ?? [] as $tagName) {
            $q->whereHas('tags', fn (Builder $t) => $t->where('name_lower', Str::lower(trim($tagName))));
        }

        match ($filters['sort'] ?? 'created_desc') {
            'created_asc' => $q->orderBy('created_at')->orderBy('id'),
            // lower() is a deliberate ANSI-SQL exception to the "no raw SQL" rule — portable across sqlite/mysql/pgsql, verified by the 3-DB CI matrix.
            'title_asc' => $q->orderByRaw('lower(title) asc')->orderBy('id'),
            default => $q->orderByDesc('created_at')->orderByDesc('id'),
        };

        return $q->paginate(self::PER_PAGE)->withQueryString();
    }
}
