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
            // Vendedor que realizó la venta (por ahora admin, luego será seller)
            $table->foreignId('seller_id')->nullable()->after('user_id')->constrained('users')->onDelete('set null');
            
            // Datos del cliente para ventas POS (cuando no es un usuario registrado)
            $table->string('customer_name')->nullable()->after('seller_id');
            $table->string('customer_lastname')->nullable()->after('customer_name');
            $table->string('customer_tel')->nullable()->after('customer_lastname');
            $table->enum('customer_cedula_type', ['v', 'j', 'e', 'g', 'r', 'p'])->nullable()->after('customer_tel');
            $table->string('customer_cedula_ID')->nullable()->after('customer_cedula_type');
            $table->text('customer_address')->nullable()->after('customer_cedula_ID');
            
            // Información de pago en moneda local
            $table->enum('currency', ['BS', 'USD'])->default('BS')->after('total');
            $table->decimal('amount_bs', 10, 2)->nullable()->after('currency');
            $table->decimal('amount_usd', 10, 2)->nullable()->after('amount_bs');
            
            // Puntos/score del cliente al momento de la compra
            $table->integer('customer_score')->default(0)->after('amount_usd');
            
            // Identificador de venta POS
            $table->boolean('is_pos_order')->default(false)->after('customer_score');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['seller_id']);
            $table->dropColumn([
                'seller_id',
                'customer_name',
                'customer_lastname',
                'customer_tel',
                'customer_cedula_type',
                'customer_cedula_ID',
                'customer_address',
                'currency',
                'amount_bs',
                'amount_usd',
                'customer_score',
                'is_pos_order',
            ]);
        });
    }
};
