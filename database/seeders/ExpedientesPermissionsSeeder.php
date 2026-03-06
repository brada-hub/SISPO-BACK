<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ExpedientesPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Get SISPO ID
        $sispo = DB::connection('core')->table('applications')->where('key', 'sispo')->first();
        $sispoId = $sispo ? $sispo->id : 1;

        // 2. Try simple insert for 'ver_todo_personal'
        // If it exists (due to unique constraint), it will fail but we catch it or use updateOrInsert logic if needed
        // Simpler approach: Check carefully
        $existing = DB::connection('core')->table('permissions')
            ->where('name', 'ver_todo_personal')
            ->where('application_id', $sispoId)
            ->first();

        if (!$existing) {
            $permId = DB::connection('core')->table('permissions')->insertGetId([
                'name' => 'ver_todo_personal',
                'guard_name' => 'api',
                'application_id' => $sispoId,
                'created_at' => now(),
                'updated_at' => now()
            ]);
            $this->command->info("Permiso creado con ID: $permId");
        } else {
            $permId = $existing->id;
            $this->command->info("Permiso ya existía con ID: $permId");
        }

        // 3. Assign to ROLES
        $roles = DB::connection('core')->table('roles')
            ->whereIn('nombre', ['Admin', 'Administrador', 'SuperAdmin'])
            ->get();

        foreach ($roles as $role) {
            // Check link
            $linked = DB::connection('core')->table('role_has_permissions')
                ->where('role_id', $role->id)
                ->where('permission_id', $permId)
                ->exists();

            if (!$linked) {
                DB::connection('core')->table('role_has_permissions')->insert([
                    'permission_id' => $permId,
                    'role_id' => $role->id
                ]);
                $this->command->info("Asignado a rol: {$role->nombre}");
            } else {
                $this->command->info("Rol {$role->nombre} ya tenía el permiso.");
            }
        }
    }
}
