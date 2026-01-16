<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MeritoArchivo extends Model
{
    protected $table = 'merito_archivos';

    protected $fillable = [
        'merito_id',
        'config_archivo_id',
        'archivo_path',
    ];

    public function merito()
    {
        return $this->belongsTo(PostulanteMerito::class, 'merito_id');
    }
}
