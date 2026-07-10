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
        Schema::create('experiencias_profesionales', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('postulante_id');
            $table->unsignedBigInteger('source_merito_id')->unique()->nullable();
            
            $table->string('cargo_raw')->nullable();
            $table->unsignedBigInteger('cargo_normalizado_id')->nullable();
            $table->string('empresa')->nullable();
            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_fin')->nullable();
            $table->integer('duracion_meses')->nullable();
            
            $table->timestamps();

            $table->foreign('postulante_id')
                  ->references('id')
                  ->on('postulantes')
                  ->onDelete('cascade');

            $table->foreign('source_merito_id')
                  ->references('id')
                  ->on('postulante_meritos')
                  ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('experiencias_profesionales');
    }
};
