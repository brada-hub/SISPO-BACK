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
        Schema::create('postulante_training_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('postulante_id')->unique()->constrained('postulantes')->onDelete('cascade');
            $table->integer('total_training_records')->default(0);
            $table->integer('total_training_hours')->default(0);
            $table->integer('training_hours_last_3_years')->default(0);
            $table->integer('training_hours_last_5_years')->default(0);
            $table->date('last_training_date')->nullable();
            $table->decimal('max_training_recency_score', 5, 2)->nullable();
            $table->decimal('average_training_recency_score', 5, 2)->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('postulante_training_summaries');
    }
};
