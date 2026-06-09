<?php

namespace Database\Seeders;

use App\Models\Application;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\Job;
use App\Models\JobPipelineStage;
use App\Models\Referral;
use App\Models\User;
use App\Services\Referral\ReferralService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ReferralModuleSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('referrals')) {
            $this->command?->warn('Skipping ReferralModuleSeeder: referrals table not found. Run migrations first.');

            return;
        }

        $company = Company::query()->where('slug', 'numa-demo')->first()
            ?? Company::query()->where('status', Company::STATUS_ACTIVE)->first();

        if (! $company instanceof Company) {
            return;
        }

        if (! Job::withoutGlobalScopes()->where('company_id', $company->id)->exists()) {
            $this->call(RecruitmentOverviewModuleSeeder::class);
        }

        $employee = User::query()->whereHas('memberships', function ($query) use ($company): void {
            $query->where('company_id', $company->id)
                ->where('membership_status', CompanyMembership::STATUS_ACTIVE)
                ->where('company_role', CompanyMembership::ROLE_EMPLOYEE);
        })->first();

        $recruiter = User::query()->whereHas('memberships', function ($query) use ($company): void {
            $query->where('company_id', $company->id)
                ->where('membership_status', CompanyMembership::STATUS_ACTIVE)
                ->whereIn('company_role', [
                    CompanyMembership::ROLE_COMPANY_ADMIN,
                    CompanyMembership::ROLE_RECRUITER,
                    CompanyMembership::ROLE_MANAGER,
                ]);
        })->first();

        if (! $employee instanceof User || ! $recruiter instanceof User) {
            return;
        }

        $referrals = collect([
            [
                'email' => 'referral.one@example.test',
                'name' => 'Referral One',
                'linkedin' => 'https://www.linkedin.com/in/referral-one',
                'days_ago' => 3,
            ],
            [
                'email' => 'referral.two@example.test',
                'name' => 'Referral Two',
                'linkedin' => 'https://www.linkedin.com/in/referral-two',
                'days_ago' => 2,
            ],
            [
                'email' => 'referral.three@example.test',
                'name' => 'Referral Three',
                'linkedin' => null,
                'days_ago' => 1,
            ],
        ])->map(function (array $entry) use ($company, $employee): Referral {
            return Referral::withoutGlobalScopes()->updateOrCreate(
                [
                    'company_id' => (string) $company->id,
                    'referrer_user_id' => (string) $employee->id,
                    'candidate_email' => Str::lower((string) $entry['email']),
                ],
                [
                    'candidate_name' => (string) $entry['name'],
                    'candidate_linkedin_url' => $entry['linkedin'],
                    'resume_file_url' => null,
                    'status' => Referral::STATUS_SUBMITTED,
                    'created_at' => now()->subDays((int) $entry['days_ago']),
                    'updated_at' => now()->subDays((int) $entry['days_ago']),
                ]
            );
        })->values();

        $job = Job::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('status', Job::STATUS_PUBLISHED)
            ->orderBy('title')
            ->first();

        if (! $job instanceof Job || $referrals->isEmpty()) {
            return;
        }

        $firstReferral = $referrals->first();
        if (! $firstReferral instanceof Referral) {
            return;
        }

        $linkedApplication = Application::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('source_type', 'referral')
            ->where('source_detail', (string) $firstReferral->id)
            ->first();

        if (! $linkedApplication instanceof Application) {
            try {
                $linkedApplication = app(ReferralService::class)->convertToApplication($firstReferral, $job, $recruiter);
            } catch (ValidationException) {
                $linkedApplication = null;
            }
        }

        if (! $linkedApplication instanceof Application) {
            return;
        }

        $terminalStage = JobPipelineStage::withoutGlobalScopes()
            ->where('job_id', $job->id)
            ->where('is_terminal', true)
            ->get()
            ->sortBy(static function (JobPipelineStage $stage): int {
                $marker = Str::lower((string) $stage->stage_key.' '.$stage->stage_label);
                if (str_contains($marker, 'hire')) {
                    return 0;
                }
                if (str_contains($marker, 'reject')) {
                    return 1;
                }

                return 2;
            })
            ->first();

        if (! $terminalStage instanceof JobPipelineStage) {
            return;
        }

        $marker = Str::lower((string) $terminalStage->stage_key.' '.$terminalStage->stage_label);
        $terminalStatus = str_contains($marker, 'hire')
            ? Application::STATUS_HIRED
            : Application::STATUS_REJECTED;

        $linkedApplication->forceFill([
            'current_stage_id' => (string) $terminalStage->id,
            'status' => $terminalStatus,
            'updated_at' => now(),
        ])->save();

        $this->command?->info('Referral module sample data seeded (3 referrals, 1 converted, terminal outcome set).');
    }
}

