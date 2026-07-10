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
        // 1. convocatoria_score_rules
        Schema::create('convocatoria_score_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('convocatoria_id');
            $table->string('criterion_code', 100);
            $table->string('criterion_name', 255);
            $table->decimal('weight', 5, 2);
            $table->decimal('max_points', 5, 2);
            $table->boolean('is_required')->default(false);
            $table->boolean('is_enabled')->default(true);
            $table->json('config_json')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('convocatoria_id')
                  ->references('id')
                  ->on('convocatorias')
                  ->onDelete('cascade');

            // Unique composite to avoid duplicate criteria per job posting
            $table->unique(['convocatoria_id', 'criterion_code']);
        });

        // 2. evaluation_results
        Schema::create('evaluation_results', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('postulacion_id')->unique();
            $table->unsignedBigInteger('convocatoria_id');
            $table->enum('evaluation_mode', ['deterministic', 'hybrid', 'ai'])->default('deterministic');
            $table->decimal('score_total', 5, 2);
            $table->string('classification', 50);
            $table->boolean('requires_ai_review')->default(false);
            $table->json('score_breakdown_json')->nullable();
            $table->json('failed_requirements_json')->nullable();
            $table->json('strengths_json')->nullable();
            $table->json('weaknesses_json')->nullable();
            $table->timestamp('evaluated_at')->nullable();
            $table->timestamps();

            $table->foreign('postulacion_id')
                  ->references('id')
                  ->on('postulaciones')
                  ->onDelete('cascade');

            $table->foreign('convocatoria_id')
                  ->references('id')
                  ->on('convocatorias')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('evaluation_results');
        Schema::dropIfExists('convocatoria_score_rules');
    }
};
