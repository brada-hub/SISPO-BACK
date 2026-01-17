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
        Schema::table('sedes', function (Blueprint $table) {
            $table->string('sigla')->nullable()->after('nombre');
        });

        Schema::table('cargos', function (Blueprint $table) {
            $table->string('sigla')->nullable()->after('nombre');
        });

        Schema::table('convocatorias', function (Blueprint $table) {
            $table->string('codigo_interno')->unique()->nullable()->after('titulo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sedes', function (Blueprint $table) {
            $table->dropColumn('sigla');
        });

        Schema::table('cargos', function (Blueprint $table) {
            $table->dropColumn('sigla');
        });

        Schema::table('convocatorias', function (Blueprint $table) {
            $table->dropColumn('codigo_interno');
        });
    }
};
