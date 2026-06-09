<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('jobs')) {
            return;
        }

        $hasDescriptionHtml = Schema::hasColumn('jobs', 'description_html');
        $hasLocationStreet = Schema::hasColumn('jobs', 'location_street');
        $hasLocationCity = Schema::hasColumn('jobs', 'location_city');
        $hasLocationCountry = Schema::hasColumn('jobs', 'location_country');
        $hasLocationPostalCode = Schema::hasColumn('jobs', 'location_postal_code');
        $hasEmploymentType = Schema::hasColumn('jobs', 'employment_type');
        $hasSalaryMin = Schema::hasColumn('jobs', 'salary_min');
        $hasSalaryMax = Schema::hasColumn('jobs', 'salary_max');
        $hasSalaryCurrency = Schema::hasColumn('jobs', 'salary_currency');

        Schema::table('jobs', function (Blueprint $table) use (
            $hasDescriptionHtml,
            $hasLocationStreet,
            $hasLocationCity,
            $hasLocationCountry,
            $hasLocationPostalCode,
            $hasEmploymentType,
            $hasSalaryMin,
            $hasSalaryMax,
            $hasSalaryCurrency
        ): void {
            if (! $hasDescriptionHtml) {
                $table->text('description_html')->nullable();
            }

            if (! $hasLocationStreet) {
                $table->string('location_street')->nullable();
            }

            if (! $hasLocationCity) {
                $table->string('location_city')->nullable();
            }

            if (! $hasLocationCountry) {
                $table->string('location_country')->nullable();
            }

            if (! $hasLocationPostalCode) {
                $table->string('location_postal_code', 32)->nullable();
            }

            if (! $hasEmploymentType) {
                $table->string('employment_type', 32)->default('full_time');
            }

            if (! $hasSalaryMin) {
                $table->integer('salary_min')->nullable();
            }

            if (! $hasSalaryMax) {
                $table->integer('salary_max')->nullable();
            }

            if (! $hasSalaryCurrency) {
                $table->string('salary_currency', 8)->nullable();
            }
        });

        DB::statement('ALTER TABLE jobs DROP CONSTRAINT IF EXISTS jobs_status_check');
        DB::statement("ALTER TABLE jobs ADD CONSTRAINT jobs_status_check CHECK (status IN ('draft', 'published', 'archived'))");

        DB::statement('ALTER TABLE jobs DROP CONSTRAINT IF EXISTS jobs_employment_type_check');
        DB::statement("ALTER TABLE jobs ADD CONSTRAINT jobs_employment_type_check CHECK (employment_type IN ('full_time', 'part_time', 'contract'))");

        DB::statement('ALTER TABLE jobs DROP CONSTRAINT IF EXISTS jobs_salary_range_check');
        DB::statement(
            'ALTER TABLE jobs ADD CONSTRAINT jobs_salary_range_check CHECK (
                (salary_min IS NULL OR salary_min >= 0)
                AND (salary_max IS NULL OR salary_max >= 0)
                AND (salary_min IS NULL OR salary_max IS NULL OR salary_min <= salary_max)
            )'
        );

        DB::statement('UPDATE jobs SET salary_max = salary_budget_max WHERE salary_max IS NULL AND salary_budget_max IS NOT NULL');
        DB::statement("UPDATE jobs SET employment_type = 'full_time' WHERE employment_type IS NULL");
    }

    public function down(): void
    {
        if (! Schema::hasTable('jobs')) {
            return;
        }

        DB::statement('ALTER TABLE jobs DROP CONSTRAINT IF EXISTS jobs_salary_range_check');
        DB::statement('ALTER TABLE jobs DROP CONSTRAINT IF EXISTS jobs_employment_type_check');

        Schema::table('jobs', function (Blueprint $table): void {
            if (Schema::hasColumn('jobs', 'salary_currency')) {
                $table->dropColumn('salary_currency');
            }

            if (Schema::hasColumn('jobs', 'salary_max')) {
                $table->dropColumn('salary_max');
            }

            if (Schema::hasColumn('jobs', 'salary_min')) {
                $table->dropColumn('salary_min');
            }

            if (Schema::hasColumn('jobs', 'employment_type')) {
                $table->dropColumn('employment_type');
            }

            if (Schema::hasColumn('jobs', 'location_postal_code')) {
                $table->dropColumn('location_postal_code');
            }

            if (Schema::hasColumn('jobs', 'location_country')) {
                $table->dropColumn('location_country');
            }

            if (Schema::hasColumn('jobs', 'location_city')) {
                $table->dropColumn('location_city');
            }

            if (Schema::hasColumn('jobs', 'location_street')) {
                $table->dropColumn('location_street');
            }

            if (Schema::hasColumn('jobs', 'description_html')) {
                $table->dropColumn('description_html');
            }
        });

        DB::statement('ALTER TABLE jobs DROP CONSTRAINT IF EXISTS jobs_status_check');
        DB::statement("ALTER TABLE jobs ADD CONSTRAINT jobs_status_check CHECK (status IN ('draft', 'published', 'archived'))");
    }
};
