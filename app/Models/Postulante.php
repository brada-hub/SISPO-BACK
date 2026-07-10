<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Postulante extends Model
{
    protected $connection = 'mysql';

    protected $fillable = [
        'user_id',
        'clasificacion',
        'sede_id',
        'ci',
        'ci_expedido',
        'ci_archivo_path',
        'nombres',
        'apellidos',
        'nacionalidad',
        'direccion_domicilio',
        'email',
        'email_institucional',
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

    public function sede()
    {
        return $this->belongsTo(Sede::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function meritos()
    {
        return $this->hasMany(PostulanteMerito::class);
    }

    public function postulaciones()
    {
        return $this->hasMany(Postulacion::class);
    }

    public function formacionesAcademicas()
    {
        return $this->hasMany(FormacionAcademica::class);
    }

    public function formacionesPostgrado()
    {
        return $this->hasMany(FormacionPostgrado::class);
    }

    public function experienciasDocencia()
    {
        return $this->hasMany(ExperienciaDocencia::class);
    }

    public function experienciasProfesionales()
    {
        return $this->hasMany(ExperienciaProfesional::class);
    }

    public function capacitaciones()
    {
        return $this->hasMany(Capacitacion::class);
    }

    public function produccionesIntelectuales()
    {
        return $this->hasMany(ProduccionIntelectual::class);
    }

    public function reconocimientos()
    {
        return $this->hasMany(Reconocimiento::class);
    }

    public function experienceSummary()
    {
        return $this->hasOne(PostulanteExperienceSummary::class);
    }

    public function trainingSummary()
    {
        return $this->hasOne(PostulanteTrainingSummary::class);
    }
}
