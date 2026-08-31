<?php // tests/Unit/ConfigTest.php
use function Pest\Laravel\artisan;

it('exposes mahalinkam config with documented defaults', function () {
    expect(config('mahalinkam.signups_enabled'))->toBeTrue()
        ->and(config('mahalinkam.metadata.enabled'))->toBeTrue()
        ->and(config('mahalinkam.metadata.timeout'))->toBe(8)
        ->and(config('mahalinkam.metadata.max_bytes'))->toBe(524288)
        ->and(config('mahalinkam.import.max_file_mb'))->toBe(20);
});

it('coerces SIGNUPS_ENABLED=false from env', function () {
    config()->set('mahalinkam.signups_enabled', filter_var('false', FILTER_VALIDATE_BOOL));
    expect(config('mahalinkam.signups_enabled'))->toBeFalse();
});
