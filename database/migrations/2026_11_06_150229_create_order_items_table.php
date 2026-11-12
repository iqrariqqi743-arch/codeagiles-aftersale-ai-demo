<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();

            // Parent order
            $table->foreignId('order_id')
                ->constrained('orders')
                ->cascadeOnDelete();

            // WooCommerce product references
            $table->unsignedBigInteger('woocommerce_item_id')->nullable();
            $table->unsignedBigInteger('woocommerce_product_id')->nullable();
            $table->unsignedBigInteger('woocommerce_variation_id')->nullable();

            // Product information
            $table->string('product_name', 255);
            $table->string('product_sku', 150)->nullable();
            $table->text('product_image')->nullable();

            // Price and quantity
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('total_price', 12, 2)->default(0);

            // Product attributes
            $table->string('color', 100)->nullable();
            $table->string('size', 100)->nullable();

            // Additional WooCommerce variations
            $table->json('variation')->nullable();

            $table->timestamps();

            // Prevent duplicate WooCommerce items per order
            $table->unique(
                ['order_id', 'woocommerce_item_id'],
                'order_items_woocommerce_unique'
            );

            $table->index('product_sku');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
