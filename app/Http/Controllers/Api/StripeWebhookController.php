<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\StripeClient;
use Stripe\Webhook;

class StripeWebhookController extends Controller
{
    public function handle(Request $request): Response
    {
        $secret = config('services.stripe.webhook_secret');
        $signature = $request->header('Stripe-Signature');

        if (!$secret || !$signature) {
            return response('Webhook configuration missing', 400);
        }

        try {
            $event = Webhook::constructEvent(
                $request->getContent(),
                $signature,
                $secret
            );
        } catch (\Throwable $e) {
            Log::warning('Invalid Stripe webhook', [
                'error' => $e->getMessage(),
            ]);

            return response('Invalid webhook signature', 400);
        }

        try {
            switch ($event->type) {
                case 'checkout.session.completed':
                    $this->handleCheckout($event->data->object);
                    break;

                case 'customer.subscription.created':
                case 'customer.subscription.updated':
                case 'customer.subscription.deleted':
                    $this->syncSubscription(
                        $event->data->object
                    );
                    break;

                case 'invoice.paid':
                case 'invoice.payment_failed':
                    $subscriptionId = $this->invoiceSubscriptionId(
                        $event->data->object
                    );

                    if ($subscriptionId) {
                        $this->syncById($subscriptionId);
                    }
                    break;
            }

            return response('Webhook received', 200);

        } catch (\Throwable $e) {
            Log::error('Stripe webhook processing failed', [
                'event_id' => $event->id,
                'event_type' => $event->type,
                'error' => $e->getMessage(),
            ]);

            return response('Webhook processing failed', 500);
        }
    }

    private function handleCheckout(object $session): void
    {
        if (($session->mode ?? null) !== 'subscription') {
            return;
        }

        $subscriptionId = $session->subscription ?? null;

        if (!$subscriptionId) {
            return;
        }

        $userId = $session->client_reference_id
            ?? ($session->metadata->user_id ?? null);

        $plan = $session->metadata->plan ?? null;

        $this->syncById(
            $subscriptionId,
            $userId,
            $plan
        );
    }

    private function syncById(
        string $subscriptionId,
        ?string $userId = null,
        ?string $plan = null
    ): void {
        $stripe = new StripeClient(
            config('services.stripe.secret')
        );

        $stripeSubscription = $stripe->subscriptions->retrieve(
            $subscriptionId,
            []
        );

        $this->syncSubscription(
            $stripeSubscription,
            $userId,
            $plan
        );
    }

    private function syncSubscription(
        object $stripeSubscription,
        ?string $userId = null,
        ?string $plan = null
    ): void {
        $stripeId = $stripeSubscription->id;

        $existing = Subscription::query()
            ->where('stripe_subscription_id', $stripeId)
            ->first();

        $userId = $existing?->user_id
            ?? $userId
            ?? ($stripeSubscription->metadata->user_id ?? null);

        if (!$userId) {
            throw new \RuntimeException(
                'Unable to identify subscription owner: ' . $stripeId
            );
        }

        $item = $stripeSubscription->items->data[0] ?? null;
        $priceId = $item?->price?->id;

        $plan = $this->planFromPrice($priceId)
            ?? $plan
            ?? ($stripeSubscription->metadata->plan ?? null)
            ?? $existing?->plan;

        if (!$plan) {
            throw new \RuntimeException(
                'Unable to identify subscription plan: ' . $stripeId
            );
        }

        $values = [
            'user_id' => (int) $userId,
            'plan' => $plan,
            'stripe_customer_id' => $stripeSubscription->customer,
            'stripe_subscription_id' => $stripeId,
            'stripe_price_id' => $priceId,
            'status' => $stripeSubscription->status,

            'current_period_start' => $this->dateFromTimestamp(
                $item?->current_period_start
                    ?? $stripeSubscription->current_period_start
                    ?? null
            ),

            'current_period_end' => $this->dateFromTimestamp(
                $item?->current_period_end
                    ?? $stripeSubscription->current_period_end
                    ?? null
            ),

            'cancel_at_period_end' =>
                (bool) ($stripeSubscription->cancel_at_period_end ?? false),

            'canceled_at' => $this->dateFromTimestamp(
                $stripeSubscription->canceled_at ?? null
            ),

            'ended_at' => $this->dateFromTimestamp(
                $stripeSubscription->ended_at ?? null
            ),
        ];

        DB::transaction(function () use (
            $stripeId,
            $userId,
            $values
        ) {
            // Serialize subscription changes for this owner.
            $user = DB::table('users')
                ->where('id', $userId)
                ->lockForUpdate()
                ->first();

            if (!$user) {
                throw new \RuntimeException(
                    'Subscription owner not found: ' . $userId
                );
            }

            // Lock all existing subscriptions for this user.
            $subscriptions = Subscription::query()
                ->where('user_id', $userId)
                ->orderByDesc('id')
                ->lockForUpdate()
                ->get();

            // Reuse the existing Stripe subscription record.
            $record = $subscriptions->first(
                fn ($subscription) =>
                    $subscription->stripe_subscription_id === $stripeId
            );

            if (!$record) {
                // Convert the local 10-day free trial into paid.
                $record = $subscriptions->first(
                    fn ($subscription) =>
                        $subscription->status === 'trialing'
                        && empty($subscription->stripe_subscription_id)
                );
            }

            if (!$record) {
                // No local trial exists: create a Stripe record.
                $record = new Subscription();
            }

            $record->fill($values);
            $record->save();

            // Close any leftover local free trial records.
            // This also handles webhook events arriving out of order.
            if (!empty($record->stripe_subscription_id)) {
                Subscription::query()
                    ->where('user_id', $userId)
                    ->where('id', '!=', $record->id)
                    ->whereNull('stripe_subscription_id')
                    ->where('status', 'trialing')
                    ->update([
                        'status' => 'canceled',
                        'current_period_end' => now(),
                        'ended_at' => now(),
                    ]);
            }
        });

        Log::info('Stripe subscription synchronized', [
            'stripe_subscription_id' => $stripeId,
            'user_id' => $userId,
            'plan' => $plan,
            'status' => $stripeSubscription->status,
        ]);
    }

    private function planFromPrice(?string $priceId): ?string
    {
        if (!$priceId) {
            return null;
        }

        foreach (['starter', 'growth', 'pro'] as $plan) {
            if (
                config("services.stripe.prices.{$plan}")
                === $priceId
            ) {
                return $plan;
            }
        }

        return null;
    }

    private function invoiceSubscriptionId(object $invoice): ?string
    {
        $id = $invoice->parent
            ->subscription_details
            ->subscription
            ?? null;

        $id = $id ?? ($invoice->subscription ?? null);

        return is_string($id) ? $id : null;
    }

    private function dateFromTimestamp(
        ?int $timestamp
    ): ?\DateTimeImmutable {
        if (!$timestamp) {
            return null;
        }

        return (new \DateTimeImmutable())
            ->setTimestamp($timestamp);
    }
}
