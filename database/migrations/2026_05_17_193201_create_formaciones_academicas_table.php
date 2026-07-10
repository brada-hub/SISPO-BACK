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
        Schema::create('formaciones_academicas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('postulante_id');
            $table->unsignedBigInteger('source_merito_id')->unique()->nullable();
            
            $table->string('nivel_academico_raw')->nullable();
            $table->string('nivel_academico_normalizado')->nullable();
            $table->string('universidad')->nullable();
            $table->string('carrera_raw')->nullable();
            $table->unsignedBigInteger('carrera_normalizada_id')->nullable();
            
            $table->date('fecha_diploma')->nullable();
            $table->date('fecha_titulo')->nullable();
            
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
        Schema::dropIfExists('formaciones_academicas');
    }
};
