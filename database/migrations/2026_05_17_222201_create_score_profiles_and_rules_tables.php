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
        // 1. Create score_profiles table
        Schema::create('score_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. Create score_profile_rules table
        Schema::create('score_profile_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('score_profile_id')->constrained('score_profiles')->onDelete('cascade');
            $table->string('criterion_code');
            $table->string('criterion_name');
            $table->decimal('weight', 5, 2);
            $table->decimal('max_points', 5, 2);
            $table->boolean('is_required')->default(false);
            $table->boolean('is_enabled')->default(true);
            $table->json('config_json')->nullable();
            $table->timestamps();
        });

        // 3. Alter convocatorias table
        Schema::table('convocatorias', function (Blueprint $table) {
            $table->foreignId('score_profile_id')->nullable()->after('id')->constrained('score_profiles')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('convocatorias', function (Blueprint $table) {
            $table->dropForeign(['score_profile_id']);
            $table->dropColumn('score_profile_id');
        });

        Schema::dropIfExists('score_profile_rules');
        Schema::dropIfExists('score_profiles');
    }
};
