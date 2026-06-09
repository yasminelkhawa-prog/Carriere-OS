
<x-shell-layout :title="__('candidates.title').' | '.config('app.name')">
    <div class="space-y-4 pb-28">
        @if(session('status'))
            <x-toast-alert type="success">{{ session('status') }}</x-toast-alert>
        @endif
        @if(session('error'))
            <x-toast-alert type="warning">{{ session('error') }}</x-toast-alert>
        @endif
        @if($errors->any())
            <div class="rounded-xl border border-danger-300/50 bg-danger-50/70 px-3 py-2 text-xs text-danger-800">
                {{ $errors->first() }}
            </div>
        @endif

        @if($requiresCompanySelection)
            <x-glass-card :title="__('candidates.title')" :subtitle="__('candidates.subtitle')">
                <x-empty-state :title="__('candidates.select_company_title')" :message="__('candidates.select_company_message')" />
            </x-glass-card>
        @else
        @if(request()->filled('application_id'))

            <div class="mb-4">
                <a href="{{ route('candidates.index', array_filter(['job_id' => $filters['job_id'] ?? null])) }}" class="inline-flex items-center gap-2 text-sm font-semibold text-slate-600 transition hover:text-aura-600">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                    </svg>
                    Retour à la liste
                </a>
            </div>
            <div class="grid gap-4">

                <x-glass-card body-class="flex min-h-0 flex-1 flex-col" class="min-w-0 flex flex-col p-0 2xl:h-[calc(100vh-11rem)] 2xl:overflow-hidden">
                    <div id="candidate-detail-scroll" class="min-h-0 flex-1 overflow-y-auto p-4">
                        @if(! $selectedApplication)
                            <div class="space-y-8">
                                @forelse($topCandidatesByJob as $group)
                                    <div>
                                        <h3 class="mb-4 text-lg font-semibold text-slate-800">{{ $group['job']->title }} <span class="text-sm font-normal text-slate-500">({{ __('candidates.detail.top_three') }})</span></h3>
                                        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                                            @foreach($group['applications'] as $app)
                                                @php
                                                    $cv = $app->cvParsingResults->first();
                                                    $skills = $cv ? collect($cv->parsed_skills ?? [])->take(4)->implode(', ') : __('candidates.detail.not_available');
                                                    $formation = $cv && !empty($cv->parsed_education) ? ($cv->parsed_education[0]['degree_name'] ?? __('candidates.detail.not_available')) : __('candidates.detail.not_available');
                                                    $experience = $cv && $cv->total_years_experience !== null ? number_format((float) $cv->total_years_experience, 1) . ' ans' : __('candidates.detail.not_available');
                                                    $score = $app->global_match_score !== null ? number_format((float) $app->global_match_score, 1) . '/100' : '-';
                                                @endphp
                                                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm hover:border-aura-300">
                                                    <div class="mb-3 flex items-center justify-between">
                                                        <p class="font-semibold text-slate-900">{{ $app->candidate?->full_name ?? 'Unknown' }}</p>
                                                        <span class="rounded-full bg-primary-50 px-2 py-0.5 text-[11px] font-bold text-primary-700 border border-primary-100">{{ $score }}</span>
                                                    </div>
                                                    <div class="space-y-2 text-xs text-slate-600">
                                                        <p><span class="font-semibold text-slate-700">Compétences:</span> {{ $skills ?: '-' }}</p>
                                                        <p><span class="font-semibold text-slate-700">Formation:</span> {{ $formation }}</p>
                                                        <p><span class="font-semibold text-slate-700">Expérience:</span> {{ $experience }}</p>
                                                    </div>
                                                    <a href="{{ route('candidates.index', ['application_id' => $app->id]) }}" class="mt-4 block w-full rounded-lg bg-slate-50 py-1.5 text-center text-xs font-semibold text-slate-700 transition hover:bg-slate-100">
                                                        Voir le profil
                                                    </a>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @empty
                                    <x-empty-state :title="__('candidates.detail.empty_title')" :message="__('candidates.detail.empty_message')" />
                                @endforelse
                            </div>
                        @else
                            @php
                                $candidate = $selectedApplication->candidate;
                                $job = $selectedApplication->job;
                                $scoring = $selectedApplication->scoring;
                                $report = $selectedApplication->unifiedInterviewReport;
                                $blind = \App\Http\Controllers\CandidateWorkspaceController::shouldMaskIdentity(
                                    $job,
                                    (string) optional($selectedApplication->currentStage)->stage_key,
                                    (string) optional($selectedApplication->currentStage)->stage_label
                                );
                                $maskedIdentifier = \App\Http\Controllers\CandidateWorkspaceController::maskedCandidateIdentifier((string) $selectedApplication->id);
                                $displayName = $blind
                                    ? __('candidates.detail.masked_identifier_value', ['identifier' => $maskedIdentifier])
                                    : (string) optional($candidate)->full_name;
                                $query = request()->query();

                                $analysisRowsCollection = $analysisByApplication instanceof \Illuminate\Support\Collection
                                    ? $analysisByApplication
                                    : collect($analysisByApplication ?? []);
                                $analysisRow = (array) $analysisRowsCollection->get((string) $selectedApplication->id, []);
                                $analysisStatus = (string) ($analysisRow['analysis_status'] ?? ($scoring?->analysis_status ?? 'pending_analysis'));
                                $analysisStatusLabel = \App\Services\Analysis\CandidateAnalysisService::analysisStatusLabel($analysisStatus);
                                $rankingPosition = is_numeric($analysisRow['ranking_position'] ?? null)
                                    ? (int) $analysisRow['ranking_position']
                                    : (is_numeric($scoring?->ranking_position ?? null) ? (int) $scoring->ranking_position : null);
                                $isTopThree = (bool) ($analysisRow['is_top_three'] ?? $scoring?->is_top_three ?? false);

                                $analysisScore = is_numeric($analysisRow['total_score'] ?? null)
                                    ? (float) $analysisRow['total_score']
                                    : (is_numeric($scoring?->global_match_score ?? null) ? (float) $scoring->global_match_score : 0.0);

                                $componentScores = is_array($scoring?->component_scores_json ?? null)
                                    ? $scoring->component_scores_json
                                    : [];
                                $sourceStatuses = is_array($scoring?->source_status_json ?? null)
                                    ? $scoring->source_status_json
                                    : [];
                                $cvSourceStatus = (string) data_get($sourceStatuses, 'cv', '');
                                $cvSourceStatusLabel = $cvSourceStatus !== ''
                                    ? \App\Services\Analysis\CandidateAnalysisService::sourceStatusLabel($cvSourceStatus, 'cv')
                                    : '';
                                $skillsComponent = (array) data_get($componentScores, 'skills_match', []);
                                $vrinFromScoring = (array) ($scoring?->vrin_json ?? []);
                                $evaluationModel = (array) data_get($vrinFromScoring, 'evaluation_model', []);

                                $acquired = collect((array) data_get($vrinFromScoring, 'acquired_skills', data_get($skillsComponent, 'matched', [])))
                                    ->map(fn ($value) => trim((string) $value))
                                    ->filter(fn ($value) => $value !== '')
                                    ->values();
                                $missing = collect((array) data_get($vrinFromScoring, 'missing_skills', data_get($skillsComponent, 'missing', [])))
                                    ->map(fn ($value) => trim((string) $value))
                                    ->filter(fn ($value) => $value !== '')
                                    ->values();
                                $evaluationTechnicalSkills = collect((array) data_get($evaluationModel, 'technical_skills', []))
                                    ->map(fn ($value) => trim((string) $value))
                                    ->filter(fn ($value) => $value !== '')
                                    ->values();
                                $evaluationSoftSkills = collect((array) data_get($evaluationModel, 'soft_skills', []))
                                    ->map(fn ($value) => trim((string) $value))
                                    ->filter(fn ($value) => $value !== '')
                                    ->values();
                                $evaluationRequiredSoftSkills = collect((array) data_get($evaluationModel, 'required_soft_skills', []))
                                    ->map(fn ($value) => \Illuminate\Support\Str::headline(trim((string) $value)))
                                    ->filter(fn ($value) => $value !== '')
                                    ->values();
                                $evaluationMissingCriticalSkills = collect((array) data_get($evaluationModel, 'missing_critical_skills', []))
                                    ->map(fn ($value) => trim((string) $value))
                                    ->filter(fn ($value) => $value !== '')
                                    ->values();
                                $evaluationSummary = trim((string) data_get($evaluationModel, 'concise_summary', ''));
                                $evaluationExperienceYears = data_get($evaluationModel, 'relevant_experience.years');
                                $evaluationExperienceHighlights = collect((array) data_get($evaluationModel, 'relevant_experience.highlights', []))
                                    ->map(fn ($value) => trim((string) $value))
                                    ->filter(fn ($value) => $value !== '')
                                    ->values();
                                $evaluationMatchScores = collect([
                                    [
                                        'label' => __('candidates.detail.evaluation_model.technical_match'),
                                        'score' => data_get($evaluationModel, 'match_scores.technical'),
                                        'tone' => 'primary',
                                    ],
                                    [
                                        'label' => __('candidates.detail.evaluation_model.soft_match'),
                                        'score' => data_get($evaluationModel, 'match_scores.soft'),
                                        'tone' => 'success',
                                    ],
                                    [
                                        'label' => __('candidates.detail.evaluation_model.experience_match'),
                                        'score' => data_get($evaluationModel, 'match_scores.experience'),
                                        'tone' => 'aura',
                                    ],
                                ])->map(function (array $item): array {
                                    $score = $item['score'];

                                    return [
                                        'label' => (string) $item['label'],
                                        'score' => is_numeric($score) ? max(0, min(100, (float) $score)) : null,
                                        'tone' => (string) $item['tone'],
                                    ];
                                });

                                $strengths = collect((array) ($scoring?->strengths_json ?? []))
                                    ->map(fn ($value) => trim((string) $value))
                                    ->filter(fn ($value) => $value !== '')
                                    ->values();
                                $weaknesses = collect((array) ($scoring?->weaknesses_json ?? []))
                                    ->map(fn ($value) => trim((string) $value))
                                    ->filter(fn ($value) => $value !== '')
                                    ->values();
                                $overallRecommendation = trim((string) ($scoring?->overall_recommendation ?? ''));
                                $xaiSummary = trim((string) ($scoring?->xai_summary ?? ''));
                                if ($xaiSummary === '' && $overallRecommendation !== '') {
                                    $xaiSummary = $overallRecommendation;
                                }

                                $hasMatchScore = $analysisStatus !== 'pending_analysis' || $analysisScore > 0;
                                $hasAiSynthesis = $xaiSummary !== '' || $strengths->isNotEmpty() || $weaknesses->isNotEmpty() || $overallRecommendation !== '';
                                $matchPct = max(0, min(100, $analysisScore));
                                $hasSkillGap = $acquired->isNotEmpty() || $missing->isNotEmpty();
                                $hasEvaluationModel = $evaluationSummary !== ''
                                    || $evaluationTechnicalSkills->isNotEmpty()
                                    || $evaluationSoftSkills->isNotEmpty()
                                    || $evaluationRequiredSoftSkills->isNotEmpty()
                                    || $evaluationMissingCriticalSkills->isNotEmpty()
                                    || $evaluationExperienceHighlights->isNotEmpty()
                                    || is_numeric($evaluationExperienceYears)
                                    || $evaluationMatchScores->contains(fn (array $score): bool => is_numeric($score['score'] ?? null));
                                $analysisIndicators = (array) ($analysisRow['indicators'] ?? []);
                                $matchRadarAxes = collect([
                                    ['key' => \App\Services\Analysis\CandidateAnalysisService::FACTOR_SKILLS_MATCH, 'label' => __('jobs.weighting.skills_match')],
                                    ['key' => \App\Services\Analysis\CandidateAnalysisService::FACTOR_EXPERIENCE_MATCH, 'label' => __('jobs.weighting.experience_match')],
                                    ['key' => \App\Services\Analysis\CandidateAnalysisService::FACTOR_EDUCATION_MATCH, 'label' => __('jobs.weighting.education_match')],
                                    ['key' => \App\Services\Analysis\CandidateAnalysisService::FACTOR_ASSESSMENT, 'label' => __('jobs.weighting.assessment_performance')],
                                    ['key' => \App\Services\Analysis\CandidateAnalysisService::FACTOR_INTERVIEW, 'label' => __('jobs.weighting.interview_performance')],
                                    ['key' => \App\Services\Analysis\CandidateAnalysisService::FACTOR_STRATEGY_LAB, 'label' => __('jobs.weighting.strategy_lab')],
                                ])->map(function (array $axis) use ($componentScores, $analysisIndicators): array {
                                    $score = data_get($componentScores, $axis['key'].'.score');
                                    $fallbackScore = match ((string) ($axis['key'] ?? '')) {
                                        \App\Services\Analysis\CandidateAnalysisService::FACTOR_SKILLS_MATCH => data_get($analysisIndicators, 'skills'),
                                        \App\Services\Analysis\CandidateAnalysisService::FACTOR_EXPERIENCE_MATCH => data_get($analysisIndicators, 'experience'),
                                        \App\Services\Analysis\CandidateAnalysisService::FACTOR_EDUCATION_MATCH => data_get($analysisIndicators, 'education'),
                                        default => null,
                                    };
                                    $effectiveScore = is_numeric($score)
                                        ? (float) $score
                                        : (is_numeric($fallbackScore) ? (float) $fallbackScore : null);

                                    return [
                                        'label' => (string) $axis['label'],
                                        'score' => is_numeric($effectiveScore) ? max(0, min(100, (float) $effectiveScore)) : 0.0,
                                        'has_score' => is_numeric($effectiveScore),
                                    ];
                                })->values();
                                $hasMatchRadar = $matchRadarAxes->contains(fn (array $axis): bool => (bool) ($axis['has_score'] ?? false));

                                $ocean = [
                                    (int) ($report->ocean_openness ?? 0),
                                    (int) ($report->ocean_conscientiousness ?? 0),
                                    (int) ($report->ocean_extraversion ?? 0),
                                    (int) ($report->ocean_agreeableness ?? 0),
                                    (int) ($report->ocean_neuroticism ?? 0),
                                ];
                                $hasOcean = $report && collect($ocean)->filter(fn ($value) => $value > 0)->isNotEmpty();
                                $baseline = [
                                    (int) ($oceanBaseline?->openness ?? 0),
                                    (int) ($oceanBaseline?->conscientiousness ?? 0),
                                    (int) ($oceanBaseline?->extraversion ?? 0),
                                    (int) ($oceanBaseline?->agreeableness ?? 0),
                                    (int) ($oceanBaseline?->neuroticism ?? 0),
                                ];
                                $hasBaseline = collect($baseline)->filter(fn ($value) => $value > 0)->isNotEmpty();

                                $motivation = (float) ($report->match_percentage ?? data_get($report?->ai_full_payload, 'motivation_score', 0));
                                $hasMotivation = $report && (
                                    $report->match_percentage !== null
                                    || data_get($report?->ai_full_payload, 'motivation_score') !== null
                                );

                                $salaryFit = (float) ($report->salary_fit_score ?? data_get($report?->ai_full_payload, 'salary.fit_score', 0));
                                $hasSalary = $report && (
                                    $report->salary_fit_score !== null
                                    || $report->salary_expected_min !== null
                                    || $report->salary_expected_max !== null
                                    || data_get($report?->ai_full_payload, 'salary.fit_score') !== null
                                );

                                $documents = $candidate?->documents ?? collect();
                                $strategyBrief = $selectedApplication->strategyLabBrief;
                                $strategySubmission = $selectedApplication->strategyLabSubmission;
                                $strategySummary = $selectedApplication->strategyLabAiSummary;
                                $strategyEligible = \App\Http\Controllers\StrategyLabController::canAccessStrategyLab($selectedApplication);
                                $strategyEligibilityError = \App\Http\Controllers\StrategyLabController::strategyLabEligibilityError($selectedApplication);
                                $strategyDeadline = $strategyBrief?->deadline_at;
                                $strategyPastDeadline = $strategyDeadline && $strategyDeadline->isPast();
                                $strategyDecisionStatus = trim((string) ($strategyBrief?->final_decision_status ?? ''));
                                $hasStrategyFinalDecision = $strategyDecisionStatus !== '';
                                $canSetStrategyFinalDecision = $strategySubmission && $strategySummary && $strategyBrief
                                    && (string) $strategyBrief->status === \App\Models\StrategyLabBrief::STATUS_REVIEWED;
                                $videoResponses = $selectedApplication->videoInterviewResponses ?? collect();
                                $latestVideoResponseByQuestion = $videoResponses
                                    ->groupBy(fn ($response) => (string) $response->question_id)
                                    ->map(fn ($responses) => $responses->sortByDesc('attempt_number')->first())
                                    ->sortBy(fn ($response) => (int) ($response?->question?->display_order ?? 999))
                                    ->values();
                                $videoUnifiedStatus = (string) ($latestVideoUnifiedRequest?->status ?? '');
                                $videoReportFailed = $videoUnifiedStatus === \App\Models\AiRequest::STATUS_FAILED;
                                $videoReportProcessing = in_array($videoUnifiedStatus, [\App\Models\AiRequest::STATUS_QUEUED, \App\Models\AiRequest::STATUS_RUNNING], true);

                                $latestCvParsingResult = ($selectedApplication->cvParsingResults ?? collect())->first();
                                $parsedCvHardSkills = collect((array) ($latestCvParsingResult?->hard_skills_json ?? $latestCvParsingResult?->extracted_skills ?? []))
                                    ->map(fn ($value) => trim((string) $value))
                                    ->filter(fn ($value) => $value !== '')
                                    ->values();
                                $parsedCvSoftSkills = collect((array) ($latestCvParsingResult?->soft_skills_json ?? []))
                                    ->map(fn ($value) => trim((string) $value))
                                    ->filter(fn ($value) => $value !== '')
                                    ->values();
                                $parsedCvLanguages = collect((array) ($latestCvParsingResult?->languages_json ?? []))
                                    ->map(fn ($value) => trim((string) $value))
                                    ->filter(fn ($value) => $value !== '')
                                    ->values();
                                $parsedCvTools = collect((array) ($latestCvParsingResult?->tools_frameworks_json ?? []))
                                    ->map(fn ($value) => trim((string) $value))
                                    ->filter(fn ($value) => $value !== '')
                                    ->values();
                                $parsedCvExperience = collect((array) ($latestCvParsingResult?->experience_entries_json ?? []))
                                    ->filter(fn ($value) => is_array($value))
                                    ->values();
                                $parsedCvEducation = collect((array) ($latestCvParsingResult?->education_entries_json ?? []))
                                    ->filter(fn ($value) => is_array($value))
                                    ->values();
                                $parsedCvCertifications = collect((array) ($latestCvParsingResult?->certifications_json ?? []))
                                    ->filter(fn ($value) => is_array($value))
                                    ->values();
                                $parsedCvProjects = collect((array) ($latestCvParsingResult?->projects_json ?? []))
                                    ->filter(fn ($value) => is_array($value))
                                    ->values();
                                $parsedCvKeywords = collect((array) ($latestCvParsingResult?->keywords_json ?? []))
                                    ->map(fn ($value) => trim((string) $value))
                                    ->filter(fn ($value) => $value !== '')
                                    ->values();
                                $parsedCvSummary = trim((string) ($latestCvParsingResult?->profile_summary ?? ''));
                                $parsedCvMetadata = (array) ($latestCvParsingResult?->parsed_metadata_json ?? []);
                                $cvFlags = (array) ($latestCvParsingResult?->flags_json ?? []);
                                $cvParsingStatus = (string) ($latestCvParsingRequest?->status ?? '');
                                $cvParsingProcessing = in_array($cvParsingStatus, [\App\Models\AiRequest::STATUS_QUEUED, \App\Models\AiRequest::STATUS_RUNNING], true);
                                $cvParsingFailed = $cvParsingStatus === \App\Models\AiRequest::STATUS_FAILED;
                                $hasCvParsingResult = $latestCvParsingResult !== null;
                                $isHiredApplication = (string) $selectedApplication->status === \App\Models\Application::STATUS_HIRED;
                                $offer = $selectedApplication->offer;
                                $contract = $selectedApplication->contract;
                                $onboardingDocuments = $selectedApplication->onboardingDocuments ?? collect();
                                $onboardingScheduleItems = $selectedApplication->onboardingScheduleItems ?? collect();
                                $onboardingTasks = $selectedApplication->onboardingTasks ?? collect();
                                $analysisRows = collect((array) data_get($analysisSnapshot, 'rows', []));
                                $analysisTopThree = collect((array) data_get($analysisSnapshot, 'top_three', []));
                                $analysisDistribution = collect((array) data_get($analysisSnapshot, 'score_distribution', []));
                                $analysisFairness = is_array(data_get($analysisSnapshot, 'fairness'))
                                    ? (array) data_get($analysisSnapshot, 'fairness')
                                    : null;
                                $maxAnalysisDistributionCount = max(1, (int) $analysisDistribution->max('count'));
                            @endphp

                            <div class="space-y-4">
                                <div class="rounded-2xl border border-slate-200 bg-white/70 p-4">
                                    <div class="flex flex-wrap items-center justify-between gap-3">
                                        <div>
                                            <div class="flex items-center gap-2">
                                                @php
                                                    $cvDocument = $selectedApplication->candidate?->documents->first();
                                                    $cvUrl = $cvDocument ? \App\Http\Controllers\CandidateWorkspaceController::signedDocumentUrl($cvDocument) : null;
                                                @endphp
                                                <h3 class="text-xl font-semibold text-slate-900 flex items-center gap-2">
                                                    {{ $displayName }}
                                                    @if($cvUrl)
                                                        <a href="{{ $cvUrl }}" target="_blank" class="text-slate-400 hover:text-aura-600 transition-colors" title="View CV">
                                                            <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                              <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                                              <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                            </svg>
                                                        </a>
                                                    @endif
                                                </h3>
                                                @if($blind)
                                                    <x-badge>{{ __('candidates.detail.blind_mode') }}</x-badge>
                                                @endif
                                            </div>
                                            <p class="mt-1 text-sm text-slate-600">{{ $job?->title }} - {{ optional($selectedApplication->currentStage)->stage_label }}</p>
                                            @if($blind)
                                                <p class="mt-1 text-xs text-slate-500">{{ __('candidates.detail.blind_mode_hint') }}</p>
                                                <p class="mt-1 text-xs font-semibold text-slate-600">{{ __('candidates.detail.masked_identifier', ['identifier' => $maskedIdentifier]) }}</p>
                                            @endif
                                            <p class="mt-1 text-xs text-slate-500">{{ __('candidates.detail.missing_feedback') }}: {{ $missingFeedbackCount }}</p>
                                        </div>
                                        <div class="flex flex-wrap gap-2">
                                            <a href="{{ route('interviews.index', ['company_id' => $selectedCompanyId]) }}" class="rounded-lg border border-aura-300/50 bg-white px-3 py-1.5 text-xs font-medium text-slate-800">
                                                {{ __('candidates.detail.view_interviews') }}
                                            </a>
                                            @if($videoReportFailed)
                                                <form method="POST" action="{{ route('candidates.video-report.retry', ['application' => $selectedApplication->id]) }}">
                                                    @csrf
                                                    @foreach($query as $key => $value)
                                                        <input type="hidden" name="{{ $key }}" value="{{ is_scalar($value) ? (string) $value : '' }}">
                                                    @endforeach
                                                    <button type="submit" class="rounded-lg border border-danger-300/60 bg-danger-50 px-3 py-1.5 text-xs font-medium text-danger-700">
                                                        {{ __('candidates.detail.retry_video_report') }}
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="mb-4 grid gap-3 lg:grid-cols-4">
                                    <div class="rounded-xl border border-slate-200 bg-white p-4 lg:col-span-1 flex flex-col justify-center items-center">
                                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-600 mb-2">Score Match</p>
                                        <div class="grid size-16 place-items-center rounded-full text-lg font-semibold text-slate-900" style="background: conic-gradient(#6467f2 {{ $matchPct ?? 0 }}%, #e2e8f0 {{ $matchPct ?? 0 }}%);">
                                            <div class="grid size-12 place-items-center rounded-full bg-white">{{ number_format($matchPct ?? 0, 0) }}%</div>
                                        </div>
                                    </div>
                                    <div class="rounded-xl border border-slate-200 bg-white p-4 lg:col-span-3">
                                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-600">Profil Simplifié</p>
                                        <div class="mt-3 space-y-2 text-sm text-slate-700">
                                            @php
                                                $cv = $latestCvParsingResult;
                                                $skills = $cv && !empty($cv->extracted_skills) ? collect($cv->extracted_skills)->take(8)->implode(', ') : __('candidates.detail.not_available');
                                                $formation = $cv && !empty($cv->education_entries_json) ? ($cv->education_entries_json[0]['degree_name'] ?? __('candidates.detail.not_available')) : __('candidates.detail.not_available');
                                                $experience = $cv && $cv->total_years_experience !== null ? number_format((float) $cv->total_years_experience, 1) . ' ans' : __('candidates.detail.not_available');
                                            @endphp
                                            <p><span class="font-semibold text-slate-900">Compétences principales:</span> {{ $skills ?: '-' }}</p>
                                            <p><span class="font-semibold text-slate-900">Formation:</span> {{ $formation }}</p>
                                            <p><span class="font-semibold text-slate-900">Années d'expérience:</span> {{ $experience }}</p>
                                            @if($parsedCvSummary)
                                                <div class="mt-3 pt-3 border-t border-slate-100">
                                                    <p class="text-xs text-slate-500 leading-relaxed">{{ $parsedCvSummary }}</p>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                    <div class="mt-3 grid gap-2 md:grid-cols-2 2xl:grid-cols-4">
                                        <form method="POST" action="{{ route('candidates.move-stage', ['application' => $selectedApplication->id]) }}" class="space-y-2 rounded-xl border border-slate-200 bg-white/80 p-2">
                                            @csrf
                                            @foreach($query as $key => $value)
                                                <input type="hidden" name="{{ $key }}" value="{{ is_scalar($value) ? (string) $value : '' }}">
                                            @endforeach
                                            <select name="stage_id" class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs" required>
                                                @foreach(($job?->pipelineStages ?? collect()) as $stageOption)
                                                    <option value="{{ $stageOption->id }}" @selected((string) $selectedApplication->current_stage_id === (string) $stageOption->id)>{{ $stageOption->stage_label }}</option>
                                                @endforeach
                                            </select>
                                            <button type="submit" class="w-full rounded-lg border border-primary-300/60 bg-primary-50 px-2 py-1.5 text-xs font-semibold text-primary-800 transition-weightless hover:bg-primary-100/80">
                                                {{ __('candidates.detail.move_stage') }}
                                            </button>
                                        </form>

                                        <form method="POST" action="{{ route('candidates.schedule-interview', ['application' => $selectedApplication->id]) }}" class="space-y-2 rounded-xl border border-slate-200 bg-white/80 p-2" data-interview-schedule-form>
                                            @csrf
                                            @foreach($query as $key => $value)
                                                <input type="hidden" name="{{ $key }}" value="{{ is_scalar($value) ? (string) $value : '' }}">
                                            @endforeach

                                            <input type="datetime-local" name="scheduled_for" class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs" required>
                                            <input type="number" name="duration_minutes" min="15" step="15" value="60" class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs" placeholder="{{ __('interviews.fields.duration_minutes') }}">
                                            <input type="hidden" name="timezone" value="{{ config('app.timezone', 'UTC') }}">
                                            <select name="interviewer_user_ids[]" multiple required data-placeholder="{{ __('interviews.fields.interviewers') }}" class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs">
                                                @foreach($interviewers as $interviewer)
                                                    <option value="{{ $interviewer->id }}">{{ $interviewer->profile?->full_name ?? $interviewer->email }}</option>
                                                @endforeach
                                            </select>
                                            <div data-interview-meeting-link-group>
                                                <input type="url" name="meeting_link" value="{{ old('meeting_link', $actorZoomLink) }}" placeholder="{{ __('interviews.fields.meeting_link') }}" class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs">
                                            </div>
                                            <div class="hidden" data-interview-location-address-group>
                                                <input type="text" name="location_address" value="{{ old('location_address') }}" placeholder="{{ __('interviews.fields.location_address') }}" class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs">
                                            </div>
                                            <select name="location_type" data-placeholder="{{ __('interviews.fields.location_type') }}" class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs" data-interview-location-select>
                                                <option value="{{ \App\Models\Interview::LOCATION_ZOOM }}" @selected(old('location_type', \App\Models\Interview::LOCATION_ZOOM) === \App\Models\Interview::LOCATION_ZOOM)>{{ __('interviews.location_types.zoom') }}</option>
                                                <option value="{{ \App\Models\Interview::LOCATION_IN_PERSON }}" @selected(old('location_type') === \App\Models\Interview::LOCATION_IN_PERSON)>{{ __('interviews.location_types.in_person') }}</option>
                                                <option value="{{ \App\Models\Interview::LOCATION_OTHER }}" @selected(old('location_type') === \App\Models\Interview::LOCATION_OTHER)>{{ __('interviews.location_types.other') }}</option>
                                            </select>
                                            <input type="text" name="channel" value="{{ old('channel') }}" placeholder="{{ __('candidates.detail.schedule_channel') }}" class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs">
                                            <textarea name="notes" rows="2" placeholder="{{ __('interviews.fields.notes') }}" class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs"></textarea>

                                            <button type="submit" class="w-full rounded-lg border border-primary-300/60 bg-primary-50 px-2 py-1.5 text-xs font-semibold text-primary-800 transition-weightless hover:bg-primary-100/80">
                                                {{ __('candidates.detail.schedule_interview') }}
                                            </button>
                                        </form>

                                        <form method="POST" action="{{ route('candidates.request-feedback', ['application' => $selectedApplication->id]) }}" class="space-y-2 rounded-xl border border-slate-200 bg-white/80 p-2">
                                            @csrf
                                            @foreach($query as $key => $value)
                                                <input type="hidden" name="{{ $key }}" value="{{ is_scalar($value) ? (string) $value : '' }}">
                                            @endforeach
                                            <input type="text" name="message" placeholder="{{ __('candidates.detail.feedback_message') }}" class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs">
                                            <button type="submit" class="w-full rounded-lg border border-primary-300/60 bg-primary-50 px-2 py-1.5 text-xs font-semibold text-primary-800 transition-weightless hover:bg-primary-100/80">
                                                {{ __('candidates.detail.request_feedback') }}
                                            </button>
                                        </form>

                                        <form method="POST" action="{{ route('candidates.reject', ['application' => $selectedApplication->id]) }}" class="space-y-2 rounded-xl border border-danger-200 bg-danger-50/60 p-2">
                                            @csrf
                                            @foreach($query as $key => $value)
                                                <input type="hidden" name="{{ $key }}" value="{{ is_scalar($value) ? (string) $value : '' }}">
                                            @endforeach
                                            <input type="text" name="reason" placeholder="{{ __('candidates.detail.reject_reason') }}" class="w-full rounded-lg border border-danger-200 bg-white px-2 py-1.5 text-xs" required>
                                            <button type="submit" class="w-full rounded-lg border border-danger-200 bg-white px-2 py-1.5 text-xs font-semibold text-danger-700">
                                                {{ __('candidates.detail.reject') }}
                                            </button>
                                        </form>
                                    </div>
                                </div>

                                <div class="space-y-4" data-candidate-tabs-root data-default-tab="{{ $isHiredApplication ? 'onboarding' : 'candidate' }}">
                                    @if($isHiredApplication)
                                        <div class="rounded-2xl border border-slate-200 bg-white/75 p-3">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <button type="button"
                                                        class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 transition-weightless"
                                                        data-candidate-tab-button="candidate">
                                                    {{ __('candidates.onboarding.tabs.candidate_detail') }}
                                                </button>
                                                <button type="button"
                                                        class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 transition-weightless"
                                                        data-candidate-tab-button="onboarding">
                                                    {{ __('candidates.onboarding.tabs.onboarding_hub') }}
                                                </button>
                                            </div>
                                        </div>
                                    @endif

                                    <div data-candidate-tab-panel="candidate" class="space-y-4 {{ $isHiredApplication ? 'hidden' : '' }}">
                                <div class="rounded-2xl border border-slate-200 bg-white/75 p-4">
                                    <div class="flex flex-wrap items-center justify-between gap-3">
                                        <div>
                                            <p class="text-xs uppercase tracking-wide text-slate-600">{{ __('strategy_lab.recruiter.title') }}</p>
                                            <p class="mt-1 text-xs text-slate-500">{{ __('strategy_lab.recruiter.subtitle') }}</p>
                                        </div>
                                        @if($strategyBrief)
                                            <div class="flex items-center gap-2">
                                                <x-badge>{{ __('strategy_lab.status.'.$strategyBrief->status) }}</x-badge>
                                                @if($hasStrategyFinalDecision)
                                                    <x-badge :variant="$strategyDecisionStatus === \App\Models\StrategyLabBrief::DECISION_APPROVED ? 'success' : 'danger'">
                                                        {{ __('strategy_lab.decision_status.'.$strategyDecisionStatus) }}
                                                    </x-badge>
                                                @endif
                                            </div>
                                        @endif
                                    </div>

                                    @if(! $strategyEligible)
                                        <p class="mt-3 text-xs text-danger-700">{{ $strategyEligibilityError !== '' ? $strategyEligibilityError : __('strategy_lab.messages.shortlist_required') }}</p>
                                    @elseif(! $strategyBrief)
                                        <form method="POST" action="{{ route('candidates.strategy-lab.assign', ['application' => $selectedApplication->id]) }}" class="mt-3 space-y-2">
                                            @csrf
                                            @foreach($query as $key => $value)
                                                <input type="hidden" name="{{ $key }}" value="{{ is_scalar($value) ? (string) $value : '' }}">
                                            @endforeach
                                            <input type="text" name="brief_title" value="{{ old('brief_title') }}" placeholder="{{ __('strategy_lab.fields.brief_title') }}" class="rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs" required>
                                            <p class="text-xs text-slate-600">{{ __('strategy_lab.labels.auto_48h_window') }}</p>
                                            <button type="submit" class="rounded-lg border border-aura-300/50 bg-white px-2 py-1.5 text-xs font-semibold text-slate-800">
                                                {{ __('strategy_lab.actions.assign_brief') }}
                                            </button>
                                        </form>
                                    @else
                                        <div class="mt-3 grid gap-3 md:grid-cols-2">
                                            <div class="rounded-xl border border-slate-200 bg-white p-3">
                                                <p class="text-xs uppercase tracking-wide text-slate-600">{{ __('strategy_lab.labels.brief') }}</p>
                                                <p class="mt-1 text-sm font-semibold text-slate-900">{{ $strategyBrief->brief_title }}</p>
                                                <p class="mt-1 text-xs text-slate-600">{{ __('strategy_lab.labels.deadline') }}: {{ optional($strategyDeadline)->format('Y-m-d H:i') }} UTC</p>
                                                @if($strategyPastDeadline)
                                                    <p class="mt-1 text-xs text-danger-700">{{ __('strategy_lab.messages.deadline_passed') }}</p>
                                                @endif
                                                @if($strategyBrief->brief_pdf_url)
                                                    <a href="{{ \App\Http\Controllers\StrategyLabController::signedBriefUrl($strategyBrief) }}" class="mt-2 inline-flex rounded-md border border-aura-300/50 bg-white px-2 py-1 text-xs text-slate-800">
                                                        {{ __('strategy_lab.actions.download_brief') }}
                                                    </a>
                                                @else
                                                    <p class="mt-2 text-xs text-primary-700">{{ __('strategy_lab.messages.brief_processing') }}</p>
                                                @endif
                                            </div>

                                            <div class="rounded-xl border border-slate-200 bg-white p-3">
                                                <p class="text-xs uppercase tracking-wide text-slate-600">{{ __('strategy_lab.labels.submission') }}</p>
                                                @if($strategySubmission)
                                                    <p class="mt-1 text-xs text-slate-600">{{ $strategySubmission->original_filename }} ({{ $strategySubmission->submission_type }})</p>
                                                    <a href="{{ \App\Http\Controllers\StrategyLabController::signedSubmissionUrl($strategySubmission) }}" class="mt-2 inline-flex rounded-md border border-aura-300/50 bg-white px-2 py-1 text-xs text-slate-800">
                                                        {{ __('strategy_lab.actions.open_submission') }}
                                                    </a>
                                                @else
                                                    <p class="mt-1 text-xs text-slate-600">{{ __('strategy_lab.messages.not_submitted') }}</p>
                                                @endif
                                            </div>
                                        </div>

                                        <div class="mt-3 grid gap-3 md:grid-cols-3">
                                            <form method="POST" action="{{ route('candidates.strategy-lab.extend-deadline', ['application' => $selectedApplication->id]) }}" class="rounded-xl border border-slate-200 bg-white p-3">
                                                @csrf
                                                @foreach($query as $key => $value)
                                                    <input type="hidden" name="{{ $key }}" value="{{ is_scalar($value) ? (string) $value : '' }}">
                                                @endforeach
                                                <label class="text-xs uppercase tracking-wide text-slate-600">{{ __('strategy_lab.fields.extend_deadline') }}</label>
                                                <input type="datetime-local" name="deadline_at" class="mt-2 w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs" required>
                                                <button type="submit" class="mt-2 rounded-lg border border-aura-300/50 bg-white px-3 py-1.5 text-xs font-semibold text-slate-800">
                                                    {{ __('strategy_lab.actions.extend_deadline') }}
                                                </button>
                                            </form>

                                            <form method="POST" action="{{ route('candidates.strategy-lab.mark-reviewed', ['application' => $selectedApplication->id]) }}" class="rounded-xl border border-slate-200 bg-white p-3">
                                                @csrf
                                                @foreach($query as $key => $value)
                                                    <input type="hidden" name="{{ $key }}" value="{{ is_scalar($value) ? (string) $value : '' }}">
                                                @endforeach
                                                <p class="text-xs uppercase tracking-wide text-slate-600">{{ __('strategy_lab.labels.recruiter_review') }}</p>
                                                @if($strategySummary)
                                                    @php
                                                        $strategyStrengths = collect((array) $strategySummary->strengths_json)->map(fn ($item) => trim((string) $item))->filter()->values();
                                                        $strategyWeaknesses = collect((array) $strategySummary->weaknesses_json)->map(fn ($item) => trim((string) $item))->filter()->values();
                                                    @endphp
                                                    <p class="mt-1 text-xs text-slate-700">{{ $strategySummary->executive_summary_text }}</p>
                                                    <p class="mt-1 text-xs text-slate-600">{{ __('strategy_lab.labels.creativity_score') }}: {{ number_format((float) $strategySummary->creativity_score, 2) }}</p>
                                                    @if(trim((string) ($strategySummary->overall_recommendation ?? '')) !== '')
                                                        <p class="mt-1 text-xs text-slate-600">{{ __('strategy_lab.labels.overall_recommendation') }}: {{ $strategySummary->overall_recommendation }}</p>
                                                    @endif
                                                    @if($strategyStrengths->isNotEmpty())
                                                        <p class="mt-1 text-xs text-slate-600">{{ __('strategy_lab.labels.strengths') }}: {{ $strategyStrengths->implode(', ') }}</p>
                                                    @endif
                                                    @if($strategyWeaknesses->isNotEmpty())
                                                        <p class="mt-1 text-xs text-slate-600">{{ __('strategy_lab.labels.weaknesses') }}: {{ $strategyWeaknesses->implode(', ') }}</p>
                                                    @endif
                                                @else
                                                    <p class="mt-1 text-xs text-primary-700">{{ __('strategy_lab.messages.summary_processing') }}</p>
                                                @endif
                                                <button type="submit" class="mt-2 rounded-lg border border-success-300/60 bg-success-50 px-3 py-1.5 text-xs font-semibold text-success-800" @disabled(! $strategySubmission || ! $strategySummary)>
                                                    {{ __('strategy_lab.actions.mark_reviewed') }}
                                                </button>
                                            </form>

                                            <form method="POST" action="{{ route('candidates.strategy-lab.final-decision', ['application' => $selectedApplication->id]) }}" class="rounded-xl border border-slate-200 bg-white p-3">
                                                @csrf
                                                @foreach($query as $key => $value)
                                                    <input type="hidden" name="{{ $key }}" value="{{ is_scalar($value) ? (string) $value : '' }}">
                                                @endforeach
                                                <p class="text-xs uppercase tracking-wide text-slate-600">{{ __('strategy_lab.labels.final_decision') }}</p>
                                                @if($hasStrategyFinalDecision)
                                                    <p class="mt-1 text-xs text-slate-700">{{ __('strategy_lab.labels.final_decision_status') }}: {{ __('strategy_lab.decision_status.'.$strategyDecisionStatus) }}</p>
                                                    @if(trim((string) ($strategyBrief?->final_decision_note ?? '')) !== '')
                                                        <p class="mt-1 text-xs text-slate-600">{{ $strategyBrief->final_decision_note }}</p>
                                                    @endif
                                                @else
                                                    <p class="mt-1 text-xs text-slate-600">{{ __('strategy_lab.messages.final_decision_pending') }}</p>
                                                @endif
                                                <label for="strategy-decision-note-{{ $selectedApplication->id }}" class="mt-2 block text-xs uppercase tracking-wide text-slate-600">{{ __('strategy_lab.fields.final_decision_note') }}</label>
                                                <textarea id="strategy-decision-note-{{ $selectedApplication->id }}" name="decision_note" rows="3" maxlength="2000" class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs" placeholder="{{ __('strategy_lab.fields.final_decision_note') }}">{{ old('decision_note') }}</textarea>
                                                <div class="mt-2 grid grid-cols-2 gap-2">
                                                    <button type="submit" name="decision_status" value="{{ \App\Models\StrategyLabBrief::DECISION_APPROVED }}" class="rounded-lg border border-success-300/60 bg-success-50 px-2 py-1.5 text-xs font-semibold text-success-800" @disabled(! $canSetStrategyFinalDecision)>
                                                        {{ __('strategy_lab.actions.approve_candidate') }}
                                                    </button>
                                                    <button type="submit" name="decision_status" value="{{ \App\Models\StrategyLabBrief::DECISION_REJECTED }}" class="rounded-lg border border-danger-300/60 bg-danger-50 px-2 py-1.5 text-xs font-semibold text-danger-800" @disabled(! $canSetStrategyFinalDecision)>
                                                        {{ __('strategy_lab.actions.reject_candidate') }}
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    @endif
                                </div>

                                <div class="rounded-2xl border border-slate-200 bg-white/75 p-4">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <div>
                                            <p class="text-xs uppercase tracking-wide text-slate-600">{{ __('candidates.detail.ranking_grid') }}</p>
                                            <p class="mt-1 text-xs text-slate-500">{{ __('candidates.detail.analysis_auto_hint') }}</p>
                                        </div>
                                        @if($rankingPosition !== null)
                                            <x-badge :variant="$isTopThree ? 'success' : 'pending'">
                                                {{ __('candidates.detail.rank_position') }} #{{ $rankingPosition }}
                                            </x-badge>
                                        @endif
                                    </div>

                                    @if($analysisRows->isEmpty())
                                        <p class="mt-3 text-xs text-slate-600">{{ __('candidates.detail.not_available') }}</p>
                                    @else
                                        <div class="mt-3 overflow-x-auto rounded-xl border border-slate-200 bg-white/80">
                                            <table class="min-w-full divide-y divide-slate-200 text-xs">
                                                <thead class="bg-slate-50">
                                                    <tr>
                                                        <th class="px-3 py-2 text-left font-semibold uppercase tracking-wide text-slate-600">#</th>
                                                        <th class="px-3 py-2 text-left font-semibold uppercase tracking-wide text-slate-600">{{ __('candidates.detail.candidate') }}</th>
                                                        <th class="px-3 py-2 text-left font-semibold uppercase tracking-wide text-slate-600">{{ __('candidates.detail.total_score') }}</th>
                                                        <th class="px-3 py-2 text-left font-semibold uppercase tracking-wide text-slate-600">{{ __('candidates.detail.status_label') }}</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-slate-100">
                                                    @foreach($analysisRows->take(8) as $row)
                                                        @php
                                                            $rowStatus = (string) ($row['analysis_status'] ?? 'pending_analysis');
                                                            $rowStatusLabel = \App\Services\Analysis\CandidateAnalysisService::analysisStatusLabel($rowStatus);
                                                            $isSelectedRow = (string) ($row['application_id'] ?? '') === (string) $selectedApplication->id;
                                                            $rowCandidateLabel = $blind
                                                                ? __('candidates.detail.masked_identifier_value', [
                                                                    'identifier' => \App\Http\Controllers\CandidateWorkspaceController::maskedCandidateIdentifier((string) ($row['application_id'] ?? '')),
                                                                ])
                                                                : (string) ($row['candidate_name'] ?? __('candidates.detail.not_available'));
                                                        @endphp
                                                        <tr @class([$isSelectedRow ? 'bg-primary-50/60' : 'bg-white'])>
                                                            <td class="px-3 py-2 text-slate-700">#{{ (int) ($row['ranking_position'] ?? 0) }}</td>
                                                            <td class="px-3 py-2 text-slate-900">{{ $rowCandidateLabel }}</td>
                                                            <td class="px-3 py-2 text-slate-700">{{ is_numeric($row['total_score'] ?? null) ? number_format((float) $row['total_score'], 1).'/100' : __('candidates.detail.not_available') }}</td>
                                                            <td class="px-3 py-2 text-slate-700">{{ $rowStatusLabel }}</td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    @endif

                                    <div class="mt-3 grid gap-3 lg:grid-cols-3">
                                        <div class="rounded-xl border border-success-200/70 bg-success-50/60 p-3">
                                            <p class="text-[11px] font-semibold uppercase tracking-wide text-success-700">{{ __('candidates.detail.top_three') }}</p>
                                            @if($analysisTopThree->isEmpty())
                                                <p class="mt-1 text-xs text-slate-600">{{ __('candidates.detail.not_available') }}</p>
                                            @else
                                                <ul class="mt-2 space-y-1 text-xs text-slate-700">
                                                    @foreach($analysisTopThree as $row)
                                                        <li>
                                                            #{{ (int) ($row['ranking_position'] ?? 0) }} -
                                                            @if($blind)
                                                                {{ __('candidates.detail.masked_identifier_value', [
                                                                    'identifier' => \App\Http\Controllers\CandidateWorkspaceController::maskedCandidateIdentifier((string) ($row['application_id'] ?? '')),
                                                                ]) }}
                                                            @else
                                                                {{ (string) ($row['candidate_name'] ?? __('candidates.detail.not_available')) }}
                                                            @endif
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            @endif
                                        </div>

                                        <div class="rounded-xl border border-primary-200/70 bg-primary-50/60 p-3">
                                            <p class="text-[11px] font-semibold uppercase tracking-wide text-primary-700">{{ __('candidates.detail.score_distribution') }}</p>
                                            @if($analysisDistribution->isEmpty())
                                                <p class="mt-1 text-xs text-slate-600">{{ __('candidates.detail.not_available') }}</p>
                                            @else
                                                <div class="mt-2 space-y-1.5">
                                                    @foreach($analysisDistribution as $bucket)
                                                        @php
                                                            $bucketCount = (int) ($bucket['count'] ?? 0);
                                                            $bucketWidth = (int) round(($bucketCount / $maxAnalysisDistributionCount) * 100);
                                                        @endphp
                                                        <div>
                                                            <div class="flex items-center justify-between text-[11px] text-slate-700">
                                                                <span>{{ (string) ($bucket['label'] ?? '') }}</span>
                                                                <span>{{ $bucketCount }}</span>
                                                            </div>
                                                            <div class="mt-0.5 h-1.5 overflow-hidden rounded-full bg-primary-100">
                                                                <div class="h-1.5 rounded-full bg-primary-500" style="width: {{ max(4, $bucketWidth) }}%"></div>
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>

                                        <div class="rounded-xl border border-aura-200/70 bg-aura-50/60 p-3">
                                            <p class="text-[11px] font-semibold uppercase tracking-wide text-aura-700">{{ __('candidates.detail.fairness') }}</p>
                                            @if(! is_array($analysisFairness))
                                                <p class="mt-1 text-xs text-slate-600">{{ __('candidates.detail.not_available') }}</p>
                                            @else
                                                @php
                                                    $genderFairness = (array) ($analysisFairness['gender'] ?? []);
                                                    $schoolFairness = (array) ($analysisFairness['school'] ?? []);
                                                @endphp
