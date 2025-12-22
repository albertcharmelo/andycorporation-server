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
        Schema::create('points_config', function (Blueprint $table) {
            $table->id();
            $table->decimal('points_per_currency', 10, 2)->default(1.00); // Puntos por unidad de moneda (ej: 1 punto = $1)
            $table->enum('currency', ['BS', 'USD'])->default('USD'); // Moneda base para el cálculo
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Insertar configuración inicial
        DB::table('points_config')->insert([
            'points_per_currency' => 1.00,
            'currency' => 'USD',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('points_config');
    }
};
