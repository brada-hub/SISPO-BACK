<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UpdateAdminPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Encontrar el permiso 'ver_mi_legajo'
        $perm = DB::connection('core')->table('permissions')->where('name', 'ver_mi_legajo')->first();

        if (!$perm) {
            $this->command->error("El permiso ver_mi_legajo no existe. Ejecuta primero LegajoPermissionsSeeder.");
            return;
        }

        // 2. Encontrar el rol 'Admin' o 'Administrador' (nombre exacto en tu BD)
        // Probamos con ambos por seguridad
        $roles = DB::connection('core')->table('roles')
            ->whereIn('name', ['Admin', 'Administrador', 'SuperAdmin'])
            ->get();

        foreach ($roles as $role) {
            // Verificar si ya lo tiene
            $exists = DB::connection('core')->table('role_has_permissions')
                ->where('role_id', $role->id)
                ->where('permission_id', $perm->id)
                ->exists();

            if (!$exists) {
                DB::connection('core')->table('role_has_permissions')->insert([
                    'role_id' => $role->id,
                    'permission_id' => $perm->id
                ]);
                $this->command->info("Permiso asignado al rol: {$role->name}");
            } else {
                $this->command->info("El rol {$role->name} ya tenía el permiso.");
            }
        }
    }
}
