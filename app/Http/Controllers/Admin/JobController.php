<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ResolvesManagedCompany;
use App\Http\Controllers\Controller;
use App\Models\AiRequest;
use App\Models\Company;
use App\Models\Department;
use App\Models\Job;
use App\Models\JobDescriptionBlock;
use App\Models\JobPosting;
use App\Models\JobPersona;
use App\Models\JobPipelineStage;
use App\Models\JobWeightingConfig;
use App\Models\User;
use App\Services\Analysis\CandidateAnalysisService;
use App\Services\Ai\AiRequestService;
use App\Services\Multiposting\MultipostingService;
use App\Support\Audit\SensitiveEventRecorder;
use App\Support\Multiposting\MultipostingChannelRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class JobController extends Controller
{
    use ResolvesManagedCompany;

    public function __construct(
        private readonly AiRequestService $aiRequestService,
        private readonly CandidateAnalysisService $candidateAnalysisService,
        private readonly SensitiveEventRecorder $sensitiveEvents,
        private readonly MultipostingChannelRegistry $multipostingChannels,
        private readonly MultipostingService $multipostingService
    ) {
    }

    public function index(Request $request): View|RedirectResponse
    {
        $actor = $request->user();
        if (! $actor instanceof User) {
            return redirect()->route('login');
        }

        $companyId = $this->managedCompanyId($request, $actor->isSuperadmin());

        $filters = $request->validate([
            'status' => ['nullable', Rule::in(Job::statuses())],
            'department_id' => ['nullable', 'uuid'],
            'q' => ['nullable', 'string', 'max:200'],
        ]);

        if ($actor->isSuperadmin() && $companyId === null) {
            return view('admin.jobs.index', [
                'jobs' => collect(),
                'companies' => Company::query()->orderBy('name')->get(['id', 'name']),
                'departments' => collect(),
                'selectedCompanyId' => null,
                'selectedStatus' => $filters['status'] ?? null,
                'selectedDepartmentId' => $filters['department_id'] ?? null,
                'searchTerm' => $filters['q'] ?? '',
                'requiresCompanySelection' => true,
            ]);
        }

        if (! $actor->isSuperadmin() && $companyId === null) {
            return redirect()->route('auth.company.dispatch');
        }

        $totalNeedsCount = \App\Models\RecruitmentNeed::where('company_id', $companyId)->count();
        $totalJobsCount = \App\Models\Job::withoutGlobalScopes()->where('company_id', $companyId)->count();
        $notYetLaunchedCount = \App\Models\RecruitmentNeed::where('company_id', $companyId)
            ->where('status', 'Pas encore lancé')
            ->count();
        
        $activeJobsCount = \App\Models\RecruitmentNeed::where('company_id', $companyId)
            ->where('status', 'En cours')
            ->count();

        $closedJobsCount = \App\Models\RecruitmentNeed::where('company_id', $companyId)
            ->where('status', 'Clôturé')
            ->count();

        $baseQuery = Job::withoutGlobalScopes()->where('company_id', $companyId);

        $jobs = (clone $baseQuery)
            ->with(['department:id,name', 'pipelineStages' => function ($q) {
                $q->withCount('applications');
            }])
            ->withCount('applications')
            ->when(($filters['status'] ?? null) !== null, fn ($q) => $q->where('status', $filters['status']))
            ->when(($filters['department_id'] ?? null) !== null, fn ($q) => $q->where('department_id', $filters['department_id']))
            ->when(($filters['q'] ?? null) !== null && trim($filters['q']) !== '', fn ($q) => $q->where('title', 'like', '%'.trim((string) $filters['q']).'%'))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        $totalApps = $jobs->sum('applications_count');
        $hiredApps = $jobs->sum(function($job) {
            $lastStage = $job->pipelineStages->last();
            return $lastStage ? $lastStage->applications_count : 0;
        });
        $conversionRate = $totalApps > 0 ? round(($hiredApps / $totalApps) * 100) : 0;

        $departments = Department::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->withCount(['jobs' => function ($query) {
                $query->withoutGlobalScopes();
            }])
            ->orderBy('name')
            ->get(['id', 'name', 'jobs_count']);

        $availableTitles = \App\Models\RecruitmentNeed::where('company_id', $companyId)
            ->where('status', 'Pas encore lancé')
            ->whereNotNull('new_recruit_position_title')
            ->pluck('new_recruit_position_title')
            ->unique()
            ->values()
            ->toArray();
        
        sort($availableTitles);

        return view('admin.jobs.index', [
            'jobs' => $jobs,
            'companies' => $actor->isSuperadmin() ? Company::query()->orderBy('name')->get(['id', 'name']) : collect(),
            'departments' => $departments,
            'availableTitles' => $availableTitles,
            'selectedCompanyId' => $companyId,
            'selectedStatus' => $filters['status'] ?? null,
            'selectedDepartmentId' => $filters['department_id'] ?? null,
            'searchTerm' => $filters['q'] ?? '',
            'requiresCompanySelection' => false,
            'totalJobsCount' => $totalJobsCount,
            'activeJobsCount' => $activeJobsCount,
            'closedJobsCount' => $closedJobsCount,
            'totalNeedsCount' => $totalNeedsCount,
            'notYetLaunchedCount' => $notYetLaunchedCount,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $companyId = $this->managedCompanyId($request, true);
        abort_unless($companyId !== null, 422);

        $validated = $this->validateCorePayload($request, $companyId);

        $job = Job::withoutGlobalScopes()->create(array_merge(
            ['company_id' => $companyId],
            $this->normalizedCorePayload($validated)
        ));

        // Save initial description blocks if provided
        $blocks = $request->input('blocks', []);
        $rows = [];
        $order = 1;
        foreach (['overview', 'responsibilities', 'requirements', 'benefits', 'company_intro'] as $type) {
            $content = trim((string) ($blocks[$type] ?? ''));
            if ($content !== '') {
                $rows[] = [
                    'id' => (string) Str::uuid(),
                    'job_id' => $job->id,
                    'block_type' => $type,
                    'block_content_json' => json_encode(['text' => $content], JSON_UNESCAPED_UNICODE),
                    'display_order' => $order++,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }
        if ($rows !== []) {
            DB::table('job_description_blocks')->insert($rows);
        }

        if ($job->status === Job::STATUS_PUBLISHED) {
            $this->sensitiveEvents->jobPublished((string) $job->id, ['source' => 'create'], $request->user());
        }

        $this->seedDefaultPipeline($job->id);
        $this->seedDefaultWeighting($job->id);

        return redirect()
            ->route('jobs.show', [
                'job' => $job,
                'company_id' => $companyId,
                'tab' => 'multiposting',
                'open_multiposting_workflow' => 1,
            ])
            ->with('status', __('jobs.created'));
    }

    public function show(Request $request, Job $job): View|RedirectResponse
    {
        $actor = $request->user();
        if (! $actor instanceof User) {
            return redirect()->route('login');
        }

        $companyId = $this->managedCompanyId($request, $actor->isSuperadmin());
        if ($actor->isSuperadmin() && $companyId === null) {
            return redirect()->route('jobs.index');
        }
        if ($companyId === null && ! $actor->isSuperadmin()) {
            return redirect()->route('auth.company.dispatch');
        }

        if ($companyId !== null && (string) $job->company_id !== (string) $companyId) {
            abort(403);
        }

        $job->load([
            'department',
            'descriptionBlocks',
            'persona',
            'weightingConfig',
            'pipelineStages',
            'jobPostings.publishAttempts.initiatedBy',
        ]);

        $latestPersonaRequest = AiRequest::withoutGlobalScopes()
            ->where('company_id', $job->company_id)
            ->where('request_type', 'job_persona_generation')
            ->where('request_payload->job_id', (string) $job->id)
            ->latest('created_at')
            ->first();

        $departments = Department::withoutGlobalScopes()
            ->where('company_id', $job->company_id)
            ->orderBy('name')
            ->get(['id', 'name']);

        $analysisSnapshot = null;
        try {
            $analysisSnapshot = $this->candidateAnalysisService->jobRankingSnapshot(
                companyId: (string) $job->company_id,
                jobId: (string) $job->id,
                limit: 80,
                synchronize: true
            );
        } catch (\Throwable) {
            $analysisSnapshot = null;
        }

        $multipostingReadinessByPlatform = collect($this->multipostingChannels->jobBoardPlatforms(true))
            ->mapWithKeys(fn (string $platform): array => [
                $platform => $this->multipostingService->readiness($platform, (string) $job->company_id),
            ])
            ->all();

        $multipostingAutomationByPlatform = collect($this->multipostingChannels->jobBoardPlatforms(true))
            ->mapWithKeys(fn (string $platform): array => [
                $platform => $this->multipostingService->automationDiagnostics($platform),
            ])
            ->all();

        return view('admin.jobs.show', [
            'job' => $job,
            'departments' => $departments,
            'companies' => $actor->isSuperadmin() ? Company::query()->orderBy('name')->get(['id', 'name']) : collect(),
            'selectedCompanyId' => $companyId ?? (string) $job->company_id,
            'latestPersonaRequest' => $latestPersonaRequest,
            'blockTypes' => Job::blockTypes(),
            'multipostingPlatforms' => JobPosting::platforms(),
            'multipostingChannelGroups' => $this->multipostingChannels->groupedActiveJobBoardChannelDetails(),
            'multipostingReadinessByPlatform' => $multipostingReadinessByPlatform,
            'multipostingAutomationByPlatform' => $multipostingAutomationByPlatform,
            'jobPostingsByPlatform' => $job->jobPostings->keyBy('platform'),
            'analysisSnapshot' => $analysisSnapshot,
        ]);
    }

    public function updateCore(Request $request, Job $job): RedirectResponse
    {
        $companyId = $this->managedCompanyId($request, true);
        abort_unless($companyId !== null && (string) $job->company_id === (string) $companyId, 403);

        $validated = $this->validateCorePayload($request, $companyId);

        $previousStatus = $job->status;

        $job->update($this->normalizedCorePayload($validated));

        if ($previousStatus !== Job::STATUS_PUBLISHED && $job->status === Job::STATUS_PUBLISHED) {
            $this->sensitiveEvents->jobPublished((string) $job->id, ['source' => 'update'], $request->user());
        }

        if ($previousStatus === Job::STATUS_PUBLISHED && $job->status !== Job::STATUS_PUBLISHED) {
            $this->sensitiveEvents->jobUnpublished((string) $job->id, ['source' => 'update'], $request->user());
        }

        $this->candidateAnalysisService->recomputeForJob(
            companyId: (string) $job->company_id,
            jobId: (string) $job->id
        );

        return back()->with('status', __('jobs.updated'));
    }

    public function publishToggle(Request $request, Job $job): RedirectResponse
    {
        $companyId = $this->managedCompanyId($request, true);
        abort_unless($companyId !== null && (string) $job->company_id === (string) $companyId, 403);

        $previousStatus = $job->status;

        if ($job->status === Job::STATUS_PUBLISHED) {
            $job->update(['status' => Job::STATUS_DRAFT]);
            $this->sensitiveEvents->jobUnpublished((string) $job->id, ['source' => 'toggle'], $request->user());
        } else {
            $job->update(['status' => Job::STATUS_PUBLISHED]);
            $this->sensitiveEvents->jobPublished((string) $job->id, ['source' => 'toggle'], $request->user());
        }

        $newStatus = $job->status;
        $key = $newStatus === Job::STATUS_PUBLISHED ? 'jobs.published' : 'jobs.unpublished';

        return back()->with('status', __($key));
    }

    public function saveBlocks(Request $request, Job $job): RedirectResponse
    {
        $companyId = $this->managedCompanyId($request, true);
        abort_unless($companyId !== null && (string) $job->company_id === (string) $companyId, 403);

        $types = $request->input('block_type', []);
        $contents = $request->input('block_content', []);
        $orders = $request->input('display_order', []);

        $rows = [];
        foreach ($types as $index => $type) {
            $type = (string) $type;
            $contentRaw = (string) ($contents[$index] ?? '');

            if (! in_array($type, Job::blockTypes(), true)) {
                continue;
            }
            if (trim($contentRaw) === '') {
                continue;
            }

            $decoded = json_decode($contentRaw, true);
            if (! is_array($decoded)) {
                $decoded = ['text' => $contentRaw];
            }

            $rows[] = [
                'id' => (string) Str::uuid(),
                'job_id' => $job->id,
                'block_type' => $type,
                'block_content_json' => json_encode($decoded, JSON_UNESCAPED_UNICODE),
                'display_order' => (int) ($orders[$index] ?? ($index + 1)),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::transaction(function () use ($job, $rows): void {
            JobDescriptionBlock::query()->where('job_id', $job->id)->delete();
            if ($rows !== []) {
                DB::table('job_description_blocks')->insert($rows);
            }
        });

        $this->candidateAnalysisService->recomputeForJob(
            companyId: (string) $job->company_id,
            jobId: (string) $job->id
        );

        return back()->with('status', __('jobs.blocks_saved'));
    }

    public function savePipeline(Request $request, Job $job): RedirectResponse
    {
        $companyId = $this->managedCompanyId($request, true);
        abort_unless($companyId !== null && (string) $job->company_id === (string) $companyId, 403);

        $stageIds = $request->input('stage_id', []);
        $keys = $request->input('stage_key', []);
        $labels = $request->input('stage_label', []);
        $orders = $request->input('display_order', []);
        $terminalIndexes = array_map('intval', Arr::wrap($request->input('is_terminal', [])));

        $existingStages = JobPipelineStage::query()
            ->where('job_id', $job->id)
            ->get()
            ->keyBy(fn (JobPipelineStage $stage): string => (string) $stage->id);

        $existingByKey = $existingStages
            ->values()
            ->keyBy(fn (JobPipelineStage $stage): string => (string) $stage->stage_key);

        $assignedStageIds = [];
        $rows = [];
        foreach ($keys as $idx => $key) {
            $stageKey = Str::of((string) $key)->lower()->replace(' ', '_')->value();
            $stageLabel = (string) ($labels[$idx] ?? '');
            if (trim($stageKey) === '' || trim($stageLabel) === '') {
                continue;
            }

            $providedId = (string) ($stageIds[$idx] ?? '');
            $resolvedId = null;

            if (
                $providedId !== ''
                && $existingStages->has($providedId)
                && ! in_array($providedId, $assignedStageIds, true)
            ) {
                $resolvedId = $providedId;
            } elseif ($existingByKey->has($stageKey)) {
                $candidateId = (string) ($existingByKey->get($stageKey)?->id ?? '');
                if ($candidateId !== '' && ! in_array($candidateId, $assignedStageIds, true)) {
                    $resolvedId = $candidateId;
                }
            }

            if ($resolvedId === null) {
                $resolvedId = (string) Str::uuid();
            }

            $assignedStageIds[] = $resolvedId;

            $rows[] = [
                'id' => $resolvedId,
                'job_id' => $job->id,
                'stage_key' => $stageKey,
                'stage_label' => $stageLabel,
                'display_order' => (int) ($orders[$idx] ?? ($idx + 1)),
                'is_terminal' => in_array((int) $idx, $terminalIndexes, true),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($rows === []) {
            return back()->withErrors(['pipeline' => __('jobs.pipeline_required')])->withInput();
        }

        $hasTerminal = collect($rows)->contains(fn ($row) => (bool) $row['is_terminal'] === true);
        $hasNonTerminal = collect($rows)->contains(fn ($row) => (bool) $row['is_terminal'] === false);
        if (! $hasTerminal || ! $hasNonTerminal) {
            return back()->withErrors(['pipeline' => __('jobs.pipeline_terminal_required')])->withInput();
        }

        if (collect($rows)->pluck('stage_key')->unique()->count() !== count($rows)) {
            return back()->withErrors(['pipeline' => __('jobs.pipeline_stage_key_unique')])->withInput();
        }

        $incomingStageIds = collect($rows)->pluck('id')->map(fn ($id): string => (string) $id);
        $stagesToDelete = $existingStages
            ->keys()
            ->map(fn ($id): string => (string) $id)
            ->diff($incomingStageIds)
            ->values();

        if ($stagesToDelete->isNotEmpty()) {
            $blockedByApplications = DB::table('applications')
                ->whereIn('current_stage_id', $stagesToDelete->all())
                ->pluck('current_stage_id')
                ->map(fn ($id): string => (string) $id);

            $blockedByHistory = DB::table('application_stage_histories')
                ->whereIn('to_stage_id', $stagesToDelete->all())
                ->pluck('to_stage_id')
                ->map(fn ($id): string => (string) $id);

            $blockedStageIds = $blockedByApplications
                ->merge($blockedByHistory)
                ->unique()
                ->values();

            if ($blockedStageIds->isNotEmpty()) {
                $blockedLabels = $blockedStageIds
                    ->map(fn (string $stageId): string => (string) ($existingStages->get($stageId)?->stage_label ?? $stageId))
                    ->implode(', ');

                return back()->withErrors([
                    'pipeline' => __('jobs.pipeline_stage_in_use', ['stages' => $blockedLabels]),
                ])->withInput();
            }
        }

        DB::transaction(function () use ($rows, $existingStages, $stagesToDelete): void {
            $now = now();

            $existingIdsToRekey = collect($rows)
                ->filter(function (array $row) use ($existingStages): bool {
                    $existing = $existingStages->get((string) $row['id']);
                    return $existing instanceof JobPipelineStage
                        && (string) $existing->stage_key !== (string) $row['stage_key'];
                })
                ->pluck('id')
                ->map(fn ($id): string => (string) $id)
                ->values();

            foreach ($existingIdsToRekey as $stageId) {
                DB::table('job_pipeline_stages')
                    ->where('id', $stageId)
                    ->update([
                        'stage_key' => '__tmp_'.$stageId,
                        'updated_at' => $now,
                    ]);
            }

            foreach ($rows as $row) {
                $stageId = (string) $row['id'];
                if ($existingStages->has($stageId)) {
                    DB::table('job_pipeline_stages')
                        ->where('id', $stageId)
                        ->update([
                            'stage_key' => $row['stage_key'],
                            'stage_label' => $row['stage_label'],
                            'display_order' => $row['display_order'],
                            'is_terminal' => $row['is_terminal'],
                            'updated_at' => $now,
                        ]);
                    continue;
                }

                DB::table('job_pipeline_stages')->insert($row);
            }

            if ($stagesToDelete->isNotEmpty()) {
                JobPipelineStage::query()
                    ->whereIn('id', $stagesToDelete->all())
                    ->delete();
            }
        });

        $this->candidateAnalysisService->recomputeForJob(
            companyId: (string) $job->company_id,
            jobId: (string) $job->id
        );

        return back()->with('status', __('jobs.pipeline_saved'));
    }

    public function saveWeighting(Request $request, Job $job): RedirectResponse
    {
        $companyId = $this->managedCompanyId($request, true);
        abort_unless($companyId !== null && (string) $job->company_id === (string) $companyId, 403);

        $validated = $request->validate([
            'weight_skills_match' => ['required', 'integer', 'min:0', 'max:100'],
            'weight_experience_match' => ['required', 'integer', 'min:0', 'max:100'],
            'weight_education_match' => ['required', 'integer', 'min:0', 'max:100'],
            'weight_certifications' => ['required', 'integer', 'min:0', 'max:100'],
            'weight_language_match' => ['required', 'integer', 'min:0', 'max:100'],
            'weight_assessment_performance' => ['required', 'integer', 'min:0', 'max:100'],
            'weight_interview_performance' => ['required', 'integer', 'min:0', 'max:100'],
            'weight_strategy_lab' => ['required', 'integer', 'min:0', 'max:100'],
            'weight_culture_fit' => ['required', 'integer', 'min:0', 'max:100'],
        ]);

        $total = collect($validated)
            ->filter(static fn ($value): bool => is_numeric($value))
            ->sum(static fn ($value): int => (int) $value);

        if ($total !== 100) {
            return back()->withErrors(['weight_total' => __('jobs.weighting_sum_invalid')])->withInput();
        }

        $weighting = [
            'skills_match' => (int) $validated['weight_skills_match'],
            'experience_match' => (int) $validated['weight_experience_match'],
            'education_match' => (int) $validated['weight_education_match'],
            'certifications' => (int) $validated['weight_certifications'],
            'language_match' => (int) $validated['weight_language_match'],
            'assessment_performance' => (int) $validated['weight_assessment_performance'],
            'interview_performance' => (int) $validated['weight_interview_performance'],
            'strategy_lab' => (int) $validated['weight_strategy_lab'],
            'culture_fit' => (int) $validated['weight_culture_fit'],
            'total' => $total,
        ];

        JobWeightingConfig::query()->updateOrCreate(
            ['job_id' => $job->id],
            ['weighting_json' => $weighting]
        );

        $this->candidateAnalysisService->recomputeForJob(
            companyId: (string) $job->company_id,
            jobId: (string) $job->id
        );

        return back()->with('status', __('jobs.weighting_saved'));
    }

    public function generatePersona(Request $request, Job $job): RedirectResponse
    {
        $companyId = $this->managedCompanyId($request, true);
        abort_unless($companyId !== null && (string) $job->company_id === (string) $companyId, 403);

        $job->loadMissing(['descriptionBlocks']);

        $prompt = $this->buildPersonaPrompt($job);

        $this->aiRequestService->queueRequest(
            companyId: (string) $job->company_id,
            requestType: 'job_persona_generation',
            requestPayload: [
                'job_id' => (string) $job->id,
                'output_mode' => 'json_schema',
                'json_schema' => [
                    'required' => ['persona_summary', 'must_haves', 'ideal_traits'],
                    'properties' => [
                        'persona_summary' => ['type' => 'string'],
                        'must_haves' => ['type' => 'array'],
                        'ideal_traits' => ['type' => 'array'],
                    ],
                ],
                'prompt' => $prompt,
            ],
            promptVersion: 'jobs_persona_v1'
        );

        return back()->with('status', __('jobs.persona_generation_queued'));
    }

    public function refreshPersona(Request $request, Job $job): RedirectResponse
    {
        $companyId = $this->managedCompanyId($request, true);
        abort_unless($companyId !== null && (string) $job->company_id === (string) $companyId, 403);

        $aiRequest = AiRequest::withoutGlobalScopes()
            ->where('company_id', $job->company_id)
            ->where('request_type', 'job_persona_generation')
            ->where('request_payload->job_id', (string) $job->id)
            ->where('status', AiRequest::STATUS_SUCCEEDED)
            ->latest('finished_at')
            ->first();

        if (! $aiRequest instanceof AiRequest) {
            return back()->with('status', __('jobs.persona_not_ready'));
        }

        $output = data_get($aiRequest->response_payload, 'output');
        if (! is_array($output)) {
            return back()->with('status', __('jobs.persona_not_ready'));
        }

        JobPersona::query()->updateOrCreate(
            ['job_id' => $job->id],
            ['persona_json' => $output]
        );

        return back()->with('status', __('jobs.persona_refreshed'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validateCorePayload(Request $request, string $companyId): array
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'department_id' => ['nullable', 'uuid', Rule::exists('departments', 'id')->where(fn ($q) => $q->where('company_id', $companyId))],
            'description_html' => ['nullable', 'string'],
            'location' => ['nullable', 'string', 'max:255'],
            'location_street' => ['nullable', 'string', 'max:255'],
            'location_city' => ['nullable', 'string', 'max:255'],
            'location_country' => ['nullable', 'string', 'max:255'],
            'location_postal_code' => ['nullable', 'string', 'max:32'],
            'employment_type' => ['nullable', Rule::in(Job::employmentTypes())],
            'status' => ['required', Rule::in(Job::statuses())],
            'blind_mode_active' => ['sometimes', 'boolean'],
            'salary_min' => ['nullable', 'integer', 'min:0'],
            'salary_max' => ['nullable', 'integer', 'min:0'],
            'salary_currency' => ['nullable', 'string', 'max:8'],
            'salary_budget_max' => ['nullable', 'integer', 'min:0'],
        ]);

        $salaryMin = $this->toNullableInt($validated['salary_min'] ?? null);
        $salaryMax = $this->toNullableInt($validated['salary_max'] ?? null);

        if ($salaryMin !== null && $salaryMax !== null && $salaryMin > $salaryMax) {
            throw ValidationException::withMessages([
                'salary_max' => 'Salary maximum must be greater than or equal to salary minimum.',
            ]);
        }

        return $validated;
    }

    /**
     * @param array<string, mixed> $validated
     * @return array<string, mixed>
     */
    private function normalizedCorePayload(array $validated): array
    {
        $salaryMin = $this->toNullableInt($validated['salary_min'] ?? null);
        $salaryMax = $this->toNullableInt($validated['salary_max'] ?? null);
        $salaryBudgetMax = $this->toNullableInt($validated['salary_budget_max'] ?? null) ?? $salaryMax;
        $salaryCurrency = strtoupper((string) ($this->trimmedOrNull($validated['salary_currency'] ?? null) ?? ''));

        return [
            'department_id' => $validated['department_id'] ?? null,
            'title' => trim((string) $validated['title']),
            'description_html' => $this->trimmedOrNull($validated['description_html'] ?? null),
            'location' => $this->resolveLocationSummary($validated),
            'location_street' => $this->trimmedOrNull($validated['location_street'] ?? null),
            'location_city' => $this->trimmedOrNull($validated['location_city'] ?? null),
            'location_country' => $this->trimmedOrNull($validated['location_country'] ?? null),
            'location_postal_code' => $this->trimmedOrNull($validated['location_postal_code'] ?? null),
            'employment_type' => $this->trimmedOrNull($validated['employment_type'] ?? null) ?? Job::EMPLOYMENT_FULL_TIME,
            'status' => $validated['status'],
            'blind_mode_active' => (bool) ($validated['blind_mode_active'] ?? false),
            'salary_min' => $salaryMin,
            'salary_max' => $salaryMax,
            'salary_currency' => $salaryCurrency !== '' ? $salaryCurrency : null,
            'salary_budget_max' => $salaryBudgetMax,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function resolveLocationSummary(array $payload): ?string
    {
        $explicit = $this->trimmedOrNull($payload['location'] ?? null);
        if ($explicit !== null) {
            return $explicit;
        }

        $city = $this->trimmedOrNull($payload['location_city'] ?? null);
        $country = $this->trimmedOrNull($payload['location_country'] ?? null);

        $parts = array_values(array_filter([$city, $country], static fn (?string $value): bool => $value !== null));
        if ($parts === []) {
            return null;
        }

        return implode(', ', $parts);
    }

    private function trimmedOrNull(mixed $value): ?string
    {
        $trimmed = trim((string) ($value ?? ''));
        return $trimmed === '' ? null : $trimmed;
    }

    private function toNullableInt(mixed $value): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }

    private function seedDefaultPipeline(string $jobId): void
    {
        $exists = JobPipelineStage::query()->where('job_id', $jobId)->exists();
        if ($exists) {
            return;
        }

        $defaults = [
            ['key' => 'applied', 'label' => __('jobs.default_stages.applied'), 'order' => 1, 'terminal' => false],
            ['key' => 'screen', 'label' => __('jobs.default_stages.screen'), 'order' => 2, 'terminal' => false],
            ['key' => 'interview', 'label' => __('jobs.default_stages.interview'), 'order' => 3, 'terminal' => false],
            ['key' => 'offer', 'label' => __('jobs.default_stages.offer'), 'order' => 4, 'terminal' => true],
        ];

        foreach ($defaults as $stage) {
            JobPipelineStage::query()->create([
                'job_id' => $jobId,
                'stage_key' => $stage['key'],
                'stage_label' => $stage['label'],
                'display_order' => $stage['order'],
                'is_terminal' => $stage['terminal'],
            ]);
        }
    }

    private function seedDefaultWeighting(string $jobId): void
    {
        JobWeightingConfig::query()->firstOrCreate(
            ['job_id' => $jobId],
            ['weighting_json' => [
                'skills_match' => 20,
                'experience_match' => 15,
                'education_match' => 10,
                'certifications' => 8,
                'language_match' => 8,
                'assessment_performance' => 10,
                'interview_performance' => 10,
                'strategy_lab' => 10,
                'culture_fit' => 9,
                'total' => 100,
            ]]
        );
    }

    private function buildPersonaPrompt(Job $job): string
    {
        $blocks = $job->descriptionBlocks
            ->map(fn (JobDescriptionBlock $block) => strtoupper($block->block_type).": ".json_encode($block->block_content_json))
            ->implode("\n");

        return trim(implode("\n\n", [
            "Generate a candidate persona for the following role.",
            "Job title: {$job->title}",
            "Location: ".($job->location ?: 'N/A'),
            "Description blocks:",
            $blocks !== '' ? $blocks : 'No blocks provided.',
            "Return strict JSON with keys: persona_summary, must_haves, ideal_traits.",
        ]));
    }
}
