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
        Schema::table('users', function (Blueprint $table) {
            $table->string('google_id')->nullable();
            $table->string('avatar')->nullable();
            $table->string('password')->nullable()->change();
            $table->string('cedula_type')->nullable()->change();
            $table->string('cedula_ID')->nullable()->change();
            $table->string('tel')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['google_id', 'avatar']);
            $table->string('password')->nullable(false)->change();
            $table->string('cedula_type')->nullable(false)->change();
            $table->string('cedula_ID')->nullable(false)->change();
            $table->string('tel')->nullable(false)->change();
        });
    }
};
