<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Rol extends Model
{
    protected $connection = 'core';
    protected $table = 'roles';
    protected $primaryKey = 'id_rol';

    protected $fillable = [
        'nombres',
        'sistema_id',
    ];

    // Compatibility accessors
    protected $appends = ['name', 'nombre'];
    public function getNameAttribute() { return $this->nombres; }
    public function getNombreAttribute() { return $this->nombres; }

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_has_roles', 'role_id', 'user_id');
    }

    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'role_has_permissions', 'role_id', 'permission_id');
    }
}
