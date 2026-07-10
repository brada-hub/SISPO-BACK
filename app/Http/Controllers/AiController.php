<?php

namespace App\Http\Controllers;

use App\Jobs\AnalyzeCvJob;
use App\Jobs\BatchAnalyzeJob;
use App\Models\AiAuditLog;
use App\Models\AiCvAnalysis;
use App\Models\AiJobLog;
use App\Models\AiMatchingResult;
use App\Models\Postulacion;
use App\Services\Ai\AiAnalysisService;
use App\Services\Ai\RankingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiController extends Controller
{
    public function __construct(
        private AiAnalysisService $analysisService,
        private RankingService    $rankingService,
    ) {}

    /**
     * Trigger deterministic evaluation for a single postulación.
     * Runs synchronously for instant UI updates in No-AI Mode.
     */
    public function analyzeCV(int $postulacionId): JsonResponse
    {
        $postulacion = Postulacion::with('postulante')->findOrFail($postulacionId);

        if (! $postulacion->postulante?->cv_pdf_path) {
            return response()->json([
                'success' => false,
                'message' => 'El postulante no tiene un CV PDF cargado.',
            ], 422);
        }

        try {
            $scoreRes = $this->runDeterministicEvaluation($postulacionId);
            return response()->json([
                'success' => true,
                'message' => 'Evaluación automática completada.',
                'postulacion_id' => $postulacionId,
                'data' => $scoreRes,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error durante la evaluación automática: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get the AI analysis result for a postulación.
     */
    public function getAnalysis(int $postulacionId): JsonResponse
    {
        $postulacionExists = Postulacion::where('id', $postulacionId)->exists();
        if (!$postulacionExists) {
            return response()->json([
                'success' => false,
                'message' => 'La postulación especificada no existe.',
                'status'  => 'not_found',
            ], 404);
        }

        // 1. Search evaluation_results (deterministic)
        $evalResult = \App\Models\EvaluationResult::where('postulacion_id', $postulacionId)->first();
        if ($evalResult) {
            $breakdown = is_string($evalResult->score_breakdown_json) 
                ? json_decode($evalResult->score_breakdown_json, true) 
                : $evalResult->score_breakdown_json;

            $strengths = is_string($evalResult->strengths_json)
                ? json_decode($evalResult->strengths_json, true)
                : $evalResult->strengths_json;

            $weaknesses = is_string($evalResult->weaknesses_json)
                ? json_decode($evalResult->weaknesses_json, true)
                : $evalResult->weaknesses_json;

            return response()->json([
                'success' => true,
                'status'  => 'evaluated',
                'data'    => [
                    'postulacion_id'    => $postulacionId,
                    'evaluation_mode'   => $evalResult->evaluation_mode ?: 'deterministic',
                    'score_total'       => (float) $evalResult->score_total,
                    'classification'    => $evalResult->classification,
                    'score_breakdown'   => $breakdown,
                    'strengths'         => $strengths,
                    'weaknesses'        => $weaknesses,
                    'review_risk_level' => $evalResult->review_risk_level,
                    'review_risk_score' => (float) $evalResult->review_risk_score,
                    'evaluation_status' => $evalResult->evaluation_status,
                ]
            ]);
        }

        // 2. Fallback to legacy ai_cv_analyses
        $analysis = AiCvAnalysis::where('postulacion_id', $postulacionId)
            ->latest()
            ->first();

        if ($analysis) {
            return response()->json([
                'success'  => true,
                'data'     => $analysis,
                'status'   => $analysis->status,
            ]);
        }

        // 3. Not evaluated yet
        return response()->json([
            'success' => true,
            'status'  => 'not_evaluated',
            'message' => 'La postulación aún no fue evaluada.',
            'data'    => null,
        ]);
    }

    /**
     * Get the matching result for a postulación.
     */
    public function getMatching(int $postulacionId): JsonResponse
    {
        $matching = AiMatchingResult::where('postulacion_id', $postulacionId)
            ->with('cvAnalysis')
            ->first();

        if (! $matching) {
            return response()->json([
                'success' => false,
                'message' => 'No existe resultado de matching para esta postulación.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $matching,
        ]);
    }

    /**
     * Get the full ranking for a convocatoria.
     */
    public function getRanking(int $convocatoriaId): JsonResponse
    {
        $ranking = $this->rankingService->getRanking($convocatoriaId);
        $stats   = $this->rankingService->getStats($convocatoriaId);

        return response()->json([
            'success' => true,
            'stats'   => $stats,
            'ranking' => $ranking,
        ]);
    }

    /**
     * Force a complete recalculation of a postulación's scores.
     */
    public function reanalyze(int $postulacionId): JsonResponse
    {
        try {
            // Delete existing data so it starts fresh
            AiCvAnalysis::where('postulacion_id', $postulacionId)->delete();
            AiMatchingResult::where('postulacion_id', $postulacionId)->delete();

            $scoreRes = $this->runDeterministicEvaluation($postulacionId);

            return response()->json([
                'success' => true,
                'message' => 'Recálculo de evaluación completado.',
                'postulacion_id' => $postulacionId,
                'data' => $scoreRes,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al recalcular evaluación: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Dispatch batch analysis for all postulaciones in a convocatoria.
     */
    public function batchAnalyze(int $convocatoriaId): JsonResponse
    {
        if (! $this->analysisService->isAvailable()) {
            return response()->json([
                'success' => false,
                'message' => 'El servicio de IA no está configurado. Por favor, asegúrese de establecer AI_ENABLED=true y una AI_API_KEY válida en el archivo .env.',
            ], 503);
        }

        BatchAnalyzeJob::dispatch($convocatoriaId)->onQueue('ai');

        return response()->json([
            'success' => true,
            'message' => 'Análisis masivo iniciado para toda la convocatoria. Los resultados se procesarán en segundo plano.',
        ]);
    }

    /**
     * Get audit log for a postulación's AI interactions.
     */
    public function getAuditLog(int $postulacionId): JsonResponse
    {
        $analysisIds = AiCvAnalysis::where('postulacion_id', $postulacionId)->pluck('id');
        $matchingIds = AiMatchingResult::where('postulacion_id', $postulacionId)->pluck('id');

        $logs = AiAuditLog::where(function ($q) use ($analysisIds) {
            $q->where('auditable_type', 'ai_cv_analyses')
              ->whereIn('auditable_id', $analysisIds);
        })
            ->orWhere(function ($q) use ($matchingIds) {
                $q->where('auditable_type', 'ai_matching_results')
                  ->whereIn('auditable_id', $matchingIds);
            })
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $logs,
        ]);
    }

    /**
     * Human override: change the AI classification with justification.
     */
    public function humanOverride(Request $request, int $matchingId): JsonResponse
    {
        $validated = $request->validate([
            'clasificacion'  => 'required|in:apto,parcialmente_apto,no_apto',
            'justificacion'  => 'required|string|min:10|max:1000',
        ]);

        $matching = AiMatchingResult::findOrFail($matchingId);

        // Log the override
        AiAuditLog::logOverride(
            matching:      $matching,
            userId:        auth()->id(),
            previousValue: [
                'clasificacion_ia' => $matching->clasificacion_ia,
                'score_total'      => $matching->score_total,
            ],
            newValue: [
                'clasificacion_ia' => $validated['clasificacion'],
            ],
            reason:     $validated['justificacion'],
            ipAddress:  $request->ip(),
        );

        // Update the matching result
        $matching->update([
            'clasificacion_ia' => $validated['clasificacion'],
            'observaciones_ia' => $matching->observaciones_ia
                . "\n\n[OVERRIDE HUMANO por " . auth()->user()->nombre_completo . "]: "
                . $validated['justificacion'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Clasificación actualizada con registro de auditoría.',
            'data'    => $matching->fresh(),
        ]);
    }

    /**
     * Get real-time AI job status for a postulación (frontend polling).
     */
    public function getJobStatus(int $postulacionId): JsonResponse
    {
        $status = AiJobLog::currentStatus($postulacionId);

        return response()->json([
            'success' => true,
            'data'    => $status,
        ]);
    }

    /**
     * Get the full AI job log history for a postulación.
     */
    public function getJobLogs(int $postulacionId): JsonResponse
    {
        $logs = AiJobLog::where('postulacion_id', $postulacionId)
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn (AiJobLog $log) => [
                'id'            => $log->id,
                'etapa'         => $log->etapa,
                'label'         => AiJobLog::stageLabel($log->etapa),
                'status'        => $log->status,
                'mensaje'       => $log->mensaje,
                'error'         => $log->error,
                'error_type'    => $log->error_type,
                'processing_ms' => $log->processing_ms,
                'metadata'      => $log->metadata,
                'created_at'    => $log->created_at?->toIso8601String(),
            ]);

        return response()->json([
            'success' => true,
            'data'    => $logs,
        ]);
    }

    /**
     * Get AI module configuration and status.
     */
    public function getConfig(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'config'  => [
                'ai_enabled'      => config('services.gemini.enabled', false),
                'ai_provider'     => config('services.gemini.provider', 'gemini'),
                'ai_model'        => config('services.gemini.model', 'gemini-2.5-flash'),
                'api_configured'  => $this->analysisService->isAvailable(),
                'default_weights' => \App\Services\Ai\MatchingEngine::DEFAULT_WEIGHTS,
                'thresholds'      => [
                    'apto'    => 75,
                    'parcial' => 50,
                ],
            ],
        ]);
    }

    /**
     * Helper to run deterministic SispoScoreEngine synchronously and populate legacy tables.
     */
    private function runDeterministicEvaluation(int $postulacionId): array
    {
        $scoreEngine = app(\App\Services\Evaluation\Score\SispoScoreEngine::class);
        $scoreRes = $scoreEngine->evaluate($postulacionId, true);

        $postulacion = Postulacion::with('postulante')->findOrFail($postulacionId);
        $postulante = $postulacion->postulante;

        // Populate AiCvAnalysis for frontend compatibility
        $analysis = \App\Models\AiCvAnalysis::updateOrCreate(
            ['postulante_id' => $postulante->id, 'postulacion_id' => $postulacionId],
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

        // Populate AiMatchingResult for frontend compatibility
        \App\Models\AiMatchingResult::updateOrCreate(
            ['postulacion_id' => $postulacionId],
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

        return $scoreRes;
    }
}
