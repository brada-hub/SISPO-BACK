<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\System;

class CoreAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Obtener Sistemas (Ya insertados por la migración)
        $sispo = System::where('name', 'SISPO')->first();
        $sigva = System::where('name', 'SIGVA')->first();

        // 2. Obtener Roles Globales (Ya insertados)
        // Puedes verificar IDs aquí si prefieres ser explícito
        $superAdminRole = DB::connection('core')->table('roles')->where('name', 'SuperAdmin')->first();

        // 3. Crear Usuario Super Admin
        $user = User::create([
            'nombres' => 'Administrador',
            'apellidos' => 'General',
            'email' => 'admin@unitepc.edu.bo',
            'ci' => '0000000', // CI Genérico
            'password' => Hash::make('password'), // Contraseña por defecto
            'activo' => true,
        ]);

        // 4. Asignar Acceso a Ambos Sistemas (SISPO y SIGVA)
        // Usamos attach si la relación está definida, o inserción directa

        // Acceso a SISPO como SuperAdmin
        DB::connection('core')->table('user_systems')->insert([
            'user_id' => $user->id,
            'system_id' => $sispo->id,
            'role_id' => $superAdminRole->id,
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Acceso a SIGVA como SuperAdmin
        DB::connection('core')->table('user_systems')->insert([
            'user_id' => $user->id,
            'system_id' => $sigva->id,
            'role_id' => $superAdminRole->id,
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->command->info('Usuario SuperAdmin creado con acceso a SISPO y SIGVA.');
        $this->command->info('Email: admin@unitepc.edu.bo');
        $this->command->info('Password: password');
    }
}
