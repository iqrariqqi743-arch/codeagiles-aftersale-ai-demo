<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('store_id')
                ->constrained('stores')
                ->cascadeOnDelete();

            $table->unsignedBigInteger('woocommerce_order_id')->nullable();

            $table->string('order_number', 100);

            $table->string('customer_name', 200)->nullable();
            $table->string('customer_email', 255)->nullable();
            $table->string('customer_phone', 50)->nullable();

            $table->string('status', 50)->default('pending');

            $table->decimal('total_amount', 12, 2)->default(0);
            $table->string('currency', 10)->default('USD');

            $table->string('payment_method', 100)->nullable();

            $table->text('shipping_address')->nullable();

            $table->boolean('is_demo')->default(false);

            $table->timestamp('order_date')->nullable();
            $table->timestamp('last_synced_at')->nullable();

            $table->timestamps();

            $table->unique(
                ['store_id', 'woocommerce_order_id'],
                'orders_store_woocommerce_unique'
            );

            $table->index(['user_id', 'status']);
            $table->index(['store_id', 'is_demo']);
            $table->index('order_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
