<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Store;
use App\Models\Subscription;
use App\Services\SafeStoreUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use RuntimeException;
use Throwable;

class StoreController extends Controller
{
    private function resolvePlan(
        ?Subscription $subscription
    ): ?Plan {
        if (!$subscription) {
            return null;
        }

        $slug = strtolower(trim((string) $subscription->plan));

        return $slug === ''
            ? null
            : Plan::where('slug', $slug)->first();
    }

    private function remainingStores(
        int $limit,
        int $used
    ): int {
        return $limit === -1
            ? -1
            : max(0, $limit - $used);
    }

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

    private function validateStoreUrl(string $url): string
    {
        return SafeStoreUrl::validate(
            rtrim(trim($url), '/')
        );
    }

    private function resolvePublicStore(string $url): array
    {
        $parts = parse_url($url);

        if (!$parts || empty($parts['host'])) {
            throw new RuntimeException('Invalid store domain.');
        }

        $host = strtolower($parts['host']);

        $records = dns_get_record(
            $host,
            DNS_A | DNS_AAAA
        );

        $ips = [];

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
                    'Store domain does not resolve exclusively to public IP addresses.'
                );
            }

            $ips[] = $ip;
        }

        if (empty($ips)) {
            throw new RuntimeException(
                'Store domain could not be resolved.'
            );
        }

        return [
            'host' => $host,
            'ip' => $ips[0],
        ];
    }

    /**
     * Sign the exact standard WooCommerce REST URL
     * with OAuth 1.0a HMAC-SHA1.
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
     * List authenticated user's stores.
     */
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $stores = Store::where('user_id', $userId)
            ->latest()
            ->get();

        $subscription = $request->attributes->get(
            'active_subscription'
        );

        $plan = $this->resolvePlan($subscription);

        if (!$plan) {
            return response()->json([
                'success' => false,
                'message' => 'Subscription plan configuration not found.',
            ], 403);
        }

        $limit = (int) $plan->max_stores;
        $used = $stores->count();

        return response()->json([
            'success' => true,
            'message' => 'Stores fetched successfully',
            'stores' => $stores,
            'subscription' => [
                'plan' => $plan->slug,
                'plan_name' => $plan->name,
                'store_limit' => $limit,
                'stores_used' => $used,
                'stores_remaining' => $this->remainingStores(
                    $limit,
                    $used
                ),
            ],
        ]);
    }

    /**
     * Add store while preserving subscription limits.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'store_name' => 'required|string|max:150',
            'store_url' => 'required|url|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $url = $this->validateStoreUrl(
                $request->store_url
            );
        } catch (RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        $userId = $request->user()->id;

        return DB::transaction(function () use (
            $request,
            $userId,
            $url
        ) {
            DB::table('users')
                ->where('id', $userId)
                ->lockForUpdate()
                ->first();

            $subscription = Subscription::query()
                ->where('user_id', $userId)
                ->whereIn('status', ['active', 'trialing'])
                ->latest('id')
                ->first();

            if (
                !$subscription ||
                (
                    $subscription->current_period_end &&
                    $subscription->current_period_end->isPast()
                )
            ) {
                return response()->json([
                    'success' => false,
                    'message' => 'An active subscription is required.',
                    'subscription_required' => true,
                ], 403);
            }

            $plan = $this->resolvePlan($subscription);

            if (!$plan) {
                return response()->json([
                    'success' => false,
                    'message' => 'Subscription plan configuration not found.',
                ], 403);
            }

            $limit = (int) $plan->max_stores;

            if ($limit < -1) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid store limit configuration.',
                ], 403);
            }

            $used = Store::where(
                'user_id',
                $userId
            )->count();

            if ($limit !== -1 && $used >= $limit) {
                return response()->json([
                    'success' => false,
                    'message' => "Your {$plan->slug} plan allows a maximum of {$limit} store(s). Please upgrade your subscription to add more stores.",
                    'limit_reached' => true,
                    'subscription' => [
                        'plan' => $plan->slug,
                        'plan_name' => $plan->name,
                        'store_limit' => $limit,
                        'stores_used' => $used,
                        'stores_remaining' => 0,
                    ],
                ], 403);
            }

            $store = Store::create([
                'user_id' => $userId,
                'store_name' => trim($request->store_name),
                'store_url' => $url,
                'platform' => 'woocommerce',
                'status' => 'disconnected',
            ]);

            $used++;

            return response()->json([
                'success' => true,
                'message' => 'Store added successfully',
                'store' => $store,
                'subscription' => [
                    'plan' => $plan->slug,
                    'plan_name' => $plan->name,
                    'store_limit' => $limit,
                    'stores_used' => $used,
                    'stores_remaining' => $this->remainingStores(
                        $limit,
                        $used
                    ),
                ],
            ], 201);
        });
    }

    /**
     * Verify WooCommerce API credentials.
     */
    public function connect(
        Request $request,
        int $id
    ): JsonResponse {
        $store = Store::where(
            'user_id',
            $request->user()->id
        )->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'consumer_key' => 'required|string|max:255',
            'consumer_secret' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $url = $this->validateStoreUrl(
                $store->store_url
            );

            $consumerKey = trim($request->consumer_key);
            $consumerSecret = trim($request->consumer_secret);

            $endpoint = $url . '/wp-json/wc/v3/orders';

            if ($this->isLocalStore($url)) {
                /*
                 * Local HTTP:
                 * OAuth 1.0a signed request.
                 */
                $query = $this->oauthParameters(
                    $endpoint,
                    $consumerKey,
                    $consumerSecret,
                    [
                        'per_page' => '1',
                    ]
                );

                $response = Http::timeout(15)
                    ->connectTimeout(5)
                    ->withoutRedirecting()
                    ->withOptions([
                        'proxy' => '',
                    ])
                    ->acceptJson()
                    ->get($endpoint, $query);
            } else {
                /*
                 * Production HTTPS:
                 * Public DNS validation and IP pinning.
                 */
                $resolved = $this->resolvePublicStore(
                    $url
                );

                $host = $resolved['host'];
                $ip = $resolved['ip'];

                $response = Http::timeout(15)
                    ->connectTimeout(5)
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
                    ->get($endpoint, [
                        'per_page' => 1,
                    ]);
            }

            if ($response->status() === 401) {
                return response()->json([
                    'success' => false,
                    'message' => 'WooCommerce authentication failed.',
                    'woocommerce_error' => $response->json('code'),
                    'woocommerce_message' => $response->json('message'),
                    'http_status' => 401,
                ], 422);
            }

            if ($response->status() === 403) {
                return response()->json([
                    'success' => false,
                    'message' => 'WooCommerce access denied. Check API key permissions.',
                    'woocommerce_error' => $response->json('code'),
                    'http_status' => 403,
                ], 422);
            }

            if (!$response->successful()) {
                return response()->json([
                    'success' => false,
                    'message' => 'WooCommerce verification failed.',
                    'http_status' => $response->status(),
                ], 422);
            }

            $orders = $response->json();

            if (
                !is_array($orders) ||
                !array_is_list($orders)
            ) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unexpected WooCommerce orders response.',
                ], 422);
            }

            /*
             * Save credentials only after successful
             * verification. Store model must encrypt
             * and hide these fields.
             */
            $store->update([
                'consumer_key' => $consumerKey,
                'consumer_secret' => $consumerSecret,
                'status' => 'connected',
                'connected_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'WooCommerce connected successfully.',
                'store' => $store->fresh(),
            ]);
        } catch (RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to verify WooCommerce connection.',
            ], 422);
        }
    }

    /**
     * Disconnect store.
     */
    public function disconnect(
        Request $request,
        int $id
    ): JsonResponse {
        $store = Store::where(
            'user_id',
            $request->user()->id
        )->findOrFail($id);

        $store->update([
            'consumer_key' => null,
            'consumer_secret' => null,
            'status' => 'disconnected',
            'connected_at' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'WooCommerce disconnected successfully.',
            'store' => $store->fresh(),
        ]);
    }

    /**
     * Delete store.
     */
    public function destroy(
        Request $request,
        int $id
    ): JsonResponse {
        $store = Store::where(
            'user_id',
            $request->user()->id
        )->findOrFail($id);

        $store->delete();

        return response()->json([
            'success' => true,
            'message' => 'Store deleted successfully.',
        ]);
    }
}
