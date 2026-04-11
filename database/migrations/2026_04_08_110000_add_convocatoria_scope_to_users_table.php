<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = 'users';
        $connection = 'core';
        $afterColumn = Schema::connection($connection)->hasColumn($tableName, 'jurisdiccion')
            ? 'jurisdiccion'
            : (Schema::connection($connection)->hasColumn($tableName, 'must_change_password') ? 'must_change_password' : 'activo');

        Schema::connection('core')->table('users', function (Blueprint $table) use ($afterColumn) {
            if (!Schema::connection('core')->hasColumn('users', 'convocatoria_scope')) {
                $table->json('convocatoria_scope')->nullable()->after($afterColumn);
            }
        });
    }

    public function down(): void
    {
        Schema::connection('core')->table('users', function (Blueprint $table) {
            if (Schema::connection('core')->hasColumn('users', 'convocatoria_scope')) {
                $table->dropColumn('convocatoria_scope');
            }
        });
    }
};
