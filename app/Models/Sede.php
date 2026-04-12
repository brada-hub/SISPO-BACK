<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sede extends Model
{
    protected $connection = 'core';
    protected $table = 'sedes';
    protected $primaryKey = 'id_sede';

    protected $fillable = ['nombre', 'departamento', 'sigla', 'activo'];

    protected $appends = ['id'];

    public function getIdAttribute()
    {
        return $this->id_sede;
    }

    public function ofertas()
    {
        return $this->hasMany(Oferta::class);
    }

    public function postulaciones()
    {
        return $this->hasManyThrough(Postulacion::class, Oferta::class);
    }
}
