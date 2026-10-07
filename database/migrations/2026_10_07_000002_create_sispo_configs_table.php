<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('sispo_configs')) {
            Schema::create('sispo_configs', function (Blueprint $table) {
                $table->id();
                $table->string('key', 100)->unique()->index();
                $table->longText('value')->nullable();
                $table->string('description', 255)->nullable();
                $table->unsignedBigInteger('updated_by_user_id')->nullable();
                $table->timestamps();
            });

            // Configuración inicial por defecto: Habilitada
            DB::table('sispo_configs')->insert([
                [
                    'key' => 'recepcion_solicitudes_habilitada',
                    'value' => '1',
                    'description' => 'Habilitar o desactivar el enlace público para recibir requerimientos de convocatorias',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'key' => 'recepcion_solicitudes_mensaje_cierre',
                    'value' => 'El periodo de recepción de requerimientos de convocatoria se encuentra actualmente cerrado. Por favor comuníquese con la Dirección de Talento Humano para consultas.',
                    'description' => 'Mensaje mostrado a directores cuando la recepción está desactivada',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sispo_configs');
    }
};
