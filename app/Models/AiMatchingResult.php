<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiMatchingResult extends Model
{
    protected $connection = 'mysql';
    protected $table = 'ai_matching_results';

    protected $fillable = [
        'ai_cv_analysis_id',
        'postulacion_id',
        'convocatoria_id',
        'score_formacion',
        'score_experiencia',
        'score_docencia',
        'score_habilidades',
        'score_requisitos',
        'score_total',
        'pesos_aplicados',
        'clasificacion_ia',
        'evaluation_mode',
        'requisitos_cumplidos',
        'requisitos_faltantes',
        'observaciones_ia',
        'fortalezas',
        'debilidades',
        'ranking_posicion',
    ];

    protected $casts = [
        'score_formacion'      => 'decimal:2',
        'score_experiencia'    => 'decimal:2',
        'score_habilidades'    => 'decimal:2',
        'score_requisitos'     => 'decimal:2',
        'score_total'          => 'decimal:2',
        'pesos_aplicados'      => 'array',
        'requisitos_cumplidos' => 'array',
        'requisitos_faltantes' => 'array',
        'fortalezas'           => 'array',
        'debilidades'          => 'array',
    ];

    // ─── Relationships ──────────────────────────────────────────

    public function cvAnalysis()
    {
        return $this->belongsTo(AiCvAnalysis::class, 'ai_cv_analysis_id');
    }

    public function postulacion()
    {
        return $this->belongsTo(Postulacion::class);
    }

    public function convocatoria()
    {
        return $this->belongsTo(Convocatoria::class);
    }

    public function auditLogs()
    {
        return $this->morphMany(AiAuditLog::class, 'auditable');
    }

    // ─── Scopes ─────────────────────────────────────────────────

    public function scopeAptos($query)
    {
        return $query->where('clasificacion_ia', 'apto');
    }

    public function scopeByRanking($query)
    {
        return $query->orderByDesc('score_total');
    }

    public function scopeForConvocatoria($query, int $convocatoriaId)
    {
        return $query->where('convocatoria_id', $convocatoriaId);
    }

    // ─── Helpers ────────────────────────────────────────────────

    /**
     * Get human-readable classification label
     */
    public function getClasificacionLabelAttribute(): string
    {
        return match ($this->clasificacion_ia) {
            'apto'               => '✅ Apto',
            'parcialmente_apto'  => '⚠️ Parcialmente Apto',
            'no_apto'            => '❌ No Apto',
            default              => '❓ Sin clasificar',
        };
    }

    /**
     * Determine classification from score using configurable thresholds
     */
    public static function classify(float $score, array $thresholds = []): string
    {
        $upper = $thresholds['apto'] ?? 75.0;
        $lower = $thresholds['parcial'] ?? 50.0;

        if ($score >= $upper) return 'apto';
        if ($score >= $lower) return 'parcialmente_apto';

        return 'no_apto';
    }
}
