<?php


namespace App\Http\Controllers;

use App\Http\Controllers\Admin\Concerns\ResolvesManagedCompany;
use App\Models\Application;
use App\Models\ApplicationActivityEvent;
use App\Models\Company;
use App\Models\CompanyIntegration;
use App\Models\Department;
use App\Models\Job;
use App\Models\JobPosting;
use App\Models\User;
use App\Models\RecruitmentNeed;
use App\Services\Analysis\CandidateAnalysisService;
use App\Services\Multiposting\LinkedInApiPublisher;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DashboardController extends Controller
{
    use ResolvesManagedCompany;

    private const DATE_RANGE_OPTIONS = [
        '7d' => 7,
        '30d' => 30,
        '90d' => 90,
        '180d' => 180,
        '365d' => 365,
        'all' => null,
    ];

    private const SOURCE_COST_MAP = [
        'career_page' => 120.0,
        'job_board' => 520.0,
        'linkedin' => 640.0,
        'referral' => 180.0,
        'agency' => 1250.0,
        'campus' => 260.0,
        'direct' => 90.0,
        'unknown' => 210.0,
    ];

    private const PRIORITIES_LIMIT = 8;

    public function __construct(
        private readonly CandidateAnalysisService $candidateAnalysisService,
        private readonly LinkedInApiPublisher $linkedInApiPublisher
    ) {
    }

    public function overview(Request $request): View|RedirectResponse
    {
        [$companyId, $companies] = $this->resolveCompanyContext($request);

        if ($companyId === null) {
            return view('dashboard.overview', [
                'requiresCompanySelection' => true,
                'companies' => collect(),
            ]);
        }

        // Fetch all recruitment needs for the dashboard
        $needs = \App\Models\RecruitmentNeed::where('company_id', $companyId)->with('department')->get();
        
        $totalPostes = $needs->count();

        // 1. Féminin / Masculin (total)
        $totalSexe = $needs->whereNotNull('gender')->count() ?: 1;
        $nbFemmes = $needs->whereIn('gender', ['F', 'Femme', 'female'])->count();
        $pctFemme = round(($nbFemmes / $totalSexe) * 100);
        $pctHomme = 100 - $pctFemme;
        $genderStats = [
            'total' => $totalSexe,
            'femme' => $nbFemmes,
            'homme' => $totalSexe - $nbFemmes,
            'pct_femme' => $pctFemme,
            'pct_homme' => $pctHomme
        ];

        // 2. Statut Poste BC vs WC (Donut)
        $totalBc = $needs->where('worker_type', 'BC')->count();
        $totalWc = $needs->where('worker_type', 'WC')->count();
        $totalBcPct = ($totalBc + $totalWc) > 0 ? round(($totalBc / ($totalBc + $totalWc)) * 100) : 0;
        $totalWcPct = ($totalBc + $totalWc) > 0 ? round(($totalWc / ($totalBc + $totalWc)) * 100) : 0;

        // 3. Répartition par Site
        $siteStats = [
            'Casablanca' => ['count' => $needs->where('site', 'Casablanca')->count(), 'top' => '40%', 'left' => '30%', 'color' => '#3B82F6'],
            'Safi' => ['count' => $needs->where('site', 'Safi')->count(), 'top' => '55%', 'left' => '25%', 'color' => '#F59E0B'],
            'Agadir' => ['count' => $needs->where('site', 'Agadir')->count(), 'top' => '70%', 'left' => '20%', 'color' => '#10B981'],
            'Nador' => ['count' => $needs->where('site', 'Nador')->count(), 'top' => '20%', 'left' => '55%', 'color' => '#EC4899'],
            'Laayoune' => ['count' => $needs->where('site', 'Laayoune')->count(), 'top' => '85%', 'left' => '15%', 'color' => '#8B5CF6'],
        ];

        // 4. Outils de Sourcing (From real Needs data)
        $sourcingStats = [
            'LinkedIn' => $needs->where('sourcing_tools', 'LinkedIn')->count(),
            'Rekrute' => $needs->where('sourcing_tools', 'Rekrute')->count(),
            'Jobboards' => $needs->where('sourcing_tools', 'Jobboards')->count(),
            'Cabinet' => $needs->where('sourcing_tools', 'Cabinet')->count(),
            'Cooptation' => $needs->where('sourcing_tools', 'Cooptation')->count()
        ];

        // 5. Canal d'Acquisition
        $acquisitionStats = [
            'interne' => $needs->where('internal_posting', true)->count(),
            'externe' => $needs->where('external_sourcing', true)->count(),
            'creation' => $needs->where('recruitment_type', 'Création de poste')->count(),
            'spontanee' => 20, // Still mocked for design filling as we don't track spontaneous in Needs natively here
        ];
        $totalAcquisition = array_sum($acquisitionStats) ?: 1;

        // 6. Masse Salariale (Mock data kept as no actual salaries table exists, but proportional to WC/BC)
        $avgBc = 87000;
        $avgWc = 235000;
        $totalBcSalarial = $totalBc * $avgBc;
        $totalWcSalarial = $totalWc * $avgWc;
        $globalSalarial = $totalBcSalarial + $totalWcSalarial ?: 1;
        
        $salaryStats = [
            'total' => round($globalSalarial / 1000000, 1) . 'M',
            'bc_montant' => round($totalBcSalarial / 1000000, 1) . 'M',
            'bc_pct' => round(($totalBcSalarial / $globalSalarial) * 100, 1),
            'wc_montant' => round($totalWcSalarial / 1000000, 1) . 'M',
            'wc_pct' => round(($totalWcSalarial / $globalSalarial) * 100, 1),
            'bc_moyenne' => round($avgBc / 1000, 1) . 'K',
            'wc_moyenne' => round($avgWc / 1000, 1) . 'K',
            'ratio' => round($avgWc / $avgBc, 1) . 'x'
        ];

        // 7. Recrutements par Société
        $totalGroup = $needs->count();
        $companyStats = [
            'CIMAR' => ['count' => $totalGroup, 'pct' => 100, 'color' => '#10B981'],
            'ADT' => ['count' => 0, 'pct' => 0, 'color' => '#3B82F6'],
            'ADC' => ['count' => 0, 'pct' => 0, 'color' => '#F59E0B'],
            'GRABEMARO' => ['count' => 0, 'pct' => 0, 'color' => '#EC4899'],
        ];

        // Taux de Clôture
        $tauxCloture = ['Global' => $totalPostes > 0 ? round(($needs->where('status', 'Clôturé')->count() / $totalPostes) * 100) : 0];
        
        $parDirection = [];
        foreach ($needs->groupBy('department_id') as $deptId => $deptNeeds) {
            $deptName = $deptNeeds->first()->department->name ?? 'Unknown';
            // Simplified name for chart
            $shortName = explode(' ', $deptName)[0];
            $tauxCloture[$shortName] = $deptNeeds->count() > 0 ? round(($deptNeeds->where('status', 'Clôturé')->count() / $deptNeeds->count()) * 100) : 0;
            $parDirection[$deptName] = [
                'total' => $deptNeeds->count(),
            ];
        }

        // Statuts des postes — source: table recruitment_needs (39 postes)
        // Synchro 1:1 avec jobs (même statut, même titre)
        // "Pas encore lancé" = job status 'draft' (pas encore publié)
        // "En cours"         = job status 'published' (offre en ligne)
        // "Clôturé"          = job status 'closed' (candidat embauché)
        $statutPostes = [
            'total'      => $totalPostes,
            'pas_encore' => $needs->where('status', 'Pas encore lancé')->count(),
            'en_cours'   => $needs->where('status', 'En cours')->count(),
            'cloture'    => $needs->where('status', 'Clôturé')->count(),
        ];

        return view('dashboard.overview', [
            'requiresCompanySelection' => false,
            'companies' => collect(),
            'kpis' => [
                'totalPostes' => $totalPostes,
                'statutPostes' => $statutPostes,
                'genderStats' => $genderStats,
                'totalBc' => $totalBc,
                'totalWc' => $totalWc,
                'totalBcPct' => $totalBcPct,
                'totalWcPct' => $totalWcPct,
                'siteStats' => $siteStats,
                'sourcingStats' => $sourcingStats,
                'acquisitionStats' => $acquisitionStats,
                'totalAcquisition' => $totalAcquisition,
                'tauxCloture' => $tauxCloture,
                'parDirection' => $parDirection
            ]
        ]);
    }

    public function exportOverview(Request $request): StreamedResponse|RedirectResponse
    {
        [$companyId] = $this->resolveCompanyContext($request);
        if ($companyId === null) {
            return redirect()->route('home')->with('error', __('ui.dashboard.select_company_to_export'));
        }

        $filters = $this->validatedOverviewFilters($request, $companyId);
        $rows = $this->buildExportRows($companyId, $filters);

        $filename = 'recruitment-overview-'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'wb');
            if ($handle === false) {
                return;
            }

            fputcsv($handle, [
                'Application ID',
                'Candidate',
                'Department',
                'Job',
                'Stage',
                'Status',
                'Source',
                'AI Score',
                'Created At',
                'Last Activity At',
            ]);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    (string) $row->application_id,
                    (string) $row->candidate_name,
                    (string) ($row->department_name ?? ''),
                    (string) $row->job_title,
                    (string) $row->stage_label,
                    (string) $row->status,
                    (string) $row->source_type,
                    $row->ai_score !== null ? number_format((float) $row->ai_score, 1, '.', '') : '',
                    optional($row->created_at)->format('Y-m-d H:i:s'),
                    optional($row->last_activity_at)->format('Y-m-d H:i:s'),
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function jobs(Request $request): View|RedirectResponse
    {
        if ($request->user() instanceof User && $request->user()->isSuperadmin()) {
            return redirect()->route('platform.console');
        }

        return view('dashboard.panel', [
            'title' => __('ui.panels.jobs'),
            'description' => __('ui.panels.jobs_description'),
        ]);
    }

    public function candidates(Request $request): View|RedirectResponse
    {
        if ($request->user() instanceof User && $request->user()->isSuperadmin()) {
            return redirect()->route('platform.console');
        }

        return view('dashboard.panel', [
            'title' => __('ui.panels.candidates'),
            'description' => __('ui.panels.candidates_description'),
        ]);
    }

    public function analytics(Request $request): View|RedirectResponse
    {
        if ($request->user() instanceof User && $request->user()->isSuperadmin()) {
            return redirect()->route('platform.console');
        }

        return view('dashboard.panel', [
            'title' => __('ui.panels.analytics'),
            'description' => __('ui.panels.analytics_description'),
        ]);
    }

    public function configuration(Request $request): View|RedirectResponse
    {
        if ($request->user() instanceof User && $request->user()->isSuperadmin()) {
            return redirect()->route('platform.console');
        }

        [$companyId] = $this->resolveCompanyContext($request);
        abort_unless($companyId !== null, 403);

        $company = Company::query()
            ->with(['integrations' => fn ($query) => $query->orderBy('provider')])
            ->find($companyId);

        abort_unless($company instanceof Company, 404);

        $linkedinIntegration = $company->integrations->firstWhere('provider', CompanyIntegration::PROVIDER_LINKEDIN);
        $latestLinkedInPosting = JobPosting::withoutGlobalScopes()
            ->with([
                'job:id,title',
                'publishAttempts' => fn ($query) => $query->latest('created_at')->limit(1),
            ])
            ->where('company_id', (string) $company->id)
            ->where('platform', CompanyIntegration::PROVIDER_LINKEDIN)
            ->latest('updated_at')
            ->first();

        return view('dashboard.configuration', [
            'title' => __('ui.panels.configuration'),
            'description' => __('ui.panels.configuration_description'),
            'company' => $company,
            'linkedinIntegration' => $linkedinIntegration,
            'linkedinConfigured' => trim((string) config('services.linkedin.client_id', '')) !== ''
                && trim((string) config('services.linkedin.client_secret', '')) !== ''
                && trim((string) config('services.linkedin.redirect_uri', '')) !== '',
            'linkedinPartnerConfigured' => $this->linkedInApiPublisher->isConfigured(),
            'linkedinPartnerReadiness' => $this->linkedInApiPublisher->partnerReadiness(
                $linkedinIntegration
            ),
            'latestLinkedInPosting' => $latestLinkedInPosting,
        ]);
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
            $activeCompanyExists = Company::query()
                ->where('id', $companyId)
                ->where('status', Company::STATUS_ACTIVE)
                ->exists();

            if (! $activeCompanyExists) {
                $companyId = null;
            }
        }

        return [$companyId, $companies];
    }

    /**
     * @return array{
     *   department_id: ?string,
     *   job_id: ?string,
     *   date_range: string,
     *   date_from: ?CarbonImmutable,
     *   date_to: ?CarbonImmutable
     * }
     */
    private function validatedOverviewFilters(Request $request, string $companyId): array
    {
        $validated = $request->validate([
            'department_id' => [
                'nullable',
                'uuid',
                Rule::exists('departments', 'id')
                    ->where(static fn ($query) => $query->where('company_id', $companyId)),
            ],
            'job_id' => [
                'nullable',
                'uuid',
                Rule::exists('jobs', 'id')
                    ->where(static fn ($query) => $query->where('company_id', $companyId)),
            ],
            'date_range' => ['nullable', Rule::in(array_keys(self::DATE_RANGE_OPTIONS))],
        ]);

        $dateRange = (string) ($validated['date_range'] ?? '30d');
        if ($dateRange === '' || ! array_key_exists($dateRange, self::DATE_RANGE_OPTIONS)) {
            $dateRange = '30d';
        }

        $days = self::DATE_RANGE_OPTIONS[$dateRange];
        $now = CarbonImmutable::now();

        return [
            'department_id' => isset($validated['department_id']) ? (string) $validated['department_id'] : null,
            'job_id' => isset($validated['job_id']) ? (string) $validated['job_id'] : null,
            'date_range' => $dateRange,
            'date_from' => is_int($days) ? $now->subDays($days)->startOfDay() : null,
            'date_to' => is_int($days) ? $now->endOfDay() : null,
        ];
    }

    /**
     * @param array{
     *   department_id: ?string,
     *   job_id: ?string,
     *   date_from: ?CarbonImmutable,
     *   date_to: ?CarbonImmutable
     * } $filters
     */
    private function applyApplicationFilters(Builder $query, string $companyId, array $filters): Builder
    {
        $query->where('applications.company_id', $companyId);

        if (is_string($filters['job_id']) && $filters['job_id'] !== '') {
            $query->where('applications.job_id', $filters['job_id']);
        }

        if (is_string($filters['department_id']) && $filters['department_id'] !== '') {
            $departmentId = $filters['department_id'];
            $query->whereExists(function ($subQuery) use ($departmentId): void {
                $subQuery->selectRaw('1')
                    ->from('jobs')
                    ->whereColumn('jobs.id', 'applications.job_id')
                    ->whereColumn('jobs.company_id', 'applications.company_id')
                    ->where('jobs.department_id', $departmentId);
            });
        }

        if ($filters['date_from'] instanceof CarbonImmutable) {
            $query->where('applications.created_at', '>=', $filters['date_from']);
        }

        if ($filters['date_to'] instanceof CarbonImmutable) {
            $query->where('applications.created_at', '<=', $filters['date_to']);
        }

        return $query;
    }

    /**
     * @param array{
     *   department_id: ?string,
     *   job_id: ?string,
     *   date_from: ?CarbonImmutable,
     *   date_to: ?CarbonImmutable
     * } $filters
     * @return Builder<Application>
     */
    private function filteredApplicationsQuery(string $companyId, array $filters): Builder
    {
        $query = Application::withoutGlobalScopes()
            ->select('applications.*');

        return $this->applyApplicationFilters($query, $companyId, $filters);
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, string>
     */
    private function candidateDrilldownBase(Request $request, string $companyId, array $filters): array
    {
        $params = [
            'job_id' => (string) ($filters['job_id'] ?? ''),
            'date_from' => ($filters['date_from'] instanceof CarbonImmutable)
                ? $filters['date_from']->toDateString()
                : '',
            'date_to' => ($filters['date_to'] instanceof CarbonImmutable)
                ? $filters['date_to']->toDateString()
                : '',
        ];

        if ($request->user() instanceof User && $request->user()->isSuperadmin()) {
            $params['company_id'] = $companyId;
        }

        return array_filter($params, static fn (string $value): bool => $value !== '');
    }

    /**
     * @param array{
     *   department_id: ?string,
     *   job_id: ?string,
     *   date_range: string,
     *   date_from: ?CarbonImmutable,
     *   date_to: ?CarbonImmutable
     * } $filters
     * @param array<string, string> $candidateDrilldownBase
     */
    private function overviewCacheKey(string $companyId, array $filters, array $candidateDrilldownBase): string
    {
        $payload = [
            'company_id' => $companyId,
            'department_id' => (string) ($filters['department_id'] ?? ''),
            'job_id' => (string) ($filters['job_id'] ?? ''),
            'date_range' => (string) ($filters['date_range'] ?? ''),
            'date_from' => $filters['date_from'] instanceof CarbonImmutable
                ? $filters['date_from']->toIso8601String()
                : '',
            'date_to' => $filters['date_to'] instanceof CarbonImmutable
                ? $filters['date_to']->toIso8601String()
                : '',
            'candidate_drilldown' => $candidateDrilldownBase,
        ];

        $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES);

        return 'dashboard_overview:'.hash('sha256', is_string($encoded) ? $encoded : '');
    }

    /**
     * @param array{
     *   department_id: ?string,
     *   job_id: ?string,
     *   date_from: ?CarbonImmutable,
     *   date_to: ?CarbonImmutable
     * } $filters
     * @return array{
     *   total_applications: int,
     *   active_pipeline: int,
     *   avg_time_to_hire_days: ?float,
     *   cost_per_hire: ?float,
     *   offer_acceptance_rate: ?float
     * }
     */
    private function buildKpis(string $companyId, array $filters): array
    {
        $base = $this->filteredApplicationsQuery($companyId, $filters);

        $totalApplications = (clone $base)->count('applications.id');
        $activePipeline = (clone $base)
            ->where('applications.status', Application::STATUS_ACTIVE)
            ->count('applications.id');
        $hiredCount = (clone $base)
            ->where('applications.status', Application::STATUS_HIRED)
            ->count('applications.id');
        $terminalCount = (clone $base)
            ->whereIn('applications.status', [
                Application::STATUS_HIRED,
                Application::STATUS_REJECTED,
                Application::STATUS_WITHDRAWN,
            ])
            ->count('applications.id');

        $offerAcceptanceRate = $terminalCount > 0
            ? round(($hiredCount / $terminalCount) * 100, 1)
            : null;

        $hiredDurations = (clone $base)
            ->where('applications.status', Application::STATUS_HIRED)
            ->get(['applications.created_at', 'applications.updated_at']);

        $avgTimeToHireDays = null;
        if ($hiredDurations->isNotEmpty()) {
            $avgDuration = $hiredDurations
                ->filter(static fn (Application $application): bool => $application->created_at !== null && $application->updated_at !== null)
                ->avg(static function (Application $application): float {
                    return max(
                        0,
                        (float) $application->updated_at->diffInHours($application->created_at) / 24
                    );
                });

            if (is_numeric($avgDuration)) {
                $avgTimeToHireDays = round((float) $avgDuration, 1);
            }
        }

        $sourceCounts = (clone $base)
            ->select([])
            ->selectRaw("COALESCE(NULLIF(applications.source_type, ''), 'unknown') as source_type")
            ->selectRaw('COUNT(applications.id) as total')
            ->groupBy('source_type')
            ->get();

        $estimatedSpend = $sourceCounts->reduce(function (float $carry, object $row): float {
            $sourceType = Str::lower(trim((string) ($row->source_type ?? 'unknown')));
            $unitCost = self::SOURCE_COST_MAP[$sourceType] ?? self::SOURCE_COST_MAP['unknown'];

            return $carry + (((int) ($row->total ?? 0)) * $unitCost);
        }, 0.0);

        $costPerHire = $hiredCount > 0
            ? round($estimatedSpend / $hiredCount, 2)
            : null;

        $needsBase = \App\Models\RecruitmentNeed::where('company_id', $companyId);
        if ($filters['date_from']) {
            $needsBase->where('created_at', '>=', $filters['date_from']);
        }
        if ($filters['date_to']) {
            $needsBase->where('created_at', '<=', $filters['date_to']);
        }

        $recruitmentNeedsTotal = (clone $needsBase)->count();
        $recruitmentNeedsPending = (clone $needsBase)->where('status', 'pas encore lancé')->count();
        $recruitmentNeedsApprovedBudget = (clone $needsBase)->where('budget_approved', true)->count();

        return [
            'total_applications' => $totalApplications,
            'active_pipeline' => $activePipeline,
            'avg_time_to_hire_days' => $avgTimeToHireDays,
            'cost_per_hire' => $costPerHire,
            'offer_acceptance_rate' => $offerAcceptanceRate,
            'recruitment_needs_total' => $recruitmentNeedsTotal,
            'recruitment_needs_pending' => $recruitmentNeedsPending,
            'recruitment_needs_approved_budget' => $recruitmentNeedsApprovedBudget,
        ];
    }

    /**
     * @param array{
     *   department_id: ?string,
     *   job_id: ?string,
     *   date_from: ?CarbonImmutable,
     *   date_to: ?CarbonImmutable
     * } $filters
     * @param array<string, string> $candidateDrilldownBase
     * @return Collection<int, array{
     *   stage_id: string,
     *   stage_label: string,
     *   total: int,
     *   height_percent: float,
     *   dropoff_percent: ?float,
     *   conversion_percent: ?float,
     *   drilldown_url: string
     * }>
     */
    private function buildFunnelStages(string $companyId, array $filters, array $candidateDrilldownBase): Collection
    {
        $base = $this->filteredApplicationsQuery($companyId, $filters);

        $rows = (clone $base)
            ->join('job_pipeline_stages as stages', 'stages.id', '=', 'applications.current_stage_id')
            ->select([
                'stages.id as stage_id',
                'stages.stage_label',
                'stages.display_order',
            ])
            ->selectRaw('COUNT(applications.id) as total')
            ->groupBy('stages.id', 'stages.stage_label', 'stages.display_order')
            ->orderBy('stages.display_order')
            ->orderBy('stages.stage_label')
            ->get();

        $max = max(1, (int) ($rows->max('total') ?? 1));
        $previousCount = null;

        return $rows->map(function (object $row) use ($max, &$previousCount, $candidateDrilldownBase): array {
            $count = (int) ($row->total ?? 0);
            $dropoffPercent = null;
            $conversionPercent = null;

            if (is_int($previousCount) && $previousCount > 0) {
                $conversionPercent = round(($count / $previousCount) * 100, 1);
                $dropoffPercent = round(max(0, 100 - $conversionPercent), 1);
            }

            $previousCount = $count;

            $drilldownUrl = route('candidates.index', array_filter(array_merge($candidateDrilldownBase, [
                'stage_id' => (string) $row->stage_id,
            ]), static fn (mixed $value): bool => $value !== null && $value !== ''));

            return [
                'stage_id' => (string) $row->stage_id,
                'stage_label' => (string) $row->stage_label,
                'total' => $count,
                'height_percent' => round(($count / $max) * 100, 2),
                'dropoff_percent' => $dropoffPercent,
                'conversion_percent' => $conversionPercent,
                'drilldown_url' => $drilldownUrl,
            ];
        })->values();
    }

    /**
     * @param array{
     *   department_id: ?string,
     *   job_id: ?string,
     *   date_from: ?CarbonImmutable,
     *   date_to: ?CarbonImmutable
     * } $filters
     * @param array<string, string> $candidateDrilldownBase
     * @return Collection<int, array{
     *   source_type: string,
     *   total: int,
     *   hires: int,
     *   conversion_rate: float,
     *   avg_match_score: ?float,
     *   estimated_spend: float,
     *   estimated_cph: ?float,
     *   roi_index: float,
     *   drilldown_url: string
     * }>
     */
    private function buildSourcePerformance(string $companyId, array $filters, array $candidateDrilldownBase): Collection
    {
        $base = $this->filteredApplicationsQuery($companyId, $filters);

        $rows = (clone $base)
            ->leftJoin('application_scorings as scoring', function ($join) use ($companyId): void {
                $join->on('scoring.application_id', '=', 'applications.id')
                    ->where('scoring.company_id', '=', $companyId);
            })
            ->select([])
            ->selectRaw("COALESCE(NULLIF(applications.source_type, ''), 'unknown') as source_type")
            ->selectRaw('COUNT(applications.id) as total')
            ->selectRaw("SUM(CASE WHEN applications.status = '".Application::STATUS_HIRED."' THEN 1 ELSE 0 END) as hires")
            ->selectRaw('AVG(scoring.global_match_score) as avg_match_score')
            ->groupBy('source_type')
            ->orderByDesc('total')
            ->get();

        return $rows->map(function (object $row) use ($candidateDrilldownBase): array {
            $sourceType = Str::lower(trim((string) ($row->source_type ?? 'unknown')));
            $total = (int) ($row->total ?? 0);
            $hires = (int) ($row->hires ?? 0);
            $conversionRate = $total > 0 ? round(($hires / $total) * 100, 1) : 0.0;
            $avgMatchScore = is_numeric($row->avg_match_score) ? round((float) $row->avg_match_score, 1) : null;
            $unitCost = self::SOURCE_COST_MAP[$sourceType] ?? self::SOURCE_COST_MAP['unknown'];
            $estimatedSpend = round($unitCost * $total, 2);
            $estimatedCph = $hires > 0 ? round($estimatedSpend / $hires, 2) : null;
            $roiIndex = round(($conversionRate * 0.6) + (($avgMatchScore ?? 0.0) * 0.4), 1);

            $drilldownUrl = route('candidates.index', array_filter(array_merge($candidateDrilldownBase, [
                'source_type' => $sourceType,
            ]), static fn (mixed $value): bool => $value !== null && $value !== ''));

            return [
                'source_type' => $sourceType,
                'total' => $total,
                'hires' => $hires,
                'conversion_rate' => $conversionRate,
                'avg_match_score' => $avgMatchScore,
                'estimated_spend' => $estimatedSpend,
                'estimated_cph' => $estimatedCph,
                'roi_index' => $roiIndex,
                'drilldown_url' => $drilldownUrl,
            ];
        })->values();
    }

    /**
     * @param Collection<int, array<string, mixed>> $sourcePerformance
     * @param Collection<int, array<string, mixed>> $funnelStages
     */
    private function buildSourceInsight(Collection $sourcePerformance, Collection $funnelStages): ?string
    {
        if ($sourcePerformance->isEmpty()) {
            return null;
        }

        /** @var array<string, mixed>|null $bestSource */
        $bestSource = $sourcePerformance
            ->filter(static fn (array $row): bool => (int) ($row['total'] ?? 0) >= 2)
            ->sortByDesc(static fn (array $row): float => (float) ($row['roi_index'] ?? 0))
            ->first();

        /** @var array<string, mixed>|null $weakSource */
        $weakSource = $sourcePerformance
            ->filter(static fn (array $row): bool => (int) ($row['total'] ?? 0) >= 2)
            ->sortBy(static fn (array $row): float => (float) ($row['conversion_rate'] ?? 0))
            ->first();

        /** @var array<string, mixed>|null $largestDrop */
        $largestDrop = $funnelStages
            ->filter(static fn (array $row): bool => is_numeric($row['dropoff_percent'] ?? null))
            ->sortByDesc(static fn (array $row): float => (float) ($row['dropoff_percent'] ?? 0))
            ->first();

        if ($bestSource === null && $weakSource === null && $largestDrop === null) {
            return null;
        }

        $parts = [];

        if (is_array($bestSource)) {
            $parts[] = __('ui.dashboard.source_insight_best', [
                'source' => Str::headline((string) ($bestSource['source_type'] ?? 'Unknown')),
                'roi' => number_format((float) ($bestSource['roi_index'] ?? 0), 1),
            ]);
        }

        if (is_array($weakSource)) {
            $parts[] = __('ui.dashboard.source_insight_weak', [
                'source' => Str::headline((string) ($weakSource['source_type'] ?? 'Unknown')),
                'conversion' => number_format((float) ($weakSource['conversion_rate'] ?? 0), 1),
            ]);
        }

        if (is_array($largestDrop)) {
            $parts[] = __('ui.dashboard.source_insight_funnel', [
                'stage' => (string) ($largestDrop['stage_label'] ?? __('ui.dashboard.unknown')),
                'drop' => number_format((float) ($largestDrop['dropoff_percent'] ?? 0), 1),
            ]);
        }

        return implode(' ', $parts);
    }

    /**
     * @param array{
     *   department_id: ?string,
     *   job_id: ?string,
     *   date_from: ?CarbonImmutable,
     *   date_to: ?CarbonImmutable
     * } $filters
     * @param array<string, string> $candidateDrilldownBase
     * @return Collection<int, array{
     *   application_id: string,
     *   candidate_name: string,
     *   job_title: string,
     *   stage_label: string,
     *   source_type: string,
     *   ai_score: ?float,
     *   last_activity_at: ?CarbonImmutable,
     *   last_activity_human: string,
     *   priority_reason: string,
     *   priority_score: float,
     *   drilldown_url: string
     * }>
     */
    private function buildPriorities(string $companyId, array $filters, array $candidateDrilldownBase): Collection
    {
        $activitySubquery = ApplicationActivityEvent::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->select('application_id')
            ->selectRaw('MAX(created_at) as last_activity_at')
            ->groupBy('application_id');

        $query = Application::withoutGlobalScopes()
            ->join('candidates', 'candidates.id', '=', 'applications.candidate_id')
            ->join('jobs', 'jobs.id', '=', 'applications.job_id')
            ->join('job_pipeline_stages as stages', 'stages.id', '=', 'applications.current_stage_id')
            ->leftJoin('application_scorings as scoring', function ($join) use ($companyId): void {
                $join->on('scoring.application_id', '=', 'applications.id')
                    ->where('scoring.company_id', '=', $companyId);
            })
            ->leftJoinSub($activitySubquery, 'activity', function ($join): void {
                $join->on('activity.application_id', '=', 'applications.id');
            })
            ->where('applications.status', Application::STATUS_ACTIVE)
            ->select([
                'applications.id as application_id',
                'candidates.full_name as candidate_name',
                'jobs.title as job_title',
                'stages.stage_label',
                'applications.source_type',
                'scoring.global_match_score as ai_score',
                'applications.created_at',
            ])
            ->selectRaw('COALESCE(activity.last_activity_at, applications.updated_at, applications.created_at) as last_activity_at');

        $this->applyApplicationFilters($query, $companyId, $filters);

        $now = CarbonImmutable::now();

        return $query
            ->get()
            ->map(function (object $row) use ($now, $candidateDrilldownBase): array {
                $lastActivityAt = isset($row->last_activity_at)
                    ? CarbonImmutable::parse((string) $row->last_activity_at)
                    : null;
                $hoursSinceLastActivity = $lastActivityAt !== null
                    ? max(0, $now->diffInHours($lastActivityAt))
                    : 999;
                $aiScore = is_numeric($row->ai_score) ? round((float) $row->ai_score, 1) : null;

                $priorityScore = (float) round(
                    ($hoursSinceLastActivity * 0.8)
                    + (is_numeric($aiScore) ? max(0, 85 - $aiScore) * 0.35 : 12)
                    + 10,
                    2
                );

                $priorityReason = __('ui.dashboard.priority_reason_monitor');
                if ($hoursSinceLastActivity >= 72) {
                    $priorityReason = __('ui.dashboard.priority_reason_stale');
                    $priorityScore += 30;
                } elseif ($hoursSinceLastActivity >= 36 && is_numeric($aiScore) && $aiScore >= 75) {
                    $priorityReason = __('ui.dashboard.priority_reason_high_value');
                    $priorityScore += 22;
                } elseif ($hoursSinceLastActivity >= 24 && Str::contains(Str::lower((string) ($row->stage_label ?? '')), 'interview')) {
                    $priorityReason = __('ui.dashboard.priority_reason_interview');
                    $priorityScore += 16;
                }

                $drilldownUrl = route('candidates.index', array_filter(array_merge($candidateDrilldownBase, [
                    'application_id' => (string) $row->application_id,
                ]), static fn (mixed $value): bool => $value !== null && $value !== ''));

                return [
                    'application_id' => (string) $row->application_id,
                    'candidate_name' => (string) $row->candidate_name,
                    'job_title' => (string) $row->job_title,
                    'stage_label' => (string) $row->stage_label,
                    'source_type' => (string) ($row->source_type ?: 'unknown'),
                    'ai_score' => $aiScore,
                    'last_activity_at' => $lastActivityAt,
                    'last_activity_human' => $lastActivityAt?->diffForHumans() ?? __('ui.dashboard.not_available'),
                    'priority_reason' => $priorityReason,
                    'priority_score' => $priorityScore,
                    'drilldown_url' => $drilldownUrl,
                ];
            })
            ->sortByDesc('priority_score')
            ->take(self::PRIORITIES_LIMIT)
            ->values();
    }

    /**
     * @param array{
     *   department_id: ?string,
     *   job_id: ?string,
     *   date_from: ?CarbonImmutable,
     *   date_to: ?CarbonImmutable
     * } $filters
     * @return Collection<int, object>
     */
    private function buildExportRows(string $companyId, array $filters): Collection
    {
        $activitySubquery = ApplicationActivityEvent::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->select('application_id')
            ->selectRaw('MAX(created_at) as last_activity_at')
            ->groupBy('application_id');

        $query = Application::withoutGlobalScopes()
            ->join('candidates', 'candidates.id', '=', 'applications.candidate_id')
            ->join('jobs', 'jobs.id', '=', 'applications.job_id')
            ->leftJoin('departments', 'departments.id', '=', 'jobs.department_id')
            ->join('job_pipeline_stages as stages', 'stages.id', '=', 'applications.current_stage_id')
            ->leftJoin('application_scorings as scoring', function ($join) use ($companyId): void {
                $join->on('scoring.application_id', '=', 'applications.id')
                    ->where('scoring.company_id', '=', $companyId);
            })
            ->leftJoinSub($activitySubquery, 'activity', function ($join): void {
                $join->on('activity.application_id', '=', 'applications.id');
            })
            ->select([
                'applications.id as application_id',
                'candidates.full_name as candidate_name',
                'jobs.title as job_title',
                'departments.name as department_name',
                'stages.stage_label',
                'applications.status',
                'applications.source_type',
                'applications.created_at',
                'scoring.global_match_score as ai_score',
            ])
            ->selectRaw('COALESCE(activity.last_activity_at, applications.updated_at, applications.created_at) as last_activity_at')
            ->orderByDesc('applications.created_at');

        $this->applyApplicationFilters($query, $companyId, $filters);

        return $query->get()->map(static function (object $row): object {
            if (isset($row->created_at)) {
                $row->created_at = CarbonImmutable::parse((string) $row->created_at);
            }
            if (isset($row->last_activity_at)) {
                $row->last_activity_at = CarbonImmutable::parse((string) $row->last_activity_at);
            }

            return $row;
        });
    }

    /**
     * @return array<string, string>
     */
    private function dateRangeLabels(): array
    {
        return [
            '7d' => __('ui.dashboard.date_ranges.7d'),
            '30d' => __('ui.dashboard.date_ranges.30d'),
            '90d' => __('ui.dashboard.date_ranges.90d'),
            '180d' => __('ui.dashboard.date_ranges.180d'),
            '365d' => __('ui.dashboard.date_ranges.365d'),
            'all' => __('ui.dashboard.date_ranges.all'),
        ];
    }
}
