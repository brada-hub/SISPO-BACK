<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PostulanteMerito extends Model
{
    protected $table = 'postulante_meritos';

    protected $fillable = [
        'postulante_id',
        'tipo_documento_id',
        'respuestas',
        'puntuacion_obtenida',
        'estado_verificacion',
    ];

    protected $casts = [
        'respuestas' => 'array',
        'puntuacion_obtenida' => 'decimal:2',
    ];

    public function postulante()
    {
        return $this->belongsTo(Postulante::class);
    }

    public function tipoDocumento()
    {
        return $this->belongsTo(TipoDocumento::class);
    }

    public function archivos()
    {
        return $this->hasMany(MeritoArchivo::class, 'merito_id');
    }
}
