<?php

namespace App\Services;

use App\Models\Store;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class WooCommerceOrderService
{
    private const PER_PAGE = 100;
    private const MAX_PAGES = 50;

    /**
     * Fetch real WooCommerce orders.
     */
    public function fetchOrders(Store $store): array
    {
        if (
            $store->status !== 'connected' ||
            strtolower((string) $store->platform) !== 'woocommerce'
        ) {
            throw new RuntimeException(
                'Please connect a WooCommerce store first.'
            );
        }

        if (
            !$store->consumer_key ||
            !$store->consumer_secret
        ) {
            throw new RuntimeException(
                'WooCommerce API credentials are missing.'
            );
        }

        $allOrders = [];

        for ($page = 1; $page <= self::MAX_PAGES; $page++) {
            $orders = $this->request(
                $store,
                'orders',
                [
                    'per_page' => self::PER_PAGE,
                    'page' => $page,
                    'orderby' => 'date',
                    'order' => 'desc',
                ]
            );

            if (!array_is_list($orders)) {
                throw new RuntimeException(
                    'WooCommerce orders response must be a list.'
                );
            }

            foreach ($orders as $order) {
                if (is_array($order)) {
                    $allOrders[] = $order;
                }
            }

            if (count($orders) < self::PER_PAGE) {
                break;
            }
        }

        return $allOrders;
    }

    /**
     * Fetch a WooCommerce product image.
     */
    public function fetchProductImage(
        Store $store,
        int $productId,
        ?int $variationId = null
    ): ?string {
        if ($productId <= 0) {
            return null;
        }

        try {
            if ($variationId && $variationId > 0) {
                $variation = $this->request(
                    $store,
                    "products/{$productId}/variations/{$variationId}"
                );

                $image = $variation['image']['src'] ?? null;

                if ($this->isValidImageUrl($image)) {
                    return $image;
                }
            }

            $product = $this->request(
                $store,
                "products/{$productId}"
            );

            foreach ($product['images'] ?? [] as $image) {
                $imageUrl = $image['src'] ?? null;

                if ($this->isValidImageUrl($imageUrl)) {
                    return $imageUrl;
                }
            }
        } catch (Throwable $e) {
            // Product images are optional.
            // A failed image lookup must not stop order sync.
            report($e);
        }

        return null;
    }

    /**
     * Local development only:
     * Allow HTTP for exact localhost hosts.
     */
    private function isLocalStore(string $url): bool
    {
        if (
            !app()->environment('local') ||
            config('app.debug') !== true
        ) {
            return false;
        }

        $parts = parse_url($url);

        if (!$parts) {
            return false;
        }

        return strtolower($parts['scheme'] ?? '') === 'http'
            && in_array(
                strtolower($parts['host'] ?? ''),
                ['localhost', '127.0.0.1'],
                true
            )
            && !isset($parts['port'])
            && !isset($parts['user'])
            && !isset($parts['pass'])
            && !isset($parts['query'])
            && !isset($parts['fragment']);
    }

    /**
     * OAuth 1.0a HMAC-SHA1.
     *
     * Matches the working StoreController
     * authentication for local WooCommerce.
     */
    private function oauthParameters(
        string $endpoint,
        string $consumerKey,
        string $consumerSecret,
        array $query
    ): array {
        $oauth = [
            'oauth_consumer_key' => $consumerKey,
            'oauth_nonce' => bin2hex(random_bytes(16)),
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_timestamp' => (string) time(),
            'oauth_version' => '1.0',
        ];

        $all = array_merge($query, $oauth);

        $pairs = [];

        foreach ($all as $key => $value) {
            $pairs[] = [
                rawurlencode((string) $key),
                rawurlencode((string) $value),
            ];
        }

        usort($pairs, function ($a, $b) {
            $result = strcmp($a[0], $b[0]);

            return $result !== 0
                ? $result
                : strcmp($a[1], $b[1]);
        });

        $normalized = [];

        foreach ($pairs as [$key, $value]) {
            $normalized[] = $key . '=' . $value;
        }

        $parameterString = implode('&', $normalized);

        $baseString = 'GET'
            . '&' . rawurlencode($endpoint)
            . '&' . rawurlencode($parameterString);

        $signingKey = rawurlencode($consumerSecret) . '&';

        $oauth['oauth_signature'] = base64_encode(
            hash_hmac(
                'sha1',
                $baseString,
                $signingKey,
                true
            )
        );

        return array_merge($query, $oauth);
    }

    /**
     * Resolve public IPs for production HTTPS.
     */
    private function resolvePublicStore(string $url): array
    {
        $parts = parse_url($url);

        if (!$parts || empty($parts['host'])) {
            throw new RuntimeException(
                'Invalid WooCommerce store domain.'
            );
        }

        $host = strtolower($parts['host']);

        $records = dns_get_record(
            $host,
            DNS_A | DNS_AAAA
        );

        $publicIps = [];

        foreach ($records ?: [] as $record) {
            $ip = $record['ip']
                ?? $record['ipv6']
                ?? null;

            if (
                !$ip ||
                !filter_var(
                    $ip,
                    FILTER_VALIDATE_IP,
                    FILTER_FLAG_NO_PRIV_RANGE |
                        FILTER_FLAG_NO_RES_RANGE
                )
            ) {
                throw new RuntimeException(
                    'Store domain resolved to an unsafe address.'
                );
            }

            $publicIps[] = $ip;
        }

        if (empty($publicIps)) {
            throw new RuntimeException(
                'No public IP address found for the store.'
            );
        }

        return [
            'host' => $host,
            'ip' => $publicIps[0],
        ];
    }

    /**
     * Authenticated WooCommerce API request.
     *
     * Local HTTP: OAuth 1.0a HMAC-SHA1
     * Production HTTPS: Basic Auth + IP pinning
     */
    private function request(
        Store $store,
        string $endpoint,
        array $query = []
    ): array {
        $baseUrl = SafeStoreUrl::validate(
            rtrim((string) $store->store_url, '/')
        );

        $endpoint = ltrim($endpoint, '/');

        $url = $baseUrl
            . '/wp-json/wc/v3/'
            . $endpoint;

        $consumerKey = $store->consumer_key;
        $consumerSecret = $store->consumer_secret;

        if ($this->isLocalStore($baseUrl)) {
            /*
             * Same OAuth authentication that
             * successfully connected the store.
             */
            $signedQuery = $this->oauthParameters(
                $url,
                $consumerKey,
                $consumerSecret,
                $query
            );

            $response = Http::timeout(25)
                ->connectTimeout(10)
                ->withoutRedirecting()
                ->withOptions([
                    'proxy' => '',
                ])
                ->acceptJson()
                ->get($url, $signedQuery);
        } else {
            /*
             * Production HTTPS:
             * Validate DNS and pin the connection.
             */
            $resolved = $this->resolvePublicStore(
                $baseUrl
            );

            $host = $resolved['host'];
            $ip = $resolved['ip'];

            $response = Http::timeout(25)
                ->connectTimeout(10)
                ->withoutRedirecting()
                ->withOptions([
                    'curl' => [
                        CURLOPT_RESOLVE => [
                            $host . ':443:' . $ip,
                        ],
                    ],
                    'verify' => true,
                    'proxy' => '',
                ])
                ->withBasicAuth(
                    $consumerKey,
                    $consumerSecret
                )
                ->acceptJson()
                ->get($url, $query);
        }

        if (!$response->successful()) {
            $status = $response->status();

            $errorCode = $response->json('code');
            $errorMessage = $response->json('message');

            $message = "WooCommerce API returned HTTP {$status}.";

            if ($status === 401) {
                $message = 'WooCommerce authentication failed.';
            } elseif ($status === 403) {
                $message = 'WooCommerce API access denied.';
            }

            if (is_string($errorCode) && $errorCode !== '') {
                $message .= ' Code: ' . $errorCode . '.';
            }

            if (is_string($errorMessage) && $errorMessage !== '') {
                $message .= ' ' . $errorMessage;
            }

            throw new RuntimeException($message);
        }

        $data = $response->json();

        if (!is_array($data)) {
            throw new RuntimeException(
                'WooCommerce returned an unexpected response.'
            );
        }

        return $data;
    }

    /**
     * Only allow HTTPS product image URLs.
     */
    private function isValidImageUrl($url): bool
    {
        if (!is_string($url) || !$url) {
            return false;
        }

        $parts = parse_url($url);

        return is_array($parts)
            && strtolower($parts['scheme'] ?? '') === 'https'
            && !empty($parts['host'])
            && !isset($parts['user'])
            && !isset($parts['pass']);
    }
}
