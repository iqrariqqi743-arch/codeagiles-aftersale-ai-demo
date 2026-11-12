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
        Schema::create('return_requests', function (Blueprint $table) {
            $table->id();

            // Store owner
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // Connected or demo store
            $table->foreignId('store_id')
                ->constrained('stores')
                ->cascadeOnDelete();

            // Order associated with the return
            $table->foreignId('order_id')
                ->constrained('orders')
                ->cascadeOnDelete();

            // Optional support ticket ID.
            // Foreign key will be added when tickets module exists.
            $table->unsignedBigInteger('ticket_id')->nullable();

            // Unique return reference
            $table->string('return_number', 50)->unique();

            // Customer information
            $table->string('customer_name', 150);
            $table->string('customer_email')->nullable();

            // Return / exchange / refund
            $table->enum('request_type', [
                'return',
                'exchange',
                'refund',
            ])->default('return');

            // Return reason
            $table->string('reason', 100);
            $table->text('description')->nullable();

            // Return workflow
            $table->enum('status', [
                'pending',
                'approved',
                'rejected',
                'received',
                'completed',
                'cancelled',
            ])->default('pending');

            // Refund tracking
            $table->decimal('refund_amount', 12, 2)->default(0);
            $table->string('currency', 10)->default('USD');

            $table->enum('refund_status', [
                'not_started',
                'pending',
                'processing',
                'completed',
                'failed',
            ])->default('not_started');

            // Internal notes
            $table->text('admin_notes')->nullable();

            // Demo or real request
            $table->boolean('is_demo')->default(false);

            $table->timestamps();

            // Indexes for faster filtering
            $table->index(['user_id', 'status']);
            $table->index(['store_id', 'request_type']);
            $table->index('order_id');
            $table->index('ticket_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('return_requests');
    }
};
