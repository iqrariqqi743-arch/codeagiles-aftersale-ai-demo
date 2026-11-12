<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReturnRequest extends Model
{
    protected $table = 'return_requests';

    protected $fillable = [
        'user_id',
        'store_id',
        'order_id',
        'ticket_id',
        'return_number',
        'customer_name',
        'customer_email',
        'request_type',
        'reason',
        'description',
        'status',
        'refund_amount',
        'currency',
        'refund_status',
        'admin_notes',
        'is_demo',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'store_id' => 'integer',
            'order_id' => 'integer',
            'ticket_id' => 'integer',
            'refund_amount' => 'decimal:2',
            'is_demo' => 'boolean',
        ];
    }

    /**
     * Return request belongs to a store owner.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Return request belongs to a store.
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Return request belongs to an order.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Return request belongs to a support ticket.
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(
            SupportTicket::class,
            'ticket_id'
        );
    }
}
