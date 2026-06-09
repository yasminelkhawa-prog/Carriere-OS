<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_configs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUuid('job_id')->constrained('jobs')->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('read_time_seconds');
            $table->unsignedInteger('answer_time_seconds');
            $table->unsignedInteger('retries_allowed')->default(0);
            $table->timestamps();

            $table->index(['company_id', 'job_id']);
        });

        Schema::create('video_questions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUuid('config_id')->constrained('video_configs')->cascadeOnDelete();
            $table->unsignedInteger('display_order');
            $table->text('question_text');
            $table->timestamps();

            $table->index(['config_id', 'display_order']);
        });

        Schema::create('video_responses', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUuid('application_id')->constrained('applications')->cascadeOnDelete();
            $table->foreignUuid('question_id')->constrained('video_questions')->cascadeOnDelete();
            $table->unsignedInteger('attempt_number');
            $table->string('video_file_url');
            $table->unsignedInteger('duration_seconds');
            $table->unsignedInteger('pauses_count')->nullable();
            $table->decimal('speech_rate_estimate', 8, 2)->nullable();
            $table->decimal('filler_ratio_estimate', 8, 4)->nullable();
            $table->text('transcript_text')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['application_id', 'question_id']);
        });

        Schema::create('degree_equivalency_mappings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('degree_label');
            $table->string('country', 120);
            $table->string('tier', 80);
            $table->text('mapping_notes')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'country']);
        });

        if (! Schema::hasColumn('unified_interview_reports', 'xai_summary')) {
            Schema::table('unified_interview_reports', function (Blueprint $table): void {
                $table->text('xai_summary')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('unified_interview_reports', 'xai_summary')) {
            Schema::table('unified_interview_reports', function (Blueprint $table): void {
                $table->dropColumn('xai_summary');
            });
        }

        Schema::dropIfExists('degree_equivalency_mappings');
        Schema::dropIfExists('video_responses');
        Schema::dropIfExists('video_questions');
        Schema::dropIfExists('video_configs');
    }
};
