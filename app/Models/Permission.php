<?php

namespace App\Models;

use Spatie\Permission\Models\Permission as SpatiePermission;

class Permission extends SpatiePermission
{
    protected $connection = 'core';

    // Mantenemos los campos adicionales si son necesarios
    protected $fillable = ['name', 'guard_name', 'system', 'description'];

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
