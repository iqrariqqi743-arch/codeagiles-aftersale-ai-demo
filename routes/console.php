<?php

namespace {

    use App\Models\Store;
    use App\Services\GmailImapService;
    use App\Services\WooCommerceOrderService;
    use Illuminate\Foundation\Inspiring;
    use Illuminate\Support\Facades\Artisan;
    use Illuminate\Support\Facades\Log;
    use Illuminate\Support\Facades\Schedule;

    /*
    |--------------------------------------------------------------------------
    | Laravel Default Command
    |--------------------------------------------------------------------------
    */

    Artisan::command('inspire', function () {
        $this->comment(Inspiring::quote());
    })->purpose('Display an inspiring quote');

    /*
    |--------------------------------------------------------------------------
    | AfterSale AI - Gmail Customer Replies Sync
    |--------------------------------------------------------------------------
    */

    Artisan::command('gmail:sync-replies', function (
        GmailImapService $gmailImapService
    ) {
        $this->info('Checking Gmail for customer replies...');

        try {
            $stats = $gmailImapService->syncCustomerReplies();

            $this->table(
                [
                    'Checked',
                    'Saved',
                    'Duplicates',
                    'Skipped',
                    'Errors',
                ],
                [[
                    $stats['checked'] ?? 0,
                    $stats['saved'] ?? 0,
                    $stats['duplicates'] ?? 0,
                    $stats['skipped'] ?? 0,
                    $stats['errors'] ?? 0,
                ]]
            );

            Log::info('Automatic Gmail sync completed', $stats);

            if (($stats['errors'] ?? 0) > 0) {
                $this->warn(
                    'Some customer emails could not be processed.'
                );

                return 1;
            }

            $this->info(
                'Gmail sync completed successfully.'
            );

            return 0;
        } catch (\Throwable $exception) {
            Log::error('Automatic Gmail sync failed', [
                'error' => $exception->getMessage(),
            ]);

            $this->error(
                'Gmail sync failed: ' .
                    $exception->getMessage()
            );

            return 1;
        }
    })->purpose(
        'Sync Gmail customer replies into support tickets'
    );

    /*
    |--------------------------------------------------------------------------
    | AfterSale AI - WooCommerce Hourly Orders Sync
    |--------------------------------------------------------------------------
    |
    | This command runs the existing authenticated
    | order sync controller for every connected store.
    |
    | The controller already handles:
    | - WooCommerce OAuth authentication
    | - Order creation and updates
    | - Order items and product images
    | - Subscription order limits
    | - Duplicate prevention
    |
    */

    Artisan::command('woocommerce:sync-orders', function () {

        $this->info(
            'Starting automatic WooCommerce orders sync...'
        );

        $stats = [
            'stores_checked' => 0,
            'stores_synced' => 0,
            'stores_failed' => 0,
            'orders_fetched' => 0,
            'orders_inserted' => 0,
            'orders_updated' => 0,
        ];

        Store::query()
            ->where('platform', 'woocommerce')
            ->where('status', 'connected')
            ->orderBy('id')
            ->chunkById(50, function ($stores) use (&$stats) {

                foreach ($stores as $store) {
                    $stats['stores_checked']++;

                    try {
                        $user = $store->user;

                        if (!$user) {
                            throw new \RuntimeException(
                                'Store owner not found.'
                            );
                        }

                        /*
                         * Reuse the existing sync endpoint
                         * through Laravel's internal request.
                         */
                        $request = \Illuminate\Http\Request::create(
                            '/api/orders/sync-woocommerce',
                            'POST',
                            [
                                'store_id' => $store->id,
                            ]
                        );

                        $request->setUserResolver(
                            fn() => $user
                        );

                        $controller = app(
                            \App\Http\Controllers\Api\OrderController::class
                        );

                        $response = $controller->syncWooCommerce(
                            $request,
                            app(WooCommerceOrderService::class),
                            app(
                                \App\Services\SubscriptionLimitService::class
                            )
                        );

                        $result = $response->getData(true);

                        if (
                            $response->getStatusCode() >= 400 ||
                            !($result['success'] ?? false)
                        ) {
                            throw new \RuntimeException(
                                $result['message']
                                    ?? 'Order sync failed.'
                            );
                        }

                        $summary = $result['summary'] ?? [];

                        $stats['stores_synced']++;

                        $stats['orders_fetched'] +=
                            (int) ($summary['totalFetched'] ?? 0);

                        $stats['orders_inserted'] +=
                            (int) ($summary['insertedOrders'] ?? 0);

                        $stats['orders_updated'] +=
                            (int) ($summary['updatedOrders'] ?? 0);

                        $this->info(
                            "Store #{$store->id}: synced successfully."
                        );
                    } catch (\Throwable $exception) {
                        $stats['stores_failed']++;

                        Log::error(
                            'WooCommerce store automatic sync failed',
                            [
                                'store_id' => $store->id,
                                'error' => $exception->getMessage(),
                            ]
                        );

                        $this->error(
                            "Store #{$store->id}: sync failed."
                        );
                    }
                }
            });

        Log::info(
            'Automatic WooCommerce orders sync completed',
            $stats
        );

        $this->table(
            [
                'Checked',
                'Synced',
                'Failed',
                'Fetched',
                'Inserted',
                'Updated',
            ],
            [[
                $stats['stores_checked'],
                $stats['stores_synced'],
                $stats['stores_failed'],
                $stats['orders_fetched'],
                $stats['orders_inserted'],
                $stats['orders_updated'],
            ]]
        );

        return $stats['stores_failed'] > 0 ? 1 : 0;
    })->purpose(
        'Automatically sync WooCommerce orders for connected stores'
    );

    /*
    |--------------------------------------------------------------------------
    | Automatic Gmail Scheduler
    |--------------------------------------------------------------------------
    */

    Schedule::command('gmail:sync-replies')
        ->name('aftersale-gmail-sync-replies')
        ->everyMinute()
        ->withoutOverlapping(5);

    /*
    |--------------------------------------------------------------------------
    | Automatic WooCommerce Scheduler
    |--------------------------------------------------------------------------
    |
    | Runs once every hour.
    | Prevents overlapping sync executions.
    |
    */

    Schedule::command('woocommerce:sync-orders')
        ->name('aftersale-woocommerce-sync-orders')
        ->hourly()
        ->withoutOverlapping(60);
}
