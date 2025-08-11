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
        Schema::table('user_addresses', function (Blueprint $table) {
            // Agrega la nueva columna 'referencia'
            $table->string('referencia', 255)->after('address_line_2')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_addresses', function (Blueprint $table) {
            // Elimina la columna 'referencia' en caso de revertir la migración
            $table->dropColumn('referencia');
        });
    }
};
