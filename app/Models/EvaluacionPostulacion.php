<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EvaluacionPostulacion extends Model
{
    protected $table = 'evaluaciones_postulaciones';

    protected $fillable = [
        'postulacion_id',
        'evaluador_id',
        'puntaje_formacion',
        'puntaje_perfeccionamiento',
        'puntaje_experiencia',
        'puntaje_otros',
        'puntaje_total',
        'detalle_evaluacion',
        'observaciones',
    ];

    protected $casts = [
        'detalle_evaluacion' => 'array',
        'puntaje_formacion' => 'decimal:2',
        'puntaje_perfeccionamiento' => 'decimal:2',
        'puntaje_experiencia' => 'decimal:2',
        'puntaje_otros' => 'decimal:2',
        'puntaje_total' => 'decimal:2',
    ];

    public function postulacion()
    {
        return $this->belongsTo(Postulacion::class);
    }

    public function evaluador()
    {
        return $this->belongsTo(User::class, 'evaluador_id');
    }
}
