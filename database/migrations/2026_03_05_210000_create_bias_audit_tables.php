<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bias_audit_stats', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUuid('job_id')->constrained('jobs')->cascadeOnDelete();
            $table->foreignUuid('stage_id')->constrained('job_pipeline_stages')->cascadeOnDelete();
            $table->dateTime('time_bucket_start');
            $table->dateTime('time_bucket_end');
            $table->string('dimension_key', 120);
            $table->unsignedInteger('group_a_count');
            $table->unsignedInteger('group_b_count');
            $table->decimal('impact_ratio', 5, 4);
            $table->decimal('fairness_index', 6, 2);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['company_id', 'created_at']);
            $table->unique(
                ['job_id', 'stage_id', 'time_bucket_start', 'dimension_key'],
                'bias_audit_stats_job_stage_bucket_dimension_unique'
            );
        });

        Schema::create('bias_alerts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUuid('job_id')->constrained('jobs')->cascadeOnDelete();
            $table->string('dimension_key', 120);
            $table->string('severity', 24);
            $table->text('message');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('resolved_at')->nullable();

            $table->index(['company_id', 'created_at']);
            $table->index(['company_id', 'resolved_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bias_alerts');
        Schema::dropIfExists('bias_audit_stats');
    }
};

