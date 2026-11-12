<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreAiSetting extends Model
{
    protected $table = 'store_ai_settings';

    protected $fillable = [
        'user_id',
        'store_id',
        'ai_enabled',
        'reply_tone',
        'reply_language',
        'return_window_days',
        'return_policy',
        'refund_policy',
        'custom_instructions',
        'auto_reply_enabled',
    ];

    protected $casts = [
        'ai_enabled' => 'boolean',
        'auto_reply_enabled' => 'boolean',
        'return_window_days' => 'integer',
    ];

    protected $attributes = [
        'ai_enabled' => false,
        'reply_tone' => 'professional',
        'reply_language' => 'English',
        'return_window_days' => 7,
        'auto_reply_enabled' => false,
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
