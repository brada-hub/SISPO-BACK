<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('solicitudes_convocatorias')) {
            Schema::create('solicitudes_convocatorias', function (Blueprint $table) {
                $table->id();
                $table->string('codigo_solicitud', 30)->unique()->index();
                
                // Datos del Solicitante
                $table->string('solicitante_nombre', 150);
                $table->string('solicitante_cargo', 100);
                $table->string('solicitante_carrera', 150);
                $table->string('solicitante_email', 100);
                $table->string('solicitante_telefono', 50);
                
                // Tipo y Configuración del Puesto (Flujo UNITEPC)
                $table->string('tipo_perfil', 20)->default('docente'); // docente, adm, tecnico, invest
                $table->string('titulo_sugerido', 255);
                $table->text('descripcion_motivo')->nullable();
                
                // Sedes y Cargos solicitados (JSON de IDs - SIN VACANTES)
                $table->json('sedes_ids');
                $table->json('cargos_ids');
                
                // Requisitos solicitados por la Carrera
                $table->text('requisito_formacion')->nullable();
                $table->text('requisito_posgrado')->nullable();
                $table->string('requisito_experiencia_profesional', 255)->nullable();
                $table->string('requisito_experiencia_docente', 255)->nullable();
                $table->text('otros_requisitos')->nullable();
                
                // Documento de Respaldo Opcional (PDF)
                $table->string('archivo_adjunto_path', 255)->nullable();
                
                // Estado del Trámite en Talento Humano
                $table->string('estado', 30)->default('pendiente'); // pendiente, observada, aprobada, rechazada
                $table->text('observaciones_rrhh')->nullable();
                
                // Trazabilidad de Visto Bueno
                $table->unsignedBigInteger('convocatoria_creada_id')->nullable();
                $table->unsignedBigInteger('aprobado_por_user_id')->nullable();
                $table->timestamp('aprobado_at')->nullable();
                
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitudes_convocatorias');
    }
};
