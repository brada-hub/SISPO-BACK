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
        Schema::table('postulantes', function (Blueprint $table) {
            $table->decimal('pretension_salarial', 10, 2)->nullable()->after('ref_laboral_detalle');
            $table->text('porque_cargo')->nullable()->after('pretension_salarial');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('postulantes', function (Blueprint $table) {
            $table->dropColumn(['pretension_salarial', 'porque_cargo']);
        });
    }
};
