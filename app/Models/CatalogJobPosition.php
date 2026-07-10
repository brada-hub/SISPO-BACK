<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CatalogJobPosition extends Model
{
    protected $connection = 'mysql';
    protected $table = 'catalog_job_positions';

    protected $fillable = [
        'professional_area_id',
        'canonical_name',
        'normalized_slug',
        'aliases_json',
        'seniority_level',
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

    public function experienciasProfesionales()
    {
        return $this->hasMany(ExperienciaProfesional::class, 'job_position_id');
    }
}
