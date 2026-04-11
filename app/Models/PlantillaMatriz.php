<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlantillaMatriz extends Model
{
    protected $table = 'plantilla_matrizs';
    
    protected $fillable = [
        'nombre',
        'descripcion',
        'matriz'
    ];

    protected $casts = [
        'matriz' => 'array'
    ];
}
