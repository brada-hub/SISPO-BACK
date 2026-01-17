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
            ['nombre' => 'La Paz', 'departamento' => 'La Paz', 'sigla' => 'LPZ'],
            ['nombre' => 'El Alto', 'departamento' => 'La Paz', 'sigla' => 'ALT'],
            ['nombre' => 'Cochabamba', 'departamento' => 'Cochabamba', 'sigla' => 'COC'],
            ['nombre' => 'Ivirgarzama', 'departamento' => 'Cochabamba', 'sigla' => 'IVI'],
            ['nombre' => 'Guayaramerin', 'departamento' => 'Beni', 'sigla' => 'GUA'],
            ['nombre' => 'Santa Cruz', 'departamento' => 'Santa Cruz', 'sigla' => 'SCZ'],
            ['nombre' => 'Puerto Quijarro', 'departamento' => 'Santa Cruz', 'sigla' => 'PUE-QUI'],
            ['nombre' => 'Cobija', 'departamento' => 'Pando', 'sigla' => 'COB'],
            ['nombre' => 'Bolivia', 'departamento' => 'Nacional', 'sigla' => 'BOL'],
        ];

        foreach ($sedes as $sede) {
            Sede::updateOrCreate(['nombre' => $sede['nombre']], $sede);
        }

        // 4. Cargos (Inventados)
        $cargos = [
            ['nombre' => 'Docente Tiempo Horario', 'sigla' => 'DOC-HOR'],
            ['nombre' => 'Docente Tiempo Completo', 'sigla' => 'DOC-COM'],
            ['nombre' => 'Director de Carrera', 'sigla' => 'DIR-CAR'],
            ['nombre' => 'Secretaria Académica', 'sigla' => 'SEC-ACA'],
            ['nombre' => 'Auxiliar de Laboratorio', 'sigla' => 'AUX-LAB'],
            ['nombre' => 'Coordinador de Investigación', 'sigla' => 'COO-INV'],
            ['nombre' => 'Asistente Administrativo', 'sigla' => 'ASI-ADM'],
            ['nombre' => 'Encargado de Sistemas', 'sigla' => 'ENC-SIS'],
            ['nombre' => 'Contador', 'sigla' => 'CON'],
            ['nombre' => 'Portero/Seguridad', 'sigla' => 'POR-SEG'],
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
