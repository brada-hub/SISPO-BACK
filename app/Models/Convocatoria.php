<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Convocatoria extends Model
{
    protected $fillable = [
        'titulo',
        'codigo_interno',
        'descripcion',
        'fecha_inicio',
        'fecha_cierre',
        'hora_limite',
        'config_requisitos_ids',
        'requisitos_afiche',
    ];

    protected $casts = [
        'config_requisitos_ids' => 'array',
        'requisitos_afiche' => 'array',
        'fecha_inicio' => 'date',
        'fecha_cierre' => 'date',
    ];

    public function ofertas()
    {
        return $this->hasMany(Oferta::class);
    }

    public function baremos()
    {
        return $this->hasMany(ConvocatoriaBaremo::class);
    }

    public function documentos_requeridos()
    {
        return $this->belongsToMany(TipoDocumento::class, 'convocatoria_tipo_documento')
                    ->withPivot(['obligatorio', 'orden'])
                    ->orderByPivot('orden');
    }

    public function postulaciones()
    {
        return $this->hasManyThrough(Postulacion::class, Oferta::class);
    }
}
