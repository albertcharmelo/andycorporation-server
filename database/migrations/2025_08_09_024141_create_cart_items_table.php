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
        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Relaciona con la tabla users
            $table->foreignId('product_id')->constrained()->onDelete('cascade'); // Relaciona con la tabla products
            $table->integer('quantity')->default(1);
            $table->decimal('price_at_purchase', 10, 2); // Para guardar el precio del producto en el momento de añadirlo
            $table->timestamps();

            // Asegura que un usuario no pueda tener el mismo producto dos veces en el carrito
            $table->unique(['user_id', 'product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cart_items');
    }
};