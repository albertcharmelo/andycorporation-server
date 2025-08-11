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
        Schema::create('orders', function (Blueprint $table) {
            // $table->uuid('id')->primary(); // UUID como ID primario
            $table->id();            
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('address_id')->nullable()->constrained('user_addresses')->onDelete('set null'); // Dirección de envío
            $table->decimal('subtotal', 10, 2);
            $table->decimal('shipping_cost', 10, 2)->default(0.00);       // Costo de envío
            $table->decimal('total', 10, 2);            
            $table->enum('payment_method', ['manual_transfer', 'mobile_payment', 'credit_card', 'paypal', 'binance'])->default('manual_transfer');
            $table->string('payment_reference')->unique()->nullable();    // Referencia única para la transferencia
            $table->enum('status', ['pending_payment', 'paid', 'shipped', 'completed', 'cancelled', 'refunded'])->default('pending_payment');
            $table->text('notes')->nullable();                            // Notas del cliente o internas
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
