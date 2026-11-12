<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    protected $fillable = [
        'user_id',
        'plan',
        'stripe_customer_id',
        'stripe_subscription_id',
        'stripe_price_id',
        'status',
        'current_period_start',
        'current_period_end',
        'cancel_at_period_end',
        'canceled_at',
        'ended_at',
    ];

    protected $casts = [
        'current_period_start' => 'datetime',
        'current_period_end' => 'datetime',
        'cancel_at_period_end' => 'boolean',
        'canceled_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    // Subscription belongs to a merchant / owner
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Check active or trial subscription
    public function isActive(): bool
    {
        return in_array($this->status, ['active', 'trialing'], true)
            && (
                $this->current_period_end === null ||
                $this->current_period_end->isFuture()
            );
    }

    // Check subscription cancellation
    public function isCanceled(): bool
    {
        return in_array($this->status, ['canceled', 'cancelled'], true);
    }
}
