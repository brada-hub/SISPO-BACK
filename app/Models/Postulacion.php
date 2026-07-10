<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Postulacion extends Model
{
    protected $connection = 'mysql';
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

    public function aiCvAnalyses()
    {
        return $this->hasMany(AiCvAnalysis::class, 'postulacion_id');
    }

    public function aiMatchingResult()
    {
        return $this->hasOne(AiMatchingResult::class, 'postulacion_id')->latest();
    }

    public function evaluationResult()
    {
        return $this->hasOne(EvaluationResult::class, 'postulacion_id');
    }
}
