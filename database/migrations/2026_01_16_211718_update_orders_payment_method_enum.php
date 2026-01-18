<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Modificar el ENUM para incluir todos los métodos de pago
        DB::statement("ALTER TABLE `orders` MODIFY COLUMN `payment_method` ENUM(
            'manual_transfer',
            'mobile_payment',
            'pago_movil',
            'zelle',
            'paypal',
            'binance',
            'bank_transfer',
            'agree_with_seller',
            'credit_card'
        ) DEFAULT 'manual_transfer'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revertir a los valores originales
        DB::statement("ALTER TABLE `orders` MODIFY COLUMN `payment_method` ENUM(
            'manual_transfer',
            'mobile_payment',
            'credit_card',
            'paypal',
            'binance'
        ) DEFAULT 'manual_transfer'");
    }
};
