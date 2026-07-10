<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PostulanteExperienceSummary extends Model
{
    protected $connection = 'mysql';
    protected $table = 'postulante_experience_summaries';

    protected $fillable = [
        'postulante_id',
        'total_accumulated_months',
        'total_unique_months',
        'accumulated_months_last_3_years',
        'unique_months_last_3_years',
        'accumulated_months_last_5_years',
        'unique_months_last_5_years',
        'max_recency_score',
        'current_experience_count',
        'experience_records_count',
        'overlap_detected',
        'overlap_months_estimated',
        'processed_at',
    ];

    protected $casts = [
        'total_accumulated_months'        => 'integer',
        'total_unique_months'             => 'integer',
        'accumulated_months_last_3_years' => 'integer',
        'unique_months_last_3_years'      => 'integer',
        'accumulated_months_last_5_years' => 'integer',
        'unique_months_last_5_years'      => 'integer',
        'max_recency_score'               => 'decimal:2',
        'current_experience_count'        => 'integer',
        'experience_records_count'        => 'integer',
        'overlap_detected'                => 'boolean',
        'overlap_months_estimated'        => 'integer',
        'processed_at'                    => 'datetime',
    ];

    public function postulante()
    {
        return $this->belongsTo(Postulante::class);
    }
}