@endif
                                        </div>
                                    </div>
                                </div>

                                                                <div class="grid gap-3 lg:grid-cols-3">
                                    <div class="rounded-2xl border border-slate-200 bg-white/70 p-4">
                                        <p class="text-xs uppercase tracking-wide text-slate-600">{{ __('candidates.detail.documents') }}</p>
                                        <div class="mt-3 space-y-2">
                                            @forelse($documents as $document)
                                                <div class="flex items-center justify-between rounded-lg border border-slate-200 bg-white p-2">
                                                    <p class="truncate text-xs text-slate-700">{{ $document->original_filename }}</p>
                                                    <a href="{{ \App\Http\Controllers\CandidateWorkspaceController::signedDocumentUrl($document) }}" class="rounded-md border border-aura-200 px-2 py-1 text-xs text-aura-700">
                                                        {{ __('candidates.detail.download') }}
                                                    </a>
                                                </div>
                                            @empty
                                                <p class="text-xs text-slate-600">{{ __('candidates.detail.not_available') }}</p>
                                            @endforelse
                                        </div>
                                    </div>

                                    <div class="rounded-2xl border border-slate-200 bg-white/70 p-4">
                                        <p class="text-xs uppercase tracking-wide text-slate-600">{{ __('candidates.detail.comments') }}</p>
                                        <form method="POST" action="{{ route('candidates.comments.store', ['application' => $selectedApplication->id]) }}" class="mt-3 space-y-2">
                                            @csrf
                                            @foreach($query as $key => $value)
                                                <input type="hidden" name="{{ $key }}" value="{{ is_scalar($value) ? (string) $value : '' }}">
                                            @endforeach
                                            <textarea name="body" rows="4" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2 text-sm" placeholder="{{ __('candidates.detail.comment_placeholder') }}">{{ old('body') }}</textarea>
                                            @error('body')
                                                <p class="text-xs text-danger-700">{{ $message }}</p>
                                            @enderror
                                            <button type="submit" class="rounded-xl bg-success-600 px-3 py-2 text-xs font-semibold text-white">
                                                {{ __('candidates.detail.save_comment') }}
                                            </button>
                                        </form>
                                    </div>

                                    <div class="rounded-2xl border border-slate-200 bg-white/70 p-4">
                                        <p class="text-xs uppercase tracking-wide text-slate-600">{{ __('candidates.detail.reverse_feedback_aggregate') }}</p>
                                        @if(! ($canViewReverseFeedbackAggregate ?? false))
                                            <p class="mt-3 text-xs text-slate-600">{{ __('candidates.detail.reverse_feedback_restricted') }}</p>
                                        @elseif(is_array($reverseFeedbackAggregate ?? null))
                                            <div class="mt-3 grid gap-2 text-xs text-slate-700">
                                                <div class="flex items-center justify-between rounded-lg border border-slate-200 bg-white px-2 py-1.5">
                                                    <span>{{ __('candidates.detail.reverse_feedback_total') }}</span>
                                                    <span class="font-semibold text-slate-900">{{ (int) ($reverseFeedbackAggregate['total'] ?? 0) }}</span>
                                                </div>
                                                <div class="flex items-center justify-between rounded-lg border border-slate-200 bg-white px-2 py-1.5">
                                                    <span>{{ __('candidates.detail.reverse_feedback_clarity') }}</span>
                                                    <span class="font-semibold text-slate-900">{{ number_format((float) ($reverseFeedbackAggregate['avg_clarity'] ?? 0), 1) }}/5</span>
                                                </div>
                                                <div class="flex items-center justify-between rounded-lg border border-slate-200 bg-white px-2 py-1.5">
                                                    <span>{{ __('candidates.detail.reverse_feedback_speed') }}</span>
                                                    <span class="font-semibold text-slate-900">{{ number_format((float) ($reverseFeedbackAggregate['avg_speed'] ?? 0), 1) }}/5</span>
                                                </div>
                                                <div class="flex items-center justify-between rounded-lg border border-slate-200 bg-white px-2 py-1.5">
                                                    <span>{{ __('candidates.detail.reverse_feedback_kindness') }}</span>
                                                    <span class="font-semibold text-slate-900">{{ number_format((float) ($reverseFeedbackAggregate['avg_kindness'] ?? 0), 1) }}/5</span>
                                                </div>
                                            </div>
                                        @else
                                            <p class="mt-3 text-xs text-slate-600">{{ __('candidates.detail.reverse_feedback_none') }}</p>
                                        @endif
                                    </div>
                                </div>

                                <div class="rounded-2xl border border-slate-200 bg-white/70 p-4">
                                    <p class="text-xs uppercase tracking-wide text-slate-600">{{ __('candidates.detail.activity') }}</p>
                                    @php
                                        $items = collect();
                                        foreach ($selectedApplication->activityEvents as $event) {
                                            $eventKey = str_replace('.', '_', (string) $event->event_type);
                                            $labelKey = 'candidates.detail.event_types.'.$eventKey;
                                            $translated = __($labelKey);
                                            $items->push([
                                                'type' => 'event',
                                                'created_at' => $event->created_at,
                                                'label' => $translated !== $labelKey ? $translated : (string) $event->event_type,
                                                'body' => json_encode($event->payload ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                                                'author' => $event->actor?->profile?->full_name ?? $event->actor?->email,
                                                'download_url' => null,
                                            ]);
                                        }
                                        foreach ($selectedApplication->notes as $note) {
                                            $items->push([
                                                'type' => 'comment',
                                                'created_at' => $note->created_at,
                                                'label' => __('candidates.detail.event_types.comment_added'),
                                                'body' => (string) $note->body,
                                                'author' => $note->author?->profile?->full_name ?? $note->author?->email,
                                                'download_url' => null,
                                            ]);
                                        }
                                        foreach ($documents as $document) {
                                            $items->push([
                                                'type' => 'upload',
                                                'created_at' => $document->created_at,
                                                'label' => __('candidates.detail.event_types.upload_document'),
                                                'body' => (string) $document->original_filename,
                                                'author' => null,
                                                'download_url' => \App\Http\Controllers\CandidateWorkspaceController::signedDocumentUrl($document),
                                            ]);
                                        }
                                        $items = $items->sortByDesc('created_at')->values();
                                    @endphp
                                    <div class="mt-3 space-y-2">
                                        @forelse($items as $item)
                                            <div class="rounded-lg border border-slate-200 bg-white p-2">
                                                <div class="flex items-center justify-between gap-2">
                                                    <p class="text-xs font-semibold text-slate-800">{{ $item['label'] }}</p>
                                                    @if($item['download_url'])
                                                        <a href="{{ $item['download_url'] }}" class="rounded-md border border-aura-200 px-2 py-1 text-[11px] text-aura-700">
                                                            {{ __('candidates.detail.download') }}
                                                        </a>
                                                    @endif
                                                </div>
                                                <p class="mt-1 text-xs text-slate-600">{{ $item['body'] }}</p>
                                                <p class="mt-1 text-[11px] text-slate-500">
                                                    {{ optional($item['created_at'])->diffForHumans() }}
                                                    @if($item['author'])
                                                        - {{ $item['author'] }}
                                                    @endif
                                                </p>
                                            </div>
                                        @empty
                                            <p class="text-xs text-slate-600">{{ __('candidates.detail.not_available') }}</p>
                                        @endforelse
                                    </div>
                                </div>

                                    </div>

                                    @if($isHiredApplication)
                                        <div data-candidate-tab-panel="onboarding" class="space-y-4">
                                            <div class="rounded-2xl border border-slate-200 bg-white/75 p-4">
                                                <p class="text-xs uppercase tracking-wide text-slate-600">{{ __('candidates.onboarding.title') }}</p>
                                                <p class="mt-1 text-sm text-slate-700">{{ __('candidates.onboarding.subtitle') }}</p>
                                            </div>

                                            <div class="grid gap-3 xl:grid-cols-2">
                                                <div class="rounded-2xl border border-slate-200 bg-white/75 p-4">
                                                    <p class="text-xs uppercase tracking-wide text-slate-600">{{ __('candidates.onboarding.offer.title') }}</p>
                                                    <form method="POST" action="{{ route('candidates.onboarding.offer.save', ['application' => $selectedApplication->id]) }}" class="mt-3 grid gap-2 md:grid-cols-2">
                                                        @csrf
                                                        @foreach($query as $key => $value)
                                                            <input type="hidden" name="{{ $key }}" value="{{ is_scalar($value) ? (string) $value : '' }}">
                                                        @endforeach
                                                        <select name="offer_status" data-placeholder="{{ __('candidates.onboarding.offer.status') }}" class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs" required>
                                                            @foreach(\App\Models\Offer::statuses() as $offerStatusOption)
                                                                <option value="{{ $offerStatusOption }}" @selected((string) old('offer_status', $offer?->offer_status) === $offerStatusOption)>
                                                                    {{ __('candidates.onboarding.offer.statuses.'.$offerStatusOption) }}
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                        <input type="number" name="salary_amount" step="0.01" min="0" value="{{ old('salary_amount', $offer?->salary_amount) }}" placeholder="{{ __('candidates.onboarding.offer.salary_amount') }}" class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs">
                                                        <input type="text" name="currency" value="{{ old('currency', $offer?->currency ?? 'USD') }}" placeholder="{{ __('candidates.onboarding.offer.currency') }}" class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs" required>
                                                        <input type="date" name="start_date" value="{{ old('start_date', $offer?->start_date?->toDateString()) }}" class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs">
                                                        <button type="submit" class="md:col-span-2 rounded-lg border border-success-300/60 bg-success-50 px-3 py-1.5 text-xs font-semibold text-success-800 transition-weightless hover:bg-success-100/80">
                                                            {{ __('candidates.onboarding.offer.save') }}
                                                        </button>
                                                    </form>
                                                </div>

                                                <div class="rounded-2xl border border-slate-200 bg-white/75 p-4">
                                                    <p class="text-xs uppercase tracking-wide text-slate-600">{{ __('candidates.onboarding.contract.title') }}</p>
                                                    @if($contract)
                                                        <div class="mt-2 flex flex-wrap items-center gap-2 text-xs text-slate-700">
                                                            <x-badge>{{ __('candidates.onboarding.contract.statuses.'.$contract->contract_status) }}</x-badge>
                                                            @if($contract->signed_at)
                                                                <span>{{ __('candidates.onboarding.contract.signed_at') }}: {{ $contract->signed_at->diffForHumans() }}</span>
                                                            @endif
                                                            <a href="{{ \App\Http\Controllers\CandidateWorkspaceController::signedContractUrl($contract) }}" class="rounded-md border border-aura-200 px-2 py-1 text-aura-700">
                                                                {{ __('candidates.onboarding.contract.download') }}
                                                            </a>
                                                        </div>
                                                    @endif

                                                    <form method="POST" action="{{ route('candidates.onboarding.contract.save', ['application' => $selectedApplication->id]) }}" enctype="multipart/form-data" class="mt-3 grid gap-2 md:grid-cols-2">
                                                        @csrf
                                                        @foreach($query as $key => $value)
                                                            <input type="hidden" name="{{ $key }}" value="{{ is_scalar($value) ? (string) $value : '' }}">
                                                        @endforeach
                                                        <select name="contract_status" data-placeholder="{{ __('candidates.onboarding.contract.status') }}" class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs" required>
                                                            <option value="{{ \App\Models\Contract::STATUS_DRAFT }}" @selected((string) old('contract_status', $contract?->contract_status ?? \App\Models\Contract::STATUS_DRAFT) === \App\Models\Contract::STATUS_DRAFT)>
                                                                {{ __('candidates.onboarding.contract.statuses.draft') }}
                                                            </option>
                                                            <option value="{{ \App\Models\Contract::STATUS_SENT }}" @selected((string) old('contract_status', $contract?->contract_status ?? \App\Models\Contract::STATUS_DRAFT) === \App\Models\Contract::STATUS_SENT)>
                                                                {{ __('candidates.onboarding.contract.statuses.sent') }}
                                                            </option>
                                                        </select>
                                                        <select name="signature_method" data-placeholder="{{ __('candidates.onboarding.contract.signature_method') }}" class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs" required>
                                                            <option value="{{ \App\Models\Contract::SIGNATURE_METHOD_TYPED }}" @selected((string) old('signature_method', $contract?->signature_method ?? \App\Models\Contract::SIGNATURE_METHOD_TYPED) === \App\Models\Contract::SIGNATURE_METHOD_TYPED)>
                                                                {{ __('candidates.onboarding.contract.signature_typed') }}
                                                            </option>
                                                        </select>
                                                        <input type="file" name="contract_file" accept=".pdf,.doc,.docx" class="md:col-span-2 w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs">
                                                        <button type="submit" class="md:col-span-2 rounded-lg border border-success-300/60 bg-success-50 px-3 py-1.5 text-xs font-semibold text-success-800 transition-weightless hover:bg-success-100/80">
                                                            {{ __('candidates.onboarding.contract.save') }}
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>

                                            <div class="grid gap-3 xl:grid-cols-2">
                                                <div class="rounded-2xl border border-slate-200 bg-white/75 p-4">
                                                    <p class="text-xs uppercase tracking-wide text-slate-600">{{ __('candidates.onboarding.documents.title') }}</p>
                                                    <form method="POST" action="{{ route('candidates.onboarding.documents.store', ['application' => $selectedApplication->id]) }}" enctype="multipart/form-data" class="mt-3 space-y-2">
                                                        @csrf
                                                        @foreach($query as $key => $value)
                                                            <input type="hidden" name="{{ $key }}" value="{{ is_scalar($value) ? (string) $value : '' }}">
                                                        @endforeach
                                                        <select name="doc_type" data-placeholder="{{ __('candidates.onboarding.documents.doc_type') }}" class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs" required>
                                                            @foreach(\App\Models\OnboardingDocument::types() as $docTypeOption)
                                                                <option value="{{ $docTypeOption }}" @selected((string) old('doc_type') === $docTypeOption)>
                                                                    {{ __('candidates.onboarding.documents.types.'.$docTypeOption) }}
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                        <input type="file" name="file" accept=".pdf,.doc,.docx,.png,.jpg,.jpeg" class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs" required>
                                                        <button type="submit" class="rounded-lg border border-success-300/60 bg-success-50 px-3 py-1.5 text-xs font-semibold text-success-800 transition-weightless hover:bg-success-100/80">
                                                            {{ __('candidates.onboarding.documents.upload') }}
                                                        </button>
                                                    </form>

                                                    <div class="mt-3 space-y-2">
                                                        @forelse($onboardingDocuments as $onboardingDocument)
                                                            <div class="flex items-center justify-between rounded-lg border border-slate-200 bg-white p-2">
                                                                <p class="truncate text-xs text-slate-700">{{ __('candidates.onboarding.documents.types.'.$onboardingDocument->doc_type) }}</p>
                                                                <a href="{{ \App\Http\Controllers\CandidateWorkspaceController::signedOnboardingDocumentUrl($onboardingDocument) }}" class="rounded-md border border-aura-200 px-2 py-1 text-xs text-aura-700">
                                                                    {{ __('candidates.detail.download') }}
                                                                </a>
                                                            </div>
                                                        @empty
                                                            <p class="text-xs text-slate-600">{{ __('candidates.detail.not_available') }}</p>
                                                        @endforelse
                                                    </div>
                                                </div>

                                                <div class="rounded-2xl border border-slate-200 bg-white/75 p-4">
                                                    <p class="text-xs uppercase tracking-wide text-slate-600">{{ __('candidates.onboarding.calendar.title') }}</p>
                                                    <form method="POST" action="{{ route('candidates.onboarding.schedule.store', ['application' => $selectedApplication->id]) }}" class="mt-3 grid gap-2">
                                                        @csrf
                                                        @foreach($query as $key => $value)
                                                            <input type="hidden" name="{{ $key }}" value="{{ is_scalar($value) ? (string) $value : '' }}">
                                                        @endforeach
                                                        <input type="text" name="title" placeholder="{{ __('candidates.onboarding.calendar.fields.title') }}" class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs" required>
                                                        <input type="datetime-local" name="start_at" class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs" required>
                                                        <input type="datetime-local" name="end_at" class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs" required>
                                                        <input type="text" name="location" placeholder="{{ __('candidates.onboarding.calendar.fields.location') }}" class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs">
                                                        <button type="submit" class="rounded-lg border border-success-300/60 bg-success-50 px-3 py-1.5 text-xs font-semibold text-success-800 transition-weightless hover:bg-success-100/80">
                                                            {{ __('candidates.onboarding.calendar.save') }}
                                                        </button>
                                                    </form>

                                                    <div class="mt-3 space-y-2">
                                                        @forelse($onboardingScheduleItems as $scheduleItem)
                                                            <div class="rounded-lg border border-slate-200 bg-white p-2 text-xs text-slate-700">
                                                                <p class="font-semibold text-slate-800">{{ $scheduleItem->title }}</p>
                                                                <p>{{ optional($scheduleItem->start_at)->format('Y-m-d H:i') }} - {{ optional($scheduleItem->end_at)->format('Y-m-d H:i') }} UTC</p>
                                                                @if($scheduleItem->location)
                                                                    <p>{{ $scheduleItem->location }}</p>
                                                                @endif
                                                            </div>
                                                        @empty
                                                            <p class="text-xs text-slate-600">{{ __('candidates.detail.not_available') }}</p>
                                                        @endforelse
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="rounded-2xl border border-slate-200 bg-white/75 p-4">
                                                <p class="text-xs uppercase tracking-wide text-slate-600">{{ __('candidates.onboarding.tasks.title') }}</p>
                                                <form method="POST" action="{{ route('candidates.onboarding.tasks.store', ['application' => $selectedApplication->id]) }}" class="mt-3 grid gap-2 md:grid-cols-3">
                                                    @csrf
                                                    @foreach($query as $key => $value)
                                                        <input type="hidden" name="{{ $key }}" value="{{ is_scalar($value) ? (string) $value : '' }}">
                                                    @endforeach
                                                    <input type="text" name="task_name" placeholder="{{ __('candidates.onboarding.tasks.fields.task_name') }}" class="rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs md:col-span-2" required>
                                                    <input type="datetime-local" name="due_at" class="rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs">
                                                    <button type="submit" class="md:col-span-3 rounded-lg border border-success-300/60 bg-success-50 px-3 py-1.5 text-xs font-semibold text-success-800 transition-weightless hover:bg-success-100/80">
                                                        {{ __('candidates.onboarding.tasks.add') }}
                                                    </button>
                                                </form>

                                                <div class="mt-3 space-y-2">
                                                    @forelse($onboardingTasks as $onboardingTask)
                                                        <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-slate-200 bg-white p-2">
                                                            <div>
                                                                <p class="text-xs font-semibold text-slate-800">{{ $onboardingTask->task_name }}</p>
                                                                <p class="text-[11px] text-slate-600">
                                                                    {{ __('candidates.onboarding.tasks.due_at') }}:
                                                                    {{ $onboardingTask->due_at ? $onboardingTask->due_at->format('Y-m-d H:i').' UTC' : __('candidates.detail.not_available') }}
                                                                </p>
                                                            </div>
                                                            <form method="POST" action="{{ route('candidates.onboarding.tasks.toggle', ['application' => $selectedApplication->id, 'onboardingTask' => $onboardingTask->id]) }}">
                                                                @csrf
                                                                @foreach($query as $key => $value)
                                                                    <input type="hidden" name="{{ $key }}" value="{{ is_scalar($value) ? (string) $value : '' }}">
                                                                @endforeach
                                                                <button type="submit" class="rounded-md border px-2 py-1 text-xs {{ $onboardingTask->is_completed ? 'border-success-200 bg-success-50 text-success-800' : 'border-slate-200 bg-white text-slate-700' }}">
                                                                    {{ $onboardingTask->is_completed ? __('candidates.onboarding.tasks.mark_open') : __('candidates.onboarding.tasks.mark_done') }}
                                                                </button>
                                                            </form>
                                                        </div>
                                                    @empty
                                                        <p class="text-xs text-slate-600">{{ __('candidates.detail.not_available') }}</p>
                                                    @endforelse
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </div>
                </x-glass-card>
            </div>
﻿        @else
            <!-- NEW LAYOUT: JOB CARDS & RANKED LIST -->
            <x-glass-card class="mb-4 p-0 overflow-hidden">
                <div class="p-4 border-b border-white/60 bg-white/55">
                    <form method="GET" action="{{ route('candidates.index') }}" class="flex flex-wrap items-end gap-4">
                        @if(auth()->user()->isSuperadmin())
                            <x-form-field :label="__('jobs.company')" name="company_id" class="w-48">
                                <select name="company_id" data-placeholder="{{ __('jobs.company_placeholder') }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2 text-sm">
                                    <option value="">{{ __('jobs.company_placeholder') }}</option>
                                    @foreach($companies as $company)
                                        <option value="{{ $company->id }}" @selected((string) $selectedCompanyId === (string) $company->id)>{{ $company->name }}</option>
                                    @endforeach
                                </select>
                            </x-form-field>
                        @endif
                        <x-form-field :label="__('candidates.filters.search')" name="q" class="w-48">
                            <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2 text-sm" autocomplete="off">
                        </x-form-field>
                        <x-form-field :label="__('candidates.filters.job')" name="job_id" class="w-48">
                            <select name="job_id" data-placeholder="{{ __('candidates.filters.job_placeholder') }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2 text-sm">
                                <option value="">{{ __('candidates.filters.job_placeholder') }}</option>
                                @foreach($jobs as $job)
                                    <option value="{{ $job->id }}" @selected(($filters['job_id'] ?? null) === $job->id)>{{ $job->title }}</option>
                                @endforeach
                            </select>
                        </x-form-field>
                        <x-form-field :label="__('candidates.filters.status')" name="status" class="w-48">
                            <select name="status" data-placeholder="{{ __('candidates.filters.status_placeholder') }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2 text-sm">
                                <option value="">{{ __('candidates.filters.status_placeholder') }}</option>
                                @foreach($statuses as $status)
                                    <option value="{{ $status }}" @selected(($filters['status'] ?? null) === $status)>{{ __('candidates.list.status.'.$status) }}</option>
                                @endforeach
                            </select>
                        </x-form-field>
                        <div class="flex items-center gap-2 pb-1">
                            <button type="submit" class="rounded-xl bg-success-600 px-4 py-2 text-sm font-semibold text-white transition-weightless hover:bg-success-700">
                                {{ __('candidates.filters.apply') }}
                            </button>
                            <a href="{{ route('candidates.index', array_filter(['company_id' => $selectedCompanyId])) }}" class="rounded-xl border border-aura-300/40 bg-white/80 px-4 py-2 text-center text-sm font-semibold text-slate-700 transition-weightless hover:bg-white">
                                {{ __('candidates.filters.reset') }}
                            </a>
                        </div>
                    </form>
                </div>
            </x-glass-card>

            @if(!request()->filled('job_id') && !request()->filled('q'))
                <!-- JOB CARDS GRID -->
                <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    @forelse($jobs as $job)
                        <a href="{{ route('candidates.index', array_merge(request()->query(), ['job_id' => $job->id])) }}" class="group block rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition hover:border-aura-400 hover:shadow-md">
                            <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-aura-50 text-aura-600 group-hover:bg-aura-100">
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 0 0 .75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 0 0-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0 1 12 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 0 1-.673-.38m0 0A2.18 2.18 0 0 1 3 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 0 1 3.413-.387m7.5 0V5.25A2.25 2.25 0 0 0 13.5 3h-3a2.25 2.25 0 0 0-2.25 2.25v.894m7.5 0a48.667 48.667 0 0 0-7.5 0M12 12.75h.008v.008H12v-.008Z" />
                                </svg>
                            </div>
                            <h3 class="truncate text-lg font-bold text-slate-800">{{ $job->title }}</h3>
                            <p class="mt-1 text-sm font-medium text-slate-500">
                                {{ $job->applications_count }} candidat{{ $job->applications_count !== 1 ? 's' : '' }}
                            </p>
                        </a>
                    @empty
                        <div class="col-span-full">
                            <x-empty-state :title="__('candidates.list.empty_title')" :message="__('candidates.list.empty_message')" />
                        </div>
                    @endforelse
                </div>
            @else
                <!-- RANKED CANDIDATES LIST -->
                <x-glass-card class="p-0 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm text-slate-600">
                            <thead class="border-b border-slate-200 bg-slate-50/50 text-xs uppercase text-slate-500">
                                <tr>
                                    <th scope="col" class="px-6 py-4 font-semibold">Nom complet</th>
                                    <th scope="col" class="px-6 py-4 font-semibold">Score</th>
                                    <th scope="col" class="px-6 py-4 font-semibold">Formation / École</th>
                                    <th scope="col" class="px-6 py-4 font-semibold">Expérience</th>
                                    <th scope="col" class="px-6 py-4 font-semibold">Dernière entreprise</th>
                                    <th scope="col" class="px-6 py-4 font-semibold text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 bg-white">
                                @forelse($applications as $app)
                                    @php
                                        $cv = $app->cvParsingResults->first();
                                        $school = $cv && !empty($cv->education_entries_json) ? ($cv->education_entries_json[0]['institution_name'] ?? $cv->education_entries_json[0]['degree_name'] ?? '-') : '-';
                                        $experience = $cv && $cv->total_years_experience !== null ? number_format((float) $cv->total_years_experience, 1) . ' ans' : '-';
                                        $lastCompany = $cv && !empty($cv->experience_entries_json) ? ($cv->experience_entries_json[0]['company_name'] ?? $cv->experience_entries_json[0]['job_title'] ?? '-') : '-';
                                        $score = $app->global_match_score !== null ? number_format((float) $app->global_match_score, 1) : '-';
                                    @endphp
                                    <tr class="transition hover:bg-slate-50">
                                        <td class="whitespace-nowrap px-6 py-4 font-medium text-slate-900">
                                            {{ $app->candidate?->full_name ?? 'Inconnu' }}
                                        </td>
                                        <td class="whitespace-nowrap px-6 py-4">
                                            @if($score !== '-')
                                                <span class="inline-flex items-center rounded-full bg-primary-50 px-2.5 py-1 text-xs font-bold text-primary-700 border border-primary-100">
                                                    {{ $score }}%
                                                </span>
                                            @else
                                                <span class="text-slate-400">-</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 truncate max-w-[200px]" title="{{ $school }}">
                                            {{ $school }}
                                        </td>
                                        <td class="whitespace-nowrap px-6 py-4">
                                            {{ $experience }}
                                        </td>
                                        <td class="px-6 py-4 truncate max-w-[200px]" title="{{ $lastCompany }}">
                                            {{ $lastCompany }}
                                        </td>
                                        <td class="whitespace-nowrap px-6 py-4 text-right">
                                            <a href="{{ route('candidates.index', array_merge(request()->query(), ['application_id' => $app->id])) }}" class="inline-flex items-center rounded-lg bg-white px-3 py-1.5 text-xs font-semibold text-aura-700 border border-aura-200 shadow-sm transition hover:bg-aura-50">
                                                Voir le profil
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-6 py-8 text-center text-slate-500">
                                            Aucun candidat trouvÃ© pour ce filtre.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if($applications instanceof \Illuminate\Pagination\LengthAwarePaginator && $applications->hasPages())
                        <div class="border-t border-slate-200 bg-white px-6 py-4">
                            {{ $applications->links() }}
                        </div>
                    @endif
                </x-glass-card>
            @endif

        @endif
    </div>

    @if(! $requiresCompanySelection)
        @include('candidates.partials.recruiter-assistant', [
            'selectedCompanyId' => $selectedCompanyId,
            'selectedApplication' => $selectedApplication,
            'applications' => $applications,
        ])
    @endif

    @if(! $requiresCompanySelection)
        <script>
            (() => {
                const tabRoots = document.querySelectorAll('[data-candidate-tabs-root]');
                const tabActiveClasses = ['border-aura-300', 'bg-aura-100/70', 'text-aura-900'];
                const tabInactiveClasses = ['border-slate-200', 'bg-white', 'text-slate-700'];

                tabRoots.forEach((tabRoot) => {
                    const tabButtons = Array.from(tabRoot.querySelectorAll('[data-candidate-tab-button]'));
                    const tabPanels = Array.from(tabRoot.querySelectorAll('[data-candidate-tab-panel]'));
                    if (tabButtons.length === 0 || tabPanels.length === 0) {
                        return;
                    }

                    const defaultTab = tabRoot.dataset.defaultTab || tabButtons[0].dataset.candidateTabButton || 'candidate';
                    const knownTabs = new Set(tabButtons.map((tabButton) => tabButton.dataset.candidateTabButton));

                    const setActiveTab = (nextTab) => {
                        const activeTab = knownTabs.has(nextTab) ? nextTab : (tabButtons[0].dataset.candidateTabButton || 'candidate');

                        tabButtons.forEach((tabButton) => {
                            const isActive = tabButton.dataset.candidateTabButton === activeTab;
                            tabButton.classList.toggle('hidden', false);
                            tabButton.classList.toggle('pointer-events-none', isActive);
                            tabButton.classList.toggle('opacity-100', isActive);
                            tabButton.classList.toggle('opacity-90', !isActive);
                            tabActiveClasses.forEach((className) => tabButton.classList.toggle(className, isActive));
                            tabInactiveClasses.forEach((className) => tabButton.classList.toggle(className, !isActive));
                        });

                        tabPanels.forEach((tabPanel) => {
                            const isActive = tabPanel.dataset.candidateTabPanel === activeTab;
                            tabPanel.classList.toggle('hidden', !isActive);
                        });
                    };

                    tabButtons.forEach((tabButton) => {
                        tabButton.addEventListener('click', () => {
                            setActiveTab(tabButton.dataset.candidateTabButton || '');
                        });
                    });

                    setActiveTab(defaultTab);
                });

                const leftPanel = document.getElementById('candidate-list-scroll');
                const rightPanel = document.getElementById('candidate-detail-scroll');
                if (!leftPanel || !rightPanel) {
                    return;
                }

                const params = new URLSearchParams(window.location.search);
                params.delete('application_id');
                const keyBase = `candidates.workspace.scroll:${params.toString()}`;
                const leftKey = `${keyBase}:left`;
                const rightKey = `${keyBase}:right`;

                const restore = (element, key) => {
                    const raw = window.sessionStorage.getItem(key);
                    if (raw === null) {
                        return;
                    }

                    const value = Number(raw);
                    if (!Number.isNaN(value)) {
                        const maxScroll = Math.max(0, element.scrollHeight - element.clientHeight);
                        element.scrollTop = Math.min(Math.max(0, value), maxScroll);
                    }
                };

                const save = () => {
                    window.sessionStorage.setItem(leftKey, String(leftPanel.scrollTop));
                    window.sessionStorage.setItem(rightKey, String(rightPanel.scrollTop));
                };

                const restoreAll = () => {
                    restore(leftPanel, leftKey);
                    restore(rightPanel, rightKey);
                };

                restoreAll();
                requestAnimationFrame(restoreAll);
                window.setTimeout(restoreAll, 120);

                leftPanel.addEventListener('scroll', save, { passive: true });
                rightPanel.addEventListener('scroll', save, { passive: true });
                window.addEventListener('pageshow', restoreAll);
                window.addEventListener('beforeunload', save);
                document.querySelectorAll('.js-candidate-link').forEach((link) => {
                    link.addEventListener('click', save);
                });

                document.querySelectorAll('[data-interview-schedule-form]').forEach((form) => {
                    const locationSelect = form.querySelector('[data-interview-location-select]');
                    const meetingLinkGroup = form.querySelector('[data-interview-meeting-link-group]');
                    const addressGroup = form.querySelector('[data-interview-location-address-group]');
                    const addressInput = addressGroup?.querySelector('input[name=\"location_address\"]');

                    if (!locationSelect) {
                        return;
                    }

                    const syncInterviewLocationFields = () => {
                        const isInPerson = locationSelect.value === @json(\App\Models\Interview::LOCATION_IN_PERSON);

                        if (meetingLinkGroup) {
                            meetingLinkGroup.classList.toggle('hidden', isInPerson);
                        }

                        if (addressGroup) {
                            addressGroup.classList.toggle('hidden', !isInPerson);
                        }

                        if (addressInput) {
                            addressInput.required = isInPerson;
                        }
                    };

                    locationSelect.addEventListener('change', syncInterviewLocationFields);
                    syncInterviewLocationFields();
                });
            })();
        </script>
    @endif
    
    @endif
</x-shell-layout>