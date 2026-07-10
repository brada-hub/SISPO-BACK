<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reconocimiento extends Model
{
    protected $connection = 'mysql';
    protected $table = 'reconocimientos';

    protected $fillable = [
        'postulante_id',
        'source_merito_id',
        'titulo_reconocimiento',
        'fecha',
        'institucion_otorgante',
        'lugar',
    ];

    protected $casts = [
        'fecha' => 'date',
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
