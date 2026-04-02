<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Persona extends Model
{
    use SoftDeletes;

    // Conectar explícitamente a la BD del SSO (núcleo central)
    protected $connection = 'core';

    protected $table = 'personas';

    protected $fillable = [
        'nombres',
        'apellido_paterno',
        'apellido_materno',
        'ci',
        'ci_expedicion',
        'fecha_nacimiento',
        'genero',
        'correo_personal',
        'celular',
        'direccion',
        'foto',
        'cv_path',
    ];

    protected $casts = [
        'fecha_nacimiento' => 'date',
    ];
}
