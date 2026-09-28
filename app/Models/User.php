<?php

namespace App\Models;


use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\HasApiTokens;
use Tymon\JWTAuth\Contracts\JWTSubject;
use App\Models\System;
use App\Models\Sede;
use App\Models\Rol;

use App\Traits\HasSharedPermissions;

class User extends Authenticatable implements JWTSubject
{
    use HasApiTokens, Notifiable, HasSharedPermissions;
    
    public function getMorphClass()
    {
        return 'user';
    }

    public function getKeyName()
    {
        static $primaryKey;

        if ($primaryKey) {
            return $primaryKey;
        }

        $primaryKey = Schema::connection($this->getConnectionName())->hasColumn($this->getTable(), 'id_user')
            ? 'id_user'
            : 'id';

        return $primaryKey;
    }

    public function getIdUserAttribute()
    {
        return $this->attributes['id_user'] ?? $this->getAttributeFromArray($this->getKeyName());
    }

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
        'apellido_paterno',
        'apellido_materno',
        'ci',
        'email',
        'password',
        'google_id',
        'activo',
        'must_change_password',
        'jurisdiccion',
        'convocatoria_scope',
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
            'jurisdiccion' => 'array',
            'convocatoria_scope' => 'array',
        ];
    }

    /**
     * The attributes that should be appended to arrays.
     */
    protected $appends = ['nombre_completo', 'permisos'];

    protected $with = [];

    public function getNombreCompletoAttribute(): string
    {
        return trim("{$this->nombres} {$this->apellido_paterno} {$this->apellido_materno}");
    }

    /**
     * Get merged permissions (from role + individual)
     */
    public function getPermisosAttribute(): array
    {
        return $this->getAllPermissions()->pluck('nombres')->values()->toArray();
    }

    /**
     * Relación con Roles (Many-to-Many como en SIGETH)
     */
    public function roles()
    {
        return $this->belongsToMany(Rol::class, 'user_has_roles', 'user_id', 'role_id');
    }

    /**
     * Get single role for backward compatibility
     */
    public function getRolAttribute()
    {
        try {
            $sispoRole = $this->roles->firstWhere('sistema_id', 2);
            if ($sispoRole) {
                return $sispoRole;
            }

            // Fallback ONLY if user is a Global Super Admin from SIGETH (sistema_id: 1)
            $globalAdmin = $this->roles->first(function ($r) {
                return (int)($r->sistema_id ?? 0) === 1 && in_array(strtoupper(trim($r->nombres ?? '')), ['ADMINISTRADOR', 'ADMIN', 'SUPER ADMIN', 'SUPERADMIN', 'DIRECTOR (ENCARGADO)']);
            });

            return $globalAdmin ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function sede()
    {
        return $this->belongsTo(Sede::class, 'id_sede_scope', 'id_sede');
    }

    public function getSedeIdAttribute()
    {
        return $this->attributes['id_sede_scope'] ?? null;
    }

    public function setSedeIdAttribute($value)
    {
        $this->attributes['id_sede_scope'] = $value;
    }

    /**
     * Relación con Persona (Shared in core)
     */
    public function persona()
    {
        return $this->belongsTo(Persona::class, 'id_persona', 'id');
    }

    public function individualPermissions()
    {
        return $this->belongsToMany(Permission::class, 'user_has_permissions', 'user_id', 'permission_id');
    }

    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'user_has_permissions', 'user_id', 'permission_id');
    }

    public function postulante()
    {
        return $this->hasOne(Postulante::class);
    }

    public function isAdminUser(): bool
    {
        foreach ($this->roles as $role) {
            $rName = strtoupper(trim($role->nombres ?? ''));
            $sysId = (int)($role->sistema_id ?? 0);
            if ($sysId === 2 && in_array($rName, ['ADMINISTRADOR', 'ADMIN'])) {
                return true;
            }
            if ($sysId === 1 && in_array($rName, ['ADMINISTRADOR', 'ADMIN', 'SUPER ADMIN', 'SUPERADMIN', 'DIRECTOR (ENCARGADO)'])) {
                return true;
            }
        }
        return false;
    }

    public function hasSystemAccess(int $systemId = 2): bool
    {
        if ($this->isAdminUser()) {
            return true;
        }
        return $this->roles->contains(fn($r) => (int)($r->sistema_id ?? 0) === $systemId)
            || $this->permissions->contains(fn($p) => (int)($p->sistema_id ?? 0) === $systemId);
    }

    public function allowedSedeIds(): array
    {
        $jurisdiccion = $this->jurisdiccion ?? [];

        if (!empty($jurisdiccion)) {
            return array_values(array_unique(array_map('intval', $jurisdiccion)));
        }

        return $this->sede_id ? [(int) $this->sede_id] : [];
    }

    public function allowedConvocatoriaIds(): array
    {
        $convocatorias = $this->convocatoria_scope ?? [];

        return array_values(array_unique(array_map('intval', $convocatorias)));
    }

    public function hasConvocatoriaScope(): bool
    {
        return count($this->allowedConvocatoriaIds()) > 0;
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
            'id_rol' => $this->roles->first()?->id_rol,
            'ci' => $this->ci,
            'sede_id' => $this->sede_id,
        ];
    }
}


