<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProduccionIntelectual extends Model
{
    protected $connection = 'mysql';
    protected $table = 'producciones_intelectuales';

    protected $fillable = [
        'postulante_id',
        'source_merito_id',
        'tipo_produccion_raw',
        'tipo_produccion_normalizado',
        'titulo',
        'fecha_publicacion',
        'editorial_revista',
        'lugar',
    ];

    protected $casts = [
        'fecha_publicacion' => 'date',
    ];

    public function postulante()
    {
        return $this->belongsTo(Postulante::class);
    }

    public function sourceMerito()
    {
        return $this->belongsTo(PostulanteMerito::class, 'source_merito_id');
    }
}
