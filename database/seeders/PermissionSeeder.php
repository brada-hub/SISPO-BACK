<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Permission;
use App\Models\Rol;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class PermissionSeeder extends Seeder
{
    public function run()
    {
        $connection = 'core';

        // 1. LIMPIEZA DE PERMISOS REALMENTE ANTIGUOS
        $obsolete = ['sigva_dashboard', 'reportes_sigva', 'kardex'];
        Permission::whereIn('name', $obsolete)->delete();

        // 2. ASEGURAR COLUMNAS PARA EL FRONTEND (En la conexion core)
        if (!Schema::connection($connection)->hasColumn('permissions', 'system')) {
            DB::connection($connection)->statement('ALTER TABLE permissions ADD COLUMN system VARCHAR(50) NULL AFTER guard_name');
        }
        if (!Schema::connection($connection)->hasColumn('permissions', 'description')) {
            DB::connection($connection)->statement('ALTER TABLE permissions ADD COLUMN description VARCHAR(255) NULL AFTER name');
        }

        // 3. DEFINICION DE PERMISOS
        $permissions = [
            // --- SISTEMA: SISPO (Postulaciones) ---
            ['name' => 'dashboard', 'description' => 'Ver Dashboard Principal', 'system' => 'SISPO'],
            ['name' => 'convocatorias', 'description' => 'Gestionar Convocatorias', 'system' => 'SISPO'],
            ['name' => 'postulaciones', 'description' => 'Ver y Gestionar Postulaciones', 'system' => 'SISPO'],
            ['name' => 'evaluaciones', 'description' => 'Evaluar Postulantes', 'system' => 'SISPO'],
            ['name' => 'sedes', 'description' => 'Gestionar Sedes', 'system' => 'SISPO'],
            ['name' => 'cargos', 'description' => 'Gestionar Cargos', 'system' => 'SISPO'],
            ['name' => 'requisitos', 'description' => 'Gestionar Tipos de Documento', 'system' => 'SISPO'],
            ['name' => 'usuarios', 'description' => 'Gestionar Usuarios', 'system' => 'SISPO'],
            ['name' => 'roles', 'description' => 'Gestionar Roles y Permisos', 'system' => 'SISPO'],

            // --- SISTEMA: SIGVA (Vacaciones) ---
            ['name' => 'vacaciones_dashboard', 'description' => 'Ver Dashboard de Vacaciones', 'system' => 'SISTEMA DE VACACIONES'],
            ['name' => 'solicitudes', 'description' => 'Gestionar Solicitudes de Vacacion', 'system' => 'SISTEMA DE VACACIONES'],
            ['name' => 'calendario', 'description' => 'Ver Calendario de Vacaciones', 'system' => 'SISTEMA DE VACACIONES'],
            ['name' => 'empleados', 'description' => 'Gestionar Empleados', 'system' => 'SISTEMA DE VACACIONES'],
            ['name' => 'feriados', 'description' => 'Gestionar Feriados', 'system' => 'SISTEMA DE VACACIONES'],
            ['name' => 'reportes', 'description' => 'Generar Reportes de Vacacion', 'system' => 'SISTEMA DE VACACIONES'],
            ['name' => 'documentacion', 'description' => 'Ver Documentacion', 'system' => 'SISTEMA DE VACACIONES'],
        ];

        foreach ($permissions as $p) {
            Permission::updateOrCreate(
                ['name' => $p['name'], 'guard_name' => 'api'],
                ['description' => $p['description'], 'system' => $p['system']]
            );
        }

        // 4. ASIGNACION A ROLES ADMINISTRATIVOS
        $column = Schema::connection($connection)->hasColumn('roles', 'name') ? 'name' : 'nombre';
        $adminRoles = Rol::whereIn($column, ['ADMIN', 'ADMINISTRADOR', 'SUPER ADMIN'])->get();
        $allPermissions = Permission::all();

        foreach ($adminRoles as $role) {
            // Sincronizamos los permisos (usando la relacion de Spatie en la conexion core)
            $role->permissions()->sync($allPermissions->pluck('id'));
        }
    }
}
