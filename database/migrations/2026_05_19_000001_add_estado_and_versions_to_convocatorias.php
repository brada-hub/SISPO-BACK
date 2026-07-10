<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        if (!Schema::hasColumn('convocatorias', 'estado')) {
            Schema::table('convocatorias', function (Blueprint $table) {
                $table->string('estado', 30)->default('draft'); // draft, reviewing, published, archived, closed
            });
        }

        if (!Schema::hasColumn('convocatorias', 'draft_versions')) {
            Schema::table('convocatorias', function (Blueprint $table) {
                $table->longText('draft_versions')->nullable(); // Saved history of drafts
            });
        }

        if (!Schema::hasTable('convocatoria_audit_logs')) {
            Schema::create('convocatoria_audit_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('convocatoria_id');
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('action', 100);
                $table->longText('changes')->nullable(); // JSON list of changes or metadata
                $table->timestamp('created_at')->useCurrent();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('convocatoria_audit_logs');
        Schema::table('convocatorias', function (Blueprint $table) {
            $table->dropColumn(['estado', 'draft_versions']);
        });
    }
};
