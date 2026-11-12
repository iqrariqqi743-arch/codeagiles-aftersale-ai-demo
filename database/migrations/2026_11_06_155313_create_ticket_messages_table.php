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
        Schema::create('ticket_messages', function (Blueprint $table) {
            $table->id();

            // Parent support ticket
            $table->foreignId('ticket_id')
                ->constrained('support_tickets')
                ->cascadeOnDelete();

            // Sender: customer, owner or AI
            $table->enum('sender_type', [
                'customer',
                'owner',
                'ai',
            ]);

            // Optional authenticated sender
            $table->foreignId('sender_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Message content
            $table->text('message');

            // Optional message metadata
            $table->boolean('is_ai_generated')->default(false);

            // Email notification tracking
            $table->enum('email_status', [
                'not_applicable',
                'pending',
                'sent',
                'failed',
                'skipped',
            ])->default('not_applicable');

            $table->timestamp('email_sent_at')->nullable();

            $table->timestamps();

            // Conversation queries
            $table->index(['ticket_id', 'created_at']);
            $table->index(['ticket_id', 'sender_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_messages');
    }
};
