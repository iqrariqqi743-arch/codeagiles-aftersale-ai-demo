<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketReplyApproval extends Model
{
    protected $table = 'ticket_reply_approvals';

    public const TYPE_GENERAL_SUPPORT = 'general_support';
    public const TYPE_VERIFICATION_REQUEST = 'verification_request';
    public const TYPE_ORDER_SPECIFIC = 'order_specific';

    public const STATUS_APPROVED = 'approved';
    public const STATUS_USED = 'used';
    public const STATUS_REVOKED = 'revoked';

    protected $fillable = [
        'ticket_id',
        'user_id',
        'message',
        'message_hash',
        'approval_type',
        'approved_order_id',
        'verification_status',
        'status',
        'expires_at',
        'approved_at',
        'used_at',
        'sent_at',
        'email_status',
        'ticket_message_id',
    ];

    protected function casts(): array
    {
        return [
            'ticket_id' => 'integer',
            'user_id' => 'integer',
            'approved_order_id' => 'integer',
            'ticket_message_id' => 'integer',
            'expires_at' => 'datetime',
            'approved_at' => 'datetime',
            'used_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    /**
     * Parent support ticket.
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(
            SupportTicket::class,
            'ticket_id'
        );
    }

    /**
     * Store owner who approved the reply.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'user_id'
        );
    }

    /**
     * Order linked at approval time.
     */
    public function approvedOrder(): BelongsTo
    {
        return $this->belongsTo(
            Order::class,
            'approved_order_id'
        );
    }

    /**
     * Message created after approval was consumed.
     */
    public function ticketMessage(): BelongsTo
    {
        return $this->belongsTo(
            TicketMessage::class,
            'ticket_message_id'
        );
    }

    /**
     * Only valid, unused and unexpired approvals.
     */
    public function scopeValid(Builder $query): Builder
    {
        return $query
            ->where('status', self::STATUS_APPROVED)
            ->where('expires_at', '>', now())
            ->whereNull('used_at')
            ->whereNull('ticket_message_id');
    }

    /**
     * Check whether an approval can still be used.
     */
    public function isValid(): bool
    {
        return $this->status === self::STATUS_APPROVED
            && $this->expires_at !== null
            && $this->expires_at->isFuture()
            && $this->used_at === null
            && $this->ticket_message_id === null;
    }

    /**
     * Verify the exact approved reply content.
     */
    public function matchesMessage(string $message): bool
    {
        $hash = hash('sha256', $message);

        return hash_equals(
            $this->message_hash,
            $hash
        );
    }

    /**
     * Check whether the approval is for a verified order.
     */
    public function isOrderSpecific(): bool
    {
        return $this->approval_type === self::TYPE_ORDER_SPECIFIC;
    }

    /**
     * Check whether this is a verification request.
     */
    public function isVerificationRequest(): bool
    {
        return $this->approval_type === self::TYPE_VERIFICATION_REQUEST;
    }

    /**
     * Check whether this is a general support reply.
     */
    public function isGeneralSupport(): bool
    {
        return $this->approval_type === self::TYPE_GENERAL_SUPPORT;
    }

    /**
     * Check if a verification email was accepted
     * by SMTP within the last 24 hours.
     */
    public function scopeRecentVerificationEmail(
        Builder $query,
        int $ticketId,
        int $userId
    ): Builder {
        return $query
            ->where('ticket_id', $ticketId)
            ->where('user_id', $userId)
            ->where(
                'approval_type',
                self::TYPE_VERIFICATION_REQUEST
            )
            ->where('email_status', 'sent')
            ->whereNotNull('sent_at')
            ->where('sent_at', '>', now()->subHours(24));
    }

    /**
     * Mark approval as consumed.
     *
     * Must be called inside a database transaction
     * while the approval row is locked.
     */
    public function markAsUsed(TicketMessage $message): void
    {
        $this->forceFill([
            'status' => self::STATUS_USED,
            'used_at' => now(),
            'ticket_message_id' => $message->id,
        ])->save();
    }

    /**
     * Record the SMTP delivery outcome.
     *
     * 'sent' means accepted by SMTP,
     * not guaranteed inbox delivery.
     */
    public function recordEmailStatus(string $status): void
    {
        if (!in_array($status, [
            'pending',
            'sent',
            'failed',
            'skipped',
            'unknown',
        ], true)) {
            throw new \InvalidArgumentException(
                'Invalid email notification status.'
            );
        }

        $this->forceFill([
            'email_status' => $status,
            'sent_at' => $status === 'sent'
                ? now()
                : null,
        ])->save();
    }
}
