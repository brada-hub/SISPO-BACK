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
        Schema::create('postulante_experience_summaries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('postulante_id')->unique();
            $table->integer('total_accumulated_months')->default(0);
            $table->integer('total_unique_months')->default(0);
            $table->integer('accumulated_months_last_3_years')->default(0);
            $table->integer('unique_months_last_3_years')->default(0);
            $table->integer('accumulated_months_last_5_years')->default(0);
            $table->integer('unique_months_last_5_years')->default(0);
            $table->decimal('max_recency_score', 5, 2)->nullable();
            $table->integer('current_experience_count')->default(0);
            $table->integer('experience_records_count')->default(0);
            $table->boolean('overlap_detected')->default(false);
            $table->integer('overlap_months_estimated')->default(0);
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->foreign('postulante_id')
                  ->references('id')
                  ->on('postulantes')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('postulante_experience_summaries');
    }
};
