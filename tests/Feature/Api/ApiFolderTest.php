<?php

use App\Models\Folder;
use App\Models\User;
use App\Services\FolderService;

function folderTok(User $u): string
{
    return $u->createToken('t')->plainTextToken;
}

it('requires a token', function () {
    $this->postJson('/api/folders', ['name' => 'Reading'])->assertUnauthorized();
});

it('creates a root folder and returns it unwrapped', function () {
    $u = User::factory()->create();

    $this->withToken(folderTok($u))
        ->postJson('/api/folders', ['name' => 'Reading'])
        ->assertCreated()
        ->assertJsonPath('name', 'Reading')
        ->assertJsonPath('parent_id', null)
        ->assertJsonPath('position', 1);

    expect(Folder::where('user_id', $u->id)->where('name', 'Reading')->exists())->toBeTrue();
});

it('creates a nested folder under an owned parent', function () {
    $u = User::factory()->create();
    $parent = Folder::factory()->for($u)->create(['name' => 'Reading', 'position' => 0]);

    $this->withToken(folderTok($u))
        ->postJson('/api/folders', ['name' => 'Tech', 'parent_id' => $parent->id])
        ->assertCreated()
        ->assertJsonPath('parent_id', $parent->id)
        ->assertJsonPath('name', 'Tech');
});

it('rejects a name that is missing or too long', function () {
    $u = User::factory()->create();
    $h = folderTok($u);

    $this->withToken($h)->postJson('/api/folders', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors('name');

    $this->withToken($h)->postJson('/api/folders', ['name' => str_repeat('a', 256)])
        ->assertStatus(422)
        ->assertJsonValidationErrors('name');
});

it('rejects a parent owned by another user', function () {
    $u = User::factory()->create();
    $stranger = User::factory()->create();
    $theirs = Folder::factory()->for($stranger)->create(['position' => 0]);

    $this->withToken(folderTok($u))
        ->postJson('/api/folders', ['name' => 'X', 'parent_id' => $theirs->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors('parent_id');
});

it('surfaces the depth limit as a 422 on parent_id', function () {
    $u = User::factory()->create();
    $svc = new FolderService;
    $parent = null;
    for ($i = 1; $i <= FolderService::MAX_DEPTH; $i++) {
        $parent = $svc->create($u, "L{$i}", $parent?->id);
    }

    $this->withToken(folderTok($u))
        ->postJson('/api/folders', ['name' => 'toodeep', 'parent_id' => $parent->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors('parent_id');
});

it('scopes the created folder to the token user', function () {
    $u = User::factory()->create();
    $other = User::factory()->create();

    $this->withToken(folderTok($u))->postJson('/api/folders', ['name' => 'Mine'])->assertCreated();

    expect(Folder::where('user_id', $other->id)->count())->toBe(0)
        ->and(Folder::where('user_id', $u->id)->count())->toBe(1);
});
