<?php // tests/Unit/ConfigTest.php
use Tests\TestCase;

uses(TestCase::class);

it('exposes mahalinkam config with documented defaults', function () {
    expect(config('mahalinkam.signups_enabled'))->toBeTrue()
        ->and(config('mahalinkam.metadata.enabled'))->toBeTrue()
        ->and(config('mahalinkam.metadata.timeout'))->toBe(8)
        ->and(config('mahalinkam.metadata.max_bytes'))->toBe(524288)
        ->and(config('mahalinkam.import.max_file_mb'))->toBe(20);
});

it('casts numeric knobs to integers', function () {
    expect(config('mahalinkam.metadata.timeout'))->toBeInt()
        ->and(config('mahalinkam.metadata.max_bytes'))->toBeInt()
        ->and(config('mahalinkam.import.max_file_mb'))->toBeInt();
});
