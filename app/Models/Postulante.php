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
        'fecha_nacimiento',
        'genero',
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
