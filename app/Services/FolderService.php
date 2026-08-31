<?php

namespace App\Services;

use App\Exceptions\FolderCycleException;
use App\Exceptions\FolderDepthException;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class FolderService
{
    public const MAX_DEPTH = 10;

    public function create(User $user, string $name, ?int $parentId): Folder
    {
        $parent = $parentId ? $this->ownedFolder($user, $parentId) : null;

        if ($parent && $this->depthOf($parent) + 1 > self::MAX_DEPTH) {
            throw new FolderDepthException('Folder nesting limit reached.');
        }

        return $user->folders()->create([
            'name' => $name,
            'parent_id' => $parent?->id,
            'position' => (int) $user->folders()->where('parent_id', $parent?->id)->max('position') + 1,
        ]);
    }

    public function move(Folder $folder, ?int $newParentId): Folder
    {
        if ($newParentId !== null) {
            if ($newParentId === $folder->id || $this->isDescendant($folder, $newParentId)) {
                throw new FolderCycleException('Cannot move a folder into itself or a descendant.');
            }

            $newParent = $this->ownedFolder($folder->user, $newParentId);

            if ($this->depthOf($newParent) + $this->subtreeHeight($folder) > self::MAX_DEPTH) {
                throw new FolderDepthException('Move would exceed the nesting limit.');
            }
        }

        $folder->parent_id = $newParentId;
        $folder->position = (int) $folder->user->folders()->where('parent_id', $newParentId)->max('position') + 1;
        $folder->save();

        return $folder;
    }

    public function delete(Folder $folder): void
    {
        DB::transaction(function () use ($folder) {
            $folder->children()->update(['parent_id' => $folder->parent_id]);
            $folder->bookmarks()->update(['folder_id' => null]);
            $folder->delete();
        });
    }

    public function depthOf(Folder $folder): int
    {
        $depth = 1;
        $cur = $folder;

        while ($cur->parent_id !== null) {
            $cur = $cur->parent()->first();
            $depth++;
        }

        return $depth;
    }

    public function subtreeHeight(Folder $folder): int
    {
        $children = $folder->children()->get();

        if ($children->isEmpty()) {
            return 1;
        }

        return 1 + $children->max(fn (Folder $c) => $this->subtreeHeight($c));
    }

    private function isDescendant(Folder $ancestor, int $candidateId): bool
    {
        $cur = Folder::find($candidateId);

        while ($cur && $cur->parent_id !== null) {
            if ($cur->parent_id === $ancestor->id) {
                return true;
            }
            $cur = $cur->parent()->first();
        }

        return false;
    }

    private function ownedFolder(User $user, int $id): Folder
    {
        return $user->folders()->findOrFail($id);
    }
}
