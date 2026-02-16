<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Permission;
use App\Models\System;
use App\Models\Rol;
use Illuminate\Support\Facades\DB;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Obtener Sistemas
        $sispo = System::firstOrCreate(['name' => 'SISPO'], ['display_name' => 'Sistema de Postulaciones', 'active' => true]);
        $sigva = System::firstOrCreate(['name' => 'SIGVA'], ['display_name' => 'Sistema de Vacaciones', 'active' => true]);

        // 2. Definir Permisos por Sistema
        $permissions = [
            // --- SISPO ---
            [
                'name' => 'dashboard',
                'description' => 'Ver Dashboard Principal',
                'system_id' => $sispo->id,
                'guard_name' => 'api'
            ],
            [
                'name' => 'convocatorias',
                'description' => 'Gestionar Convocatorias',
                'system_id' => $sispo->id,
                'guard_name' => 'api'
            ],
            [
                'name' => 'postulaciones',
                'description' => 'Ver y Gestionar Postulaciones',
                'system_id' => $sispo->id,
                'guard_name' => 'api'
            ],
            [
                'name' => 'evaluaciones',
                'description' => 'Evaluar Postulantes',
                'system_id' => $sispo->id,
                'guard_name' => 'api'
            ],
            [
                'name' => 'sedes',
                'description' => 'Gestionar Sedes',
                'system_id' => $sispo->id,
                'guard_name' => 'api'
            ],
            [
                'name' => 'cargos',
                'description' => 'Gestionar Cargos',
                'system_id' => $sispo->id,
                'guard_name' => 'api'
            ],
            [
                'name' => 'requisitos',
                'description' => 'Gestionar Tipos de Documento',
                'system_id' => $sispo->id,
                'guard_name' => 'api'
            ],
            [
                'name' => 'usuarios',
                'description' => 'Gestionar Usuarios',
                'system_id' => $sispo->id,
                'guard_name' => 'api'
            ],
            [
                'name' => 'roles',
                'description' => 'Gestionar Roles y Permisos',
                'system_id' => $sispo->id,
                'guard_name' => 'api'
            ],

            // --- SIGVA ---
            [
                'name' => 'vacaciones_dashboard',
                'description' => 'Ver Dashboard de Vacaciones',
                'system_id' => $sigva->id,
                'guard_name' => 'api'
            ],
            [
                'name' => 'solicitudes',
                'description' => 'Gestionar Solicitudes de Vacación',
                'system_id' => $sigva->id,
                'guard_name' => 'api'
            ],
            [
                'name' => 'kardex',
                'description' => 'Ver Kardex de Vacación',
                'system_id' => $sigva->id,
                'guard_name' => 'api'
            ],
            [
                'name' => 'calendario',
                'description' => 'Ver Calendario de Vacaciones',
                'system_id' => $sigva->id,
                'guard_name' => 'api'
            ],
            [
                'name' => 'reportes',
                'description' => 'Generar Reportes de Vacación',
                'system_id' => $sigva->id,
                'guard_name' => 'api'
            ],
        ];

        foreach ($permissions as $perm) {
            Permission::updateOrCreate(
                ['name' => $perm['name'], 'system_id' => $perm['system_id']],
                $perm
            );
        }

        // 3. Asignar TODO al Rol ADMIN / ADMINISTRADOR
        $adminRoles = Rol::whereIn('nombre', ['ADMIN', 'ADMINISTRADOR', 'SUPER ADMIN'])->get();
        $allPermissions = Permission::all();

        foreach ($adminRoles as $role) {
            // Sincronizar permisos (con tabla pivote role_has_permissions)
            foreach ($allPermissions as $p) {
                try {
                    DB::connection('core')->table('role_has_permissions')->updateOrInsert([
                        'role_id' => $role->id,
                        'permission_id' => $p->id
                    ]);
                } catch (\Exception $e) {
                    // Ignorar duplicados
                }
            }
        }

        // 4. Asignar Permisos Básicos a USUARIO
        $userRoles = Rol::where('nombre', 'USUARIO')->get();
        $userPerms = Permission::whereIn('name', ['solicitudes', 'kardex'])->get();

        foreach ($userRoles as $role) {
            foreach ($userPerms as $p) {
               try {
                    DB::connection('core')->table('role_has_permissions')->updateOrInsert([
                        'role_id' => $role->id,
                        'permission_id' => $p->id
                    ]);
                } catch (\Exception $e) {}
            }
        }
    }
}
