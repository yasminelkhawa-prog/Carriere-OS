<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE INDEX IF NOT EXISTS applications_company_status_created_idx ON applications (company_id, status, created_at)');
        DB::statement('CREATE INDEX IF NOT EXISTS applications_company_source_created_idx ON applications (company_id, source_type, created_at)');
        DB::statement('CREATE INDEX IF NOT EXISTS applications_company_job_created_idx ON applications (company_id, job_id, created_at)');
        DB::statement('CREATE INDEX IF NOT EXISTS applications_company_stage_created_idx ON applications (company_id, current_stage_id, created_at)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS applications_company_status_created_idx');
        DB::statement('DROP INDEX IF EXISTS applications_company_source_created_idx');
        DB::statement('DROP INDEX IF EXISTS applications_company_job_created_idx');
        DB::statement('DROP INDEX IF EXISTS applications_company_stage_created_idx');
    }
};
