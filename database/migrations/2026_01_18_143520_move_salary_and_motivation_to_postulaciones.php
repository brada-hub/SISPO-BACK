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
        Schema::table('postulaciones', function (Blueprint $table) {
            $table->decimal('pretension_salarial', 10, 2)->nullable()->after('oferta_id');
            $table->text('porque_cargo')->nullable()->after('pretension_salarial');
        });
    }

    public function down(): void
    {
        Schema::table('postulaciones', function (Blueprint $table) {
            $table->dropColumn(['pretension_salarial', 'porque_cargo']);
        });
    }
};
