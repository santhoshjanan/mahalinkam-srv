<?php

// tests/Unit/SmokeTest.php
use Tests\TestCase;

uses(TestCase::class);

it('boots the framework', fn () => expect(app()->version())->toStartWith('12.'));
