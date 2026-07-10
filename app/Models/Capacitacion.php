<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Capacitacion extends Model
{
    protected $connection = 'mysql';
    protected $table = 'capacitaciones';

    protected $fillable = [
        'postulante_id',
        'source_merito_id',
        'nombre_curso',
        'fecha',
        'institucion_organizadora',
        'carga_horaria',
        'training_year',
        'last_training_date',
        'is_recent_last_3_years',
        'is_recent_last_5_years',
        'temporal_weight',
        'training_recency_score',
        'temporal_processed_at',
    ];

    protected $casts = [
        'fecha'                  => 'date',
        'carga_horaria'          => 'integer',
        'training_year'          => 'integer',
        'last_training_date'     => 'date',
        'is_recent_last_3_years' => 'boolean',
        'is_recent_last_5_years' => 'boolean',
        'temporal_weight'        => 'decimal:2',
        'training_recency_score' => 'decimal:2',
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
}
