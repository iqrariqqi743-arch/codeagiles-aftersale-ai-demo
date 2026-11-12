<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use App\Services\SubscriptionLimitService;
use App\Services\WooCommerceOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Throwable;

class OrderController extends Controller
{
    /**
     * GET /api/orders
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $orders = Order::query()
                ->where('user_id', $request->user()->id)
                ->with([
                    'store:id,store_name',
                    'items',
                ])
                ->orderByDesc('order_date')
                ->orderByDesc('id')
                ->get()
                ->map(function ($order) {
                    $data = $order->toArray();

                    $data['store_name'] =
                        $order->store?->store_name;

                    unset($data['store']);

                    return $data;
                });

            return response()->json([
                'success' => true,
                'message' => 'Orders fetched successfully.',
                'orders' => $orders,
                'total' => $orders->count(),
            ]);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch orders.',
            ], 500);
        }
    }

    /**
     * POST /api/orders/demo
     */
    public function generateDemo(Request $request, SubscriptionLimitService $limits): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'store_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $userId = $request->user()->id;

        $demoOrders = [
            ['CA-1001', 'Ali Khan', 'iqrariqqi743@gmail.com', 120.00, 'completed'],
            ['CA-1002', 'Sara Ahmed', 'iqrariqqi743@gmail.com', 85.50, 'processing'],
            ['CA-1003', 'Usman Malik', 'iqrariqqi743@gmail.com', 230.00, 'pending'],
            ['CA-1004', 'Ayesha Noor', 'iqrariqqi743@gmail.com', 49.99, 'completed'],
            ['CA-1005', 'Bilal Hussain', 'iqrariqqi743@gmail.com', 170.00, 'on-hold'],
            ['CA-1006', 'Fatima Ali', 'iqrariqqi743@gmail.com', 65.00, 'refunded'],
            ['CA-1007', 'Hamza Khan', 'iqrariqqi743@gmail.com', 310.00, 'processing'],
            ['CA-1008', 'Zara Shah', 'iqrariqqi743@gmail.com', 92.00, 'completed'],
            ['CA-1009', 'Ahmed Raza', 'iqrariqqi743@gmail.com', 155.75, 'cancelled'],
            ['CA-1010', 'Hina Malik', 'iqrariqqi743@gmail.com', 210.00, 'pending'],
            ['CA-1011', 'Danish Ali', 'iqrariqqi743@gmail.com', 78.25, 'completed'],
            ['CA-1012', 'Mariam Khan', 'iqrariqqi743@gmail.com', 440.00, 'processing'],
        ];

        $products = [
            [
                'name' => 'Classic Cotton T-Shirt',
                'sku' => 'DEMO-TSHIRT-001',
                'image' => 'https://images.unsplash.com/photo-1521572163474-6864f9cf17ab?w=500',
                'color' => 'Black',
                'size' => 'M',
                'variation' => [
                    'material' => 'Cotton',
                    'fit' => 'Regular',
                ],
            ],
            [
                'name' => 'Running Sneakers',
                'sku' => 'DEMO-SHOES-002',
                'image' => 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=500',
                'color' => 'Red',
                'size' => '42',
                'variation' => [
                    'material' => 'Synthetic',
                    'style' => 'Running',
                ],
            ],
            [
                'name' => 'Casual Backpack',
                'sku' => 'DEMO-BAG-003',
                'image' => 'https://images.unsplash.com/photo-1553062407-98eeb64c6a62?w=500',
                'color' => 'Black',
                'size' => 'Standard',
                'variation' => [
                    'material' => 'Polyester',
                ],
            ],
            [
                'name' => 'Classic Wrist Watch',
                'sku' => 'DEMO-WATCH-004',
                'image' => 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=500',
                'color' => 'Silver',
                'size' => 'Standard',
                'variation' => [
                    'strap' => 'Metal',
                    'display' => 'Analog',
                ],
            ],
            [
                'name' => 'Denim Jacket',
                'sku' => 'DEMO-JACKET-005',
                'image' => 'https://images.unsplash.com/photo-1551028719-00167b16eac5?w=500',
                'color' => 'Blue',
                'size' => 'L',
                'variation' => [
                    'material' => 'Denim',
                    'fit' => 'Regular',
                ],
            ],
            [
                'name' => 'Casual Handbag',
                'sku' => 'DEMO-HANDBAG-006',
                'image' => 'https://images.unsplash.com/photo-1548036328-c9fa89d128fa?w=500',
                'color' => 'Brown',
                'size' => 'Standard',
                'variation' => [
                    'material' => 'Leather',
                ],
            ],
        ];

