<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FormacionPostgrado extends Model
{
    protected $connection = 'mysql';
    protected $table = 'formaciones_postgrado';

    protected $fillable = [
        'postulante_id',
        'source_merito_id',
        'postgraduate_type_id',
        'tipo_posgrado_raw',
        'tipo_posgrado_normalizado',
        'nombre_programa',
        'fecha_certificacion',
        'institucion',
        'normalization_confidence',
        'normalization_method',
        'normalized_at',
    ];

    protected $casts = [
        'fecha_certificacion' => 'date',
        'normalized_at' => 'datetime',
    ];

    public function postulante()
    {
        return $this->belongsTo(Postulante::class);
    }

    public function sourceMerito()
    {
        return $this->belongsTo(PostulanteMerito::class, 'source_merito_id');
    }

    public function postgraduateType()
    {
        return $this->belongsTo(CatalogPostgraduateType::class, 'postgraduate_type_id');
    }
}
