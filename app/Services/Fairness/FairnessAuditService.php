<?php

namespace App\Services\Fairness;

use App\Jobs\RunBiasAuditAggregationJob;
use App\Models\Application;
use App\Models\ApplicationScoring;
use App\Models\BiasAuditStat;
use App\Models\CvParsingResult;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class FairnessAuditService
{
    public const IMPACT_RATIO_ALERT_THRESHOLD = 0.80;

    public const DIMENSION_GENDER_MEN_WOMEN = 'inference.gender_men_vs_women';
    public const DIMENSION_SCHOOL_TOP_REGULAR = 'inference.school_top_vs_regular';

    public function __construct(private readonly FairnessBiasAlertService $alerts)
    {
    }

    public function queueForApplication(Application $application, ?string $originalStageId = null): void
    {
        $companyId = (string) $application->company_id;
        $jobId = (string) $application->job_id;
        $stageId = (string) $application->current_stage_id;

        if ($companyId === '' || $jobId === '' || $stageId === '') {
            return;
        }

        $bucketStart = $this->bucketStartFor($application->created_at);

        if (is_string($originalStageId) && $originalStageId !== '' && $originalStageId !== $stageId) {
            $this->queueRecompute($companyId, $jobId, $originalStageId, $bucketStart);
        }

        $this->queueRecompute($companyId, $jobId, $stageId, $bucketStart);
    }

    public function queueForScoring(ApplicationScoring $scoring): void
    {
        $application = Application::withoutGlobalScopes()
            ->where('id', $scoring->application_id)
            ->where('company_id', $scoring->company_id)
            ->first();

        if (! $application instanceof Application) {
            return;
        }

        $this->queueForApplication($application);
    }

    public function queueRecompute(
        string $companyId,
        string $jobId,
        string $stageId,
        CarbonImmutable $timeBucketStart
    ): void {
        RunBiasAuditAggregationJob::dispatch(
            companyId: $companyId,
            jobId: $jobId,
            stageId: $stageId,
            timeBucketStartIso: $timeBucketStart->toISOString()
        )->afterResponse();
    }

    public function recompute(
        string $companyId,
        string $jobId,
        string $stageId,
        CarbonImmutable $timeBucketStart
    ): void {
        $bucketStart = $timeBucketStart->utc()->startOfDay();
        $bucketEnd = $bucketStart->addDay();

        $applications = Application::withoutGlobalScopes()
            ->with(['scoring', 'job:id,company_id'])
            ->where('company_id', $companyId)
            ->where('job_id', $jobId)
            ->where('current_stage_id', $stageId)
            ->where('created_at', '>=', $bucketStart)
            ->where('created_at', '<', $bucketEnd)
            ->get([
                'id',
                'company_id',
                'job_id',
                'current_stage_id',
                'candidate_id',
                'source_type',
                'created_at',
            ]);

        if ($applications->isEmpty()) {
            BiasAuditStat::withoutGlobalScopes()
                ->where('job_id', $jobId)
                ->where('stage_id', $stageId)
                ->where('time_bucket_start', $bucketStart)
                ->delete();

            return;
        }

        foreach ($this->buildDimensionCounts($companyId, $applications) as $dimensionKey => $counts) {
            $groupACount = (int) ($counts['group_a_count'] ?? 0);
            $groupBCount = (int) ($counts['group_b_count'] ?? 0);
            $metrics = $this->buildMetrics($groupACount, $groupBCount);

            $stat = BiasAuditStat::withoutGlobalScopes()->updateOrCreate(
                [
                    'job_id' => $jobId,
                    'stage_id' => $stageId,
                    'time_bucket_start' => $bucketStart,
                    'dimension_key' => $dimensionKey,
                ],
                [
                    'company_id' => $companyId,
                    'time_bucket_end' => $bucketEnd,
                    'group_a_count' => $groupACount,
                    'group_b_count' => $groupBCount,
                    'impact_ratio' => $metrics['impact_ratio'],
                    'fairness_index' => $metrics['fairness_index'],
                    'created_at' => now(),
                ]
            );

            $this->alerts->evaluateFromStat($stat);
        }
    }

    /**
     * @return array<int, string>
     */
    public static function dimensions(): array
    {
        return [
            self::DIMENSION_GENDER_MEN_WOMEN,
            self::DIMENSION_SCHOOL_TOP_REGULAR,
        ];
    }

    /**
     * @return array<string, array{group_a_count: int, group_b_count: int}>
     */
    private function buildDimensionCounts(string $companyId, Collection $applications): array
    {
        $applicationIds = $applications->pluck('id')
            ->map(static fn ($value): string => (string) $value)
            ->filter(static fn (string $value): bool => $value !== '')
            ->values();

        $latestParsesByApplication = $this->latestCvParsesForApplications($companyId, $applicationIds);

        $menCount = 0;
        $womenCount = 0;
        $topSchoolCount = 0;
        $regularSchoolCount = 0;

        foreach ($applicationIds as $applicationId) {
            $parse = $latestParsesByApplication->get($applicationId);
            if (! $parse instanceof CvParsingResult) {
                continue;
            }

            $gender = strtolower(trim((string) ($parse->gender_inference ?? 'unknown')));
            if ($gender === 'male') {
                $menCount++;
            } elseif ($gender === 'female') {
                $womenCount++;
            }

            $tier = strtolower(trim((string) ($parse->school_background_tier ?? 'unknown')));
            if (in_array($tier, ['top_school', 'grande_ecole'], true)) {
                $topSchoolCount++;
            } elseif (in_array($tier, ['regular_university', 'faculty'], true)) {
                $regularSchoolCount++;
            }
        }

        return [
            self::DIMENSION_GENDER_MEN_WOMEN => [
                'group_a_count' => $menCount,
                'group_b_count' => $womenCount,
            ],
            self::DIMENSION_SCHOOL_TOP_REGULAR => [
                'group_a_count' => $topSchoolCount,
                'group_b_count' => $regularSchoolCount,
            ],
        ];
    }

    /**
     * @return array{impact_ratio: float, fairness_index: float}
     */
    private function buildMetrics(int $groupACount, int $groupBCount): array
    {
        $maxCount = max($groupACount, $groupBCount);
        if ($maxCount <= 0) {
            return [
                'impact_ratio' => 1.0,
                'fairness_index' => 100.0,
            ];
        }

        $impactRatio = round(min($groupACount, $groupBCount) / $maxCount, 4);

        return [
            'impact_ratio' => $impactRatio,
            'fairness_index' => round($impactRatio * 100, 2),
        ];
    }

    /**
     * @param Collection<int, string> $applicationIds
     * @return Collection<string, CvParsingResult>
     */
    private function latestCvParsesForApplications(string $companyId, Collection $applicationIds): Collection
    {
        if ($applicationIds->isEmpty()) {
            return collect();
        }

        return CvParsingResult::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereIn('application_id', $applicationIds->all())
            ->orderByDesc('updated_at')
            ->orderByDesc('created_at')
            ->get()
            ->unique(fn (CvParsingResult $result): string => (string) $result->application_id)
            ->keyBy(fn (CvParsingResult $result): string => (string) $result->application_id);
    }

    private function bucketStartFor(mixed $dateTime): CarbonImmutable
    {
        if ($dateTime instanceof CarbonImmutable) {
            return $dateTime->utc()->startOfDay();
        }

        if ($dateTime instanceof \Carbon\CarbonInterface) {
            return CarbonImmutable::instance($dateTime)->utc()->startOfDay();
        }

        return CarbonImmutable::now()->utc()->startOfDay();
    }

    public static function dimensionLabel(string $dimensionKey): string
    {
        return match ($dimensionKey) {
            self::DIMENSION_GENDER_MEN_WOMEN => __('ui.fairness.dimensions.gender_men_women'),
            self::DIMENSION_SCHOOL_TOP_REGULAR => __('ui.fairness.dimensions.school_top_regular'),
            default => $dimensionKey,
        };
    }

    public static function dimensionExplanation(string $dimensionKey): string
    {
        return match ($dimensionKey) {
            self::DIMENSION_GENDER_MEN_WOMEN => __('ui.fairness.dimension_explanations.gender_men_women'),
            self::DIMENSION_SCHOOL_TOP_REGULAR => __('ui.fairness.dimension_explanations.school_top_regular'),
            default => __('ui.fairness.dimension_explanations.fallback'),
        };
    }

    /**
     * @return array{group_a: string, group_b: string}
     */
    public static function dimensionGroupLabels(string $dimensionKey): array
    {
        return match ($dimensionKey) {
            self::DIMENSION_GENDER_MEN_WOMEN => [
                'group_a' => __('ui.fairness.groups.men'),
                'group_b' => __('ui.fairness.groups.women'),
            ],
            self::DIMENSION_SCHOOL_TOP_REGULAR => [
                'group_a' => __('ui.fairness.groups.top_grande'),
                'group_b' => __('ui.fairness.groups.regular_faculty'),
            ],
            default => [
                'group_a' => __('ui.fairness.groups.group_a'),
                'group_b' => __('ui.fairness.groups.group_b'),
            ],
        };
    }
}
