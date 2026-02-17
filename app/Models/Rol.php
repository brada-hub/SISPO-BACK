<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Rol extends Model
{
    protected $connection = 'core';
    protected $table = 'roles';

    protected $fillable = ['nombre', 'descripcion', 'guard_name', 'system_id', 'activo'];

    protected $casts = [
        'activo' => 'boolean',
    ];

    // Eliminamos el append y el accessor conflictivo para usar la columna real 'name'
    // protected $appends = ['name'];

    // public function getNameAttribute()
    // {
    //     return $this->nombre;
    // }

    public function users()
    {
        return $this->hasMany(User::class, 'rol_id');
    }

    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'role_has_permissions', 'role_id', 'permission_id');
    }
}
