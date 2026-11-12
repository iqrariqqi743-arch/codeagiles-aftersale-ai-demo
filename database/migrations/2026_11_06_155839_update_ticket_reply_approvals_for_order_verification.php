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
        Schema::table('ticket_reply_approvals', function (Blueprint $table) {

            // Type of approved reply
            $table->enum('approval_type', [
                'general_support',
                'verification_request',
                'order_specific',
            ])->default('general_support');

            // Order linked when reply was approved
            $table->foreignId('approved_order_id')
                ->nullable()
                ->constrained('orders')
                ->nullOnDelete();

            // Customer/order verification state at approval time
            $table->enum('verification_status', [
                'no_linked_order',
                'customer_mismatch',
                'no_products',
                'verified',
            ])->default('no_linked_order');

            // Approval timestamp
            $table->timestamp('approved_at')->nullable();

            // Successful SMTP acceptance timestamp
            $table->timestamp('sent_at')->nullable();

            // Email delivery tracking
            $table->enum('email_status', [
                'pending',
                'sent',
                'failed',
                'skipped',
                'unknown',
            ])->default('pending');

            // Index for verification cooldown checks
            $table->index(
                ['ticket_id', 'user_id', 'approval_type', 'sent_at'],
                'ticket_approval_cooldown_idx'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ticket_reply_approvals', function (Blueprint $table) {

            $table->dropIndex('ticket_approval_cooldown_idx');

            $table->dropForeign([
                'approved_order_id',
            ]);

            $table->dropColumn([
                'approval_type',
                'approved_order_id',
                'verification_status',
                'approved_at',
                'sent_at',
                'email_status',
            ]);
        });
    }
};
