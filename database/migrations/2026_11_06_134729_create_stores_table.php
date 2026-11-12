<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stores', function (Blueprint $table) {
            $table->id();

            // Store owner
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // Store details
            $table->string('store_name', 150);
            $table->string('store_url', 2048);
            $table->string('platform', 50)->default('woocommerce');

            // Connection status
            $table->string('status', 30)->default('disconnected');

            // WooCommerce API credentials
            // These must be encrypted before saving.
            $table->text('consumer_key')->nullable();
            $table->text('consumer_secret')->nullable();

            // Connection information
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();

            $table->timestamps();

            // Query performance
            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'platform']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stores');
    }
};
