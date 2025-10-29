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
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('delivery_id')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable()->after('delivery_id');
            $table->timestamp('delivered_at')->nullable()->after('assigned_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['delivery_id']);
            $table->dropColumn(['delivery_id', 'assigned_at', 'delivered_at']);
        });
    }
};
