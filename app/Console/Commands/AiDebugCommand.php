<?php

namespace App\Console\Commands;

use App\Models\AiJobLog;
use App\Models\AiMatchingResult;
use App\Models\Postulacion;
use App\Services\Ai\AiAnalysisService;
use App\Services\Ai\AiPipelineTracker;
use Illuminate\Console\Command;

/**
 * Manual AI pipeline debugger.
 *
 * Runs the full AI analysis pipeline synchronously, printing
 * every stage to the console in real-time. Ideal for:
 * - Debugging failures
 * - Verifying Gemini connectivity
 * - Testing with specific postulaciones
 * - Demo/presentation
 */
class AiDebugCommand extends Command
{
    protected $signature = 'ai:debug
        {postulacion_id : ID de la postulación a analizar}
        {--fresh : Borrar análisis previo antes de re-analizar}
        {--dry-run : Solo mostrar qué se haría, sin llamar a Gemini}';

    protected $description = 'Ejecutar pipeline IA manualmente con output detallado por etapas';

    public function handle(AiAnalysisService $service): int
    {
        $postulacionId = (int) $this->argument('postulacion_id');
        $fresh = $this->option('fresh');
        $dryRun = $this->option('dry-run');

        $this->newLine();
        $this->components->info("SISPO AI Debug Pipeline — Postulación #{$postulacionId}");
        $this->line('═══════════════════════════════════════════════════');

        // ── Validate postulación exists ──────────────────────────
        $postulacion = Postulacion::with([
            'postulante.meritos.tipoDocumento',
            'oferta.cargo',
            'oferta.convocatoria',
        ])->find($postulacionId);

        if (!$postulacion) {
            $this->components->error("Postulación #{$postulacionId} no encontrada.");
            return self::FAILURE;
        }

        $postulante = $postulacion->postulante;
        $cargo = $postulacion->oferta?->cargo;
        $convocatoria = $postulacion->oferta?->convocatoria;
        $postulanteName = trim(($postulante->nombres ?? '') . ' ' . ($postulante->apellidos ?? ''));

        // ── Display metadata ────────────────────────────────────
        $this->table(['Campo', 'Valor'], [
            ['Postulación ID', $postulacionId],
            ['Postulante', $postulanteName],
            ['CI', $postulante->ci ?? 'N/A'],
            ['Cargo', $cargo->nombre ?? 'N/A'],
            ['Convocatoria ID', $convocatoria->id ?? 'N/A'],
            ['Méritos registrados', $postulante->meritos->count()],
            ['Modelo IA', config('services.gemini.model')],
            ['API Key', substr(config('services.gemini.api_key', ''), 0, 12) . '...'],
            ['AI Enabled', config('services.gemini.enabled') ? 'Sí' : 'No'],
        ]);

        // ── Show existing results ───────────────────────────────
        $existing = AiMatchingResult::where('postulacion_id', $postulacionId)->first();
        if ($existing) {
            $this->newLine();
            $this->components->warn("Ya existe un resultado previo:");
            $this->table(['Campo', 'Valor'], [
                ['Score', $existing->score_total . '/100'],
                ['Clasificación', $existing->clasificacion_ia],
                ['Observaciones', mb_substr($existing->observaciones_ia ?? '', 0, 80) . '...'],
                ['Actualizado', $existing->updated_at?->toDateTimeString()],
            ]);
        }

        // ── Show recent ai_job_logs ─────────────────────────────
        $recentLogs = AiJobLog::where('postulacion_id', $postulacionId)
            ->orderByDesc('id')
            ->limit(8)
            ->get();

        if ($recentLogs->isNotEmpty()) {
            $this->newLine();
            $this->components->info("Historial de etapas recientes:");
            $logRows = $recentLogs->map(fn($l) => [
                $l->etapa,
                $l->status,
                mb_substr($l->mensaje ?? '', 0, 60),
                $l->error_type ?? '-',
                $l->processing_ms ? "{$l->processing_ms}ms" : '-',
                $l->created_at?->format('H:i:s'),
            ])->toArray();
            $this->table(['Etapa', 'Status', 'Mensaje', 'Error Type', 'Tiempo', 'Hora'], $logRows);
        }

        if ($dryRun) {
            $this->newLine();
            $this->components->info("Modo --dry-run: No se ejecutará el pipeline.");
            return self::SUCCESS;
        }

        // ── Check availability ──────────────────────────────────
        if (!$service->isAvailable()) {
            $this->components->error("Servicio IA no disponible. Verifica AI_ENABLED=true y AI_API_KEY en .env");
            return self::FAILURE;
        }

        // ── Fresh mode: delete previous ─────────────────────────
        if ($fresh && $existing) {
            $this->components->warn("--fresh: Borrando análisis previo...");
            \App\Models\AiCvAnalysis::where('postulacion_id', $postulacionId)->delete();
            AiMatchingResult::where('postulacion_id', $postulacionId)->delete();
            AiJobLog::where('postulacion_id', $postulacionId)->delete();
        }

        // ── Run pipeline with verbose tracker ───────────────────
        $this->newLine();
        $this->line('─── Ejecutando pipeline IA ───────────────────────');
        $this->newLine();

        $tracker = new AiPipelineTracker(
            $postulacionId,
            $postulanteName,
            $cargo->nombre ?? 'N/A',
            $convocatoria->id ?? null,
            verbose: true, // Print to console
        );

        $tracker->start();

        try {
            $result = $service->analyzePostulacion($postulacionId, $tracker);

            $this->newLine();
            $this->line('─── Resultado ───────────────────────────────────');
            if ($result) {
                $this->newLine();
                $this->components->info("✅ Pipeline completado exitosamente");
                $this->table(['Campo', 'Valor'], [
                    ['Score Total', $result->score_total . '/100'],
                    ['Clasificación', $result->clasificacion_ia],
                    ['Matching ID', $result->id],
                    ['Observaciones', mb_substr($result->observaciones_ia ?? '', 0, 120)],
                ]);

                // Show fortalezas/brechas
                $fortalezas = $result->fortalezas ?? [];
                $debilidades = $result->debilidades ?? [];

                if (!empty($fortalezas)) {
                    $this->newLine();
                    $this->components->info("Fortalezas:");
                    foreach ($fortalezas as $f) {
                        $this->line("  ✅ {$f}");
                    }
                }
                if (!empty($debilidades)) {
                    $this->newLine();
                    $this->components->warn("Brechas:");
                    foreach ($debilidades as $d) {
                        $this->line("  ⚠️  {$d}");
                    }
                }

                // Show justificación
                $justificacion = $result->pesos_aplicados['justificacion'] ?? null;
                if ($justificacion) {
                    $this->newLine();
                    $this->components->info("Justificación del score:");
                    $this->line("  {$justificacion}");
                }
            } else {
                $this->components->warn("Pipeline terminó sin resultado.");
            }

            return self::SUCCESS;

        } catch (\Throwable $e) {
            $this->newLine();
            $this->components->error("❌ Pipeline FALLÓ");
            $this->table(['Campo', 'Valor'], [
                ['Exception', get_class($e)],
                ['Mensaje', mb_substr($e->getMessage(), 0, 200)],
                ['Archivo', basename($e->getFile()) . ':' . $e->getLine()],
                ['Error Type', AiJobLog::classifyError($e)],
            ]);

            return self::FAILURE;
        }
    }
}
