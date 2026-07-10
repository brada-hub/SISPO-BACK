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
            $table->boolean('requires_human_review')->default(false)->after('requires_ai_review');
            $table->json('review_flags_json')->nullable()->after('requires_human_review');
            $table->text('review_reason_summary')->nullable()->after('review_flags_json');
            $table->string('evaluation_status', 50)->default('evaluated')->after('review_reason_summary');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('evaluation_results', function (Blueprint $table) {
            $table->dropColumn([
                'requires_human_review',
                'review_flags_json',
                'review_reason_summary',
                'evaluation_status',
            ]);
        });
    }
};
