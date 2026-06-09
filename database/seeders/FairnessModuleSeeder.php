<?php

namespace Database\Seeders;

use App\Models\Application;
use App\Models\ApplicationScoring;
use App\Models\BiasAlert;
use App\Models\Candidate;
use App\Models\Company;
use App\Models\Job;
use App\Models\JobPipelineStage;
use App\Services\Fairness\FairnessAuditService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class FairnessModuleSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('bias_audit_stats') || ! Schema::hasTable('bias_alerts')) {
            $this->command?->warn('Skipping FairnessModuleSeeder: fairness tables not found. Run migrations first.');

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

        $job = Job::withoutGlobalScopes()->updateOrCreate(
            [
                'company_id' => (string) $company->id,
                'title' => 'Fairness Audit Pilot Role',
            ],
            [
                'status' => Job::STATUS_PUBLISHED,
                'location' => 'Remote',
                'blind_mode_active' => true,
            ]
        );

        $screeningStage = JobPipelineStage::withoutGlobalScopes()->updateOrCreate(
            [
                'job_id' => (string) $job->id,
                'stage_key' => 'screening',
            ],
            [
                'stage_label' => 'Screening',
                'display_order' => 1,
                'is_terminal' => false,
            ]
        );

        $interviewStage = JobPipelineStage::withoutGlobalScopes()->updateOrCreate(
            [
                'job_id' => (string) $job->id,
                'stage_key' => 'interview',
            ],
            [
                'stage_label' => 'Interview',
                'display_order' => 2,
                'is_terminal' => false,
            ]
        );

        $bucketStart = CarbonImmutable::now()->utc()->startOfDay();

        $this->seedApplicationsForStage(
            companyId: (string) $company->id,
            jobId: (string) $job->id,
            stageId: (string) $screeningStage->id,
            referralCount: 10,
            nonReferralCount: 2,
            highScoreCount: 10,
            bucketStart: $bucketStart
        );

        $this->seedApplicationsForStage(
            companyId: (string) $company->id,
            jobId: (string) $job->id,
            stageId: (string) $interviewStage->id,
            referralCount: 3,
            nonReferralCount: 3,
            highScoreCount: 3,
            bucketStart: $bucketStart
        );

        $audit = app(FairnessAuditService::class);
        $audit->recompute((string) $company->id, (string) $job->id, (string) $screeningStage->id, $bucketStart);
        $audit->recompute((string) $company->id, (string) $job->id, (string) $interviewStage->id, $bucketStart);

        $criticalAlertExists = BiasAlert::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('job_id', $job->id)
            ->whereNull('resolved_at')
            ->exists();

        if ($criticalAlertExists) {
            $this->command?->info('Fairness module sample data seeded (includes critical impact-ratio alert case).');
        } else {
            $this->command?->warn('Fairness module sample data seeded but no active alert was created.');
        }
    }

    private function seedApplicationsForStage(
        string $companyId,
        string $jobId,
        string $stageId,
        int $referralCount,
        int $nonReferralCount,
        int $highScoreCount,
        CarbonImmutable $bucketStart
    ): void {
        $total = $referralCount + $nonReferralCount;

        foreach (range(1, $total) as $index) {
            $candidate = Candidate::withoutGlobalScopes()->create([
                'company_id' => $companyId,
                'full_name' => 'Fairness Candidate '.Str::upper(Str::random(6)),
                'email' => 'fairness.'.$stageId.'.'.$index.'.'.Str::lower(Str::random(5)).'@example.com',
                'phone' => null,
                'location' => 'Remote',
            ]);

            $sourceType = $index <= $referralCount ? 'referral' : 'career_page';
            $createdAt = $bucketStart->addMinutes($index * 5);

            // Use saveQuietly() to bypass ApplicationObserver::created(), which synchronously
            // calls CandidateAnalysisService::recomputeForApplication() and pre-creates an
            // ApplicationScoring row. If that INSERT commits before the explicit scoring
            // create below, MySQL's REPEATABLE READ snapshot causes a UniqueConstraintViolation.
            $application = new Application([
                'id' => (string) Str::orderedUuid(),
                'company_id' => $companyId,
                'candidate_id' => (string) $candidate->id,
                'job_id' => $jobId,
                'current_stage_id' => $stageId,
                'status' => Application::STATUS_ACTIVE,
                'source_type' => $sourceType,
                'source_detail' => null,
                'utm_source' => null,
                'utm_campaign' => null,
                'utm_medium' => null,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
            $application->saveQuietly();

            $isLowScore = $index > $highScoreCount;
            $score = $isLowScore ? 42.0 : 86.0;
            $missingSkills = $isLowScore ? ['Domain depth', 'System design', 'Cloud security'] : ['System design'];

            ApplicationScoring::withoutGlobalScopes()->create([
                'application_id' => (string) $application->id,
                'company_id' => $companyId,
                'global_match_score' => $score,
                'vrin_json' => [
                    'acquired_skills' => ['Communication', 'Execution'],
                    'missing_skills' => $missingSkills,
                ],
                'xai_summary' => 'Seeded fairness sample scoring.',
                'updated_at' => $createdAt,
            ]);
        }
    }
}

