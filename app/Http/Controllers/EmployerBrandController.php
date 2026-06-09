<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Admin\Concerns\ResolvesManagedCompany;
use App\Models\BrandAlert;
use App\Models\CandidateSurvey;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\InterviewFeedback;
use App\Models\Job;
use App\Models\ReverseFeedback;
use App\Models\SentimentResult;
use App\Models\User;
use App\Services\EmployerBrand\EmployerBrandSentimentService;
use App\Support\Audit\SensitiveEventRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EmployerBrandController extends Controller
{
    use ResolvesManagedCompany;

    private const PERIOD_OPTIONS = [
        '7d' => 7,
        '30d' => 30,
        '90d' => 90,
        '180d' => 180,
        'all' => null,
    ];

    public function __construct(private readonly SensitiveEventRecorder $sensitiveEvents)
    {
    }

    public function index(Request $request): View|RedirectResponse
    {
        [$companyId, $companies] = $this->resolveCompanyContext($request);
        $actor = $request->user();

        if ($companyId === null) {
            return view('dashboard.employer-brand', [
                'requiresCompanySelection' => true,
                'companies' => $companies,
                'filters' => [
                    'recruiter_id' => null,
                    'job_id' => null,
                    'period' => '30d',
                    'date_from' => null,
                    'date_to' => null,
                ],
                'periodOptions' => $this->periodLabels(),
                'recruiters' => collect(),
                'jobs' => collect(),
                'ratingSummary' => [
                    'responses' => 0,
                    'avg_clarity' => null,
                    'avg_speed' => null,
                    'avg_kindness' => null,
                    'avg_overall' => null,
                ],
                'sentimentEntries' => collect(),
                'trendPoints' => collect(),
                'topThemes' => collect(),
                'activeAlerts' => collect(),
                'pendingSentimentCount' => 0,
                'canViewReverseFeedbackInsights' => false,
            ]);
        }

        $filters = $this->validatedFilters($request, $companyId);
        $canViewReverseFeedbackInsights = $this->canViewReverseFeedbackInsights(
            $actor instanceof User ? $actor : null,
            $companyId
        );
        $recruiters = $this->recruiterOptions($companyId);
        $jobs = Job::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->orderBy('title')
            ->get(['id', 'title']);

        $ratingSummary = $this->buildRatingSummary($companyId, $filters, $canViewReverseFeedbackInsights);
        $sentimentEntries = $this->buildSentimentEntries(
            $request,
            $companyId,
            $filters,
            $canViewReverseFeedbackInsights
        );
        $trendPoints = $this->buildTrendPoints($sentimentEntries);
        $topThemes = $this->buildTopThemes($sentimentEntries);
        $activeAlerts = $this->buildActiveAlerts(
            $request,
            $companyId,
            $filters,
            $canViewReverseFeedbackInsights
        );
        $pendingSentimentCount = $sentimentEntries
            ->where('risk_level', SentimentResult::RISK_PENDING)
            ->count();

        return view('dashboard.employer-brand', [
            'requiresCompanySelection' => false,
            'companies' => $companies,
            'filters' => $filters,
            'periodOptions' => $this->periodLabels(),
            'recruiters' => $recruiters,
            'jobs' => $jobs,
            'ratingSummary' => $ratingSummary,
            'sentimentEntries' => $sentimentEntries,
            'trendPoints' => $trendPoints,
            'topThemes' => $topThemes,
            'activeAlerts' => $activeAlerts,
            'pendingSentimentCount' => $pendingSentimentCount,
            'canViewReverseFeedbackInsights' => $canViewReverseFeedbackInsights,
        ]);
    }

    public function resolveAlert(Request $request, BrandAlert $brandAlert): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $companyId = $this->managedCompanyId($request, true);
        abort_unless($companyId !== null, 403);
        abort_unless((string) $brandAlert->company_id === $companyId, 403);

        if ($brandAlert->resolved_at !== null) {
            return back()->with('status', __('ui.employer_brand.alert_already_resolved'));
        }

        if (
            (string) $brandAlert->severity === BrandAlert::SEVERITY_CRITICAL
            && ! $this->canResolveCritical($actor, $companyId)
        ) {
            abort(403, __('ui.employer_brand.resolve_critical_forbidden'));
        }

        $brandAlert->forceFill(['resolved_at' => now()])->save();

        $this->sensitiveEvents->record(
            actionType: 'employer_brand.alert_resolved',
            entityType: 'brand_alert',
            entityId: (string) $brandAlert->id,
            metadata: [
                'alert_type' => (string) $brandAlert->alert_type,
                'severity' => (string) $brandAlert->severity,
            ],
            actor: $actor
        );

        return back()->with('status', __('ui.employer_brand.alert_resolved'));
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
     * @return array{
     *   recruiter_id: ?string,
     *   job_id: ?string,
     *   period: string,
     *   date_from: ?CarbonImmutable,
     *   date_to: ?CarbonImmutable
     * }
     */
    private function validatedFilters(Request $request, string $companyId): array
    {
        $validated = $request->validate([
            'recruiter_id' => [
                'nullable',
                'uuid',
                Rule::exists('company_memberships', 'user_id')
                    ->where(static function ($query) use ($companyId): void {
                        $query->where('company_id', $companyId)
                            ->where('membership_status', CompanyMembership::STATUS_ACTIVE)
                            ->whereIn('company_role', [
                                CompanyMembership::ROLE_COMPANY_ADMIN,
                                CompanyMembership::ROLE_RECRUITER,
                                CompanyMembership::ROLE_MANAGER,
                            ]);
                    }),
            ],
            'job_id' => [
                'nullable',
                'uuid',
                Rule::exists('jobs', 'id')
                    ->where(static fn ($query) => $query->where('company_id', $companyId)),
            ],
            'period' => ['nullable', Rule::in(array_keys(self::PERIOD_OPTIONS))],
        ]);

        $period = (string) ($validated['period'] ?? '30d');
        if ($period === '' || ! array_key_exists($period, self::PERIOD_OPTIONS)) {
            $period = '30d';
        }

        $days = self::PERIOD_OPTIONS[$period];
        $now = CarbonImmutable::now();

        return [
            'recruiter_id' => isset($validated['recruiter_id']) ? (string) $validated['recruiter_id'] : null,
            'job_id' => isset($validated['job_id']) ? (string) $validated['job_id'] : null,
            'period' => $period,
            'date_from' => is_int($days) ? $now->subDays($days)->startOfDay() : null,
            'date_to' => is_int($days) ? $now->endOfDay() : null,
        ];
    }

    /**
     * @param array{
     *   recruiter_id: ?string,
     *   job_id: ?string,
     *   date_from: ?CarbonImmutable,
     *   date_to: ?CarbonImmutable
     * } $filters
     * @return array{
     *   responses: int,
     *   avg_clarity: ?float,
     *   avg_speed: ?float,
     *   avg_kindness: ?float,
     *   avg_overall: ?float
     * }
     */
    private function buildRatingSummary(string $companyId, array $filters, bool $canViewReverseFeedbackInsights): array
    {
        if (! $canViewReverseFeedbackInsights) {
            return [
                'responses' => 0,
                'avg_clarity' => null,
                'avg_speed' => null,
                'avg_kindness' => null,
                'avg_overall' => null,
            ];
        }

        $query = ReverseFeedback::withoutGlobalScopes()
            ->join('applications', 'applications.id', '=', 'reverse_feedback.application_id')
            ->where('reverse_feedback.company_id', $companyId);

        if (is_string($filters['job_id']) && $filters['job_id'] !== '') {
            $query->where('applications.job_id', $filters['job_id']);
        }
        if (is_string($filters['recruiter_id']) && $filters['recruiter_id'] !== '') {
            $query->where('reverse_feedback.recruiter_user_id', $filters['recruiter_id']);
        }
        if ($filters['date_from'] instanceof CarbonImmutable) {
            $query->where('reverse_feedback.created_at', '>=', $filters['date_from']);
        }
        if ($filters['date_to'] instanceof CarbonImmutable) {
            $query->where('reverse_feedback.created_at', '<=', $filters['date_to']);
        }

        $aggregate = $query
            ->selectRaw('COUNT(reverse_feedback.id) as responses')
            ->selectRaw('AVG(reverse_feedback.rating_clarity) as avg_clarity')
            ->selectRaw('AVG(reverse_feedback.rating_speed) as avg_speed')
            ->selectRaw('AVG(reverse_feedback.rating_kindness) as avg_kindness')
            ->first();

        $avgClarity = is_numeric($aggregate?->avg_clarity) ? round((float) $aggregate->avg_clarity, 2) : null;
        $avgSpeed = is_numeric($aggregate?->avg_speed) ? round((float) $aggregate->avg_speed, 2) : null;
        $avgKindness = is_numeric($aggregate?->avg_kindness) ? round((float) $aggregate->avg_kindness, 2) : null;

        $averages = collect([$avgClarity, $avgSpeed, $avgKindness])->filter(static fn ($value): bool => is_numeric($value));
        $avgOverall = $averages->isNotEmpty() ? round((float) $averages->avg(), 2) : null;

        return [
            'responses' => (int) ($aggregate?->responses ?? 0),
            'avg_clarity' => $avgClarity,
            'avg_speed' => $avgSpeed,
            'avg_kindness' => $avgKindness,
            'avg_overall' => $avgOverall,
        ];
    }

    /**
     * @param array{
     *   recruiter_id: ?string,
     *   job_id: ?string,
     *   date_from: ?CarbonImmutable,
     *   date_to: ?CarbonImmutable
     * } $filters
     * @return Collection<int, array<string, mixed>>
     */
    private function buildSentimentEntries(
        Request $request,
        string $companyId,
        array $filters,
        bool $canViewReverseFeedbackInsights
    ): Collection
    {
        $query = SentimentResult::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->orderByDesc('created_at');

        if (! $canViewReverseFeedbackInsights) {
            $query->where('source_type', '!=', EmployerBrandSentimentService::SOURCE_REVERSE_FEEDBACK);
        }

        if ($filters['date_from'] instanceof CarbonImmutable) {
            $query->where('created_at', '>=', $filters['date_from']);
        }
        if ($filters['date_to'] instanceof CarbonImmutable) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        $rows = $query->limit(300)->get([
            'id',
            'source_type',
            'source_id',
            'sentiment_score',
            'top_themes_json',
            'risk_level',
            'created_at',
        ]);

        $sourceMeta = $this->resolveSentimentSourceMeta($companyId, $rows);
        $candidateBase = $this->candidateDrilldownBase($request, $companyId, $filters);

        return $rows
            ->map(function (SentimentResult $row) use ($sourceMeta, $candidateBase): array {
                $key = (string) $row->source_type.':'.(string) $row->source_id;
                $meta = $sourceMeta[$key] ?? [
                    'application_id' => null,
                    'job_id' => null,
                    'recruiter_user_id' => null,
                    'source_label' => Str::headline((string) $row->source_type),
                    'feedback_excerpt' => null,
                ];

                $drilldownUrl = null;
                if (is_string($meta['application_id'] ?? null) && $meta['application_id'] !== '') {
                    $drilldownUrl = route('candidates.index', array_filter(array_merge($candidateBase, [
                        'application_id' => (string) $meta['application_id'],
                    ]), static fn (mixed $value): bool => $value !== null && $value !== ''));
                }

                return [
                    'id' => (string) $row->id,
                    'source_type' => (string) $row->source_type,
                    'source_id' => (string) $row->source_id,
                    'source_label' => (string) ($meta['source_label'] ?? Str::headline((string) $row->source_type)),
                    'feedback_excerpt' => $meta['feedback_excerpt'] ?? null,
                    'application_id' => $meta['application_id'] ?? null,
                    'job_id' => $meta['job_id'] ?? null,
                    'recruiter_user_id' => $meta['recruiter_user_id'] ?? null,
                    'sentiment_score' => is_numeric($row->sentiment_score) ? (float) $row->sentiment_score : null,
                    'themes' => is_array($row->top_themes_json) ? $row->top_themes_json : [],
                    'risk_level' => (string) $row->risk_level,
                    'created_at' => $row->created_at,
                    'drilldown_url' => $drilldownUrl,
                ];
            })
            ->filter(function (array $row) use ($filters): bool {
                if (is_string($filters['job_id']) && $filters['job_id'] !== '') {
                    if ((string) ($row['job_id'] ?? '') !== $filters['job_id']) {
                        return false;
                    }
                }

                if (is_string($filters['recruiter_id']) && $filters['recruiter_id'] !== '') {
                    if ((string) ($row['recruiter_user_id'] ?? '') !== $filters['recruiter_id']) {
                        return false;
                    }
                }

                return true;
            })
            ->values();
    }

    /**
     * @param Collection<int, SentimentResult> $rows
     * @return array<string, array{
     *   application_id: ?string,
     *   job_id: ?string,
     *   recruiter_user_id: ?string,
     *   source_label: string,
     *   feedback_excerpt: ?string
     * }>
     */
    private function resolveSentimentSourceMeta(string $companyId, Collection $rows): array
    {
        $meta = [];

        $reverseIds = $rows->where('source_type', 'reverse_feedback')->pluck('source_id')->values()->all();
        if ($reverseIds !== []) {
            $reverseFeedback = ReverseFeedback::withoutGlobalScopes()
                ->with('application:id,job_id')
                ->where('company_id', $companyId)
                ->whereIn('id', $reverseIds)
                ->get();

            foreach ($reverseFeedback as $item) {
                $meta['reverse_feedback:'.$item->id] = [
                    'application_id' => $item->application?->id ? (string) $item->application->id : null,
                    'job_id' => $item->application?->job_id ? (string) $item->application->job_id : null,
                    'recruiter_user_id' => $item->recruiter_user_id ? (string) $item->recruiter_user_id : null,
                    'source_label' => __('ui.employer_brand.sources.reverse_feedback'),
                    'feedback_excerpt' => $item->comment ? Str::limit((string) $item->comment, 160) : null,
                ];
            }
        }

        $interviewIds = $rows->where('source_type', 'interview_feedback')->pluck('source_id')->values()->all();
        if ($interviewIds !== []) {
            $interviewFeedback = InterviewFeedback::withoutGlobalScopes()
                ->with('interview.application:id,job_id')
                ->where('company_id', $companyId)
                ->whereIn('id', $interviewIds)
                ->get();

            foreach ($interviewFeedback as $item) {
                $meta['interview_feedback:'.$item->id] = [
                    'application_id' => $item->interview?->application?->id ? (string) $item->interview->application->id : null,
                    'job_id' => $item->interview?->application?->job_id ? (string) $item->interview->application->job_id : null,
                    'recruiter_user_id' => $item->author_user_id ? (string) $item->author_user_id : null,
                    'source_label' => __('ui.employer_brand.sources.interview_feedback'),
                    'feedback_excerpt' => $item->notes ? Str::limit((string) $item->notes, 160) : null,
                ];
            }
        }

        $surveyIds = $rows->where('source_type', 'candidate_survey')->pluck('source_id')->values()->all();
        if ($surveyIds !== []) {
            $candidateSurveys = CandidateSurvey::withoutGlobalScopes()
                ->with('application:id,job_id')
                ->where('company_id', $companyId)
                ->whereIn('id', $surveyIds)
                ->get();

            foreach ($candidateSurveys as $item) {
                $meta['candidate_survey:'.$item->id] = [
                    'application_id' => $item->application?->id ? (string) $item->application->id : null,
                    'job_id' => $item->application?->job_id ? (string) $item->application->job_id : null,
                    'recruiter_user_id' => null,
                    'source_label' => __('ui.employer_brand.sources.candidate_survey'),
                    'feedback_excerpt' => $item->comment ? Str::limit((string) $item->comment, 160) : null,
                ];
            }
        }

        return $meta;
    }

    /**
     * @param Collection<int, array<string, mixed>> $entries
     * @return Collection<int, array{date: string, avg_score: float, count: int, level: string, bar_percent: float}>
     */
    private function buildTrendPoints(Collection $entries): Collection
    {
        return $entries
            ->filter(static fn (array $entry): bool => is_numeric($entry['sentiment_score'] ?? null))
            ->groupBy(static function (array $entry): string {
                return CarbonImmutable::parse((string) $entry['created_at'])->toDateString();
            })
            ->map(static function (Collection $dayEntries, string $date): array {
                $avg = (float) $dayEntries->avg(static fn (array $entry): float => (float) $entry['sentiment_score']);

                $level = match (true) {
                    $avg <= -0.75 => SentimentResult::RISK_CRITICAL,
                    $avg <= -0.45 => SentimentResult::RISK_HIGH,
                    $avg <= -0.20 => SentimentResult::RISK_MEDIUM,
                    default => SentimentResult::RISK_LOW,
                };

                return [
                    'date' => $date,
                    'avg_score' => round($avg, 3),
                    'count' => $dayEntries->count(),
                    'level' => $level,
                    'bar_percent' => round(max(0, min(100, (($avg + 1) / 2) * 100)), 2),
                ];
            })
            ->sortBy('date')
            ->values()
            ->take(-14)
            ->values();
    }

    /**
     * @param Collection<int, array<string, mixed>> $entries
     * @return Collection<int, array{theme: string, count: int}>
     */
    private function buildTopThemes(Collection $entries): Collection
    {
        return $entries
            ->flatMap(static fn (array $entry): array => is_array($entry['themes'] ?? null) ? $entry['themes'] : [])
            ->map(static fn (mixed $theme): string => trim((string) $theme))
            ->filter(static fn (string $theme): bool => $theme !== '')
            ->map(static fn (string $theme): string => Str::limit(Str::lower($theme), 60, ''))
            ->countBy()
            ->sortDesc()
            ->take(8)
            ->map(static fn (int $count, string $theme): array => [
                'theme' => Str::headline($theme),
                'count' => $count,
            ])
            ->values();
    }

    /**
     * @param array{
     *   recruiter_id: ?string,
     *   job_id: ?string,
     *   date_from: ?CarbonImmutable,
     *   date_to: ?CarbonImmutable
     * } $filters
     * @return Collection<int, array<string, mixed>>
     */
    private function buildActiveAlerts(
        Request $request,
        string $companyId,
        array $filters,
        bool $canViewReverseFeedbackInsights
    ): Collection
    {
        $query = BrandAlert::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereNull('resolved_at');

        if (! $canViewReverseFeedbackInsights) {
            $query->where('related_entity_type', '!=', EmployerBrandSentimentService::SOURCE_REVERSE_FEEDBACK);
        }

        if ($filters['date_from'] instanceof CarbonImmutable) {
            $query->where('created_at', '>=', $filters['date_from']);
        }
        if ($filters['date_to'] instanceof CarbonImmutable) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        $rows = $query
            ->orderByRaw(
                "CASE severity WHEN 'critical' THEN 4 WHEN 'high' THEN 3 WHEN 'medium' THEN 2 ELSE 1 END DESC"
            )
            ->orderByDesc('created_at')
            ->get();

        $sourceMeta = $this->resolveAlertSourceMeta($companyId, $rows);
        $candidateBase = $this->candidateDrilldownBase($request, $companyId, $filters);
        $actor = $request->user();
        $canResolveCritical = $actor instanceof User ? $this->canResolveCritical($actor, $companyId) : false;

        return $rows
            ->map(function (BrandAlert $alert) use ($sourceMeta, $candidateBase, $canResolveCritical): array {
                $key = (string) $alert->related_entity_type.':'.(string) $alert->related_entity_id;
                $meta = $sourceMeta[$key] ?? [
                    'application_id' => null,
                    'job_id' => null,
                    'recruiter_user_id' => null,
                ];

                $drilldownUrl = null;
                if (is_string($meta['application_id'] ?? null) && $meta['application_id'] !== '') {
                    $drilldownUrl = route('candidates.index', array_filter(array_merge($candidateBase, [
                        'application_id' => (string) $meta['application_id'],
                    ]), static fn (mixed $value): bool => $value !== null && $value !== ''));
                }

                $severity = (string) $alert->severity;
                $canResolve = $severity !== BrandAlert::SEVERITY_CRITICAL || $canResolveCritical;

                return [
                    'id' => (string) $alert->id,
                    'alert_type' => (string) $alert->alert_type,
                    'severity' => $severity,
                    'message' => (string) $alert->message,
                    'created_at' => $alert->created_at,
                    'application_id' => $meta['application_id'] ?? null,
                    'job_id' => $meta['job_id'] ?? null,
                    'recruiter_user_id' => $meta['recruiter_user_id'] ?? null,
                    'drilldown_url' => $drilldownUrl,
                    'can_resolve' => $canResolve,
                ];
            })
            ->filter(function (array $alert) use ($filters): bool {
                if (is_string($filters['job_id']) && $filters['job_id'] !== '') {
                    if ((string) ($alert['job_id'] ?? '') !== $filters['job_id']) {
                        return false;
                    }
                }

                if (is_string($filters['recruiter_id']) && $filters['recruiter_id'] !== '') {
                    if ((string) ($alert['recruiter_user_id'] ?? '') !== $filters['recruiter_id']) {
                        return false;
                    }
                }

                return true;
            })
            ->values();
    }

    /**
     * @param Collection<int, BrandAlert> $alerts
     * @return array<string, array{application_id: ?string, job_id: ?string, recruiter_user_id: ?string}>
     */
    private function resolveAlertSourceMeta(string $companyId, Collection $alerts): array
    {
        $rows = $alerts->map(static function (BrandAlert $alert): SentimentResult {
            /** @var SentimentResult $row */
            $row = new SentimentResult([
                'source_type' => (string) $alert->related_entity_type,
                'source_id' => (string) $alert->related_entity_id,
            ]);

            return $row;
        });

        $meta = $this->resolveSentimentSourceMeta($companyId, $rows);

        return collect($meta)
            ->map(static fn (array $value): array => [
                'application_id' => $value['application_id'] ?? null,
                'job_id' => $value['job_id'] ?? null,
                'recruiter_user_id' => $value['recruiter_user_id'] ?? null,
            ])
            ->all();
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
     * @return Collection<int, array{id: string, name: string}>
     */
    private function recruiterOptions(string $companyId): Collection
    {
        return CompanyMembership::query()
            ->with('user.profile')
            ->where('company_id', $companyId)
            ->where('membership_status', CompanyMembership::STATUS_ACTIVE)
            ->whereIn('company_role', [
                CompanyMembership::ROLE_COMPANY_ADMIN,
                CompanyMembership::ROLE_RECRUITER,
                CompanyMembership::ROLE_MANAGER,
            ])
            ->get()
            ->map(static function (CompanyMembership $membership): array {
                return [
                    'id' => (string) $membership->user_id,
                    'name' => (string) ($membership->user?->profile?->full_name ?? $membership->user?->email ?? $membership->user_id),
                ];
            })
            ->sortBy('name')
            ->values();
    }

    /**
     * @return array<string, string>
     */
    private function periodLabels(): array
    {
        return [
            '7d' => __('ui.employer_brand.periods.7d'),
            '30d' => __('ui.employer_brand.periods.30d'),
            '90d' => __('ui.employer_brand.periods.90d'),
            '180d' => __('ui.employer_brand.periods.180d'),
            'all' => __('ui.employer_brand.periods.all'),
        ];
    }

    private function canResolveCritical(User $user, string $companyId): bool
    {
        if ($user->isSuperadmin()) {
            return true;
        }

        return $user->memberships()
            ->where('company_id', $companyId)
            ->where('membership_status', CompanyMembership::STATUS_ACTIVE)
            ->where('company_role', CompanyMembership::ROLE_COMPANY_ADMIN)
            ->exists();
    }

    private function canViewReverseFeedbackInsights(?User $user, string $companyId): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        if ($user->isSuperadmin()) {
            return true;
        }

        return $user->memberships()
            ->where('company_id', $companyId)
            ->where('membership_status', CompanyMembership::STATUS_ACTIVE)
            ->whereIn('company_role', [
                CompanyMembership::ROLE_COMPANY_ADMIN,
                CompanyMembership::ROLE_MANAGER,
            ])
            ->exists();
    }
}
