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
            ['nombre' => 'ADMINISTRADOR'],
            ['descripcion' => 'ACCESO TOTAL AL SISTEMA', 'activo' => true]
        );

        $userRol = Rol::updateOrCreate(
            ['nombre' => 'USUARIO'],
            ['descripcion' => 'ACCESO LIMITADO', 'activo' => true]
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
            ['nombre' => 'LA PAZ', 'departamento' => 'LA PAZ', 'sigla' => 'LPZ'],
            ['nombre' => 'EL ALTO', 'departamento' => 'LA PAZ', 'sigla' => 'ALT'],
            ['nombre' => 'COCHABAMBA', 'departamento' => 'COCHABAMBA', 'sigla' => 'COC'],
            ['nombre' => 'IVIRGARZAMA', 'departamento' => 'COCHABAMBA', 'sigla' => 'IVI'],
            ['nombre' => 'GUAYARAMERIN', 'departamento' => 'BENI', 'sigla' => 'GUA'],
            ['nombre' => 'SANTA CRUZ', 'departamento' => 'SANTA CRUZ', 'sigla' => 'SCZ'],
            ['nombre' => 'PUERTO QUIJARRO', 'departamento' => 'SANTA CRUZ', 'sigla' => 'PUE-QUI'],
            ['nombre' => 'COBIJA', 'departamento' => 'PANDO', 'sigla' => 'COB'],
            ['nombre' => 'BOLIVIA', 'departamento' => 'NACIONAL', 'sigla' => 'BOL'],
        ];

        foreach ($sedes as $sede) {
            Sede::updateOrCreate(['nombre' => $sede['nombre']], $sede);
        }

        // 4. Cargos (Inventados)
        $cargos = [
            ['nombre' => 'DOCENTE TIEMPO HORARIO', 'sigla' => 'DOC-HOR'],
            ['nombre' => 'DOCENTE TIEMPO COMPLETO', 'sigla' => 'DOC-COM'],
            ['nombre' => 'DIRECTOR DE CARRERA', 'sigla' => 'DIR-CAR'],
            ['nombre' => 'SECRETARIA ACADÉMICA', 'sigla' => 'SEC-ACA'],
            ['nombre' => 'AUXILIAR DE LABORATORIO', 'sigla' => 'AUX-LAB'],
            ['nombre' => 'COORDINADOR DE INVESTIGACIÓN', 'sigla' => 'COO-INV'],
            ['nombre' => 'ASISTENTE ADMINISTRATIVO', 'sigla' => 'ASI-ADM'],
            ['nombre' => 'ENCARGADO DE SISTEMAS', 'sigla' => 'ENC-SIS'],
            ['nombre' => 'CONTADOR', 'sigla' => 'CON'],
            ['nombre' => 'PORTERO/SEGURIDAD', 'sigla' => 'POR-SEG'],
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
