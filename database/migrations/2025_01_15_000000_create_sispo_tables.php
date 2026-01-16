<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Posutlantes
        Schema::create('postulantes', function (Blueprint $table) {
            $table->id();
            $table->string('ci')->unique();
            $table->string('ci_expedido')->nullable();
            $table->string('nombres');
            $table->string('apellidos');
            $table->date('fecha_nacimiento')->nullable();
            $table->string('genero')->nullable();
            $table->string('nacionalidad')->nullable();
            $table->string('direccion_domicilio')->nullable();
            $table->string('email')->nullable();
            $table->string('celular')->nullable();
            $table->string('foto_perfil_path')->nullable();
            $table->string('cv_pdf_path')->nullable();
            $table->string('carta_postulacion_path')->nullable();
            $table->timestamps();
        });

        // Sedes
        Schema::create('sedes', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->timestamps();
        });

        // Cargos
        Schema::create('cargos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->timestamps();
        });

        // Tipos Documento
        Schema::create('tipos_documento', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->string('categoria')->nullable();
            $table->json('campos')->nullable();
            $table->json('config_archivos')->nullable();
            $table->boolean('permite_multiples')->default(false);
            $table->integer('orden')->default(0);
            $table->timestamps();
        });

        // Convocatorias
        Schema::create('convocatorias', function (Blueprint $table) {
            $table->id();
            $table->string('titulo');
            $table->text('descripcion')->nullable();
            $table->date('fecha_inicio');
            $table->date('fecha_cierre');
            $table->time('hora_limite')->nullable();
            $table->json('config_requisitos_ids')->nullable();
            $table->timestamps();
        });

        // Convocatoria Baremos
        Schema::create('convocatoria_baremos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('convocatoria_id')->constrained('convocatorias')->onDelete('cascade');
            $table->foreignId('tipo_documento_id')->constrained('tipos_documento')->onDelete('cascade');
            $table->json('regla_puntos_json')->nullable();
            $table->decimal('puntaje_por_item', 8, 2)->nullable();
            $table->decimal('puntaje_maximo_seccion', 8, 2)->nullable();
            $table->timestamps();
        });

        // Ofertas
        Schema::create('ofertas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('convocatoria_id')->constrained('convocatorias')->onDelete('cascade');
            $table->foreignId('sede_id')->constrained('sedes')->onDelete('cascade');
            $table->foreignId('cargo_id')->constrained('cargos')->onDelete('cascade');
            $table->integer('vacantes')->default(1);
            $table->timestamps();
        });

        // Postulante Meritos
        Schema::create('postulante_meritos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('postulante_id')->constrained('postulantes')->onDelete('cascade');
            $table->foreignId('tipo_documento_id')->constrained('tipos_documento')->onDelete('cascade');
            $table->json('respuestas')->nullable();
            $table->decimal('puntuacion_obtenida', 8, 2)->nullable();
            $table->enum('estado_verificacion', ['pendiente', 'validado', 'observado', 'rechazado'])->default('pendiente');
            $table->timestamps();
        });

        // Merito Archivos
        Schema::create('merito_archivos', function (Blueprint $table) {
            $table->id();
            // Note: foreign key to merito, assumed to be postulante_meritos
             $table->foreignId('merito_id')->constrained('postulante_meritos')->onDelete('cascade');
            $table->string('config_archivo_id');
            $table->string('archivo_path');
            $table->timestamps();
        });

        // Postulaciones
        Schema::create('postulaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('postulante_id')->constrained('postulantes')->onDelete('cascade');
            $table->foreignId('oferta_id')->constrained('ofertas')->onDelete('cascade');
            $table->string('estado')->default('enviada');
            $table->dateTime('fecha_postulacion')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('postulaciones');
        Schema::dropIfExists('merito_archivos');
        Schema::dropIfExists('postulante_meritos');
        Schema::dropIfExists('ofertas');
        Schema::dropIfExists('convocatoria_baremos');
        Schema::dropIfExists('convocatorias');
        Schema::dropIfExists('tipos_documento');
        Schema::dropIfExists('cargos');
        Schema::dropIfExists('sedes');
        Schema::dropIfExists('postulantes');
    }
};
