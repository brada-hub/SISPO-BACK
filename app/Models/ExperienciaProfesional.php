<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExperienciaProfesional extends Model
{
    protected $connection = 'mysql';
    protected $table = 'experiencias_profesionales';

    protected $fillable = [
        'postulante_id',
        'source_merito_id',
        'job_position_id',
        'is_current',
        'last_experience_date',
        'months_in_last_3_years',
        'months_in_last_5_years',
        'months_total',
        'cargo_raw',
        'cargo_normalizado_id',
        'empresa',
        'fecha_inicio',
        'fecha_fin',
        'duracion_meses',
        'recency_score',
        'normalization_confidence',
        'normalization_method',
        'normalized_at',
        'temporal_processed_at',
    ];

    protected $casts = [
        'fecha_inicio'          => 'date',
        'fecha_fin'             => 'date',
        'last_experience_date'  => 'date',
        'duracion_meses'        => 'integer',
        'months_in_last_3_years' => 'integer',
        'months_in_last_5_years' => 'integer',
        'months_total'          => 'integer',
        'is_current'            => 'boolean',
        'recency_score'         => 'decimal:2',
        'normalized_at'         => 'datetime',
        'temporal_processed_at' => 'datetime',
    ];

    public function postulante()
    {
        return $this->belongsTo(Postulante::class);
    }

    public function sourceMerito()
    {
        return $this->belongsTo(PostulanteMerito::class, 'source_merito_id');
    }

    public function jobPosition()
    {
        return $this->belongsTo(CatalogJobPosition::class, 'job_position_id');
    }
}
