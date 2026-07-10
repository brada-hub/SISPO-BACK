<?php

namespace App\Services\Ai;

use App\Models\AiJobLog;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Pipeline Tracker — observability layer for the AI analysis pipeline.
 *
 * Provides:
 * - Console-friendly log output ([AI] prefix)
 * - Laravel log structured entries
 * - Persistent DB tracking in ai_job_logs
 *
 * Usage:
 *   $tracker = new AiPipelineTracker($postulacionId, 'Juan Pérez', 'Docente Sistemas', 42);
 *   $tracker->start();
 *   $tracker->buildContext(120);
 *   $tracker->callingGemini();
 *   $tracker->geminiResponse(4728, 17500);
 *   $tracker->parsingResponse(70, 'parcialmente_apto');
 *   $tracker->savingResults();
 *   $tracker->finished(18200);
 *   // or: $tracker->failed($exception);
 */
class AiPipelineTracker
{
    private string $jobUuid;
    private float $startTime;
    private bool $verbose;

    public function __construct(
        private int $postulacionId,
        private string $postulanteName = '',
        private string $cargoName = '',
        private ?int $convocatoriaId = null,
        bool $verbose = false,
    ) {
        $this->jobUuid = Str::uuid()->toString();
        $this->startTime = microtime(true);
        $this->verbose = $verbose;
    }

    // ─── Stage Methods ──────────────────────────────────────────

    public function start(): void
    {
        $msg = "postulacion={$this->postulacionId} nombre=\"{$this->postulanteName}\" cargo=\"{$this->cargoName}\" convocatoria={$this->convocatoriaId}";

        $this->log('START', $msg);

        AiJobLog::stage(
            postulacionId: $this->postulacionId,
            stage: AiJobLog::STAGE_START,
            status: AiJobLog::STATUS_RUNNING,
            mensaje: "Iniciando análisis IA para {$this->postulanteName}",
            metadata: [
                'postulante_nombre' => $this->postulanteName,
                'cargo'             => $this->cargoName,
                'convocatoria_id'   => $this->convocatoriaId,
            ],
            jobUuid: $this->jobUuid,
        );
    }

    public function buildContext(int $elapsedMs, int $contextLength = 0): void
    {
        $msg = "in {$elapsedMs}ms (context_length={$contextLength})";

        $this->log('CONTEXT BUILT', $msg);

        AiJobLog::stage(
            postulacionId: $this->postulacionId,
            stage: AiJobLog::STAGE_BUILD_CONTEXT,
            status: AiJobLog::STATUS_OK,
            mensaje: "Expediente consolidado: {$contextLength} chars",
            processingMs: $elapsedMs,
            metadata: ['context_length' => $contextLength],
            jobUuid: $this->jobUuid,
        );
    }

    public function buildPrompt(int $elapsedMs, string $version, int $promptLength): void
    {
        $msg = "v{$version} in {$elapsedMs}ms (prompt_length={$promptLength})";

        $this->log('PROMPT BUILT', $msg);

        AiJobLog::stage(
            postulacionId: $this->postulacionId,
            stage: AiJobLog::STAGE_BUILD_PROMPT,
            status: AiJobLog::STATUS_OK,
            mensaje: "Prompt v{$version}: {$promptLength} chars",
            processingMs: $elapsedMs,
            metadata: ['prompt_version' => $version, 'prompt_length' => $promptLength],
            jobUuid: $this->jobUuid,
        );
    }

    public function callingGemini(string $model): void
    {
        $msg = "model={$model}";

        $this->log('CALLING GEMINI', $msg);

        AiJobLog::stage(
            postulacionId: $this->postulacionId,
            stage: AiJobLog::STAGE_CALLING_GEMINI,
            status: AiJobLog::STATUS_RUNNING,
            mensaje: "Enviando a {$model}...",
            metadata: ['model' => $model],
            jobUuid: $this->jobUuid,
        );
    }

    public function geminiResponse(int $tokensUsed, int $elapsedMs): void
    {
        $msg = "OK tokens={$tokensUsed} in {$elapsedMs}ms";

        $this->log('GEMINI RESPONSE', $msg);

        // Update the CALLING_GEMINI stage with the result
        AiJobLog::stage(
            postulacionId: $this->postulacionId,
            stage: AiJobLog::STAGE_CALLING_GEMINI,
            status: AiJobLog::STATUS_OK,
            mensaje: "Gemini respondió OK. Tokens: {$tokensUsed}",
            processingMs: $elapsedMs,
            metadata: ['tokens_used' => $tokensUsed],
            jobUuid: $this->jobUuid,
        );
    }

