<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Admin\Concerns\ResolvesManagedCompany;
use App\Models\BiasAlert;
use App\Models\BiasAuditStat;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\Job;
use App\Models\User;
use App\Services\Fairness\FairnessAuditService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FairnessDashboardController extends Controller
{
    use ResolvesManagedCompany;

    private const PERIOD_OPTIONS = [
        '7d' => 7,
        '30d' => 30,
        '90d' => 90,
        'all' => null,
    ];

    public function index(Request $request): View
    {
        [$companyId, $companies] = $this->resolveCompanyContext($request);
        $actor = $request->user();

        if (! $actor instanceof User || $companyId === null) {
            return view('dashboard.fairness', [
                'requiresCompanySelection' => true,
                'companies' => $companies,
                'filters' => [
                    'job_id' => null,
                    'dimension_key' => FairnessAuditService::dimensions()[0],
                    'period' => '30d',
                ],
                'jobs' => collect(),
                'dimensions' => $this->dimensionOptions(),
                'selectedDimensionKey' => FairnessAuditService::dimensions()[0],
                'selectedDimensionExplanation' => FairnessAuditService::dimensionExplanation(FairnessAuditService::dimensions()[0]),
                'selectedDimensionGroups' => FairnessAuditService::dimensionGroupLabels(FairnessAuditService::dimensions()[0]),
                'periodOptions' => $this->periodLabels(),
                'equalityPulse' => null,
                'diversityFunnel' => collect(),
                'hasSufficientData' => false,
                'alerts' => collect(),
                'showAlertsProminently' => false,
                'canResolveAlerts' => false,
            ]);
        }

        $filters = $this->validatedFilters($request, $companyId);
        $jobs = Job::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->orderBy('title')
            ->get(['id', 'title']);

        $selectedDimensionKey = $filters['dimension_key'] ?: FairnessAuditService::dimensions()[0];

        $stats = $this->statsQuery($companyId, $filters, $selectedDimensionKey)->get();
        $hasSufficientData = $this->hasSufficientData($stats);
        $equalityPulse = $hasSufficientData ? $this->buildEqualityPulse($stats) : null;
        $diversityFunnel = $hasSufficientData ? $this->buildDiversityFunnel($stats) : collect();

        $activeRole = $this->activeMembershipRole($actor, $companyId);
        $showAlertsProminently = $actor->isSuperadmin() || in_array((string) $activeRole, [
            CompanyMembership::ROLE_COMPANY_ADMIN,
            CompanyMembership::ROLE_MANAGER,
        ], true);

        $alerts = $this->alertsQuery($companyId, $filters)->get();
        $canResolveAlerts = $showAlertsProminently;

        return view('dashboard.fairness', [
            'requiresCompanySelection' => false,
            'companies' => $companies,
            'filters' => $filters,
            'jobs' => $jobs,
            'dimensions' => $this->dimensionOptions(),
            'selectedDimensionKey' => $selectedDimensionKey,
            'selectedDimensionExplanation' => FairnessAuditService::dimensionExplanation($selectedDimensionKey),
            'selectedDimensionGroups' => FairnessAuditService::dimensionGroupLabels($selectedDimensionKey),
            'periodOptions' => $this->periodLabels(),
            'equalityPulse' => $equalityPulse,
            'diversityFunnel' => $diversityFunnel,
            'hasSufficientData' => $hasSufficientData,
            'alerts' => $alerts,
            'showAlertsProminently' => $showAlertsProminently,
            'canResolveAlerts' => $canResolveAlerts,
        ]);
    }

    public function resolveAlert(Request $request, BiasAlert $biasAlert): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $companyId = $this->managedCompanyId($request, true);
        abort_unless(is_string($companyId) && $companyId !== '', 403);
        abort_unless((string) $biasAlert->company_id === $companyId, 403);

        $activeRole = $this->activeMembershipRole($actor, $companyId);
        $canResolve = $actor->isSuperadmin() || in_array((string) $activeRole, [
            CompanyMembership::ROLE_COMPANY_ADMIN,
            CompanyMembership::ROLE_MANAGER,
        ], true);

        abort_unless($canResolve, 403);

        if ($biasAlert->resolved_at === null) {
            $biasAlert->forceFill(['resolved_at' => now()])->save();
        }

        return back()->with('status', __('ui.fairness.alerts.resolved'));
    }

    /**
     * @return array{0: ?string, 1: Collection<int, Company>}
     */
    private function resolveCompanyContext(Request $request): array
    {
        $user = $request->user();
        $companies = collect();

        if ($user instanceof User && $user->isSuperadmin()) {
            $companies = Company::query()
                ->where('status', Company::STATUS_ACTIVE)
                ->orderBy('name')
                ->get(['id', 'name']);
        }

        $companyId = $this->managedCompanyId($request, false);
        if ($companyId !== null) {
            $exists = Company::query()
                ->where('id', $companyId)
                ->where('status', Company::STATUS_ACTIVE)
                ->exists();
            if (! $exists) {
                $companyId = null;
            }
        }

        return [$companyId, $companies];
    }

    /**
     * @return array{job_id: ?string, dimension_key: string, period: string, date_from: ?CarbonImmutable, date_to: ?CarbonImmutable}
     */
    private function validatedFilters(Request $request, string $companyId): array
    {
        $validated = $request->validate([
            'job_id' => [
                'nullable',
                'uuid',
                Rule::exists('jobs', 'id')->where(static fn ($query) => $query->where('company_id', $companyId)),
            ],
            'dimension_key' => ['nullable', Rule::in(FairnessAuditService::dimensions())],
            'period' => ['nullable', Rule::in(array_keys(self::PERIOD_OPTIONS))],
        ]);

        $period = (string) ($validated['period'] ?? '30d');
        if (! array_key_exists($period, self::PERIOD_OPTIONS)) {
            $period = '30d';
        }

        $days = self::PERIOD_OPTIONS[$period];
        $now = CarbonImmutable::now();

        return [
            'job_id' => isset($validated['job_id']) ? (string) $validated['job_id'] : null,
            'dimension_key' => (string) ($validated['dimension_key'] ?? FairnessAuditService::dimensions()[0]),
            'period' => $period,
            'date_from' => is_int($days) ? $now->subDays($days)->startOfDay() : null,
            'date_to' => is_int($days) ? $now->endOfDay() : null,
        ];
    }

    /**
     * @param array{job_id: ?string, dimension_key: string, period: string, date_from: ?CarbonImmutable, date_to: ?CarbonImmutable} $filters
     */
    private function statsQuery(string $companyId, array $filters, string $dimensionKey)
    {
        return BiasAuditStat::withoutGlobalScopes()
            ->with('stage:id,stage_label,display_order')
            ->where('company_id', $companyId)
            ->where('dimension_key', $dimensionKey)
            ->when(
                is_string($filters['job_id']) && $filters['job_id'] !== '',
                fn ($query) => $query->where('job_id', $filters['job_id'])
            )
            ->when(
                $filters['date_from'] instanceof CarbonImmutable,
                fn ($query) => $query->where('time_bucket_start', '>=', $filters['date_from'])
            )
            ->when(
                $filters['date_to'] instanceof CarbonImmutable,
                fn ($query) => $query->where('time_bucket_end', '<=', $filters['date_to'])
            )
            ->orderBy('time_bucket_start');
    }

    /**
     * @param array{job_id: ?string, dimension_key: string, period: string, date_from: ?CarbonImmutable, date_to: ?CarbonImmutable} $filters
     */
    private function alertsQuery(string $companyId, array $filters)
    {
        return BiasAlert::withoutGlobalScopes()
            ->with('job:id,title')
            ->where('company_id', $companyId)
            ->whereNull('resolved_at')
            ->when(
                is_string($filters['job_id']) && $filters['job_id'] !== '',
                fn ($query) => $query->where('job_id', $filters['job_id'])
            )
            ->when(
                $filters['date_from'] instanceof CarbonImmutable,
                fn ($query) => $query->where('created_at', '>=', $filters['date_from'])
            )
            ->when(
                $filters['date_to'] instanceof CarbonImmutable,
                fn ($query) => $query->where('created_at', '<=', $filters['date_to'])
            )
            ->orderByRaw("CASE severity WHEN 'critical' THEN 4 WHEN 'high' THEN 3 WHEN 'medium' THEN 2 ELSE 1 END DESC")
            ->orderByDesc('created_at');
    }

    /**
     * @param Collection<int, BiasAuditStat> $stats
     */
    private function hasSufficientData(Collection $stats): bool
    {
        if ($stats->isEmpty()) {
            return false;
        }

        $totalPopulation = $stats->sum(
            static fn (BiasAuditStat $stat): int => (int) $stat->group_a_count + (int) $stat->group_b_count
        );

        return $totalPopulation >= 8;
    }

    /**
     * @param Collection<int, BiasAuditStat> $stats
     * @return array{fairness_index: float, impact_ratio: float, status_key: string}
     */
    private function buildEqualityPulse(Collection $stats): array
    {
        $weightedFairness = 0.0;
        $weightedImpact = 0.0;
        $totalWeight = 0.0;

        foreach ($stats as $stat) {
            $weight = max(1, ((int) $stat->group_a_count + (int) $stat->group_b_count));
            $weightedFairness += ((float) $stat->fairness_index * $weight);
            $weightedImpact += ((float) $stat->impact_ratio * $weight);
            $totalWeight += $weight;
        }

        $fairnessIndex = $totalWeight > 0 ? round($weightedFairness / $totalWeight, 2) : 0.0;
        $impactRatio = $totalWeight > 0 ? round($weightedImpact / $totalWeight, 4) : 0.0;

        $statusKey = match (true) {
            $impactRatio < FairnessAuditService::IMPACT_RATIO_ALERT_THRESHOLD => 'critical',
            $impactRatio < 0.90 => 'watch',
            default => 'healthy',
        };

        return [
            'fairness_index' => $fairnessIndex,
            'impact_ratio' => $impactRatio,
            'status_key' => $statusKey,
        ];
    }

    /**
     * @param Collection<int, BiasAuditStat> $stats
     * @return Collection<int, array{
     *   stage_label: string,
     *   group_a_count: int,
     *   group_b_count: int,
     *   impact_ratio: float,
     *   group_a_percent: float,
     *   group_b_percent: float
     * }>
     */
    private function buildDiversityFunnel(Collection $stats): Collection
    {
        $grouped = $stats
            ->groupBy(fn (BiasAuditStat $stat): string => (string) $stat->stage_id)
            ->map(function (Collection $stageStats): array {
                $first = $stageStats->sortByDesc('time_bucket_start')->first();
                $stageLabel = (string) ($first?->stage?->stage_label ?? __('ui.fairness.not_available'));
                $stageOrder = (int) ($first?->stage?->display_order ?? PHP_INT_MAX);
                $groupACount = (int) $stageStats->sum('group_a_count');
                $groupBCount = (int) $stageStats->sum('group_b_count');
                $impactRatio = $this->impactRatioFromCounts($groupACount, $groupBCount);

                return [
                    'stage_label' => $stageLabel,
                    'display_order' => $stageOrder,
                    'group_a_count' => $groupACount,
                    'group_b_count' => $groupBCount,
                    'impact_ratio' => $impactRatio,
                ];
            })
            ->sortBy('display_order')
            ->values();

        $maxCount = max(
            1,
            (int) $grouped->max(fn (array $item): int => max((int) $item['group_a_count'], (int) $item['group_b_count']))
        );

        return $grouped
            ->map(static function (array $item) use ($maxCount): array {
                return [
                    'stage_label' => (string) $item['stage_label'],
                    'group_a_count' => (int) $item['group_a_count'],
                    'group_b_count' => (int) $item['group_b_count'],
                    'impact_ratio' => (float) $item['impact_ratio'],
                    'group_a_percent' => round(((int) $item['group_a_count'] / $maxCount) * 100, 2),
                    'group_b_percent' => round(((int) $item['group_b_count'] / $maxCount) * 100, 2),
                ];
            })
            ->values();
    }

    private function impactRatioFromCounts(int $groupACount, int $groupBCount): float
    {
        $maxCount = max($groupACount, $groupBCount);
        if ($maxCount <= 0) {
            return 1.0;
        }

        return round(min($groupACount, $groupBCount) / $maxCount, 4);
    }

    /**
     * @return Collection<int, array{key: string, label: string}>
     */
    private function dimensionOptions(): Collection
    {
        return collect(FairnessAuditService::dimensions())
            ->map(static fn (string $key): array => [
                'key' => $key,
                'label' => FairnessAuditService::dimensionLabel($key),
            ])
            ->values();
    }

    /**
     * @return array<string, string>
     */
    private function periodLabels(): array
    {
        return [
            '7d' => __('ui.fairness.periods.7d'),
            '30d' => __('ui.fairness.periods.30d'),
            '90d' => __('ui.fairness.periods.90d'),
            'all' => __('ui.fairness.periods.all'),
        ];
    }

    private function activeMembershipRole(User $actor, string $companyId): ?string
    {
        if ($actor->isSuperadmin()) {
            return null;
        }

        return $actor->memberships()
            ->where('company_id', $companyId)
            ->where('membership_status', CompanyMembership::STATUS_ACTIVE)
            ->value('company_role');
    }
}
