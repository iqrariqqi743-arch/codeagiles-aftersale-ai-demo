<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgeBase extends Model
{
    use HasFactory;

    protected $table = 'knowledge_bases';

    protected $fillable = [
        'store_id',
        'title',
        'content',
        'category',
        'status',
    ];

    protected $attributes = [
        'category' => 'general',
        'status' => 'active',
    ];

    protected function casts(): array
    {
        return [
            'store_id' => 'integer',
        ];
    }

    // Each Knowledge Base article belongs to a store.
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'store_id');
    }
}
