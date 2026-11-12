<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Starter',
                'slug' => 'starter',
                'description' => 'Perfect for small stores getting started.',
                'price' => 29.00,
                'currency' => 'USD',
                'billing_interval' => 'month',
                'max_stores' => 1,
                'max_orders_per_month' => 100,
                'max_tickets_per_month' => 50,
                'ai_access' => 'none',
                'analytics_access' => 'basic',
                'returns_enabled' => true,
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Growth',
                'slug' => 'growth',
                'description' => 'For growing businesses managing multiple stores.',
                'price' => 49.00,
                'currency' => 'USD',
                'billing_interval' => 'month',
                'max_stores' => 3,
                'max_orders_per_month' => 1000,
                'max_tickets_per_month' => 500,
                'ai_access' => 'basic',
                'analytics_access' => 'advanced',
                'returns_enabled' => true,
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Pro',
                'slug' => 'pro',
                'description' => 'Full features for established businesses.',
                'price' => 119.00,
                'currency' => 'USD',
                'billing_interval' => 'month',
                'max_stores' => 10,
                'max_orders_per_month' => -1,
                'max_tickets_per_month' => -1,
                'ai_access' => 'full',
                'analytics_access' => 'advanced',
                'returns_enabled' => true,
                'is_active' => true,
                'sort_order' => 3,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(
                ['slug' => $plan['slug']],
                $plan
            );
        }

        $this->command->info(
            'Starter, Growth and Pro plans seeded successfully.'
        );
    }
}
