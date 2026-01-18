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
        Schema::create('push_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('expo_push_token')->unique();
            $table->string('device_id')->nullable();
            $table->enum('platform', ['ios', 'android'])->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Índice para búsquedas rápidas por usuario
            $table->index('user_id');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('push_tokens');
    }
};
