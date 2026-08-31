<?php

namespace App\Support;

use App\Exceptions\BlockedHostException;

class PrivateNetworkGuard
{
    /**
     * True for anything that is not a valid PUBLIC IP address:
     * loopback, RFC1918 private, link-local, ULA, reserved, 0.0.0.0/8, etc.
     */
    public function isBlockedIp(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
            return false; // valid public IP
        }

        return true;
    }

    /**
     * Ensure the given host does not point at a private/reserved network.
     *
     * @throws BlockedHostException on any blocked IP or on resolution failure.
     */
    public function assertHostAllowed(string $host): void
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            if ($this->isBlockedIp($host)) {
                throw new BlockedHostException("Blocked IP host: {$host}");
            }

            return;
        }

        $records = array_merge(
            @dns_get_record($host, DNS_A) ?: [],
            @dns_get_record($host, DNS_AAAA) ?: [],
        );

        if ($records === []) {
            throw new BlockedHostException("Cannot resolve host: {$host}");
        }

        foreach ($records as $record) {
            $ip = $record['ip'] ?? $record['ipv6'] ?? null;
            if ($ip !== null && $this->isBlockedIp($ip)) {
                throw new BlockedHostException("Host {$host} resolves to blocked IP: {$ip}");
            }
        }
    }
}
