<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Persistent log of each AI pipeline stage.
 *
 * Each row represents one stage (START, BUILD_CONTEXT, CALLING_GEMINI, etc.)
 * for a single postulación analysis run.
 */
class AiJobLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'postulacion_id',
        'job_uuid',
        'etapa',
        'status',
        'mensaje',
        'error',
        'error_type',
        'processing_ms',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'metadata'   => 'array',
        'created_at' => 'datetime',
    ];

    // ─── Stage Constants ────────────────────────────────────────

    public const STAGE_START            = 'START';
    public const STAGE_BUILD_CONTEXT    = 'BUILD_CONTEXT';
    public const STAGE_BUILD_PROMPT     = 'BUILD_PROMPT';
    public const STAGE_CALLING_GEMINI   = 'CALLING_GEMINI';
    public const STAGE_PARSING_RESPONSE = 'PARSING_RESPONSE';
    public const STAGE_SAVING_RESULTS   = 'SAVING_RESULTS';
    public const STAGE_FINISHED         = 'FINISHED';
    public const STAGE_FAILED           = 'FAILED';

    public const STATUS_RUNNING = 'running';
    public const STATUS_OK      = 'ok';
    public const STATUS_ERROR   = 'error';

    // ─── Error Type Classification ──────────────────────────────

    public const ERROR_TIMEOUT        = 'timeout';
    public const ERROR_QUOTA_429      = 'quota_429';
    public const ERROR_AUTH            = 'auth';
    public const ERROR_MALFORMED_JSON  = 'malformed_json';
    public const ERROR_EMPTY_RESPONSE  = 'empty_response';
    public const ERROR_INVALID_SCHEMA  = 'invalid_schema';
    public const ERROR_CONNECTION      = 'connection';
    public const ERROR_UNKNOWN         = 'unknown';

    // ─── Frontend-Friendly Labels ───────────────────────────────

    /**
     * Map stage names to user-friendly Spanish labels for frontend display.
     */
    public static function stageLabel(string $stage): string
    {
        return match ($stage) {
            self::STAGE_START            => 'Procesando IA...',
            self::STAGE_BUILD_CONTEXT    => 'Leyendo expediente...',
            self::STAGE_BUILD_PROMPT     => 'Analizando requisitos...',
            self::STAGE_CALLING_GEMINI   => 'Evaluando con IA...',
            self::STAGE_PARSING_RESPONSE => 'Generando score...',
            self::STAGE_SAVING_RESULTS   => 'Guardando evaluación...',
            self::STAGE_FINISHED         => 'Evaluación completada',
            self::STAGE_FAILED           => 'Falló evaluación',
            default                      => $stage,
        };
    }

    // ─── Relationships ──────────────────────────────────────────

    public function postulacion()
    {
        return $this->belongsTo(Postulacion::class);
    }

    // ─── Scopes ─────────────────────────────────────────────────

    public function scopeForPostulacion($query, int $postulacionId)
    {
        return $query->where('postulacion_id', $postulacionId);
    }

    public function scopeForJob($query, string $jobUuid)
    {
        return $query->where('job_uuid', $jobUuid);
    }

    public function scopeLatestRun($query, int $postulacionId)
    {
        $latestUuid = static::where('postulacion_id', $postulacionId)
            ->whereNotNull('job_uuid')
            ->orderByDesc('id')
            ->value('job_uuid');

        if ($latestUuid) {
            return $query->where('job_uuid', $latestUuid)->orderBy('id');
        }

        return $query->where('postulacion_id', $postulacionId)->orderByDesc('id')->limit(1);
    }

    // ─── Factory Methods ────────────────────────────────────────

    /**
     * Log a pipeline stage. Returns the created log entry.
     */
    public static function stage(
        int $postulacionId,
        string $stage,
        string $status = self::STATUS_OK,
        ?string $mensaje = null,
        ?string $error = null,
        ?string $errorType = null,
        ?int $processingMs = null,
        ?array $metadata = null,
        ?string $jobUuid = null,
    ): self {
        return static::create([
            'postulacion_id' => $postulacionId,
            'job_uuid'       => $jobUuid,
            'etapa'          => $stage,
            'status'         => $status,
            'mensaje'        => $mensaje ? mb_substr($mensaje, 0, 500) : null,
            'error'          => $error,
            'error_type'     => $errorType,
            'processing_ms'  => $processingMs,
            'metadata'       => $metadata,
            'created_at'     => now(),
        ]);
    }

    /**
     * Classify an exception into an error_type constant.
     */
    public static function classifyError(\Throwable $e): string
    {
        $message = $e->getMessage();

        if (str_contains($message, '429') || str_contains($message, 'quota') || str_contains($message, 'RESOURCE_EXHAUSTED')) {
            return self::ERROR_QUOTA_429;
        }
        if (str_contains($message, '401') || str_contains($message, '403') || str_contains($message, 'API Key')) {
            return self::ERROR_AUTH;
        }
        if (str_contains($message, 'timeout') || str_contains($message, 'timed out') || str_contains($message, 'conexión')) {
            return self::ERROR_TIMEOUT;
        }
        if (str_contains($message, 'not valid JSON') || str_contains($message, 'json_decode') || str_contains($message, 'JSON')) {
            return self::ERROR_MALFORMED_JSON;
        }
        if (str_contains($message, 'empty') || str_contains($message, 'malformed response')) {
            return self::ERROR_EMPTY_RESPONSE;
        }
        if (str_contains($message, 'incompleto') || str_contains($message, 'score_total') || str_contains($message, 'clasificacion')) {
            return self::ERROR_INVALID_SCHEMA;
        }
        if ($e instanceof \Illuminate\Http\Client\ConnectionException) {
            return self::ERROR_CONNECTION;
        }

        return self::ERROR_UNKNOWN;
    }

    /**
     * Get the current status for a postulación's latest pipeline run.
     * Returns data formatted for the frontend.
     */
    public static function currentStatus(int $postulacionId): array
    {
        $logs = static::where('postulacion_id', $postulacionId)
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        if ($logs->isEmpty()) {
            return [
                'status'     => 'pending',
                'stage'      => null,
                'label'      => 'Sin evaluar',
                'error'      => null,
                'error_type' => null,
            ];
        }

        $latest = $logs->first();

        return [
            'status'       => $latest->status,
            'stage'        => $latest->etapa,
            'label'        => self::stageLabel($latest->etapa),
            'error'        => $latest->error,
            'error_type'   => $latest->error_type,
            'processing_ms' => $latest->processing_ms,
            'updated_at'   => $latest->created_at?->toIso8601String(),
        ];
    }
}
