<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // MySQL/MariaDB no permite modificar ENUMs directamente, necesitamos usar ALTER TABLE
        DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM(
            'pending_payment',
            'paid',
            'received',
            'invoiced',
            'in_agency',
            'on_the_way',
            'shipped',
            'delivered',
            'completed',
            'cancelled',
            'refunded'
        ) DEFAULT 'pending_payment'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revertir al enum original
        DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM(
            'pending_payment',
            'paid',
            'shipped',
            'completed',
            'cancelled',
            'refunded'
        ) DEFAULT 'pending_payment'");
    }
};
