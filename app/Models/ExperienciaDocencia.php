<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExperienciaDocencia extends Model
{
    protected $connection = 'mysql';
    protected $table = 'experiencias_docencia';

    protected $fillable = [
        'postulante_id',
        'source_merito_id',
        'career_id',
        'teaching_year_start',
        'teaching_year_end',
        'years_in_last_5_years',
        'is_recent_last_5_years',
        'last_teaching_year',
        'universidad',
        'carrera_raw',
        'carrera_normalizada_id',
        'asignaturas',
        'gestion_periodo',
        'recency_score',
        'normalization_confidence',
        'normalization_method',
        'normalized_at',
        'temporal_processed_at',
    ];

    protected $casts = [
        'teaching_year_start'    => 'integer',
        'teaching_year_end'      => 'integer',
        'years_in_last_5_years'  => 'integer',
        'is_recent_last_5_years' => 'boolean',
        'last_teaching_year'     => 'integer',
        'recency_score'          => 'decimal:2',
        'normalized_at'          => 'datetime',
        'temporal_processed_at'  => 'datetime',
    ];

    public function postulante()
    {
        return $this->belongsTo(Postulante::class);
    }

    public function sourceMerito()
    {
        return $this->belongsTo(PostulanteMerito::class, 'source_merito_id');
    }

    public function career()
    {
        return $this->belongsTo(CatalogCareer::class, 'career_id');
    }
}
