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
        if (!Schema::connection('core')->hasColumn('users', 'jurisdiccion')) {
            Schema::connection('core')->table('users', function (Blueprint $table) {
                // jurisdictions: array of sede_id allowed to be viewed/managed
                // NULL or empty means only their own sede_id
                $table->json('jurisdiccion')->nullable()->after('rol_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('core')->table('users', function (Blueprint $table) {
            $table->dropColumn('jurisdiccion');
        });
    }
};
