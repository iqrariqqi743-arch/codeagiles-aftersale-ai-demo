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
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();

            // Store owner
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // Store associated with the ticket
            $table->foreignId('store_id')
                ->constrained('stores')
                ->cascadeOnDelete();

            // Optional order
            $table->foreignId('order_id')
                ->nullable()
                ->constrained('orders')
                ->nullOnDelete();

            // Unique ticket reference
            $table->string('ticket_number', 50)->unique();

            // Customer information
            $table->string('customer_name', 150);
            $table->string('customer_email')->nullable();

            // Ticket information
            $table->string('subject', 255);
            $table->text('description');

            // Categories from original Next.js page
            $table->enum('category', [
                'general',
                'delivery',
                'damaged_product',
                'wrong_product',
                'return',
                'refund',
                'other',
            ])->default('general');

            // Priority
            $table->enum('priority', [
                'low',
                'medium',
                'high',
                'urgent',
            ])->default('medium');

            // Ticket workflow
            $table->enum('status', [
                'open',
                'in_progress',
                'waiting_customer',
                'resolved',
                'closed',
            ])->default('open');

            // Demo ticket identification
            $table->boolean('is_demo')->default(false);

            // Resolution details
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();

            $table->timestamps();

            // Indexes for ticket filtering
            $table->index(['user_id', 'status']);
            $table->index(['store_id', 'status']);
            $table->index(['user_id', 'priority']);
            $table->index(['user_id', 'category']);
            $table->index('order_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('support_tickets');
    }
};
