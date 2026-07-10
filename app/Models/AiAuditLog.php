<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiAuditLog extends Model
{
    protected $connection = 'mysql';
    protected $table = 'ai_audit_logs';

    public $timestamps = false; // Only created_at, no updated_at

    protected $fillable = [
        'auditable_type',
        'auditable_id',
        'action',
        'triggered_by',
        'user_id',
        'prompt_sent',
        'prompt_version',
        'ai_raw_response',
        'human_override_reason',
        'previous_value',
        'new_value',
        'ip_address',
        'created_at',
    ];

    protected $casts = [
        'previous_value' => 'array',
        'new_value'      => 'array',
        'created_at'     => 'datetime',
    ];

    // ─── Relationships ──────────────────────────────────────────

    public function auditable()
    {
        return $this->morphTo();
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // ─── Factory methods ────────────────────────────────────────

    /**
     * Log an AI analysis event
     */
    public static function logAnalysis(
        AiCvAnalysis $analysis,
        string $promptSent,
        string $promptVersion,
        ?string $rawResponse = null
    ): self {
        return self::create([
            'auditable_type'  => 'ai_cv_analyses',
            'auditable_id'    => $analysis->id,
            'action'          => 'created',
            'triggered_by'    => 'system',
            'prompt_sent'     => $promptSent,
            'prompt_version'  => $promptVersion,
            'ai_raw_response' => $rawResponse,
            'created_at'      => now(),
        ]);
    }

    /**
     * Log a matching calculation event
     */
    public static function logMatching(AiMatchingResult $matching): self
    {
        return self::create([
            'auditable_type' => 'ai_matching_results',
            'auditable_id'   => $matching->id,
            'action'         => 'created',
            'triggered_by'   => 'system',
            'new_value'      => [
                'score_total'      => $matching->score_total,
                'clasificacion_ia' => $matching->clasificacion_ia,
                'pesos_aplicados'  => $matching->pesos_aplicados,
            ],
            'created_at'     => now(),
        ]);
    }

    /**
     * Log a human override
     */
    public static function logOverride(
        AiMatchingResult $matching,
        int $userId,
        array $previousValue,
        array $newValue,
        string $reason,
        ?string $ipAddress = null
    ): self {
        return self::create([
            'auditable_type'       => 'ai_matching_results',
            'auditable_id'         => $matching->id,
            'action'               => 'overridden_by_human',
            'triggered_by'         => 'user',
            'user_id'              => $userId,
            'human_override_reason' => $reason,
            'previous_value'       => $previousValue,
            'new_value'            => $newValue,
            'ip_address'           => $ipAddress,
            'created_at'           => now(),
        ]);
    }
}
