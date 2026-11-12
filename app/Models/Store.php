<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Store extends Model
{
    /**
     * Mass assignable attributes.
     */
    protected $fillable = [
        'user_id',
        'store_name',
        'store_url',
        'platform',
        'status',
        'consumer_key',
        'consumer_secret',
        'connected_at',
        'last_synced_at',
    ];

    /**
     * Attribute casting.
     */
    protected function casts(): array
    {
        return [
            'consumer_key' => 'encrypted',
            'consumer_secret' => 'encrypted',
            'connected_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    /**
     * Hide sensitive WooCommerce credentials
     * from JSON/API responses.
     */
    protected $hidden = [
        'consumer_key',
        'consumer_secret',
    ];

    /**
     * Each store belongs to a user.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
