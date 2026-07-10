<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FormacionAcademica extends Model
{
    protected $connection = 'mysql';
    protected $table = 'formaciones_academicas';

    protected $fillable = [
        'postulante_id',
        'source_merito_id',
        'academic_level_id',
        'career_id',
        'professional_area_id',
        'nivel_academico_raw',
        'nivel_academico_normalizado',
        'universidad',
        'carrera_raw',
        'carrera_normalizada_id',
        'fecha_diploma',
        'fecha_titulo',
        'normalization_confidence',
        'normalization_method',
        'area_confidence',
        'area_normalization_method',
        'normalized_at',
    ];

    protected $casts = [
        'fecha_diploma'   => 'date',
        'fecha_titulo'    => 'date',
        'area_confidence' => 'decimal:2',
        'normalized_at'   => 'datetime',
    ];

    public function postulante()
    {
        return $this->belongsTo(Postulante::class);
    }

    public function sourceMerito()
    {
        return $this->belongsTo(PostulanteMerito::class, 'source_merito_id');
    }

    public function academicLevel()
    {
        return $this->belongsTo(CatalogAcademicLevel::class, 'academic_level_id');
    }

    public function career()
    {
        return $this->belongsTo(CatalogCareer::class, 'career_id');
    }

    public function professionalArea()
    {
        return $this->belongsTo(CatalogProfessionalArea::class, 'professional_area_id');
    }
}
