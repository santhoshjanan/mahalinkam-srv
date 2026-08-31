<?php

use App\Models\Bookmark;
use App\Models\Folder;
use App\Models\User;
use App\Services\FolderService;

use function Pest\Laravel\actingAs;

it('creates, renames, moves, and deletes folders with re-parenting', function () {
    $u = User::factory()->create(['email_verified_at' => now()]);
    actingAs($u);

    $this->post('/folders', ['name' => 'root'])->assertRedirect();
    $root = Folder::where('user_id', $u->id)->firstOrFail();

    $this->post('/folders', ['name' => 'child', 'parent_id' => $root->id])->assertRedirect();
    $child = Folder::where('name', 'child')->firstOrFail();

    $this->patch("/folders/{$child->id}", ['name' => 'renamed'])->assertRedirect();
    expect($child->fresh()->name)->toBe('renamed');

    $this->patch("/folders/{$child->id}/move", ['parent_id' => null])->assertRedirect();
    expect($child->fresh()->parent_id)->toBeNull();

    $bm = Bookmark::factory()->for($u)->create(['folder_id' => $child->id]);
    $this->delete("/folders/{$child->id}")->assertRedirect();
    expect(Folder::find($child->id))->toBeNull()
        ->and($bm->fresh()->folder_id)->toBeNull();
});

it('rejects a cyclic move with a validation error', function () {
    $u = User::factory()->create(['email_verified_at' => now()]);
    actingAs($u);
    $svc = app(FolderService::class);
    $rootF = $svc->create($u, 'A', null);
    $childF = $svc->create($u, 'B', $rootF->id);
    $this->patch("/folders/{$rootF->id}/move", ['parent_id' => $childF->id])
        ->assertSessionHasErrors('parent_id');
});

it('forbids renaming another user\'s folder', function () {
    $u = User::factory()->create(['email_verified_at' => now()]);
    $foreign = Folder::factory()->create();
    actingAs($u)->patch("/folders/{$foreign->id}", ['name' => 'x'])->assertForbidden();
});

it('forbids moving another user\'s folder', function () {
    $u = User::factory()->create(['email_verified_at' => now()]);
    $foreign = Folder::factory()->create();
    actingAs($u)->patch("/folders/{$foreign->id}/move", ['parent_id' => null])->assertForbidden();
});

it('forbids deleting another user\'s folder', function () {
    $u = User::factory()->create(['email_verified_at' => now()]);
    $foreign = Folder::factory()->create();
    actingAs($u)->delete("/folders/{$foreign->id}")->assertForbidden();
});
