<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Postulante extends Model
{
    protected $fillable = [
        'ci',
        'ci_expedido',
        'ci_archivo_path',
        'nombres',
        'apellidos',
        'nacionalidad',
        'direccion_domicilio',
        'email',
        'celular',
        'foto_perfil_path',
        'cv_pdf_path',
        'carta_postulacion_path',
        'ref_personal_celular',
        'ref_personal_parentesco',
        'ref_laboral_celular',
        'ref_laboral_detalle',
        'pretension_salarial',
        'porque_cargo',
    ];

    public function meritos()
    {
        return $this->hasMany(PostulanteMerito::class);
    }

    public function postulaciones()
    {
        return $this->hasMany(Postulacion::class);
    }
}
