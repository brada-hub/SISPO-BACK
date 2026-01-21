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
        Schema::create('evaluaciones_postulaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('postulacion_id')->constrained('postulaciones')->onDelete('cascade');
            $table->foreignId('evaluador_id')->constrained('users');

            $table->decimal('puntaje_formacion', 8, 2)->default(0);
            $table->decimal('puntaje_perfeccionamiento', 8, 2)->default(0);
            $table->decimal('puntaje_experiencia', 8, 2)->default(0);
            $table->decimal('puntaje_otros', 8, 2)->default(0);
            $table->decimal('puntaje_total', 8, 2)->default(0);

            $table->json('detalle_evaluacion')->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('evaluaciones_postulaciones');
    }
};
