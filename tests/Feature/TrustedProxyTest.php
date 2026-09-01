<?php

use Illuminate\Support\Facades\Route;

it('trusts X-Forwarded-Proto from an arbitrary proxy so the request is seen as secure', function () {
    Route::get('/__proxy-probe', fn () => [
        'secure' => request()->isSecure(),
        'scheme' => request()->getScheme(),
    ]);

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])
        ->get('/__proxy-probe', ['X-Forwarded-Proto' => 'https'])
        ->assertOk()
        ->assertJson(['secure' => true, 'scheme' => 'https']);
});

it('leaves a plain HTTP request insecure', function () {
    Route::get('/__proxy-probe-plain', fn () => ['secure' => request()->isSecure()]);

    $this->get('/__proxy-probe-plain')
        ->assertOk()
        ->assertJson(['secure' => false]);
});
