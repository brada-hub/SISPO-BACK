<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CatalogPostgraduateType extends Model
{
    protected $connection = 'mysql';
    protected $table = 'catalog_postgraduate_types';

    protected $fillable = [
        'code',
        'name',
        'aliases_json',
    ];

    protected $casts = [
        'aliases_json' => 'array',
    ];

    public function formacionesPostgrado()
    {
        return $this->hasMany(FormacionPostgrado::class, 'postgraduate_type_id');
    }
}
