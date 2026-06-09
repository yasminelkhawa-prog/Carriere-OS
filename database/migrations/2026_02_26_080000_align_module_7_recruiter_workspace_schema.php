<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("UPDATE application_activity_events SET payload = '{}'::jsonb WHERE payload IS NULL");
            DB::statement('ALTER TABLE application_activity_events ALTER COLUMN event_type TYPE text');
            DB::statement('ALTER TABLE application_activity_events ALTER COLUMN event_type SET NOT NULL');
            DB::statement('ALTER TABLE application_activity_events ALTER COLUMN payload SET NOT NULL');

            DB::statement('UPDATE application_scorings SET global_match_score = 0 WHERE global_match_score IS NULL');
            DB::statement("UPDATE application_scorings SET vrin_json = jsonb_build_object('acquired_skills', jsonb_build_array(), 'missing_skills', jsonb_build_array()) WHERE vrin_json IS NULL");
            DB::statement("UPDATE application_scorings SET xai_summary = 'Not scored yet.' WHERE xai_summary IS NULL OR btrim(xai_summary) = ''");
            DB::statement('UPDATE application_scorings SET updated_at = NOW() WHERE updated_at IS NULL');
            DB::statement('ALTER TABLE application_scorings ALTER COLUMN global_match_score SET NOT NULL');
            DB::statement('ALTER TABLE application_scorings ALTER COLUMN vrin_json SET NOT NULL');
            DB::statement('ALTER TABLE application_scorings ALTER COLUMN xai_summary SET NOT NULL');
            DB::statement('ALTER TABLE application_scorings ALTER COLUMN updated_at SET NOT NULL');

            DB::statement(<<<'SQL'
DO $$
BEGIN
    IF EXISTS (
        SELECT 1
        FROM information_schema.columns
        WHERE table_name = 'unified_interview_reports'
          AND column_name = 'is_generic_motivation'
    ) THEN
        ALTER TABLE unified_interview_reports RENAME COLUMN is_generic_motivation TO generic_motivation;
    END IF;
END $$;
SQL);

            return;
        }

        DB::statement("UPDATE application_activity_events SET payload = '{}' WHERE payload IS NULL");
        DB::statement('UPDATE application_scorings SET global_match_score = 0 WHERE global_match_score IS NULL');
        DB::statement("UPDATE application_scorings SET vrin_json = JSON_OBJECT('acquired_skills', JSON_ARRAY(), 'missing_skills', JSON_ARRAY()) WHERE vrin_json IS NULL");
        DB::statement("UPDATE application_scorings SET xai_summary = 'Not scored yet.' WHERE xai_summary IS NULL OR TRIM(xai_summary) = ''");
        DB::statement('UPDATE application_scorings SET updated_at = NOW() WHERE updated_at IS NULL');

        if (Schema::hasColumn('unified_interview_reports', 'is_generic_motivation')
            && ! Schema::hasColumn('unified_interview_reports', 'generic_motivation')) {
            DB::statement('ALTER TABLE unified_interview_reports CHANGE is_generic_motivation generic_motivation TINYINT(1) NOT NULL DEFAULT 0');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
DO $$
BEGIN
    IF EXISTS (
        SELECT 1
        FROM information_schema.columns
        WHERE table_name = 'unified_interview_reports'
          AND column_name = 'generic_motivation'
    ) THEN
        ALTER TABLE unified_interview_reports RENAME COLUMN generic_motivation TO is_generic_motivation;
    END IF;
END $$;
SQL);

            DB::statement('ALTER TABLE application_activity_events ALTER COLUMN event_type TYPE varchar(255)');
            DB::statement('ALTER TABLE application_activity_events ALTER COLUMN payload DROP NOT NULL');

            DB::statement('ALTER TABLE application_scorings ALTER COLUMN global_match_score DROP NOT NULL');
            DB::statement('ALTER TABLE application_scorings ALTER COLUMN vrin_json DROP NOT NULL');
            DB::statement('ALTER TABLE application_scorings ALTER COLUMN xai_summary DROP NOT NULL');

            return;
        }

        if (Schema::hasColumn('unified_interview_reports', 'generic_motivation')
            && ! Schema::hasColumn('unified_interview_reports', 'is_generic_motivation')) {
            DB::statement('ALTER TABLE unified_interview_reports CHANGE generic_motivation is_generic_motivation TINYINT(1) NOT NULL DEFAULT 0');
        }
    }
};
