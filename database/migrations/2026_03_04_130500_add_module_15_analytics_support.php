<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE INDEX IF NOT EXISTS applications_company_job_stage_created_source_idx ON applications (company_id, job_id, current_stage_id, created_at, source_type)');

        Schema::create('analytics_snapshots', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('snapshot_key', 100);
            $table->string('filters_hash', 64);
            $table->jsonb('payload')->nullable();
            $table->timestamp('captured_at')->useCurrent();

            $table->unique(['company_id', 'snapshot_key', 'filters_hash'], 'analytics_snapshots_company_key_hash_unique');
            $table->index(['company_id', 'captured_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_snapshots');
        DB::statement('DROP INDEX IF EXISTS applications_company_job_stage_created_source_idx');
    }
};
