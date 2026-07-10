<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EvaluationResult extends Model
{
    protected $connection = 'mysql';
    protected $table = 'evaluation_results';

    protected $fillable = [
        'postulacion_id',
        'convocatoria_id',
        'evaluation_mode',
        'score_total',
        'classification',
        'requires_ai_review',
        'requires_human_review',
        'review_risk_score',
        'review_risk_level',
        'review_flags_json',
        'review_reason_summary',
        'evaluation_status',
        'score_breakdown_json',
        'failed_requirements_json',
        'strengths_json',
        'weaknesses_json',
        'evaluated_at',
    ];

    protected $casts = [
        'score_total'              => 'decimal:2',
        'requires_ai_review'       => 'boolean',
        'requires_human_review'    => 'boolean',
        'review_risk_score'        => 'decimal:2',
        'review_flags_json'        => 'array',
        'evaluation_status'        => 'string',
        'score_breakdown_json'     => 'array',
        'failed_requirements_json' => 'array',
        'strengths_json'           => 'array',
        'weaknesses_json'          => 'array',
        'evaluated_at'             => 'datetime',
    ];

    public function postulacion()
    {
        return $this->belongsTo(Postulacion::class);
    }

    public function convocatoria()
    {
        return $this->belongsTo(Convocatoria::class);
    }
}
