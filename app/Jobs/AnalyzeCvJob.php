<?php

namespace App\Jobs;

use App\Models\AiJobLog;
use App\Models\Postulacion;
use App\Services\Ai\AiAnalysisService;
use App\Services\Ai\AiPipelineTracker;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Asynchronous job to analyze a single CV via AI.
 *
 * Dispatched when a postulación is submitted or when an admin
 * manually triggers analysis. Fails gracefully — the postulación
 * remains valid even if AI analysis fails.
 *
 * Observability: Every stage is tracked via AiPipelineTracker,
 * producing structured console output, Laravel logs, and
 * persistent ai_job_logs DB records.
 */
class AnalyzeCvJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Max attempts before marking as failed.
     */
    public int $tries = 3;

    /**
     * Timeout in seconds (Gemini can be slow).
     */
    public int $timeout = 120;

    /**
     * Delay between retries in seconds.
     */
    public int $backoff = 10;

    public function __construct(
        public int $postulacionId,
    ) {}

    public function handle(AiAnalysisService $service, \App\Services\Evaluation\Score\SispoScoreEngine $scoreEngine): void
    {
        // ── Pre-flight: Load metadata for tracking ──────────────
        $postulacion = Postulacion::with(['postulante', 'oferta.cargo', 'oferta.convocatoria'])
            ->find($this->postulacionId);

        if (!$postulacion) {
            Log::error("[AI] FAILED postulacion={$this->postulacionId} — Postulación no encontrada en BD");
            return;
        }

        $postulante = $postulacion->postulante;
        $postulanteName = trim(($postulante->nombres ?? '') . ' ' . ($postulante->apellidos ?? ''));
        $cargoName = $postulacion->oferta?->cargo?->nombre ?? 'N/A';
        $convocatoriaId = $postulacion->oferta?->convocatoria?->id;

        // ── Create tracker ──────────────────────────────────────
        $tracker = new AiPipelineTracker(
            $this->postulacionId,
            $postulanteName,
            $cargoName,
            $convocatoriaId,
        );

        $tracker->start(); // [START]

        // ── Step 1: Run Deterministic Evaluation via Score Engine ───
        try {
            Log::info("[ScoreEngine] [RUNNING_SCORE_ENGINE] Evaluating postulacion={$this->postulacionId} deterministically (100% No-AI Mode)");
            
            $scoreRes = $scoreEngine->evaluate($this->postulacionId, true);
            
            Log::info("[ScoreEngine] [SAVING_RESULTS] Persisting results for postulacion={$this->postulacionId} score={$scoreRes['score_total']}");

            // Populate AiCvAnalysis for frontend compatibility
            $analysis = \App\Models\AiCvAnalysis::updateOrCreate(
                ['postulante_id' => $postulante->id, 'postulacion_id' => $this->postulacionId],
                [
                    'status'                => 'completed',
                    'raw_text'              => 'Deterministic Scoring Mode (100% No-AI Mode active, Gemini bypassed)',
                    'text_extraction_method' => 'db_consolidated',
                    'text_length'           => 100,
                    'ai_response'           => $scoreRes,
                    'ai_provider'           => 'score_engine',
                    'ai_model'              => 'deterministic_v1',
                    'prompt_version'        => '1.0',
                    'tokens_used'           => 0,
                    'processing_time_ms'    => 5,
                ]
            );

            // Populate AiMatchingResult for frontend compatibility (Fase 5)
            \App\Models\AiMatchingResult::updateOrCreate(
                ['postulacion_id' => $this->postulacionId],
                [
                    'ai_cv_analysis_id'    => $analysis->id,
                    'convocatoria_id'      => $scoreRes['convocatoria_id'],
                    'score_total'          => $scoreRes['score_total'],
                    'score_formacion'      => $scoreRes['score_breakdown_json']['academic_formation']['score'] ?? 0,
                    'score_experiencia'    => $scoreRes['score_breakdown_json']['professional_experience']['score'] ?? 0,
                    'score_docencia'       => $scoreRes['score_breakdown_json']['teaching_experience']['score'] ?? 0,
                    'score_habilidades'    => 0.0,
                    'score_requisitos'     => 0.0,
                    'score_idiomas'        => 0.0,
                    'pesos_aplicados'      => [],
                    'clasificacion_ia'     => $scoreRes['classification'],
                    'cumple_requisitos'    => true,
                    'requisitos_cumplidos' => [],
                    'requisitos_faltantes' => [],
                    'observaciones_ia'     => 'Fortalezas: ' . implode(', ', $scoreRes['strengths_json']) . '. Explicación: ' . ($scoreRes['review_reason_summary'] ?? 'Evaluado determinísticamente.'),
                    'fortalezas'           => $scoreRes['strengths_json'],
                    'debilidades'          => $scoreRes['weaknesses_json'],
                    'justificacion'        => 'Score: ' . $scoreRes['score_total'] . ' pts. ' . ($scoreRes['review_reason_summary'] ?? 'Cumple con parámetros deterministicos.'),
                    'evaluation_mode'      => 'deterministic',
                ]
            );

            // Log HUMAN_REVIEW_REQUIRED if applicable
            if ($scoreRes['requires_human_review']) {
                Log::warning("[ScoreEngine] [HUMAN_REVIEW_REQUIRED] postulacion={$this->postulacionId} triggers manual audit!");
                
                AiJobLog::stage(
                    postulacionId: $this->postulacionId,
                    stage: 'HUMAN_REVIEW_REQUIRED',
                    status: AiJobLog::STATUS_SUCCESS,
                    mensaje: "Revisión manual requerida. Banderas: " . implode(', ', $scoreRes['review_flags_json']),
                    jobUuid: $tracker->getJobUuid(),
                );
            }

            AiJobLog::stage(
                postulacionId: $this->postulacionId,
                stage: AiJobLog::STAGE_SAVING_RESULTS,
                status: AiJobLog::STATUS_SUCCESS,
                mensaje: "Cálculo determinístico finalizado sin IA (Score: {$scoreRes['score_total']}) [FINISHED]",
                jobUuid: $tracker->getJobUuid(),
            );

        } catch (\Throwable $e) {
            Log::error("[ScoreEngine] Error during deterministic queue run: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Handle a job failure — called by the queue worker after all retries are exhausted.
     */
    public function failed(\Throwable $exception): void
    {
        $errorType = AiJobLog::classifyError($exception);

        Log::error("[AI] FAILED postulacion={$this->postulacionId}");
        Log::error("[AI] Reason: " . mb_substr($exception->getMessage(), 0, 300));
        Log::error("[AI] Type: {$errorType}");

        // Persist the failure
        AiJobLog::stage(
            postulacionId: $this->postulacionId,
            stage: AiJobLog::STAGE_FAILED,
            status: AiJobLog::STATUS_ERROR,
            mensaje: mb_substr($exception->getMessage(), 0, 500),
            error: mb_substr($exception->getTraceAsString(), 0, 2000),
            errorType: $errorType,
        );

        // Ensure the analysis record reflects the failure so the frontend knows
        try {
            $analysis = \App\Models\AiCvAnalysis::where('postulacion_id', $this->postulacionId)->latest()->first();
            if ($analysis && $analysis->status !== 'completed') {
                $analysis->update([
                    'status'        => 'failed',
                    'error_message' => 'Error en el Job de colas: ' . mb_substr($exception->getMessage(), 0, 400),
                ]);
            }
        } catch (\Throwable $e) {
            Log::error("[AI] Could not update failure status for postulacion={$this->postulacionId}: " . $e->getMessage());
        }
    }
}
