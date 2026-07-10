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
        Schema::table('formaciones_academicas', function (Blueprint $table) {
            $table->unsignedBigInteger('professional_area_id')->nullable()->after('career_id');
            $table->decimal('area_confidence', 5, 2)->nullable()->after('professional_area_id');
            $table->string('area_normalization_method', 50)->nullable()->after('area_confidence');

            // Foreign key constraints (optional but good practice)
            // catalog_professional_areas table might exist, let's look for it
            // We can add the foreign key if the table exists, otherwise just keep it as unsignedBigInteger.
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('formaciones_academicas', function (Blueprint $table) {
            $table->dropColumn([
                'professional_area_id',
                'area_confidence',
                'area_normalization_method'
            ]);
        });
    }
};
