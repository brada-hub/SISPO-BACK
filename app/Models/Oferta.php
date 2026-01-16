<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Oferta extends Model
{
    protected $fillable = [
        'convocatoria_id',
        'sede_id',
        'cargo_id',
        'vacantes',
    ];

    public function convocatoria()
    {
        return $this->belongsTo(Convocatoria::class);
    }

    public function sede()
    {
        return $this->belongsTo(Sede::class);
    }

    public function cargo()
    {
        return $this->belongsTo(Cargo::class);
    }

    public function postulaciones()
    {
        return $this->hasMany(Postulacion::class);
    }
}
