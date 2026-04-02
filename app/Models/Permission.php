<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    protected $connection = 'core';
    protected $table = 'permissions';
    protected $primaryKey = 'id_permision';

    protected $fillable = [
        'nombres',
        'sistema_id',
    ];

    // Compatibility accessors for code that uses 'name'
    protected $appends = ['name'];

    public function getNameAttribute()
    {
        return $this->nombres;
    }

    /**
     * Get permission system name
     */
    public function getSystemAttribute()
    {
        return $this->sistema?->sistema;
    }

    public function roles()
    {
        return $this->belongsToMany(Rol::class, 'role_has_permissions', 'permission_id', 'role_id');
    }

    public function sistema()
    {
        return $this->belongsTo(System::class, 'sistema_id', 'id_sistema');
    }
}
