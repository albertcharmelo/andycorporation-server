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
        Schema::table('messages', function (Blueprint $table) {
            $table->enum('message_type', ['text', 'image', 'file'])->default('text')->after('message');
            $table->string('file_path')->nullable()->after('message_type');
            $table->boolean('is_delivery_message')->default(false)->after('file_path');
            
            // Índice para filtros rápidos
            $table->index('is_delivery_message');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex(['is_delivery_message']);
            $table->dropColumn(['message_type', 'file_path', 'is_delivery_message']);
        });
    }
};
