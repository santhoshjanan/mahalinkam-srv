<?php

use App\Models\Bookmark;
use App\Models\Folder;
use App\Models\Tag;
use App\Models\User;

it('lets an owner act and blocks a stranger for each model', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();

    $models = [
        Bookmark::factory()->for($owner)->create(),
        Folder::factory()->for($owner)->create(),
        Tag::factory()->for($owner)->create(),
    ];

    foreach ($models as $m) {
        foreach (['view', 'update', 'delete'] as $ability) {
            expect($owner->can($ability, $m))->toBeTrue()
                ->and($stranger->can($ability, $m))->toBeFalse();
        }
    }
});
