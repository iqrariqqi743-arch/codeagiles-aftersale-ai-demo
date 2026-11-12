<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Plan extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'price',
        'currency',
        'billing_interval',
        'stripe_price_id',
        'max_stores',
        'max_orders_per_month',
        'max_tickets_per_month',
        'ai_access',
        'analytics_access',
        'returns_enabled',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'max_stores' => 'integer',
        'max_orders_per_month' => 'integer',
        'max_tickets_per_month' => 'integer',
        'returns_enabled' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Get only active plans.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Check if a feature is unlimited.
     */
    public function isUnlimited(string $field): bool
    {
        return in_array($field, [
            'max_stores',
            'max_orders_per_month',
            'max_tickets_per_month',
        ], true) && (int) $this->{$field} === -1;
    }

    /**
     * Check if plan allows adding more stores.
     */
    public function canAddStore(int $currentStores): bool
    {
        return $this->isUnlimited('max_stores')
            || $currentStores < $this->max_stores;
    }

    /**
     * Check if plan allows adding more orders.
     */
    public function canAddOrder(int $currentOrders): bool
    {
        return $this->isUnlimited('max_orders_per_month')
            || $currentOrders < $this->max_orders_per_month;
    }

    /**
     * Check if plan allows adding more tickets.
     */
    public function canAddTicket(int $currentTickets): bool
    {
        return $this->isUnlimited('max_tickets_per_month')
            || $currentTickets < $this->max_tickets_per_month;
    }

    /**
     * Check AI access.
     */
    public function hasAiAccess(): bool
    {
        return in_array($this->ai_access, [
            'basic',
            'full',
        ], true);
    }

    /**
     * Check advanced analytics access.
     */
    public function hasAdvancedAnalytics(): bool
    {
        return $this->analytics_access === 'advanced';
    }
}
