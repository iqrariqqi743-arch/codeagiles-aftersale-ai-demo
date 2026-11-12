<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'user_id',
        'store_id',
        'woocommerce_order_id',
        'order_number',
        'customer_name',
        'customer_email',
        'customer_phone',
        'status',
        'total_amount',
        'currency',
        'payment_method',
        'shipping_address',
        'is_demo',
        'order_date',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'woocommerce_order_id' => 'integer',
            'total_amount' => 'decimal:2',
            'is_demo' => 'boolean',
            'order_date' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    /**
     * Order belongs to a user.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Order belongs to a store.
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Order has multiple products.
     *
     * We'll create OrderItem model in the next steps.
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
