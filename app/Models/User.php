<?php

namespace App\Models;


use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;
use App\Models\System;
use App\Models\Sede;
use App\Models\Rol;

use App\Traits\HasSharedPermissions;

class User extends Authenticatable implements JWTSubject
{
    use Notifiable, HasSharedPermissions;

    protected $connection = 'core';
    protected $table = 'users';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'rol_id',
        'sede_id',
        'nombres',
        'apellidos',
        'apellido_paterno',
        'apellido_materno',
        'ci',
        'email',
        'password',
        'google_id',
        'activo',
        'must_change_password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'must_change_password' => 'boolean',
        ];
    }

    /**
     * The attributes that should be appended to arrays.
     */
    protected $appends = ['permisos', 'systems'];

    /**
     * Get merged permissions (from role + individual)
     */
    public function getPermisosAttribute(): array
    {
        return $this->getAllPermissions()->pluck('name')->toArray();
    }

    public function rol()
    {
        return $this->belongsTo(Rol::class, 'rol_id');
    }

    public function sede()
    {
        return $this->belongsTo(Sede::class);
    }

    /**
     * Systems link (pivot)
     */
    public function userSystems()
    {
        return $this->belongsToMany(System::class, 'user_systems', 'user_id', 'system_id')
                    ->withPivot('role_id', 'activo')
                    ->withTimestamps();
    }

    /**
     * Dynamic systems attribute (filtered by permissions)
     */
    public function getSystemsAttribute()
    {
        $systemIds = $this->getAllPermissions()->pluck('system_id')->unique()->filter();
        return System::whereIn('id', $systemIds)->get();
    }

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     *
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [
            'rol_id' => $this->rol_id,
            'ci' => $this->ci,
            'sede_id' => $this->sede_id,
        ];
    }
}
