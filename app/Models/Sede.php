<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sede extends Model
{
    protected $connection = 'core';
    protected $table = 'sedes'; // Explicit table name
    protected $fillable = ['nombre', 'departamento', 'sigla', 'abreviacion', 'direccion', 'activo']; // Added core fields

    public function ofertas()
    {
        return $this->hasMany(Oferta::class);
    }

    public function postulaciones()
    {
        return $this->hasManyThrough(Postulacion::class, Oferta::class);
    }
}
