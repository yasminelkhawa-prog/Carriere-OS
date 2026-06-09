<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cv_parsing_results', function (Blueprint $table): void {
            $table->foreignUuid('source_document_id')->nullable()->constrained('candidate_documents')->nullOnDelete();
            $table->string('source_document_sha256', 64)->nullable();
            $table->string('parser_version', 40)->default('cv_parsing_v2');
            $table->string('parse_status', 24)->default('succeeded');
            $table->text('profile_summary')->nullable();

            $table->string('parsed_full_name')->nullable();
            $table->string('parsed_email')->nullable();
            $table->string('parsed_phone')->nullable();
            $table->string('parsed_location')->nullable();
            $table->decimal('total_years_experience', 5, 2)->nullable();

            $table->jsonb('languages_json')->nullable();
            $table->jsonb('hard_skills_json')->nullable();
            $table->jsonb('soft_skills_json')->nullable();
            $table->jsonb('tools_frameworks_json')->nullable();
            $table->jsonb('job_titles_json')->nullable();
            $table->jsonb('companies_json')->nullable();
            $table->jsonb('experience_entries_json')->nullable();
            $table->jsonb('employment_chronology_json')->nullable();
            $table->jsonb('certifications_json')->nullable();
            $table->jsonb('projects_json')->nullable();
            $table->jsonb('education_entries_json')->nullable();
            $table->jsonb('honors_json')->nullable();
            $table->jsonb('school_categories_json')->nullable();
            $table->jsonb('keywords_json')->nullable();

            $table->string('gender_inference', 24)->nullable();
            $table->string('school_background_tier', 40)->nullable();
            $table->string('ocean_dependency_status', 32)->default('pending_input');

            $table->jsonb('parsed_metadata_json')->nullable();
            $table->jsonb('parsed_payload_json')->nullable();
            $table->jsonb('raw_output_json')->nullable();
            $table->jsonb('parse_errors_json')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->index(['company_id', 'candidate_id', 'application_id'], 'cv_parse_lookup_idx');
            $table->index(['company_id', 'parser_version', 'source_document_sha256'], 'cv_parse_doc_sig_idx');
        });
    }

    public function down(): void
    {
        Schema::table('cv_parsing_results', function (Blueprint $table): void {
            $table->dropIndex('cv_parse_lookup_idx');
            $table->dropIndex('cv_parse_doc_sig_idx');

            $table->dropConstrainedForeignId('source_document_id');

            $table->dropColumn([
                'source_document_sha256',
                'parser_version',
                'parse_status',
                'profile_summary',
                'parsed_full_name',
                'parsed_email',
                'parsed_phone',
                'parsed_location',
                'total_years_experience',
                'languages_json',
                'hard_skills_json',
                'soft_skills_json',
                'tools_frameworks_json',
                'job_titles_json',
                'companies_json',
                'experience_entries_json',
                'employment_chronology_json',
                'certifications_json',
                'projects_json',
                'education_entries_json',
                'honors_json',
                'school_categories_json',
                'keywords_json',
                'gender_inference',
                'school_background_tier',
                'ocean_dependency_status',
                'parsed_metadata_json',
                'parsed_payload_json',
                'raw_output_json',
                'parse_errors_json',
                'updated_at',
            ]);
        });
    }
};
