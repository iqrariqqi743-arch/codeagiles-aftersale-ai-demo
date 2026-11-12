<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketMessage extends Model
{
    protected $table = 'ticket_messages';

    protected $fillable = [
        'ticket_id',
        'sender_type',
        'sender_id',
        'message',
        'is_ai_generated',
        'email_status',
        'email_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'ticket_id' => 'integer',
            'sender_id' => 'integer',
            'is_ai_generated' => 'boolean',
            'email_sent_at' => 'datetime',
        ];
    }

    /**
     * Message belongs to a support ticket.
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(
            SupportTicket::class,
            'ticket_id'
        );
    }

    /**
     * Optional authenticated sender.
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'sender_id'
        );
    }

    /**
     * Customer messages.
     */
    public function scopeCustomer($query)
    {
        return $query->where('sender_type', 'customer');
    }

    /**
     * Store owner messages.
     */
    public function scopeOwner($query)
    {
        return $query->where('sender_type', 'owner');
    }

    /**
     * Messages in chronological order.
     */
    public function scopeConversation($query)
    {
        return $query
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc');
    }
}
