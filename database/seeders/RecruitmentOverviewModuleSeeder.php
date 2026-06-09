<?php

namespace Database\Seeders;

use App\Models\Application;
use App\Models\ApplicationActivityEvent;
use App\Models\ApplicationScoring;
use App\Models\Candidate;
use App\Models\Company;
use App\Models\Department;
use App\Models\Job;
use App\Models\JobPipelineStage;
use Illuminate\Database\Seeder;

class RecruitmentOverviewModuleSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::query()->where('slug', 'numa-demo')->first()
            ?? Company::query()->where('status', Company::STATUS_ACTIVE)->first();

        if (! $company instanceof Company) {
            return;
        }

        $existingCandidates = Candidate::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('email', 'like', 'analytics.candidate%')
            ->get();

        if ($existingCandidates->isNotEmpty()) {
            Candidate::withoutGlobalScopes()
                ->whereIn('id', $existingCandidates->pluck('id')->all())
                ->delete();
        }

        $engineering = Department::withoutGlobalScopes()->updateOrCreate(
            ['company_id' => $company->id, 'name' => 'Engineering']
        );
        $growth = Department::withoutGlobalScopes()->updateOrCreate(
            ['company_id' => $company->id, 'name' => 'Growth']
        );

        $jobs = collect([
            Job::withoutGlobalScopes()->updateOrCreate(
                ['company_id' => $company->id, 'title' => 'Backend Engineer'],
                ['department_id' => $engineering->id, 'status' => Job::STATUS_PUBLISHED]
            ),
            Job::withoutGlobalScopes()->updateOrCreate(
                ['company_id' => $company->id, 'title' => 'Frontend Engineer'],
                ['department_id' => $engineering->id, 'status' => Job::STATUS_PUBLISHED]
            ),
            Job::withoutGlobalScopes()->updateOrCreate(
                ['company_id' => $company->id, 'title' => 'Growth Specialist'],
                ['department_id' => $growth->id, 'status' => Job::STATUS_PUBLISHED]
            ),
        ]);

        $pipelineTemplate = [
            ['key' => 'applied', 'label' => 'Applied', 'order' => 1, 'terminal' => false],
            ['key' => 'screening', 'label' => 'Screening', 'order' => 2, 'terminal' => false],
            ['key' => 'interview', 'label' => 'Interview', 'order' => 3, 'terminal' => false],
            ['key' => 'offer', 'label' => 'Offer', 'order' => 4, 'terminal' => false],
            ['key' => 'hired', 'label' => 'Hired', 'order' => 5, 'terminal' => true],
            ['key' => 'rejected', 'label' => 'Rejected', 'order' => 6, 'terminal' => true],
        ];

        $stagesByJob = [];
        foreach ($jobs as $job) {
            foreach ($pipelineTemplate as $stageDefinition) {
                $stage = JobPipelineStage::withoutGlobalScopes()->updateOrCreate(
                    ['job_id' => $job->id, 'stage_key' => $stageDefinition['key']],
                    [
                        'stage_label' => $stageDefinition['label'],
                        'display_order' => $stageDefinition['order'],
                        'is_terminal' => $stageDefinition['terminal'],
                    ]
                );

                $stagesByJob[(string) $job->id][$stageDefinition['key']] = $stage;
            }
        }

        $stagePlan = [
            'applied', 'applied', 'applied', 'applied', 'applied', 'applied',
            'screening', 'screening', 'screening', 'screening', 'screening',
            'interview', 'interview', 'interview', 'interview',
            'offer', 'offer', 'offer',
            'hired',
            'rejected',
        ];

        $sourcePlan = [
            'career_page', 'job_board', 'linkedin', 'referral', 'career_page',
            'linkedin', 'job_board', 'career_page', 'referral', 'linkedin',
            'career_page', 'job_board', 'linkedin', 'career_page', 'referral',
            'linkedin', 'career_page', 'job_board', 'referral', 'career_page',
        ];

        foreach (range(1, 20) as $index) {
            $job = $jobs->values()->get(($index - 1) % $jobs->count());
            if (! $job instanceof Job) {
                continue;
            }

            $stageKey = $stagePlan[$index - 1] ?? 'applied';
            $stage = $stagesByJob[(string) $job->id][$stageKey] ?? null;
            if (! $stage instanceof JobPipelineStage) {
                continue;
            }

            $status = match ($stageKey) {
                'hired' => Application::STATUS_HIRED,
                'rejected' => Application::STATUS_REJECTED,
                default => Application::STATUS_ACTIVE,
            };

            $candidate = Candidate::withoutGlobalScopes()->create([
                'company_id' => $company->id,
                'user_id' => null,
                'full_name' => 'Analytics Candidate '.$index,
                'email' => 'analytics.candidate'.$index.'@example.test',
                'phone' => null,
                'location' => 'Remote',
                'created_at' => now()->subDays(120 - ($index * 2)),
                'updated_at' => now()->subDays(120 - ($index * 2)),
            ]);

            $createdAt = now()->subDays(95 - ($index * 3));
            $updatedAt = $createdAt->copy()->addHours(8 + ($index * 5));
            $lastActivityAt = $updatedAt->copy()->addHours(($index * 7) % 96);

            $application = Application::withoutGlobalScopes()->create([
                'company_id' => $company->id,
                'candidate_id' => $candidate->id,
                'job_id' => $job->id,
                'current_stage_id' => $stage->id,
                'status' => $status,
                'source_type' => $sourcePlan[$index - 1] ?? 'career_page',
                'source_detail' => null,
                'utm_source' => null,
                'utm_campaign' => null,
                'utm_medium' => null,
                'created_at' => $createdAt,
                'updated_at' => $lastActivityAt,
            ]);

            ApplicationScoring::withoutGlobalScopes()->updateOrCreate(
                ['application_id' => $application->id],
                [
                    'company_id' => $company->id,
                    'global_match_score' => (float) (58 + (($index * 3) % 38)),
                    'vrin_json' => ['acquired_skills' => [], 'missing_skills' => []],
                    'xai_summary' => 'Seeded analytics score for module 15 testing.',
                    'updated_at' => $lastActivityAt,
                ]
            );

            ApplicationActivityEvent::withoutGlobalScopes()->create([
                'company_id' => $company->id,
                'application_id' => $application->id,
                'event_type' => 'application.created',
                'payload' => ['seed' => 'module_15'],
                'actor_user_id' => null,
                'created_at' => $lastActivityAt,
            ]);
        }

        $this->command?->info('Recruitment Overview module sample data seeded (20 applications).');
    }
}

