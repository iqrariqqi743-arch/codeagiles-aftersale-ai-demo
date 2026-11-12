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
        Schema::create('ticket_reply_approvals', function (Blueprint $table) {
            $table->id();

            // Parent support ticket
            $table->foreignId('ticket_id')
                ->constrained('support_tickets')
                ->cascadeOnDelete();

            // Store owner who approved the reply
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // Exact approved reply content
            $table->text('message');

            // SHA-256 hash for exact reply matching
            $table->string('message_hash', 64);

            // Approval workflow
            $table->enum('status', [
                'approved',
                'used',
                'revoked',
            ])->default('approved');

            // Approval expiry
            $table->timestamp('expires_at');

            // When the approval was used
            $table->timestamp('used_at')->nullable();

            // Message created using this approval
            $table->foreignId('ticket_message_id')
                ->nullable()
                ->constrained('ticket_messages')
                ->nullOnDelete();

            $table->timestamps();

            // Lookup indexes
            $table->index([
                'ticket_id',
                'user_id',
                'status',
            ], 'ticket_approval_lookup_idx');

            $table->index('message_hash');
            $table->index('expires_at');

            // One approval cannot be linked to multiple messages.
            $table->unique('ticket_message_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_reply_approvals');
    }
};
