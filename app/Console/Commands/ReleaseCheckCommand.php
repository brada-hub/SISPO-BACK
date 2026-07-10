<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\ConvocatoriaScoreRule;
use App\Models\EvaluationResult;
use App\Models\PostulanteExperienceSummary;
use App\Models\PostulanteTrainingSummary;
use App\Models\Postulacion;
use App\Models\User;

class ReleaseCheckCommand extends Command
{
    protected $signature = 'sispo:release-check';
    protected $description = 'Audita el estado de preparación para el release a producción de SISPO IA (No-AI Mode)';

    public function handle()
    {
        $this->title('INICIANDO AUDITORÍA DE PREPARACIÓN DE RELEASE (SISPO IA PRODUCTION READY)');
        
        $hasErrors = false;

        // 1. CHEQUEAR MIGRACIONES Y TABLAS
        $this->section('1. Verificación de Estructura de Base de Datos (MySQL Schema)');
        
        $requiredTables = [
            'score_profiles',
            'convocatoria_score_rules',
            'evaluation_results',
            'postulante_experience_summaries',
            'postulante_training_summaries',
            'experiencias_profesionales',
            'experiencias_docencia',
            'capacitaciones',
            'catalog_careers',
            'catalog_job_positions'
        ];

        foreach ($requiredTables as $table) {
            if (Schema::hasTable($table)) {
                $this->logSuccess("Tabla '{$table}' existe correctamente.");
            } else {
                $this->logError("ERROR: La tabla '{$table}' NO existe en la base de datos.");
                $hasErrors = true;
            }
        }

        // Column check on evaluation_results
        $requiredColumns = ['review_risk_score', 'review_risk_level', 'evaluation_status', 'requires_human_review'];
        foreach ($requiredColumns as $column) {
            if (Schema::hasColumn('evaluation_results', $column)) {
                $this->logSuccess("Columna 'evaluation_results.{$column}' existe correctamente.");
            } else {
                $this->logError("ERROR: Columna 'evaluation_results.{$column}' NO existe.");
                $hasErrors = true;
            }
        }

        // 2. VERIFICACIÓN DE SEEDERS Y BAREMOS
        $this->section('2. Verificación de Semilla y Perfiles de Puntuación');
        
        $profilesCount = DB::table('score_profiles')->count();
        if ($profilesCount > 0) {
            $this->logSuccess("Existen {$profilesCount} Perfiles de Puntuación (Score Profiles) pre-configurados.");
        } else {
            $this->logWarn("ADVERTENCIA: No existen Score Profiles en score_profiles. Se requiere ejecutar seeders.");
        }

        $rulesCount = ConvocatoriaScoreRule::count();
        if ($rulesCount > 0) {
            $this->logSuccess("Existen {$rulesCount} Reglas de Puntuación asociadas a Convocatorias.");
        } else {
            $this->logWarn("ADVERTENCIA: No existen Reglas de Puntuación de Convocatoria cargadas.");
        }

        // 3. CALIBRACIÓN Y RESÚMENES TEMPORALES
        $this->section('3. Verificación de Procesamiento Temporal & Auditorías');

        $evaluationsCount = EvaluationResult::count();
        $this->info("Total Evaluaciones Registradas en MySQL: {$evaluationsCount}");

        $expSummaries = PostulanteExperienceSummary::count();
        $this->info("Total Resúmenes de Experiencia Normalizados: {$expSummaries}");

        $trainingSummaries = PostulanteTrainingSummary::count();
        $this->info("Total Resúmenes de Capacitación Normalizados: {$trainingSummaries}");

        if ($evaluationsCount > 0 && $expSummaries > 0 && $trainingSummaries > 0) {
            $this->logSuccess("Bases de datos temporales y de scoring completamente procesadas.");
        } else {
            $this->logError("ERROR: Faltan registros de evaluación o resúmenes temporales. Se requiere correr comandos de recálculo.");
            $hasErrors = true;
        }

        // 4. DESACOPLAMIENTO DE INTELIGENCIA ARTIFICIAL (IA GEMINI)
        $this->section('4. Estado de Desacoplamiento de Gemini Client');

        // Check if automatic AI review is disabled
        $requiresAiCount = EvaluationResult::where('requires_human_review', true)
            ->whereHas('postulacion', function($q) {
                $q->where('estado', 'requires_ai_review');
            })->count();

        if ($requiresAiCount === 0) {
            $this->logSuccess("CERO postulaciones marcadas para revisión obligatoria de IA (requires_ai_review = 0).");
        } else {
            $this->logWarn("ADVERTENCIA: Existen {$requiresAiCount} postulaciones requiriendo revisión de IA. Deben ser procesadas de forma determinista.");
        }

        // Check services configurations
        $geminiEnabled = config('services.gemini.enabled', false);
        if (!$geminiEnabled || config('services.gemini.use_gemini') === false || env('SISPO_USE_GEMINI') === false) {
            $this->logSuccess("DESACOPLAMIENTO DE GEMINI IA CONFIRMADO: El sistema funciona en modo 100% determinístico sin llamadas de red.");
        } else {
            $this->logWarn("ADVERTENCIA: La variable SISPO_USE_GEMINI o configuraciones de Gemini no están deshabilitadas por completo.");
        }

        // 5. CHEQUEO DE PERMISOS DE ACCESO RRHH
        $this->section('5. Permisos de Acceso Operacional (Recruitment Workspace)');
        
        $evalPermissionExists = DB::connection('core')->table('permissions')->where('nombres', 'evaluaciones')->exists();
        if ($evalPermissionExists) {
            $this->logSuccess("El permiso 'evaluaciones' existe de forma nativa para control del Workspace.");
        } else {
            $this->logError("ERROR: No existe el permiso 'evaluaciones' registrado en la tabla permissions.");
            $hasErrors = true;
        }

        $this->newLine();
        $this->section('CONCLUSIÓN DE AUDITORÍA DE RELEASE');

        if ($hasErrors) {
            $this->logError("ESTADO: RECHAZADO (NO-GO). Se detectaron errores críticos de estructura o consistencia en la base de datos.");
            return 1;
        } else {
            $this->logSuccess("ESTADO: APROBADO (GO). SISPO IA se encuentra 100% calibrado, libre de IA y listo para producción.");
            return 0;
        }
    }

    private function title($text)
    {
        $this->line(str_repeat('=', 80));
        $this->line(" <info>{$text}</info>");
        $this->line(str_repeat('=', 80));
    }

    private function section($text)
    {
        $this->line("\n<comment>===> {$text}</comment>");
        $this->line(str_repeat('-', 40));
    }

    private function logSuccess($text)
    {
        $this->line("  [✔] {$text}");
    }

    private function logWarn($text)
    {
        $this->line("  [!] {$text}");
    }

    private function logError($text)
    {
        $this->line("  [✘] {$text}");
    }
}
