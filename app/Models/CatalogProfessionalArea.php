<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CatalogProfessionalArea extends Model
{
    protected $connection = 'mysql';
    protected $table = 'catalog_professional_areas';

    protected $fillable = [
        'code',
        'name',
        'description',
    ];

    public function careers()
    {
        return $this->hasMany(CatalogCareer::class, 'professional_area_id');
    }

    public function jobPositions()
    {
        return $this->hasMany(CatalogJobPosition::class, 'professional_area_id');
    }
}
