<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiCvAnalysis extends Model
{
    protected $connection = 'mysql';
    protected $table = 'ai_cv_analyses';

    protected $fillable = [
        'postulante_id',
        'postulacion_id',
        'raw_text',
        'text_extraction_method',
        'text_length',
        'ai_response',
        'nivel_academico',
        'profesion',
        'anios_experiencia',
        'habilidades',
        'experiencia_laboral',
        'formacion_academica',
        'idiomas',
        'certificaciones',
        'ai_provider',
        'ai_model',
        'prompt_version',
        'tokens_used',
        'processing_time_ms',
        'confidence_score',
        'status',
        'error_message',
    ];

    protected $casts = [
        'ai_response'        => 'array',
        'habilidades'         => 'array',
        'experiencia_laboral' => 'array',
        'formacion_academica' => 'array',
        'idiomas'             => 'array',
        'certificaciones'     => 'array',
        'anios_experiencia'   => 'decimal:1',
        'confidence_score'    => 'decimal:2',
    ];

    // ─── Relationships ──────────────────────────────────────────

    public function postulante()
    {
        return $this->belongsTo(Postulante::class);
    }

    public function postulacion()
    {
        return $this->belongsTo(Postulacion::class);
    }

    public function matchingResult()
    {
        return $this->hasOne(AiMatchingResult::class);
    }

    public function auditLogs()
    {
        return $this->morphMany(AiAuditLog::class, 'auditable');
    }

    // ─── Scopes ─────────────────────────────────────────────────

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    // ─── Helpers ────────────────────────────────────────────────

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function markAsProcessing(): void
    {
        $this->update(['status' => 'processing']);
    }

    public function markAsCompleted(array $data): void
    {
        $this->update(array_merge($data, ['status' => 'completed']));
    }

    public function markAsFailed(string $error): void
    {
        $this->update([
            'status'        => 'failed',
            'error_message' => $error,
        ]);
    }
}
