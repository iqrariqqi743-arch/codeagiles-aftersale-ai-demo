<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_ai_settings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('store_id')
                ->constrained('stores')
                ->cascadeOnDelete();

            $table->boolean('ai_enabled')->default(false);

            $table->string('reply_tone', 30)
                ->default('professional');

            $table->string('reply_language', 30)
                ->default('English');

            $table->unsignedSmallInteger('return_window_days')
                ->default(7);

            $table->text('return_policy')->nullable();

            $table->text('refund_policy')->nullable();

            $table->text('custom_instructions')->nullable();

            $table->boolean('auto_reply_enabled')
                ->default(false);

            $table->timestamps();

            // Each store has only one AI settings record.
            $table->unique('store_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_ai_settings');
    }
};
