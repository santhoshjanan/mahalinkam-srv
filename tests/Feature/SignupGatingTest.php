<?php

use function Pest\Laravel\get;
use function Pest\Laravel\post;

it('allows registration when signups are enabled', function () {
    config()->set('mahalinkam.signups_enabled', true);
    get('/register')->assertOk();
});

it('blocks the register page with 403 when signups are disabled', function () {
    config()->set('mahalinkam.signups_enabled', false);
    get('/register')->assertForbidden();
});

it('blocks register POST with 403 when signups are disabled', function () {
    config()->set('mahalinkam.signups_enabled', false);
    post('/register', [
        'name' => 'A', 'email' => 'a@b.test',
        'password' => 'password123', 'password_confirmation' => 'password123',
    ])->assertForbidden();
    $this->assertDatabaseCount('users', 0);
});

it('shares signupsEnabled to the frontend', function () {
    config()->set('mahalinkam.signups_enabled', false);
    get('/login')->assertInertia(fn ($p) => $p->where('signupsEnabled', false));
});
