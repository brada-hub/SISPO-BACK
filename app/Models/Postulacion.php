<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Postulacion extends Model
{
    protected $table = 'postulaciones';

    protected $fillable = [
        'postulante_id',
        'oferta_id',
        'pretension_salarial',
        'porque_cargo',
        'estado',
        'fecha_postulacion',
    ];

    protected $casts = [
        'fecha_postulacion' => 'datetime',
    ];

    public function postulante()
    {
        return $this->belongsTo(Postulante::class);
    }

    public function oferta()
    {
        return $this->belongsTo(Oferta::class);
    }

    public function evaluacion()
    {
        return $this->hasOne(EvaluacionPostulacion::class, 'postulacion_id');
    }
}
