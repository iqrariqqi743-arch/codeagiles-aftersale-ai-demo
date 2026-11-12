<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_usage_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('ticket_id')
                ->nullable()
                ->constrained('support_tickets')
                ->nullOnDelete();

            $table->string('feature', 50);

            $table->string('status', 20)
                ->default('reserved');

            $table->timestamps();

            $table->index(
                ['user_id', 'created_at'],
                'ai_usage_owner_month_index'
            );

            $table->index(
                ['user_id', 'feature'],
                'ai_usage_owner_feature_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_logs');
    }
};
