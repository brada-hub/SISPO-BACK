<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\System;

class LegajoPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Get SISPO system id directly from DB to avoid Model mismatch issues
        $sispo = DB::connection('core')->table('systems')->where('name', 'SISPO')->first();

        // Fallback if SISPO system doesn't exist (creates it safely)
        if (!$sispo) {
            $sispoId = DB::connection('core')->table('systems')->insertGetId([
                'name' => 'SISPO',
                'display_name' => 'Sistema de Postulaciones',
                'active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        } else {
            $sispoId = $sispo->id;
        }

        $guardName = 'api';
        $permName = 'ver_mi_legajo';

        // 2. Create Permission manually to avoid Model $fillable issues
        // Table 'permissions' columns: name, guard_name, system_id, description
        $perm = DB::connection('core')->table('permissions')->where('name', $permName)->where('guard_name', $guardName)->first();

        if (!$perm) {
            $permId = DB::connection('core')->table('permissions')->insertGetId([
                'name' => $permName,
                'guard_name' => $guardName,
                'system_id' => $sispoId,
                'description' => 'Permite al usuario visualizar y editar su propio legajo personal.',
                'created_at' => now(),
                'updated_at' => now()
            ]);
            $this->command->info("Permiso '{$permName}' creado directamente en BD.");
        } else {
            $permId = $perm->id;
            $this->command->info("Permiso '{$permName}' ya existía.");
        }

        // 3. Create Role manually
        // Table 'roles' columns: name, guard_name, system_id, description, activo
        $roleName = 'Administrativo';
        $role = DB::connection('core')->table('roles')->where('name', $roleName)->where('guard_name', $guardName)->first();

        if (!$role) {
            $roleId = DB::connection('core')->table('roles')->insertGetId([
                'name' => $roleName,
                'guard_name' => $guardName,
                'system_id' => $sispoId,
                'description' => 'Personal administrativo con acceso a su legajo',
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now()
            ]);
            $this->command->info("Rol '{$roleName}' creado directamente en BD.");
        } else {
            $roleId = $role->id;
            $this->command->info("Rol '{$roleName}' ya existía.");
        }

        // 4. Assign permission to role (pivot table)
        // Table 'role_has_permissions' columns: permission_id, role_id
        $exists = DB::connection('core')->table('role_has_permissions')
            ->where('permission_id', $permId)
            ->where('role_id', $roleId)
            ->exists();

        if (!$exists) {
            DB::connection('core')->table('role_has_permissions')->insert([
                'permission_id' => $permId,
                'role_id' => $roleId
            ]);
            $this->command->info('Permiso asignado al rol correctamente.');
        } else {
            $this->command->info('El rol ya tenía el permiso asignado.');
        }
    }
}
