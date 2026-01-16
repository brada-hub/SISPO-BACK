<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Rol;
use App\Models\Sede;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Roles
        $adminRol = Rol::updateOrCreate(
            ['nombre' => 'Administrador'],
            ['descripcion' => 'Acceso total al sistema', 'activo' => true]
        );

        $userRol = Rol::updateOrCreate(
            ['nombre' => 'Usuario'],
            ['descripcion' => 'Acceso limitado', 'activo' => true]
        );

        // 2. Usuario Administrador
        User::updateOrCreate(
            ['ci' => '13260003'],
            [
                'nombres' => 'BRAYAN DAVID',
                'apellidos' => 'PADILLA SILES',
                'rol_id' => $adminRol->id,
                'password' => Hash::make('admin123'),
                'activo' => true,
                'must_change_password' => false,
            ]
        );

        // 3. Sedes
        $sedes = [
            ['nombre' => 'La Paz', 'departamento' => 'La Paz'],
            ['nombre' => 'El Alto', 'departamento' => 'La Paz'],
            ['nombre' => 'Cochabamba', 'departamento' => 'Cochabamba'],
            ['nombre' => 'Ivirgarzama', 'departamento' => 'Cochabamba'],
            ['nombre' => 'Guayaramerin', 'departamento' => 'Beni'],
            ['nombre' => 'Santa Cruz', 'departamento' => 'Santa Cruz'],
            ['nombre' => 'Puerto Quijarro', 'departamento' => 'Santa Cruz'],
            ['nombre' => 'Cobija', 'departamento' => 'Pando'],
            ['nombre' => 'Bolivia', 'departamento' => 'Nacional'],
        ];

        foreach ($sedes as $sede) {
            Sede::updateOrCreate(['nombre' => $sede['nombre']], $sede);
        }

        // 4. Cargos (Inventados)
        $cargos = [
            ['nombre' => 'Docente Tiempo Horario'],
            ['nombre' => 'Docente Tiempo Completo'],
            ['nombre' => 'Director de Carrera'],
            ['nombre' => 'Secretaria Académica'],
            ['nombre' => 'Auxiliar de Laboratorio'],
            ['nombre' => 'Coordinador de Investigación'],
            ['nombre' => 'Asistente Administrativo'],
            ['nombre' => 'Encargado de Sistemas'],
            ['nombre' => 'Contador'],
            ['nombre' => 'Portero/Seguridad'],
        ];

        foreach ($cargos as $cargo) {
            \App\Models\Cargo::updateOrCreate(['nombre' => $cargo['nombre']], $cargo);
        }

        // 5. Otros Seeders
        $this->call([
            TipoDocumentoSeeder::class,
        ]);
    }
}
