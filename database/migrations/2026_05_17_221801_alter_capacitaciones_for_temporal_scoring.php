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
        Schema::table('capacitaciones', function (Blueprint $table) {
            $table->integer('training_year')->nullable()->after('carga_horaria');
            $table->date('last_training_date')->nullable()->after('training_year');
            $table->boolean('is_recent_last_3_years')->default(false)->after('last_training_date');
            $table->boolean('is_recent_last_5_years')->default(false)->after('is_recent_last_3_years');
            $table->decimal('temporal_weight', 5, 2)->nullable()->after('is_recent_last_5_years');
            $table->decimal('training_recency_score', 5, 2)->nullable()->after('temporal_weight');
            $table->timestamp('temporal_processed_at')->nullable()->after('training_recency_score');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('capacitaciones', function (Blueprint $table) {
            $table->dropColumn([
                'training_year',
                'last_training_date',
                'is_recent_last_3_years',
                'is_recent_last_5_years',
                'temporal_weight',
                'training_recency_score',
                'temporal_processed_at',
            ]);
        });
    }
};
