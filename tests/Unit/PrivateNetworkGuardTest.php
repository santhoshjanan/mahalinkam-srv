<?php

use App\Support\PrivateNetworkGuard;

it('classifies private and public IPs', function () {
    $g = new PrivateNetworkGuard;

    foreach (['127.0.0.1', '10.0.0.5', '192.168.1.1', '169.254.1.1', '::1', '0.0.0.0'] as $ip) {
        expect($g->isBlockedIp($ip))->toBeTrue("{$ip} should be blocked");
    }

    foreach (['1.1.1.1', '93.184.216.34', '2606:2800:220:1:248:1893:25c8:1946'] as $ip) {
        expect($g->isBlockedIp($ip))->toBeFalse("{$ip} should be allowed");
    }
});
