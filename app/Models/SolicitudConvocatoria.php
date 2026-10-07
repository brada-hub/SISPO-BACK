<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SolicitudConvocatoria extends Model
{
    protected $connection = 'mysql';
    protected $table = 'solicitudes_convocatorias';

    protected $fillable = [
        'codigo_solicitud',
        'solicitante_nombre',
        'solicitante_cargo',
        'solicitante_carrera',
        'solicitante_email',
        'solicitante_telefono',
        'tipo_perfil',
        'titulo_sugerido',
        'descripcion_motivo',
        'sedes_ids',
        'cargos_ids',
        'requisito_formacion',
        'requisito_posgrado',
        'requisito_experiencia_profesional',
        'requisito_experiencia_docente',
        'otros_requisitos',
        'archivo_adjunto_path',
        'estado',
        'observaciones_rrhh',
        'convocatoria_creada_id',
        'aprobado_por_user_id',
        'aprobado_at',
    ];

    protected $casts = [
        'sedes_ids' => 'array',
        'cargos_ids' => 'array',
        'aprobado_at' => 'datetime',
    ];

    public function convocatoria()
    {
        return $this->belongsTo(Convocatoria::class, 'convocatoria_creada_id');
    }

    public function aprobadoPor()
    {
        return $this->belongsTo(User::class, 'aprobado_por_user_id');
    }
}
