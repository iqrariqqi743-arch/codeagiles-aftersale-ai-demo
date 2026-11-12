<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();

            // Plan details
            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->text('description')->nullable();

            // Monthly subscription price (USD)
            $table->decimal('price', 10, 2)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->string('billing_interval', 20)->default('month');

            // Stripe integration
            $table->string('stripe_price_id')->nullable()->unique();

            // Plan limits (-1 means unlimited)
            $table->integer('max_stores')->default(1);
            $table->integer('max_orders_per_month')->default(100);
            $table->integer('max_tickets_per_month')->default(50);

            // Feature access
            $table->string('ai_access', 20)->default('none');
            $table->string('analytics_access', 20)->default('basic');
            $table->boolean('returns_enabled')->default(true);

            // Plan visibility
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
