<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. formaciones_academicas
        Schema::table('formaciones_academicas', function (Blueprint $table) {
            $table->string('diploma_archivo_path')->nullable();
            $table->string('diploma_archivo_original_name')->nullable();
            $table->string('diploma_archivo_mime')->nullable();
            
            $table->string('titulo_archivo_path')->nullable();
            $table->string('titulo_archivo_original_name')->nullable();
            $table->string('titulo_archivo_mime')->nullable();

            $table->timestamp('files_migrated_at')->nullable();
            $table->string('files_migration_status')->nullable();
            $table->text('files_migration_notes')->nullable();
        });

        // 2. formaciones_postgrado
        Schema::table('formaciones_postgrado', function (Blueprint $table) {
            $table->string('certificado_archivo_path')->nullable();
            $table->string('certificado_archivo_original_name')->nullable();
            $table->string('certificado_archivo_mime')->nullable();

            $table->timestamp('files_migrated_at')->nullable();
            $table->string('files_migration_status')->nullable();
            $table->text('files_migration_notes')->nullable();
        });

        // 3. experiencias_docencia
        Schema::table('experiencias_docencia', function (Blueprint $table) {
            $table->string('respaldo_archivo_path')->nullable();
            $table->string('respaldo_archivo_original_name')->nullable();
            $table->string('respaldo_archivo_mime')->nullable();

            $table->timestamp('files_migrated_at')->nullable();
            $table->string('files_migration_status')->nullable();
            $table->text('files_migration_notes')->nullable();
        });

        // 4. experiencias_profesionales
        Schema::table('experiencias_profesionales', function (Blueprint $table) {
            $table->string('certificado_archivo_path')->nullable();
            $table->string('certificado_archivo_original_name')->nullable();
            $table->string('certificado_archivo_mime')->nullable();

            $table->timestamp('files_migrated_at')->nullable();
            $table->string('files_migration_status')->nullable();
            $table->text('files_migration_notes')->nullable();
        });

        // 5. capacitaciones
        Schema::table('capacitaciones', function (Blueprint $table) {
            $table->string('certificado_archivo_path')->nullable();
            $table->string('certificado_archivo_original_name')->nullable();
            $table->string('certificado_archivo_mime')->nullable();

            $table->timestamp('files_migrated_at')->nullable();
            $table->string('files_migration_status')->nullable();
            $table->text('files_migration_notes')->nullable();
        });

        // 6. producciones_intelectuales
        Schema::table('producciones_intelectuales', function (Blueprint $table) {
            $table->string('evidencia_archivo_path')->nullable();
            $table->string('evidencia_archivo_original_name')->nullable();
            $table->string('evidencia_archivo_mime')->nullable();

            $table->timestamp('files_migrated_at')->nullable();
            $table->string('files_migration_status')->nullable();
            $table->text('files_migration_notes')->nullable();
        });

        // 7. reconocimientos
        Schema::table('reconocimientos', function (Blueprint $table) {
            $table->string('reconocimiento_archivo_path')->nullable();
            $table->string('reconocimiento_archivo_original_name')->nullable();
            $table->string('reconocimiento_archivo_mime')->nullable();

            $table->timestamp('files_migrated_at')->nullable();
            $table->string('files_migration_status')->nullable();
            $table->text('files_migration_notes')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('formaciones_academicas', function (Blueprint $table) {
            $table->dropColumn([
                'diploma_archivo_path', 'diploma_archivo_original_name', 'diploma_archivo_mime',
                'titulo_archivo_path', 'titulo_archivo_original_name', 'titulo_archivo_mime',
                'files_migrated_at', 'files_migration_status', 'files_migration_notes'
            ]);
        });

        Schema::table('formaciones_postgrado', function (Blueprint $table) {
            $table->dropColumn([
                'certificado_archivo_path', 'certificado_archivo_original_name', 'certificado_archivo_mime',
                'files_migrated_at', 'files_migration_status', 'files_migration_notes'
            ]);
        });

        Schema::table('experiencias_docencia', function (Blueprint $table) {
            $table->dropColumn([
                'respaldo_archivo_path', 'respaldo_archivo_original_name', 'respaldo_archivo_mime',
                'files_migrated_at', 'files_migration_status', 'files_migration_notes'
            ]);
        });

        Schema::table('experiencias_profesionales', function (Blueprint $table) {
            $table->dropColumn([
                'certificado_archivo_path', 'certificado_archivo_original_name', 'certificado_archivo_mime',
                'files_migrated_at', 'files_migration_status', 'files_migration_notes'
            ]);
        });

        Schema::table('capacitaciones', function (Blueprint $table) {
            $table->dropColumn([
                'certificado_archivo_path', 'certificado_archivo_original_name', 'certificado_archivo_mime',
                'files_migrated_at', 'files_migration_status', 'files_migration_notes'
            ]);
        });

        Schema::table('producciones_intelectuales', function (Blueprint $table) {
            $table->dropColumn([
                'evidencia_archivo_path', 'evidencia_archivo_original_name', 'evidencia_archivo_mime',
                'files_migrated_at', 'files_migration_status', 'files_migration_notes'
            ]);
        });

        Schema::table('reconocimientos', function (Blueprint $table) {
            $table->dropColumn([
                'reconocimiento_archivo_path', 'reconocimiento_archivo_original_name', 'reconocimiento_archivo_mime',
                'files_migrated_at', 'files_migration_status', 'files_migration_notes'
            ]);
        });
    }
};
