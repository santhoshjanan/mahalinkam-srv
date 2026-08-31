<?php

use App\Exceptions\FolderCycleException;
use App\Exceptions\FolderDepthException;
use App\Models\Bookmark;
use App\Models\Folder;
use App\Models\User;
use App\Services\FolderService;

beforeEach(fn () => $this->svc = new FolderService);

it('creates nested folders and reports depth', function () {
    $u = User::factory()->create();
    $a = $this->svc->create($u, 'A', null);
    $b = $this->svc->create($u, 'B', $a->id);
    expect($this->svc->depthOf($a))->toBe(1)
        ->and($this->svc->depthOf($b))->toBe(2);
});

it('refuses to create past MAX_DEPTH', function () {
    $u = User::factory()->create();
    $parent = null;
    for ($i = 1; $i <= FolderService::MAX_DEPTH; $i++) {
        $parent = $this->svc->create($u, "L{$i}", $parent?->id);
    }
    expect(fn () => $this->svc->create($u, 'toodeep', $parent->id))
        ->toThrow(FolderDepthException::class);
});

it('rejects moving a folder under its own descendant', function () {
    $u = User::factory()->create();
    $a = $this->svc->create($u, 'A', null);
    $b = $this->svc->create($u, 'B', $a->id);
    expect(fn () => $this->svc->move($a, $b->id))->toThrow(FolderCycleException::class);
});

it('re-parents children and unfiles bookmarks on delete', function () {
    $u = User::factory()->create();
    $root = $this->svc->create($u, 'root', null);
    $mid = $this->svc->create($u, 'mid', $root->id);
    $leaf = $this->svc->create($u, 'leaf', $mid->id);
    $bm = Bookmark::factory()->for($u)->create(['folder_id' => $mid->id]);

    $this->svc->delete($mid);

    expect(Folder::find($mid->id))->toBeNull()
        ->and($leaf->fresh()->parent_id)->toBe($root->id)
        ->and($bm->fresh()->folder_id)->toBeNull();
});
