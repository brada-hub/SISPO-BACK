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
        Schema::create('ai_job_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('postulacion_id')->index();
            $table->string('job_uuid', 64)->nullable()->index();
            $table->string('etapa', 50)->index();
            $table->string('status', 20)->default('running'); // running, ok, error
            $table->string('mensaje', 500)->nullable();
            $table->text('error')->nullable();
            $table->string('error_type', 60)->nullable(); // timeout, quota_429, malformed_json, empty_response, invalid_schema, connection, auth, unknown
            $table->unsignedInteger('processing_ms')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('postulacion_id')
                  ->references('id')
                  ->on('postulaciones')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_job_logs');
    }
};