        try {
            $result = DB::transaction(function () use (
                $request,
                $userId,
                $demoOrders,
                $products,
                $limits
            ) {
                $store = Store::query()
                    ->whereKey($request->store_id)
                    ->where('user_id', $userId)
                    ->lockForUpdate()
                    ->first();

                if (!$store) {
                    return ['error' => 'not_found'];
                }

                if ($store->status === 'connected') {
                    return ['error' => 'connected'];
                }

                // Serialize all order creation for this owner, including syncs.
                User::query()->whereKey($userId)->lockForUpdate()->firstOrFail();
                $usage = $limits->getOrderUsage($userId);
                $remaining = $usage['unlimited'] ?? false
                    ? PHP_INT_MAX
                    : (int) ($usage['remaining'] ?? 0);
                $skippedLimit = 0;
                $created = 0;
                $itemsInserted = 0;
                $imagesUpdated = 0;

                foreach ($demoOrders as $index => $demo) {
                    [
                        $number,
                        $name,
                        $email,
                        $total,
                        $status
                    ] = $demo;

                    $order = Order::query()
                        ->where('user_id', $userId)
                        ->where('store_id', $store->id)
                        ->where('order_number', $number)
                        ->first();

                    if ($order && !$order->is_demo) {
                        continue;
                    }

                    $newOrder = !$order;

                    if ($newOrder) {
                        if ($remaining <= 0) {
                            $skippedLimit++;
                            continue;
                        }
                        $order = Order::create([
                            'user_id' => $userId,
                            'store_id' => $store->id,
                            'woocommerce_order_id' => null,
                            'order_number' => $number,
                            'customer_name' => $name,
                            'customer_email' => $email,
                            'customer_phone' => null,
                            'status' => $status,
                            'total_amount' => $total,
                            'currency' => 'USD',
                            'payment_method' => 'Demo Payment',
                            'shipping_address' => 'Demo Address, Pakistan',
                            'is_demo' => true,
                            'order_date' => now()->subDays($index),
                        ]);

                        $created++;
                        $remaining--;
                    }

                    $first = $products[$index % count($products)];
                    $second = $products[($index + 1) % count($products)];

                    $firstPrice = round($total * 0.6, 2);

                    $demoItems = [
                        [
                            'product' => $first,
                            'price' => $firstPrice,
                        ],
                        [
                            'product' => $second,
                            'price' => round($total - $firstPrice, 2),
                        ],
                    ];

                    foreach ($demoItems as $entry) {
                        $product = $entry['product'];

                        $item = $order->items()
                            ->where('product_sku', $product['sku'])
                            ->first();

                        if ($item) {
                            if (!$item->product_image) {
                                $item->update([
                                    'product_image' => $product['image'],
                                ]);

                                $imagesUpdated++;
                            }

                            continue;
                        }

                        // Preserve existing demo orders with
                        // a different product catalog.
                        if (
                            !$newOrder &&
                            $order->items()->exists()
                        ) {
                            continue;
                        }

                        $order->items()->create([
                            'woocommerce_item_id' => null,
                            'woocommerce_product_id' => null,
                            'woocommerce_variation_id' => null,
                            'product_name' => $product['name'],
                            'product_sku' => $product['sku'],
                            'product_image' => $product['image'],
                            'quantity' => 1,
                            'unit_price' => $entry['price'],
                            'total_price' => $entry['price'],
                            'color' => $product['color'],
                            'size' => $product['size'],
                            'variation' => $product['variation'],
                        ]);

                        $itemsInserted++;
                    }
                }

                return [
                    'created' => $created,
                    'skippedLimit' => $skippedLimit,
                    'usage' => $usage,
                    'itemsInserted' => $itemsInserted,
                    'imagesUpdated' => $imagesUpdated,
                    'store_id' => $store->id,
                ];
            });

            if (($result['error'] ?? null) === 'not_found') {
                return response()->json([
                    'success' => false,
                    'message' => 'Store not found or access denied.',
                ], 404);
            }

            if (($result['error'] ?? null) === 'connected') {
                return response()->json([
                    'success' => false,
                    'message' => 'Select a disconnected store.',
                ], 422);
            }

            return response()->json([
                'success' => true,
                'message' => $result['created'] > 0
                    ? "{$result['created']} demo orders generated successfully."
                    : 'Demo orders already exist. No duplicates created.',
                'created' => $result['created'],
                'inserted' => $result['created'],
                'itemsInserted' => $result['itemsInserted'],
                'imagesUpdated' => $result['imagesUpdated'],
                'store_id' => $result['store_id'],
                'skippedLimit' => $result['skippedLimit'],
                'limitReached' => $result['skippedLimit'] > 0,
            ]);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate demo orders.',
            ], 500);
        }
    }

    /**
     * POST /api/orders/sync-woocommerce
     */
    public function syncWooCommerce(
        Request $request,
        WooCommerceOrderService $wooService,
        SubscriptionLimitService $limits
    ): JsonResponse {
        $validator = Validator::make($request->all(), [
            'store_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $userId = $request->user()->id;

        $store = Store::query()
            ->whereKey($request->store_id)
            ->where('user_id', $userId)
            ->first();

        if (!$store) {
            return response()->json([
                'success' => false,
                'message' => 'Store not found or access denied.',
            ], 404);
        }

        if (
            $store->status !== 'connected' ||
            strtolower((string) $store->platform) !== 'woocommerce'
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Please connect your WooCommerce store first.',
            ], 422);
        }

        try {
            // Fetch from WooCommerce before opening a DB transaction.
            $wooOrders = $wooService->fetchOrders($store);

            $insertedOrders = 0;
            $updatedOrders = 0;
            $insertedItems = 0;
            $skippedLimit = 0;

            // Cache product image lookups within this sync.
            $imageCache = [];

            foreach (array_chunk($wooOrders, 50) as $batch) {
                $prepared = [];

                foreach ($batch as $wooOrder) {
                    $wooId = (int) ($wooOrder['id'] ?? 0);

                    if ($wooId <= 0) {
                        continue;
                    }

                    $billing = $wooOrder['billing'] ?? [];
                    $shipping = $wooOrder['shipping'] ?? [];

                    $billing = is_array($billing) ? $billing : [];
                    $shipping = is_array($shipping) ? $shipping : [];

                    $customerName = trim(
                        ($billing['first_name'] ?? '') . ' ' .
                            ($billing['last_name'] ?? '')
                    );

                    if ($customerName === '') {
                        $customerName = trim(
                            ($shipping['first_name'] ?? '') . ' ' .
                                ($shipping['last_name'] ?? '')
                        );
                    }

                    if ($customerName === '') {
                        $customerName = 'Guest Customer';
                    }

                    $addressParts = array_filter([
                        $shipping['address_1'] ?? null,
                        $shipping['address_2'] ?? null,
                        $shipping['city'] ?? null,
                        $shipping['state'] ?? null,
                        $shipping['postcode'] ?? null,
                        $shipping['country'] ?? null,
                    ]);

                    if (!$addressParts) {
                        $addressParts = array_filter([
                            $billing['address_1'] ?? null,
                            $billing['address_2'] ?? null,
                            $billing['city'] ?? null,
                            $billing['state'] ?? null,
                            $billing['postcode'] ?? null,
                            $billing['country'] ?? null,
                        ]);
                    }

                    $orderDate = $wooOrder['date_created_gmt']
                        ?? null;

                    $orderDate = $orderDate
                        ? $orderDate . ' UTC'
                        : ($wooOrder['date_created'] ?? null);

                    $items = [];

                    foreach (
                        ($wooOrder['line_items'] ?? []) as $line
                    ) {
                        if (!is_array($line)) {
                            continue;
                        }

                        $productId = (int) (
                            $line['product_id'] ?? 0
                        );

                        $variationId = (int) (
                            $line['variation_id'] ?? 0
                        );

                        $imageUrl = $line['image']['src']
                            ?? null;

                        if (
                            !is_string($imageUrl) ||
                            !filter_var(
                                $imageUrl,
                                FILTER_VALIDATE_URL
                            ) ||
                            strtolower(
                                parse_url(
                                    $imageUrl,
                                    PHP_URL_SCHEME
                                ) ?? ''
                            ) !== 'https'
                        ) {
                            $imageUrl = null;
                        }

                        if (!$imageUrl && $productId > 0) {
                            $cacheKey = $productId . ':' .
                                $variationId;

                            if (!array_key_exists(
                                $cacheKey,
                                $imageCache
                            )) {
                                $imageCache[$cacheKey] =
                                    $wooService->fetchProductImage(
                                        $store,
                                        $productId,
                                        $variationId ?: null
                                    );
                            }

                            $imageUrl = $imageCache[$cacheKey];
                        }

                        $variation = [];
                        $color = null;
                        $size = null;

                        foreach (
                            ($line['meta_data'] ?? []) as $meta
                        ) {
                            if (!is_array($meta)) {
                                continue;
                            }

                            $key = trim(
                                (string) ($meta['display_key']
                                    ?? $meta['key']
                                    ?? '')
                            );

                            $value = $meta['display_value']
                                ?? $meta['value']
                                ?? null;

                            if (
                                $key === '' ||
                                str_starts_with($key, '_') ||
                                !is_scalar($value)
                            ) {
                                continue;
                            }

                            $value = trim((string) $value);

                            if ($value === '') {
                                continue;
                            }

                            $variation[$key] = $value;

                            $normalizedKey = strtolower(
                                str_replace(
                                    ['pa_', 'attribute_', ' '],
                                    '',
                                    $key
                                )
                            );

                            if (
                                str_contains(
                                    $normalizedKey,
                                    'color'
                                ) ||
                                str_contains(
                                    $normalizedKey,
                                    'colour'
                                )
                            ) {
                                $color = $value;
                            }

                            if (
                                str_contains(
                                    $normalizedKey,
                                    'size'
                                )
                            ) {
                                $size = $value;
                            }
                        }

                        $quantity = max(
                            1,
                            (int) ($line['quantity'] ?? 1)
                        );

                        $lineTotal = round(
                            (float) ($line['total'] ?? 0),
                            2
                        );

                        $unitPrice = round(
                            $lineTotal / $quantity,
                            2
                        );

                        $items[] = [
                            'woocommerce_item_id' =>
                            $line['id'] ?? null,
                            'woocommerce_product_id' =>
                            $productId ?: null,
                            'woocommerce_variation_id' =>
                            $variationId ?: null,
                            'product_name' =>
                            $line['name'] ?? 'Unnamed Product',
                            'product_sku' =>
                            $line['sku'] ?? null,
                            'product_image' => $imageUrl,
                            'quantity' => $quantity,
                            'unit_price' => $unitPrice,
                            'total_price' => $lineTotal,
                            'color' => $color,
                            'size' => $size,
                            'variation' => $variation,
                        ];
                    }

                    $prepared[] = [
                        'woocommerce_id' => $wooId,
                        'order' => [
                            'user_id' => $userId,
                            'store_id' => $store->id,
                            'order_number' => (string) (
                                $wooOrder['number']
                                ?? $wooId
                            ),
                            'customer_name' => $customerName,
                            'customer_email' =>
                            $billing['email'] ?? null,
                            'customer_phone' =>
                            $billing['phone'] ?? null,
                            'status' =>
                            $wooOrder['status'] ?? 'pending',
                            'total_amount' =>
                            $wooOrder['total'] ?? 0,
                            'currency' =>
                            $wooOrder['currency'] ?? 'USD',
                            'payment_method' =>
                            $wooOrder['payment_method_title']
                                ?? $wooOrder['payment_method']
                                ?? null,
                            'shipping_address' =>
                            implode(', ', $addressParts),
                            'is_demo' => false,
                            'order_date' => $orderDate,
                            'last_synced_at' => now(),
                        ],
                        'items' => $items,
                    ];
                }

                DB::transaction(function () use (
                    $prepared,
                    $store,
                    $userId,
                    &$insertedOrders,
                    &$updatedOrders,
                    &$insertedItems,
                    &$skippedLimit,
                    $limits
                ) {
                    // Same owner-level lock as demo order creation.
                    User::query()->whereKey($userId)->lockForUpdate()->firstOrFail();
                    $usage = $limits->getOrderUsage($userId);
                    $remaining = $usage['unlimited'] ?? false
                        ? PHP_INT_MAX
                        : (int) ($usage['remaining'] ?? 0);

                    foreach ($prepared as $entry) {
                        $wooId = $entry['woocommerce_id'];

                        $order = Order::query()
                            ->where('store_id', $store->id)
                            ->where('woocommerce_order_id', $wooId)
                            ->lockForUpdate()
                            ->first();

                        if ($order) {
                            $order->update($entry['order']);
                            $updatedOrders++;
                        } else {
                            if ($remaining <= 0) {
                                $skippedLimit++;
                                continue;
                            }
                            $order = Order::create([
                                ...$entry['order'],
                                'woocommerce_order_id' => $wooId,
                            ]);

                            $insertedOrders++;
                            $remaining--;
                        }

                        // Replace synced items so quantities,
                        // prices and variations remain current.
                        $order->items()->delete();

                        foreach ($entry['items'] as $itemData) {
                            $order->items()->create($itemData);
                            $insertedItems++;
                        }
                    }
                });
            }

            $store->update([
                'last_synced_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => $skippedLimit > 0
                    ? 'Sync completed partially. Monthly order limit reached; upgrade to import more orders.'
                    : 'WooCommerce orders synced successfully.',
                'summary' => [
                    'totalFetched' => count($wooOrders),
                    'insertedOrders' => $insertedOrders,
                    'updatedOrders' => $updatedOrders,
                    'insertedItems' => $insertedItems,
                    'skippedLimit' => $skippedLimit,
                    'limitReached' => $skippedLimit > 0,
                ],
            ]);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'WooCommerce sync failed. Check the store connection and Laravel logs.',
            ], 500);
        }
    }
}
