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
        Schema::create('formaciones_postgrado', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('postulante_id');
            $table->unsignedBigInteger('source_merito_id')->unique()->nullable();
            
            $table->string('tipo_posgrado_raw')->nullable();
            $table->string('tipo_posgrado_normalizado')->nullable();
            $table->string('nombre_programa')->nullable();
            $table->date('fecha_certificacion')->nullable();
            $table->string('institucion')->nullable();
            
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
        Schema::dropIfExists('formaciones_postgrado');
    }
};
