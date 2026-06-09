<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidates', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('full_name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('location')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'email']);
        });

        Schema::create('applications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUuid('candidate_id')->constrained('candidates')->cascadeOnDelete();
            $table->foreignUuid('job_id')->constrained('jobs')->cascadeOnDelete();
            $table->foreignUuid('current_stage_id')->constrained('job_pipeline_stages')->restrictOnDelete();
            $table->string('status')->default('active');
            $table->string('source_type');
            $table->string('source_detail')->nullable();
            $table->string('utm_source')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->string('utm_medium')->nullable();
            $table->timestamps();

            $table->index('job_id');
            $table->index('current_stage_id');
            $table->index('created_at');
        });

        DB::statement("ALTER TABLE applications DROP CONSTRAINT IF EXISTS applications_status_check");
        DB::statement("ALTER TABLE applications ADD CONSTRAINT applications_status_check CHECK (status IN ('active', 'withdrawn', 'hired', 'rejected'))");

        Schema::create('candidate_documents', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUuid('candidate_id')->constrained('candidates')->cascadeOnDelete();
            $table->string('document_type');
            $table->string('file_url');
            $table->string('original_filename');
            $table->string('mime_type', 191);
            $table->unsignedBigInteger('file_size_bytes');
            $table->timestamp('created_at')->useCurrent();

            $table->index('candidate_id');
        });

        DB::statement("ALTER TABLE candidate_documents DROP CONSTRAINT IF EXISTS candidate_documents_document_type_check");
        DB::statement("ALTER TABLE candidate_documents ADD CONSTRAINT candidate_documents_document_type_check CHECK (document_type IN ('resume', 'portfolio', 'other'))");

        Schema::create('cv_parsing_results', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUuid('candidate_id')->constrained('candidates')->cascadeOnDelete();
            $table->foreignUuid('application_id')->nullable()->constrained('applications')->nullOnDelete();
            $table->jsonb('extracted_skills')->nullable();
            $table->jsonb('flags_json')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cv_parsing_results');
        Schema::dropIfExists('candidate_documents');
        Schema::dropIfExists('applications');
        Schema::dropIfExists('candidates');
    }
};
