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
        Schema::table('ai_matching_results', function (Blueprint $table) {
            if (!Schema::hasColumn('ai_matching_results', 'score_docencia')) {
                $table->decimal('score_docencia', 5, 2)->default(0.00)->after('score_experiencia');
            }
            if (!Schema::hasColumn('ai_matching_results', 'evaluation_mode')) {
                $table->string('evaluation_mode', 50)->default('deterministic')->after('clasificacion_ia');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_matching_results', function (Blueprint $table) {
            $table->dropColumn(['score_docencia', 'evaluation_mode']);
        });
    }
};
