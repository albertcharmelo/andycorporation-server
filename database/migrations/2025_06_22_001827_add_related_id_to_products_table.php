<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_related', function (Blueprint $table) {
            $table->id();

            // Usamos IDs de WooCommerce
            $table->unsignedBigInteger('product_woocommerce_id');
            $table->unsignedBigInteger('related_woocommerce_id');

            $table->timestamps();

            // No se puede usar foreign key directa si no hay clave primaria en 'woocommerce_id'
            // pero puedes indexarlos si quieres acelerar las búsquedas:
            $table->index(['product_woocommerce_id']);
            $table->index(['related_woocommerce_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_related');
    }
};
