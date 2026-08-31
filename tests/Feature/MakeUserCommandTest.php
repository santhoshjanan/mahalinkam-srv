<?php

use App\Models\User;

it('creates a verified user non-interactively', function () {
    $this->artisan('mahalinkam:make-user', [
        'email' => 'admin@site.test', 'name' => 'Admin', '--password' => 'secret1234',
    ])->assertSuccessful();

    $u = User::whereEmail('admin@site.test')->first();
    expect($u)->not->toBeNull()
        ->and($u->name)->toBe('Admin')
        ->and($u->email_verified_at)->not->toBeNull()
        ->and(\Hash::check('secret1234', $u->password))->toBeTrue();
});

it('refuses a duplicate email', function () {
    User::factory()->create(['email' => 'dupe@site.test']);
    $this->artisan('mahalinkam:make-user', [
        'email' => 'dupe@site.test', 'name' => 'X', '--password' => 'secret1234',
    ])->assertFailed();
});
