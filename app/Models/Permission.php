<?php

namespace App\Models;

use Spatie\Permission\Models\Permission as SpatiePermission;

class Permission extends SpatiePermission
{
    protected $connection = 'core';

    // Mantenemos los campos adicionales si son necesarios
    protected $fillable = ['name', 'guard_name', 'application_id', 'description'];

    /**
     * Accessor: 'system' devuelve el nombre de la aplicación asociada.
     * Esto permite que User->getSystemsAttribute() funcione correctamente.
     */
    public function getSystemAttribute()
    {
        if ($this->application_id) {
            // Buscar en la tabla applications de core
            $app = \Illuminate\Support\Facades\DB::connection('core')
                ->table('applications')
                ->where('id', $this->application_id)
                ->first();
            return $app ? $app->nombre : null;
        }
        return null;
    }

    // Relación con la aplicación/sistema
    public function application()
    {
        return $this->belongsTo(\Illuminate\Database\Eloquent\Model::class, 'application_id');
    }

    // Spatie ya maneja la relación roles, pero si usas el modelo Rol personalizado:
    public function roles(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(
            Rol::class,
            config('permission.table_names.role_has_permissions'),
            config('permission.column_names.permission_pivot_key') ?: 'permission_id',
            config('permission.column_names.role_pivot_key') ?: 'role_id'
        );
    }
}
