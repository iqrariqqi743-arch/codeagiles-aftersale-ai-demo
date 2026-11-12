<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_incoming_emails', function (Blueprint $table) {
            $table->id();

            $table->foreignId('ticket_id')
                ->constrained('support_tickets')
                ->cascadeOnDelete();

            $table->string('message_id', 255)->unique();

            $table->string('sender_email');

            $table->timestamp('received_at')->nullable();

            $table->timestamps();

            $table->index(['ticket_id', 'received_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_incoming_emails');
    }
};
