<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CatalogAcademicLevel extends Model
{
    protected $connection = 'mysql';
    protected $table = 'catalog_academic_levels';

    protected $fillable = [
        'code',
        'name',
        'hierarchy_order',
        'aliases_json',
    ];

    protected $casts = [
        'aliases_json' => 'array',
    ];

    public function formacionesAcademicas()
    {
        return $this->hasMany(FormacionAcademica::class, 'academic_level_id');
    }
}
