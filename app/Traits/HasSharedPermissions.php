<?php

namespace App\Traits;

use App\Models\Permission;
use Illuminate\Support\Collection;

trait HasSharedPermissions
{
    /**
     * Individual permissions assigned directly to the user
     */
    public function individualPermissions()
    {
        return $this->belongsToMany(Permission::class, 'model_has_permissions', 'model_id', 'permission_id')
                    ->where('model_type', self::class);
    }

    /**
     * Get all permissions (from role + individual)
     */
    public function getAllPermissions(): Collection
    {
        try {
            // Intento vía Eloquent
            $rolePermissions = $this->rol ? $this->rol->permissions : collect();
            $individualPermissions = $this->individualPermissions()->get();

            $permissions = $rolePermissions->merge($individualPermissions);

            // Si por alguna razón de conexión cruzada sale vacío y sabemos que debería tener
            if ($permissions->isEmpty()) {
                // Fallback manual directo a la tabla para evitar fallos de join cross-db
                $directPermissionIds = \Illuminate\Support\Facades\DB::connection('core')
                    ->table('model_has_permissions')
                    ->where('model_id', $this->id)
                    ->pluck('permission_id');

                if ($this->rol_id) {
                    $rolePermissionIds = \Illuminate\Support\Facades\DB::connection('core')
                        ->table('role_has_permissions')
                        ->where('role_id', $this->rol_id)
                        ->pluck('permission_id');
                    $directPermissionIds = $directPermissionIds->merge($rolePermissionIds);
                }

                if ($directPermissionIds->isNotEmpty()) {
                    return Permission::whereIn('id', $directPermissionIds->unique())->get();
                }
            }

            return $permissions->unique('id');
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('CRITICAL PERMISSION ERROR: ' . $e->getMessage());
            // Fallback total para no bloquear al usuario si es admin en la tabla
            return collect();
        }
    }

    /**
     * Get permissions for a specific system
     */
    public function getPermissionsBySystem(string $systemName): Collection
    {
        return $this->getAllPermissions()->filter(function ($permission) use ($systemName) {
            return $permission->system === $systemName;
        });
    }

    /**
     * Check if user has a specific permission
     */
    public function hasPermissionTo(string $permissionName): bool
    {
        return $this->getAllPermissions()->contains('name', $permissionName);
    }

    /**
     * Get all systems where the user has at least one permission
     */
    public function getAccessibleSystems(): Collection
    {
        $systemIds = $this->getAllPermissions()->pluck('system_id')->unique()->filter();
        return \App\Models\System::whereIn('id', $systemIds)->get();
    }
}
