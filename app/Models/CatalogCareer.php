<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CatalogCareer extends Model
{
    protected $connection = 'mysql';
    protected $table = 'catalog_careers';

    protected $fillable = [
        'professional_area_id',
        'canonical_name',
        'normalized_slug',
        'aliases_json',
        'is_active',
    ];

    protected $casts = [
        'aliases_json' => 'array',
        'is_active' => 'boolean',
    ];

    public function professionalArea()
    {
        return $this->belongsTo(CatalogProfessionalArea::class, 'professional_area_id');
    }

    public function formacionesAcademicas()
    {
        return $this->hasMany(FormacionAcademica::class, 'career_id');
    }

    public function experienciasDocencia()
    {
        return $this->hasMany(ExperienciaDocencia::class, 'career_id');
    }
}
