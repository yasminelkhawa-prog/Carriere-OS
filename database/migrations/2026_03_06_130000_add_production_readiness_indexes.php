<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $statements = [
            "CREATE INDEX IF NOT EXISTS idx_applications_company_updated_at ON applications (company_id, updated_at DESC)",
            "CREATE INDEX IF NOT EXISTS idx_applications_company_created_at ON applications (company_id, created_at DESC)",
            "CREATE INDEX IF NOT EXISTS idx_applications_company_job_stage_status_created ON applications (company_id, job_id, current_stage_id, status, created_at DESC)",
            "CREATE INDEX IF NOT EXISTS idx_applications_company_source_created ON applications (company_id, source_type, created_at DESC)",
            "CREATE INDEX IF NOT EXISTS idx_ai_requests_company_type_created ON ai_requests (company_id, request_type, created_at DESC)",
            "CREATE INDEX IF NOT EXISTS idx_ai_artifacts_company_created ON ai_artifacts (company_id, created_at DESC)",
            "CREATE INDEX IF NOT EXISTS idx_video_responses_company_created ON video_responses (company_id, created_at DESC)",
            "CREATE INDEX IF NOT EXISTS idx_video_responses_company_application_created ON video_responses (company_id, application_id, created_at DESC)",
            "CREATE INDEX IF NOT EXISTS idx_social_reactions_post_type ON social_reactions (post_id, reaction_type)",
            "CREATE INDEX IF NOT EXISTS idx_exports_company_status_created ON exports (company_id, status, created_at DESC)",
        ];

        foreach ($statements as $statement) {
            DB::statement($statement);
        }
    }

    public function down(): void
    {
        $statements = [
            'DROP INDEX IF EXISTS idx_applications_company_updated_at',
            'DROP INDEX IF EXISTS idx_applications_company_created_at',
            'DROP INDEX IF EXISTS idx_applications_company_job_stage_status_created',
            'DROP INDEX IF EXISTS idx_applications_company_source_created',
            'DROP INDEX IF EXISTS idx_ai_requests_company_type_created',
            'DROP INDEX IF EXISTS idx_ai_artifacts_company_created',
            'DROP INDEX IF EXISTS idx_video_responses_company_created',
            'DROP INDEX IF EXISTS idx_video_responses_company_application_created',
            'DROP INDEX IF EXISTS idx_social_reactions_post_type',
            'DROP INDEX IF EXISTS idx_exports_company_status_created',
        ];

        foreach ($statements as $statement) {
            DB::statement($statement);
        }
    }
};
