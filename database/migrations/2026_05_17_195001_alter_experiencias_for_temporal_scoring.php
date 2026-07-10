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
        // 1. experiencias_profesionales
        Schema::table('experiencias_profesionales', function (Blueprint $table) {
            $table->boolean('is_current')->default(false)->after('job_position_id');
            $table->date('last_experience_date')->nullable()->after('is_current');
            $table->integer('months_in_last_3_years')->nullable()->after('last_experience_date');
            $table->integer('months_in_last_5_years')->nullable()->after('months_in_last_3_years');
            $table->integer('months_total')->nullable()->after('months_in_last_5_years');
            $table->decimal('recency_score', 5, 2)->nullable()->after('duracion_meses');
            $table->timestamp('temporal_processed_at')->nullable()->after('recency_score');
        });

        // 2. experiencias_docencia
        Schema::table('experiencias_docencia', function (Blueprint $table) {
            $table->integer('teaching_year_start')->nullable()->after('career_id');
            $table->integer('teaching_year_end')->nullable()->after('teaching_year_start');
            $table->integer('years_in_last_5_years')->nullable()->after('teaching_year_end');
            $table->boolean('is_recent_last_5_years')->default(false)->after('years_in_last_5_years');
            $table->integer('last_teaching_year')->nullable()->after('is_recent_last_5_years');
            $table->decimal('recency_score', 5, 2)->nullable()->after('gestion_periodo');
            $table->timestamp('temporal_processed_at')->nullable()->after('recency_score');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 2. experiencias_docencia
        Schema::table('experiencias_docencia', function (Blueprint $table) {
            $table->dropColumn([
                'teaching_year_start',
                'teaching_year_end',
                'years_in_last_5_years',
                'is_recent_last_5_years',
                'last_teaching_year',
                'recency_score',
                'temporal_processed_at'
            ]);
        });

        // 1. experiencias_profesionales
        Schema::table('experiencias_profesionales', function (Blueprint $table) {
            $table->dropColumn([
                'is_current',
                'last_experience_date',
                'months_in_last_3_years',
                'months_in_last_5_years',
                'months_total',
                'recency_score',
                'temporal_processed_at'
            ]);
        });
    }
};
