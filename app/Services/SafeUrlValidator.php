<?php

namespace App\Services;

use InvalidArgumentException;

class SafeUrlValidator
{
    public function validate(string $url): array
    {
        $parts = parse_url($url);
        if (! is_array($parts) || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true) || empty($parts['host'])) {
            throw new InvalidArgumentException('Enter a complete public HTTP or HTTPS URL.');
        }
        if (isset($parts['user']) || isset($parts['pass']) || isset($parts['port']) && ! in_array((int) $parts['port'], [80, 443], true)) {
            throw new InvalidArgumentException('Credentials and non-standard ports are not allowed.');
        }
        $host = trim(strtolower(rtrim($parts['host'], '.')), '[]');
        if ($host === 'localhost' || str_ends_with($host, '.localhost') || $host === 'metadata.google.internal') {
            throw new InvalidArgumentException('Local and private hosts cannot be audited.');
        }
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : $this->resolve($host);
        if ($ips === []) {
            throw new InvalidArgumentException('The hostname could not be resolved.');
        }
        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new InvalidArgumentException('The hostname resolves to a private or reserved address.');
            }
        }

        return ['url' => $url, 'host' => $host, 'ips' => array_values(array_unique($ips)), 'port' => ($parts['scheme'] === 'https' ? 443 : 80)];
    }

    protected function resolve(string $host): array
    {
        $records = @dns_get_record($host, DNS_A | DNS_AAAA) ?: [];

        return array_values(array_filter(array_map(fn ($record) => $record['ip'] ?? $record['ipv6'] ?? null, $records)));
    }
}
