<?php

namespace Database\Seeders;

use App\Models\Application;
use App\Models\Candidate;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\Contract;
use App\Models\Job;
use App\Models\JobPipelineStage;
use App\Models\Offer;
use App\Models\OnboardingDocument;
use App\Models\OnboardingSchedule;
use App\Models\OnboardingTask;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class OfferOnboardingModuleSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::query()->where('slug', 'numa-demo')->first()
            ?? Company::query()->where('status', Company::STATUS_ACTIVE)->first();

        if (! $company instanceof Company) {
            $company = Company::query()->create([
                'name' => 'numa Demo',
                'slug' => 'numa-demo',
                'status' => Company::STATUS_ACTIVE,
                'brand_logo_url' => null,
            ]);
        }

        $candidate = Candidate::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->orderBy('created_at')
            ->first();

        if (! $candidate instanceof Candidate) {
            $candidateUser = User::query()->updateOrCreate(
                ['email' => 'offer.onboarding.candidate@example.com'],
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
                    'full_name' => 'Offer Onboarding Candidate',
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

            $candidate = Candidate::withoutGlobalScopes()->create([
                'company_id' => $company->id,
                'user_id' => $candidateUser->id,
                'full_name' => 'Offer Onboarding Candidate',
                'email' => Str::lower((string) $candidateUser->email),
                'phone' => '+1-555-0123',
                'location' => 'Remote',
            ]);
        }

        $job = Job::withoutGlobalScopes()->updateOrCreate(
            [
                'company_id' => $company->id,
                'title' => 'Offer Onboarding Demo Role',
            ],
            [
                'location' => 'Remote',
                'status' => Job::STATUS_PUBLISHED,
                'blind_mode_active' => false,
            ]
        );

        $hiredStage = JobPipelineStage::withoutGlobalScopes()->updateOrCreate(
            [
                'job_id' => $job->id,
                'stage_key' => 'hired',
            ],
            [
                'stage_label' => 'Hired',
                'display_order' => 99,
                'is_terminal' => true,
            ]
        );

        $application = Application::withoutGlobalScopes()->updateOrCreate(
            [
                'company_id' => $company->id,
                'candidate_id' => $candidate->id,
                'job_id' => $job->id,
            ],
            [
                'current_stage_id' => $hiredStage->id,
                'status' => Application::STATUS_HIRED,
                'source_type' => 'career_page',
                'source_detail' => null,
            ]
        );

        Offer::withoutGlobalScopes()->updateOrCreate(
            [
                'company_id' => $company->id,
                'application_id' => $application->id,
            ],
            [
                'offer_status' => Offer::STATUS_SENT,
                'salary_amount' => 120000.00,
                'currency' => 'USD',
                'start_date' => now()->addWeeks(3)->toDateString(),
            ]
        );

        $contractPath = 'private/onboarding/contracts/'.$company->id.'/'.$application->id.'/offer-onboarding-demo-contract.pdf';
        Storage::disk('local')->put($contractPath, 'Offer onboarding demo contract');

        Contract::withoutGlobalScopes()->updateOrCreate(
            [
                'company_id' => $company->id,
                'application_id' => $application->id,
            ],
            [
                'contract_file_url' => $contractPath,
                'contract_status' => Contract::STATUS_SIGNED,
                'signed_at' => now()->subDay(),
                'signer_user_id' => $candidate->user_id ?: null,
                'signature_method' => Contract::SIGNATURE_METHOD_TYPED,
                'audit_metadata_json' => [
                    'signature' => [
                        'typed_signature' => (string) $candidate->full_name,
                        'acknowledged' => true,
                        'signed_at' => now()->subDay()->toIso8601String(),
                        'signer_user_id' => (string) ($candidate->user_id ?? ''),
                        'ip_address' => '127.0.0.1',
                    ],
                ],
            ]
        );

        OnboardingDocument::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('application_id', $application->id)
            ->delete();

        $docOnePath = 'private/onboarding/documents/'.$company->id.'/'.$application->id.'/government-id-demo.pdf';
        $docTwoPath = 'private/onboarding/documents/'.$company->id.'/'.$application->id.'/tax-form-demo.pdf';
        Storage::disk('local')->put($docOnePath, 'Government ID demo document');
        Storage::disk('local')->put($docTwoPath, 'Tax form demo document');

        OnboardingDocument::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'application_id' => $application->id,
            'doc_type' => OnboardingDocument::TYPE_ID,
            'file_url' => $docOnePath,
            'created_at' => now()->subHours(6),
        ]);

        OnboardingDocument::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'application_id' => $application->id,
            'doc_type' => OnboardingDocument::TYPE_TAX,
            'file_url' => $docTwoPath,
            'created_at' => now()->subHours(4),
        ]);

        OnboardingSchedule::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('application_id', $application->id)
            ->delete();

        OnboardingSchedule::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'application_id' => $application->id,
            'title' => 'Day 1 Orientation',
            'start_at' => now()->addDays(7)->setTime(9, 0)->utc(),
            'end_at' => now()->addDays(7)->setTime(10, 30)->utc(),
            'location' => 'HQ - Room 2A',
            'created_at' => now(),
        ]);

        OnboardingTask::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('application_id', $application->id)
            ->delete();

        OnboardingTask::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'application_id' => $application->id,
            'task_name' => 'Submit bank details',
            'due_at' => now()->addDays(5)->utc(),
            'is_completed' => false,
        ]);

        OnboardingTask::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'application_id' => $application->id,
            'task_name' => 'Review employee handbook',
            'due_at' => now()->addDays(8)->utc(),
            'is_completed' => true,
        ]);

        $this->command?->info('Offer & Onboarding module sample data seeded.');
        $this->command?->line('Created one hired application with signed contract.');
        $this->command?->line('Created two onboarding documents for secure download testing.');
    }
}

