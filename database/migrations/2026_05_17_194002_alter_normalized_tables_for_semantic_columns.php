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
            $table->unsignedBigInteger('academic_level_id')->nullable()->after('source_merito_id');
            $table->unsignedBigInteger('career_id')->nullable()->after('academic_level_id');
            $table->decimal('normalization_confidence', 5, 2)->nullable()->after('fecha_titulo');
            $table->string('normalization_method', 50)->nullable()->after('normalization_confidence');
            $table->timestamp('normalized_at')->nullable()->after('normalization_method');

            $table->foreign('academic_level_id')
                  ->references('id')
                  ->on('catalog_academic_levels')
                  ->onDelete('set null');

            $table->foreign('career_id')
                  ->references('id')
                  ->on('catalog_careers')
                  ->onDelete('set null');
        });

        // 2. formaciones_postgrado
        Schema::table('formaciones_postgrado', function (Blueprint $table) {
            $table->unsignedBigInteger('postgraduate_type_id')->nullable()->after('source_merito_id');
            $table->decimal('normalization_confidence', 5, 2)->nullable()->after('institucion');
            $table->string('normalization_method', 50)->nullable()->after('normalization_confidence');
            $table->timestamp('normalized_at')->nullable()->after('normalization_method');

            $table->foreign('postgraduate_type_id')
                  ->references('id')
                  ->on('catalog_postgraduate_types')
                  ->onDelete('set null');
        });

        // 3. experiencias_profesionales
        Schema::table('experiencias_profesionales', function (Blueprint $table) {
            $table->unsignedBigInteger('job_position_id')->nullable()->after('source_merito_id');
            $table->decimal('normalization_confidence', 5, 2)->nullable()->after('duracion_meses');
            $table->string('normalization_method', 50)->nullable()->after('normalization_confidence');
            $table->timestamp('normalized_at')->nullable()->after('normalization_method');

            $table->foreign('job_position_id')
                  ->references('id')
                  ->on('catalog_job_positions')
                  ->onDelete('set null');
        });

        // 4. experiencias_docencia
        Schema::table('experiencias_docencia', function (Blueprint $table) {
            $table->unsignedBigInteger('career_id')->nullable()->after('source_merito_id');
            $table->decimal('normalization_confidence', 5, 2)->nullable()->after('gestion_periodo');
            $table->string('normalization_method', 50)->nullable()->after('normalization_confidence');
            $table->timestamp('normalized_at')->nullable()->after('normalization_method');

            $table->foreign('career_id')
                  ->references('id')
                  ->on('catalog_careers')
                  ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 4. experiencias_docencia
        Schema::table('experiencias_docencia', function (Blueprint $table) {
            $table->dropForeign(['career_id']);
            $table->dropColumn(['career_id', 'normalization_confidence', 'normalization_method', 'normalized_at']);
        });

        // 3. experiencias_profesionales
        Schema::table('experiencias_profesionales', function (Blueprint $table) {
            $table->dropForeign(['job_position_id']);
            $table->dropColumn(['job_position_id', 'normalization_confidence', 'normalization_method', 'normalized_at']);
        });

        // 2. formaciones_postgrado
        Schema::table('formaciones_postgrado', function (Blueprint $table) {
            $table->dropForeign(['postgraduate_type_id']);
            $table->dropColumn(['postgraduate_type_id', 'normalization_confidence', 'normalization_method', 'normalized_at']);
        });

        // 1. formaciones_academicas
        Schema::table('formaciones_academicas', function (Blueprint $table) {
            $table->dropForeign(['academic_level_id']);
            $table->dropForeign(['career_id']);
            $table->dropColumn(['academic_level_id', 'career_id', 'normalization_confidence', 'normalization_method', 'normalized_at']);
        });
    }
};
