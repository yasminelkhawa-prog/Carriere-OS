<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('strategy_lab_briefs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUuid('application_id')->unique()->constrained('applications')->cascadeOnDelete();
            $table->string('brief_title');
            $table->string('brief_pdf_url')->nullable();
            $table->timestamp('deadline_at');
            $table->string('status')->default('assigned');
            $table->foreignUuid('generated_ai_request_id')->nullable()->constrained('ai_requests')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'deadline_at']);
        });

        Schema::create('strategy_lab_submissions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUuid('application_id')->unique()->constrained('applications')->cascadeOnDelete();
            $table->string('submission_type');
            $table->string('file_url');
            $table->string('original_filename');
            $table->timestamp('submitted_at');
            $table->timestamps();

            $table->index(['company_id', 'submitted_at']);
        });

        Schema::create('strategy_lab_ai_summaries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUuid('application_id')->unique()->constrained('applications')->cascadeOnDelete();
            $table->text('executive_summary_text');
            $table->jsonb('strengths_json');
            $table->jsonb('weaknesses_json');
            $table->decimal('creativity_score', 5, 2);
            $table->timestamps();

            $table->index(['company_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('strategy_lab_ai_summaries');
        Schema::dropIfExists('strategy_lab_submissions');
        Schema::dropIfExists('strategy_lab_briefs');
    }
};

