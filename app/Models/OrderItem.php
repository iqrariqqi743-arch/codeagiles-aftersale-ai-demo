<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $table = 'order_items';

    protected $fillable = [
        'order_id',
        'woocommerce_item_id',
        'woocommerce_product_id',
        'woocommerce_variation_id',
        'product_name',
        'product_sku',
        'product_image',
        'quantity',
        'unit_price',
        'total_price',
        'color',
        'size',
        'variation',
    ];

    protected function casts(): array
    {
        return [
            'woocommerce_item_id' => 'integer',
            'woocommerce_product_id' => 'integer',
            'woocommerce_variation_id' => 'integer',
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'total_price' => 'decimal:2',
            'variation' => 'array',
        ];
    }

    /**
     * Each order item belongs to an order.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
