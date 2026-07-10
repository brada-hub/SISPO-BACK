<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // =========================================================
        // 1. AI CV Analyses — Resultado del análisis de CV por IA
        // =========================================================
        Schema::create('ai_cv_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('postulante_id')->constrained('postulantes')->onDelete('cascade');
            $table->foreignId('postulacion_id')->nullable()->constrained('postulaciones')->onDelete('set null');

            // Texto extraído del PDF
            $table->longText('raw_text')->nullable();
            $table->string('text_extraction_method', 30)->default('smalot');
            $table->unsignedInteger('text_length')->default(0);

            // Respuesta completa de la IA
            $table->json('ai_response')->nullable();

            // Campos desnormalizados para queries rápidas
            $table->string('nivel_academico', 100)->nullable();
            $table->string('profesion', 255)->nullable();
            $table->decimal('anios_experiencia', 4, 1)->nullable();
            $table->json('habilidades')->nullable();
            $table->json('experiencia_laboral')->nullable();
            $table->json('formacion_academica')->nullable();
            $table->json('idiomas')->nullable();
            $table->json('certificaciones')->nullable();

            // Metadata de procesamiento
            $table->string('ai_provider', 30)->default('gemini');
            $table->string('ai_model', 50)->default('gemini-2.0-flash');
            $table->string('prompt_version', 10)->default('v1.0');
            $table->unsignedInteger('tokens_used')->nullable();
            $table->unsignedInteger('processing_time_ms')->nullable();
            $table->decimal('confidence_score', 5, 2)->nullable()->comment('0-100');

            // Estado
            $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'expired'])->default('pending');
            $table->text('error_message')->nullable();

            $table->timestamps();

            $table->index('status');
            $table->index('postulante_id');
        });

        // =========================================================
        // 2. AI Matching Results — Score de compatibilidad
        // =========================================================
        Schema::create('ai_matching_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_cv_analysis_id')->constrained('ai_cv_analyses')->onDelete('cascade');
            $table->foreignId('postulacion_id')->constrained('postulaciones')->onDelete('cascade');
            $table->foreignId('convocatoria_id')->constrained('convocatorias')->onDelete('cascade');

            // Scores desglosados (0-100)
            $table->decimal('score_formacion', 5, 2)->default(0);
            $table->decimal('score_experiencia', 5, 2)->default(0);
            $table->decimal('score_habilidades', 5, 2)->default(0);
            $table->decimal('score_requisitos', 5, 2)->default(0);
            $table->decimal('score_total', 5, 2)->default(0);

            // Pesos usados para el cálculo
            $table->json('pesos_aplicados');

            // Clasificación
            $table->enum('clasificacion_ia', ['apto', 'parcialmente_apto', 'no_apto']);

            // Detalle del matching
            $table->json('requisitos_cumplidos')->nullable();
            $table->json('requisitos_faltantes')->nullable();
            $table->text('observaciones_ia')->nullable();
            $table->json('fortalezas')->nullable();
            $table->json('debilidades')->nullable();

            // Ranking
            $table->unsignedInteger('ranking_posicion')->nullable();

            $table->timestamps();

            $table->unique('postulacion_id', 'uk_postulacion_matching');
            $table->index(['convocatoria_id', 'score_total'], 'idx_conv_score');
        });

        // =========================================================
        // 3. AI Audit Logs — Trazabilidad completa
        // =========================================================
        Schema::create('ai_audit_logs', function (Blueprint $table) {
            $table->id();

            $table->string('auditable_type', 100);
            $table->unsignedBigInteger('auditable_id');

            $table->string('action', 50)->comment('created|updated|overridden_by_human|prompt_updated|reanalyzed');

            // Quién lo hizo
            $table->enum('triggered_by', ['system', 'user'])->default('system');
            $table->unsignedBigInteger('user_id')->nullable();

            // Qué se envió a la IA
            $table->longText('prompt_sent')->nullable();
            $table->string('prompt_version', 10)->nullable();

            // Qué respondió la IA
            $table->longText('ai_raw_response')->nullable();

            // Override humano
            $table->text('human_override_reason')->nullable();
            $table->json('previous_value')->nullable();
            $table->json('new_value')->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['auditable_type', 'auditable_id'], 'idx_auditable');
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_audit_logs');
        Schema::dropIfExists('ai_matching_results');
        Schema::dropIfExists('ai_cv_analyses');
    }
};
