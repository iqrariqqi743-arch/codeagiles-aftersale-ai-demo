<?php

namespace App\Services;

use Illuminate\Support\Str;
use RuntimeException;

class SafeStoreUrl
{
    /**
     * Validate WooCommerce store URL.
     *
     * Production:
     *   Public HTTPS domains only.
     *
     * Local development:
     *   http://localhost/wordpress
     *   http://127.0.0.1/wordpress
     */
    public static function validate(string $url): string
    {
        $url = rtrim(trim($url), '/');

        $parts = parse_url($url);

        if (
            !$parts ||
            empty($parts['scheme']) ||
            empty($parts['host']) ||
            isset($parts['user']) ||
            isset($parts['pass']) ||
            isset($parts['fragment']) ||
            isset($parts['query'])
        ) {
            throw new RuntimeException(
                'Invalid WooCommerce store URL.'
            );
        }

        $scheme = strtolower($parts['scheme']);
        $host = strtolower($parts['host']);

        /*
         * Allow exact loopback hosts only during
         * local development.
         */
        $isLocalDevelopment = app()->environment('local')
            && config('app.debug') === true;

        $isLoopbackHost = in_array(
            $host,
            ['localhost', '127.0.0.1'],
            true
        );

        if ($isLoopbackHost) {
            if (
                !$isLocalDevelopment ||
                $scheme !== 'http' ||
                isset($parts['port'])
            ) {
                throw new RuntimeException(
                    'Local WooCommerce URLs are allowed only in local development over HTTP on the default port.'
                );
            }

            return $url;
        }

        /*
         * Production and other external stores
         * must use HTTPS without custom ports.
         */
        if ($scheme !== 'https' || isset($parts['port'])) {
            throw new RuntimeException(
                'A public HTTPS store URL is required.'
            );
        }

        if (
            Str::endsWith($host, [
                '.localhost',
                '.local',
                '.internal',
            ])
        ) {
            throw new RuntimeException(
                'Internal store domains are not allowed.'
            );
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            throw new RuntimeException(
                'Please use a public domain instead of an IP address.'
            );
        }

        if (
            !filter_var(
                $host,
                FILTER_VALIDATE_DOMAIN,
                FILTER_FLAG_HOSTNAME
            ) ||
            !str_contains($host, '.')
        ) {
            throw new RuntimeException(
                'Invalid public store domain.'
            );
        }

        /*
         * Resolve and verify public DNS addresses.
         */
        $records = dns_get_record(
            $host,
            DNS_A | DNS_AAAA
        );

        if (empty($records)) {
            throw new RuntimeException(
                'Store domain could not be resolved.'
            );
        }

        $addresses = [];

        foreach ($records as $record) {
            $ip = $record['ip']
                ?? $record['ipv6']
                ?? null;

            if (!$ip) {
                continue;
            }

            $addresses[] = $ip;

            if (
                !filter_var(
                    $ip,
                    FILTER_VALIDATE_IP,
                    FILTER_FLAG_NO_PRIV_RANGE |
                        FILTER_FLAG_NO_RES_RANGE
                )
            ) {
                throw new RuntimeException(
                    'Store domain resolves to a non-public IP address.'
                );
            }
        }

        if (empty($addresses)) {
            throw new RuntimeException(
                'Store domain has no valid IP address.'
            );
        }

        return $url;
    }
}
