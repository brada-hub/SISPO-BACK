<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoDocumento extends Model
{
    protected $table = 'tipos_documento';

    protected $fillable = [
        'nombre',
        'descripcion',
        'categoria',
        'campos',
        'config_archivos',
        'permite_multiples',
        'orden'
    ];

    protected $casts = [
        'campos' => 'array',
        'config_archivos' => 'array',
        'permite_multiples' => 'boolean',
    ];
}
