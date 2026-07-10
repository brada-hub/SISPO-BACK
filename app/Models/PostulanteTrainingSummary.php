<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PostulanteTrainingSummary extends Model
{
    protected $connection = 'mysql';
    protected $table = 'postulante_training_summaries';

    protected $fillable = [
        'postulante_id',
        'total_training_records',
        'total_training_hours',
        'training_hours_last_3_years',
        'training_hours_last_5_years',
        'last_training_date',
        'max_training_recency_score',
        'average_training_recency_score',
        'processed_at',
    ];

    protected $casts = [
        'total_training_records'         => 'integer',
        'total_training_hours'           => 'integer',
        'training_hours_last_3_years'    => 'integer',
        'training_hours_last_5_years'    => 'integer',
        'last_training_date'             => 'date',
        'max_training_recency_score'     => 'decimal:2',
        'average_training_recency_score' => 'decimal:2',
        'processed_at'                   => 'datetime',
    ];

    public function postulante()
    {
        return $this->belongsTo(Postulante::class);
    }
}
