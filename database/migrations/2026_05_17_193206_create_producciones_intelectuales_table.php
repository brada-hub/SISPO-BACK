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
        Schema::create('producciones_intelectuales', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('postulante_id');
            $table->unsignedBigInteger('source_merito_id')->unique()->nullable();
            
            $table->string('tipo_produccion_raw')->nullable();
            $table->string('tipo_produccion_normalizado')->nullable();
            $table->string('titulo')->nullable();
            $table->date('fecha_publicacion')->nullable();
            $table->string('editorial_revista')->nullable();
            $table->string('lugar')->nullable();
            
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
        Schema::dropIfExists('producciones_intelectuales');
    }
};
