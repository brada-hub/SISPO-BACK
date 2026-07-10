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
        // 1. catalog_professional_areas
        Schema::create('catalog_professional_areas', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // 2. catalog_careers
        Schema::create('catalog_careers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('professional_area_id')->nullable();
            $table->string('canonical_name');
            $table->string('normalized_slug')->unique();
            $table->json('aliases_json')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('professional_area_id')
                  ->references('id')
                  ->on('catalog_professional_areas')
                  ->onDelete('set null');
        });

        // 3. catalog_job_positions
        Schema::create('catalog_job_positions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('professional_area_id')->nullable();
            $table->string('canonical_name');
            $table->string('normalized_slug')->unique();
            $table->json('aliases_json')->nullable();
            $table->string('seniority_level')->nullable(); // junior, semi-senior, senior, lead, none
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('professional_area_id')
                  ->references('id')
                  ->on('catalog_professional_areas')
                  ->onDelete('set null');
        });

        // 4. catalog_postgraduate_types
        Schema::create('catalog_postgraduate_types', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->json('aliases_json')->nullable();
            $table->timestamps();
        });

        // 5. catalog_academic_levels
        Schema::create('catalog_academic_levels', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->integer('hierarchy_order');
            $table->json('aliases_json')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('catalog_academic_levels');
        Schema::dropIfExists('catalog_postgraduate_types');
        Schema::dropIfExists('catalog_job_positions');
        Schema::dropIfExists('catalog_careers');
        Schema::dropIfExists('catalog_professional_areas');
    }
};
