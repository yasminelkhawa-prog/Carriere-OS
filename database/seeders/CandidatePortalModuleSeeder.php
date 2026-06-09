<?php

namespace Database\Seeders;

use App\Models\Application;
use App\Models\Candidate;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\Job;
use App\Models\JobPipelineStage;
use App\Models\Profile;
use App\Models\ReverseFeedback;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CandidatePortalModuleSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::query()->where('slug', 'numa-demo')->first()
            ?? Company::query()->where('status', Company::STATUS_ACTIVE)->first();

        if (! $company instanceof Company) {
            return;
        }

        $candidateUser = User::query()->updateOrCreate(
            ['email' => 'candidate.portal.demo@example.com'],
            [
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
                'platform_role' => User::PLATFORM_NONE,
                'active' => true,
            ]
        );

        Profile::query()->updateOrCreate(
            ['user_id' => $candidateUser->id],
            [
                'full_name' => 'Candidate Portal Demo',
                'locale' => 'en',
                'avatar_url' => null,
            ]
        );

        CompanyMembership::query()->updateOrCreate(
            [
                'company_id' => $company->id,
                'user_id' => $candidateUser->id,
            ],
            [
                'company_role' => CompanyMembership::ROLE_CANDIDATE,
                'membership_status' => CompanyMembership::STATUS_ACTIVE,
            ]
        );

        $candidate = Candidate::withoutGlobalScopes()->updateOrCreate(
            [
                'company_id' => $company->id,
                'email' => Str::lower((string) $candidateUser->email),
            ],
            [
                'user_id' => $candidateUser->id,
                'full_name' => 'Candidate Portal Demo',
                'phone' => '+1-555-0199',
                'location' => 'Remote',
            ]
        );

        $activeJob = Job::withoutGlobalScopes()->updateOrCreate(
            [
                'company_id' => $company->id,
                'title' => 'Portal Active Role',
            ],
            [
                'location' => 'Remote',
                'status' => Job::STATUS_PUBLISHED,
                'blind_mode_active' => false,
            ]
        );

        $terminalJob = Job::withoutGlobalScopes()->updateOrCreate(
            [
                'company_id' => $company->id,
                'title' => 'Portal Terminal Role',
            ],
            [
                'location' => 'Remote',
                'status' => Job::STATUS_PUBLISHED,
                'blind_mode_active' => false,
            ]
        );

        $activeStage = JobPipelineStage::withoutGlobalScopes()->updateOrCreate(
            [
                'job_id' => $activeJob->id,
                'stage_key' => 'screening',
            ],
            [
                'stage_label' => 'Screening',
                'display_order' => 1,
                'is_terminal' => false,
            ]
        );

        $terminalStage = JobPipelineStage::withoutGlobalScopes()->updateOrCreate(
            [
                'job_id' => $terminalJob->id,
                'stage_key' => 'rejected',
            ],
            [
                'stage_label' => 'Rejected',
                'display_order' => 1,
                'is_terminal' => true,
            ]
        );

        $activeApplication = Application::withoutGlobalScopes()->updateOrCreate(
            [
                'company_id' => $company->id,
                'candidate_id' => $candidate->id,
                'job_id' => $activeJob->id,
            ],
            [
                'current_stage_id' => $activeStage->id,
                'status' => Application::STATUS_ACTIVE,
                'source_type' => 'career_page',
                'source_detail' => null,
            ]
        );

        $terminalApplication = Application::withoutGlobalScopes()->updateOrCreate(
            [
                'company_id' => $company->id,
                'candidate_id' => $candidate->id,
                'job_id' => $terminalJob->id,
            ],
            [
                'current_stage_id' => $terminalStage->id,
                'status' => Application::STATUS_REJECTED,
                'source_type' => 'career_page',
                'source_detail' => null,
            ]
        );

        ReverseFeedback::withoutGlobalScopes()->updateOrCreate(
            [
                'application_id' => $terminalApplication->id,
            ],
            [
                'company_id' => $company->id,
                'recruiter_user_id' => null,
                'rating_clarity' => 4,
                'rating_speed' => 3,
                'rating_kindness' => 5,
                'comment' => 'Clear process, but response time can improve.',
                'is_anonymous' => true,
                'created_at' => now(),
            ]
        );

        $this->command?->info('Candidate Portal module sample data seeded.');
        $this->command?->line('Candidate: candidate.portal.demo@example.com / password');
        $this->command?->line('Includes one active and one terminal application, with reverse feedback on terminal.');
        $this->command?->line('Portal URL: http://127.0.0.1:8000/candidate/'.$company->slug);
    }
}


