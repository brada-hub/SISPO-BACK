<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sede extends Model
{
    protected $connection = 'core';
    protected $table = 'sedes';

    protected $fillable = ['nombre', 'departamento', 'sigla', 'activo'];

    public function ofertas()
    {
        return $this->hasMany(Oferta::class);
    }

    public function postulaciones()
    {
        return $this->hasManyThrough(Postulacion::class, Oferta::class);
    }
}
