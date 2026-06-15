<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('request_type');
            $table->string('input_hash', 64);
            $table->string('status')->default('queued');
            $table->string('model_name');
            $table->string('prompt_version')->nullable();
            $table->jsonb('request_payload');
            $table->jsonb('response_payload')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['company_id', 'status']);
            $table->index('created_at');
        });

        if (DB::connection()->getDriverName() !== 'sqlite') {
    DB::statement("ALTER TABLE ai_requests DROP CONSTRAINT IF EXISTS ai_requests_status_check");
        }
        if (DB::connection()->getDriverName() !== 'sqlite') {
    DB::statement("ALTER TABLE ai_requests ADD CONSTRAINT ai_requests_status_check CHECK (status IN ('queued', 'running', 'succeeded', 'failed'))");
        }

        Schema::create('ai_artifacts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUuid('ai_request_id')->constrained('ai_requests')->cascadeOnDelete();
            $table->string('artifact_type');
            $table->string('storage_url');
            $table->timestamp('created_at')->useCurrent();

            $table->index('ai_request_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_artifacts');
        Schema::dropIfExists('ai_requests');
    }
};