    public function parsingResponse(float $score, string $clasificacion): void
    {
        $msg = "SCORE={$score} clasificacion={$clasificacion}";

        $this->log('SCORE', $msg);

        AiJobLog::stage(
            postulacionId: $this->postulacionId,
            stage: AiJobLog::STAGE_PARSING_RESPONSE,
            status: AiJobLog::STATUS_OK,
            mensaje: "Score: {$score}/100 — {$clasificacion}",
            metadata: ['score_total' => $score, 'clasificacion' => $clasificacion],
            jobUuid: $this->jobUuid,
        );
    }

    public function savingResults(int $matchingId, float $score): void
    {
        $this->log('SAVED SUCCESSFULLY', "matching_id={$matchingId} score={$score}");

        AiJobLog::stage(
            postulacionId: $this->postulacionId,
            stage: AiJobLog::STAGE_SAVING_RESULTS,
            status: AiJobLog::STATUS_OK,
            mensaje: "AiMatchingResult #{$matchingId} guardado. Score: {$score}",
            metadata: ['matching_id' => $matchingId, 'score_total' => $score],
            jobUuid: $this->jobUuid,
        );
    }

    public function finished(int $totalMs): void
    {
        $totalSec = round($totalMs / 1000, 1);
        $msg = "total={$totalSec}s";

        $this->log('FINISHED', $msg);

        AiJobLog::stage(
            postulacionId: $this->postulacionId,
            stage: AiJobLog::STAGE_FINISHED,
            status: AiJobLog::STATUS_OK,
            mensaje: "Pipeline completado exitosamente en {$totalSec}s",
            processingMs: $totalMs,
            metadata: [
                'postulante_nombre' => $this->postulanteName,
                'cargo'             => $this->cargoName,
            ],
            jobUuid: $this->jobUuid,
        );
    }

    public function failed(\Throwable $e): void
    {
        $errorType = AiJobLog::classifyError($e);
        $reason = $this->friendlyErrorMessage($e, $errorType);

        $this->logError("FAILED postulacion={$this->postulacionId}");
        $this->logError("Reason: {$reason}");

        $totalMs = (int) round((microtime(true) - $this->startTime) * 1000);

        AiJobLog::stage(
            postulacionId: $this->postulacionId,
            stage: AiJobLog::STAGE_FAILED,
            status: AiJobLog::STATUS_ERROR,
            mensaje: $reason,
            error: mb_substr($e->getMessage(), 0, 2000),
            errorType: $errorType,
            processingMs: $totalMs,
            metadata: [
                'exception_class'  => get_class($e),
                'file'             => basename($e->getFile()) . ':' . $e->getLine(),
                'postulante_nombre' => $this->postulanteName,
            ],
            jobUuid: $this->jobUuid,
        );
    }

    // ─── Helper: Classify error into human-friendly message ─────

    private function friendlyErrorMessage(\Throwable $e, string $errorType): string
    {
        return match ($errorType) {
            AiJobLog::ERROR_TIMEOUT       => 'Timeout: Gemini no respondió a tiempo',
            AiJobLog::ERROR_QUOTA_429     => 'Cuota excedida (429): Límite de API alcanzado',
            AiJobLog::ERROR_AUTH          => 'Error de autenticación: API key inválida',
            AiJobLog::ERROR_MALFORMED_JSON => 'JSON malformado: Gemini devolvió respuesta no parseable',
            AiJobLog::ERROR_EMPTY_RESPONSE => 'Respuesta vacía: Gemini no generó contenido',
            AiJobLog::ERROR_INVALID_SCHEMA => 'Esquema inválido: Falta score_total o clasificacion',
            AiJobLog::ERROR_CONNECTION    => 'Error de conexión de red',
            default                       => 'Error: ' . mb_substr($e->getMessage(), 0, 200),
        };
    }

    // ─── Console & Log Output ───────────────────────────────────

    private function log(string $stage, string $message): void
    {
        $line = "[AI] {$stage} {$message}";

        Log::info($line);

        if ($this->verbose) {
            echo $line . "\n";
        }
    }

    private function logError(string $message): void
    {
        $line = "[AI] {$message}";

        Log::error($line);

        if ($this->verbose) {
            echo "\033[31m{$line}\033[0m\n"; // Red in terminal
        }
    }

    // ─── Getters ────────────────────────────────────────────────

    public function getJobUuid(): string
    {
        return $this->jobUuid;
    }

    public function getElapsedMs(): int
    {
        return (int) round((microtime(true) - $this->startTime) * 1000);
    }

    public function setVerbose(bool $verbose): self
    {
        $this->verbose = $verbose;
        return $this;
    }
}
