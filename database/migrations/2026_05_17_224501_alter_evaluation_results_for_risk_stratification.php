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
        Schema::table('evaluation_results', function (Blueprint $table) {
            $table->decimal('review_risk_score', 5, 2)->nullable()->after('requires_human_review');
            $table->string('review_risk_level', 20)->nullable()->after('review_risk_score');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('evaluation_results', function (Blueprint $table) {
            $table->dropColumn([
                'review_risk_score',
                'review_risk_level'
            ]);
        });
    }
};
