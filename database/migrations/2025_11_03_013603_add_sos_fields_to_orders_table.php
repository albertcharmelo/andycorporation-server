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
            $table->boolean('sos_status')->default(false)->after('delivered_at');
            $table->text('sos_comment')->nullable()->after('sos_status');
            $table->timestamp('sos_reported_at')->nullable()->after('sos_comment');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['sos_status', 'sos_comment', 'sos_reported_at']);
        });
    }
};
