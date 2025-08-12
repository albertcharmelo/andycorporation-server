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
        // Esta migración crea la tabla 'payment_proofs' para almacenar los comprobantes de pago.
        Schema::create('payment_proofs', function (Blueprint $table) {
            $table->id();
            // Clave foránea para vincular el comprobante con una orden específica.
            $table->foreignId('order_id')->constrained('orders')->onDelete('cascade');
            // La ruta donde se almacenará la imagen del comprobante.
            $table->string('file_path');
            // Un campo opcional para notas adicionales del usuario.
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_proofs');
    }
};
