<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConvocatoriaBaremo extends Model
{
    protected $table = 'convocatoria_baremos';

    protected $fillable = [
        'convocatoria_id',
        'tipo_documento_id',
        'regla_puntos_json',
        'puntaje_por_item',
        'puntaje_maximo_seccion',
    ];

    protected $casts = [
        'regla_puntos_json' => 'array',
        'puntaje_por_item' => 'decimal:2',
        'puntaje_maximo_seccion' => 'decimal:2',
    ];

    public function convocatoria()
    {
        return $this->belongsTo(Convocatoria::class);
    }

    public function tipoDocumento()
    {
        return $this->belongsTo(TipoDocumento::class);
    }
}
