<?php

namespace Database\Seeders;

use App\Jobs\GenerateExportJob;
use App\Models\Application;
use App\Models\Candidate;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\Export;
use App\Models\Job;
use App\Models\JobPipelineStage;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ReportingExportsModuleSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('exports')) {
            $this->command?->warn('Skipping ReportingExportsModuleSeeder: exports table not found. Run migrations first.');

            return;
        }

        $company = Company::query()->firstOrCreate(
            ['slug' => 'numa-demo'],
            [
                'name' => 'numa Demo',
                'status' => Company::STATUS_ACTIVE,
                'brand_logo_url' => null,
            ]
        );

        if ((string) $company->status !== Company::STATUS_ACTIVE) {
            $company->forceFill(['status' => Company::STATUS_ACTIVE])->save();
        }

        $requester = User::query()->where('email', 'admin@example.com')->first();
        if (! $requester instanceof User) {
            $this->command?->warn('Skipping ReportingExportsModuleSeeder: requester user admin@example.com not found.');

            return;
        }

        CompanyMembership::query()->updateOrCreate(
            [
                'company_id' => (string) $company->id,
                'user_id' => (string) $requester->id,
            ],
            [
                'company_role' => CompanyMembership::ROLE_COMPANY_ADMIN,
                'membership_status' => CompanyMembership::STATUS_ACTIVE,
            ]
        );

        $job = Job::withoutGlobalScopes()->firstOrCreate(
            [
                'company_id' => (string) $company->id,
                'title' => 'Reporting Export Pilot Role',
            ],
            [
                'status' => Job::STATUS_PUBLISHED,
                'location' => 'Remote',
                'blind_mode_active' => true,
            ]
        );

        $screeningStage = JobPipelineStage::withoutGlobalScopes()->updateOrCreate(
            ['job_id' => (string) $job->id, 'stage_key' => 'screening'],
            [
                'stage_label' => 'Screening',
                'display_order' => 1,
                'is_terminal' => false,
            ]
        );

        $interviewStage = JobPipelineStage::withoutGlobalScopes()->updateOrCreate(
            ['job_id' => (string) $job->id, 'stage_key' => 'interview'],
            [
                'stage_label' => 'Interview',
                'display_order' => 2,
                'is_terminal' => false,
            ]
        );

        foreach (range(1, 4) as $index) {
            $candidate = Candidate::withoutGlobalScopes()->create([
                'company_id' => (string) $company->id,
                'full_name' => 'Export Sample Candidate '.$index,
                'email' => 'export.sample.'.$index.'.'.Str::lower(Str::random(6)).'@example.test',
                'phone' => null,
                'location' => 'Remote',
            ]);

            Application::withoutGlobalScopes()->create([
                'company_id' => (string) $company->id,
                'candidate_id' => (string) $candidate->id,
                'job_id' => (string) $job->id,
                'current_stage_id' => (string) ($index <= 2 ? $screeningStage->id : $interviewStage->id),
                'status' => Application::STATUS_ACTIVE,
                'source_type' => $index <= 2 ? 'referral' : 'career_page',
                'source_detail' => null,
                'utm_source' => null,
                'utm_campaign' => null,
                'utm_medium' => null,
            ]);
        }

        $dashboardExport = Export::withoutGlobalScopes()->create([
            'company_id' => (string) $company->id,
            'export_type' => Export::TYPE_DASHBOARD_OVERVIEW,
            'requested_by_user_id' => (string) $requester->id,
            'filters_json' => [
                'department_id' => null,
                'job_id' => (string) $job->id,
                'date_range' => 'all',
                'date_from' => null,
                'date_to' => null,
            ],
            'format' => Export::FORMAT_CSV,
            'status' => Export::STATUS_QUEUED,
            'file_url' => null,
        ]);

        $candidateExport = Export::withoutGlobalScopes()->create([
            'company_id' => (string) $company->id,
            'export_type' => Export::TYPE_CANDIDATE_LIST,
            'requested_by_user_id' => (string) $requester->id,
            'filters_json' => [
                'q' => null,
                'job_id' => (string) $job->id,
                'stage_id' => (string) $screeningStage->id,
                'status' => Application::STATUS_ACTIVE,
                'source_type' => null,
                'date_from' => null,
                'date_to' => null,
                'application_id' => null,
            ],
            'format' => Export::FORMAT_PDF,
            'status' => Export::STATUS_QUEUED,
            'file_url' => null,
        ]);

        GenerateExportJob::dispatchSync((string) $dashboardExport->id);
        GenerateExportJob::dispatchSync((string) $candidateExport->id);

        $this->command?->info('Reporting exports sample data seeded (1 dashboard export + 1 filtered candidate export).');
    }
}

