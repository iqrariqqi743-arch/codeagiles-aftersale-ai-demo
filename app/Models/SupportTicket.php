<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportTicket extends Model
{
    protected $table = 'support_tickets';

    protected $fillable = [
        'user_id',
        'store_id',
        'order_id',
        'ticket_number',
        'customer_name',
        'customer_email',
        'subject',
        'description',
        'category',
        'priority',
        'status',
        'is_demo',
        'resolved_at',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'store_id' => 'integer',
            'order_id' => 'integer',
            'is_demo' => 'boolean',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * Ticket belongs to a store owner.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Ticket belongs to a store.
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Ticket may belong to an order.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Ticket conversation messages.
     *
     * TicketMessage model will be created
     * in the next steps.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(TicketMessage::class, 'ticket_id');
    }

    /**
     * Returns linked with this ticket.
     */
    public function returns(): HasMany
    {
        return $this->hasMany(ReturnRequest::class, 'ticket_id');
    }
}
