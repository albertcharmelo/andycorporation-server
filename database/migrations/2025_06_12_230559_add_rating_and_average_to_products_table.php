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
        Schema::table('products', function (Blueprint $table) {
            $table->integer('rating_count')->nullable()->after('stock_status')->comment('Product rating');
            $table->string('average_rating')->nullable()->after('rating_count')->comment('Average rating of the product');
            $table->integer('total_sales')->nullable()->after('average_rating')->comment('Product rating');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['rating_count', 'average_rating', 'total_sales']);
        });
    }
};
