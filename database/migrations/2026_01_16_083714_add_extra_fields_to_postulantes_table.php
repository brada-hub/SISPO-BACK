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
            $table->string('ci_archivo_path')->nullable()->after('ci_expedido');
            $table->string('ref_personal_celular')->nullable()->after('carta_postulacion_path');
            $table->string('ref_personal_parentesco')->nullable()->after('ref_personal_celular');
            $table->string('ref_laboral_celular')->nullable()->after('ref_personal_parentesco');
            $table->string('ref_laboral_detalle')->nullable()->after('ref_laboral_celular');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('postulantes', function (Blueprint $table) {
            $table->dropColumn([
                'ci_archivo_path',
                'ref_personal_celular',
                'ref_personal_parentesco',
                'ref_laboral_celular',
                'ref_laboral_detalle'
            ]);
        });
    }
};
