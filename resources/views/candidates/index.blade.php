
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
                    Retour Ã  la liste
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
                                                        <p><span class="font-semibold text-slate-700">CompÃ©tences:</span> {{ $skills ?: '-' }}</p>
                                                        <p><span class="font-semibold text-slate-700">Formation:</span> {{ $formation }}</p>
                                                        <p><span class="font-semibold text-slate-700">ExpÃ©rience:</span> {{ $experience }}</p>
                                                    </div>
                                                    @php
                                                        $cvDoc = $app->candidate?->documents->first();
                                                        $cvUrlCard = $cvDoc ? \App\Http\Controllers\CandidateWorkspaceController::signedDocumentUrl($cvDoc) : null;
                                                    @endphp
                                                    <div class="mt-4 flex gap-2">
                                                        <a href="{{ route('candidates.index', ['application_id' => $app->id]) }}" class="block w-full rounded-lg bg-slate-50 py-1.5 text-center text-xs font-semibold text-slate-700 transition hover:bg-slate-100">
                                                            Voir le profil
                                                        </a>
                                                        @if($cvUrlCard)
                                                            <a href="{{ $cvUrlCard }}" target="_blank" class="flex-shrink-0 flex items-center justify-center rounded-lg bg-slate-50 px-2 py-1.5 text-slate-500 hover:text-aura-700 hover:bg-slate-100 transition" title="Voir CV">
                                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                                </svg>
                                                            </a>
                                                        @else
                                                            <button type="button" onclick="alert('Y a pas de CV')" class="flex-shrink-0 flex items-center justify-center rounded-lg bg-slate-50 px-2 py-1.5 text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition" title="Pas de CV">
                                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                                                                </svg>
                                                            </button>
                                                        @endif
                                                    </div>
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

                                    <div class="mb-4 grid gap-4 lg:grid-cols-4">
                                        <!-- Score Match -->
                                        <div class="rounded-xl border border-slate-200 bg-white p-4 lg:col-span-1 flex flex-col justify-center items-center">
                                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-600 mb-2">Score Match</p>
                                            <div class="grid size-16 place-items-center rounded-full text-lg font-semibold text-slate-900" style="background: conic-gradient(#6467f2 {{ $matchPct ?? 0 }}%, #e2e8f0 {{ $matchPct ?? 0 }}%);">
                                                <div class="grid size-12 place-items-center rounded-full bg-white">{{ number_format($matchPct ?? 0, 0) }}%</div>
                                            </div>
                                        </div>
                                        
                                        <!-- Profil Détaillé -->
                                        <div class="rounded-xl border border-slate-200 bg-white p-4 lg:col-span-3">
                                            <div class="flex justify-between items-center mb-4">
                                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-600">Profil Simplifié</p>
                                                @if($cvUrl)
                                                <div class="flex gap-2">
                                                    <a href="{{ $cvUrl }}" target="_blank" class="inline-flex items-center rounded-lg bg-white px-3 py-1.5 text-xs font-semibold text-aura-700 border border-aura-200 shadow-sm transition hover:bg-aura-50">
                                                        <svg class="mr-1.5 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                        </svg>
                                                        Aperçu CV
                                                    </a>
                                                    <a href="{{ $cvUrl }}" download class="inline-flex items-center rounded-lg bg-white px-3 py-1.5 text-xs font-semibold text-aura-700 border border-aura-200 shadow-sm transition hover:bg-aura-50">
                                                        <svg class="mr-1.5 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                                        </svg>
                                                        Télécharger CV
                                                    </a>
                                                </div>
                                                @endif
                                            </div>
                                            
                                            @php
                                                $cv = $latestCvParsingResult;
                                                $personalInfo = $cv ? (array) $cv->personal_info_json : [];
                                                $exp1 = $cv && !empty($cv->experience_entries_json) ? $cv->experience_entries_json[0] : [];
                                                $edu1 = $cv && !empty($cv->education_entries_json) ? $cv->education_entries_json[0] : [];
                                                
                                                $currentJob = $exp1['job_title'] ?? '-';
                                                $currentCompany = $exp1['company_name'] ?? '-';
                                                $startDate = $exp1['start_date'] ?? '-';
                                                
                                                $school = $edu1['institution_name'] ?? '-';
                                                $degree = $edu1['degree_name'] ?? '-';
                                                
                                                $experience = $cv && $cv->total_years_experience !== null ? number_format((float) $cv->total_years_experience, 1) . ' ans' : '-';
                                                $email = $selectedApplication->candidate?->email ?? $personalInfo['email'] ?? '-';
                                                $phone = $selectedApplication->candidate?->phone ?? $personalInfo['phone'] ?? '-';
                                                $skills = $cv && !empty($cv->extracted_skills) ? collect($cv->extracted_skills)->take(10) : collect();
                                            @endphp

                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm text-slate-700 mb-4">
                                                <div class="flex items-center gap-2">
                                                    <div class="flex-shrink-0 w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center">
                                                        <svg class="w-4 h-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                                        </svg>
                                                    </div>
                                                    <div>
                                                        <p class="text-xs text-slate-500 uppercase tracking-wider">Poste Actuel</p>
                                                        <p class="font-medium text-slate-900 truncate max-w-[200px]" title="{{ $currentJob }}">{{ $currentJob }}</p>
                                                    </div>
                                                </div>
                                                
                                                <div class="flex items-center gap-2">
                                                    <div class="flex-shrink-0 w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center">
                                                        <svg class="w-4 h-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                                        </svg>
                                                    </div>
                                                    <div>
                                                        <p class="text-xs text-slate-500 uppercase tracking-wider">Entreprise Actuelle</p>
                                                        <p class="font-medium text-slate-900 truncate max-w-[200px]" title="{{ $currentCompany }}">{{ $currentCompany }}</p>
                                                    </div>
                                                </div>

                                                <div class="flex items-center gap-2">
                                                    <div class="flex-shrink-0 w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center">
                                                        <svg class="w-4 h-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                                        </svg>
                                                    </div>
                                                    <div>
                                                        <p class="text-xs text-slate-500 uppercase tracking-wider">Début Poste Actuel</p>
                                                        <p class="font-medium text-slate-900">{{ $startDate }}</p>
                                                    </div>
                                                </div>

                                                <div class="flex items-center gap-2">
                                                    <div class="flex-shrink-0 w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center">
                                                        <svg class="w-4 h-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                        </svg>
                                                    </div>
                                                    <div>
                                                        <p class="text-xs text-slate-500 uppercase tracking-wider">Années d'expérience</p>
                                                        <p class="font-medium text-slate-900">{{ $experience }}</p>
                                                    </div>
                                                </div>
                                                
                                                <div class="flex items-center gap-2">
                                                    <div class="flex-shrink-0 w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center">
                                                        <svg class="w-4 h-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14zm-4 6v-7.5l4-2.222"/>
                                                        </svg>
                                                    </div>
                                                    <div>
                                                        <p class="text-xs text-slate-500 uppercase tracking-wider">Établissement Formation</p>
                                                        <p class="font-medium text-slate-900 truncate max-w-[200px]" title="{{ $school }}">{{ $school }}</p>
                                                    </div>
                                                </div>

                                                <div class="flex items-center gap-2">
                                                    <div class="flex-shrink-0 w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center">
                                                        <svg class="w-4 h-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                                        </svg>
                                                    </div>
                                                    <div>
                                                        <p class="text-xs text-slate-500 uppercase tracking-wider">Formation</p>
                                                        <p class="font-medium text-slate-900 truncate max-w-[200px]" title="{{ $degree }}">{{ $degree }}</p>
                                                    </div>
                                                </div>

                                                <div class="flex items-center gap-2">
                                                    <div class="flex-shrink-0 w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center">
                                                        <svg class="w-4 h-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                                        </svg>
                                                    </div>
                                                    <div>
                                                        <p class="text-xs text-slate-500 uppercase tracking-wider">Adresse E-mail</p>
                                                        <p class="font-medium text-slate-900 truncate max-w-[200px]" title="{{ $email }}">{{ $email }}</p>
                                                    </div>
                                                </div>

                                                <div class="flex items-center gap-2">
                                                    <div class="flex-shrink-0 w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center">
                                                        <svg class="w-4 h-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                                                        </svg>
                                                    </div>
                                                    <div>
                                                        <p class="text-xs text-slate-500 uppercase tracking-wider">Téléphone</p>
                                                        <p class="font-medium text-slate-900 truncate max-w-[200px]" title="{{ $phone }}">{{ $phone }}</p>
                                                    </div>
                                                </div>
                                            </div>

                                            @if($skills->isNotEmpty())
                                                <div class="mb-4">
                                                    <p class="text-xs text-slate-500 uppercase tracking-wider mb-2">Compétences clés</p>
                                                    <div class="flex flex-wrap gap-2">
                                                        @foreach($skills as $skill)
                                                            <span class="inline-flex items-center rounded-full bg-slate-100 border border-slate-200 px-2.5 py-0.5 text-xs font-medium text-slate-700">
                                                                {{ $skill }}
                                                            </span>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @endif

                                            @if($parsedCvSummary)
                                                <div class="mt-3 pt-3 border-t border-slate-100">
                                                    <div class="flex gap-2 text-slate-500">
                                                        <svg class="w-4 h-4 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                                        </svg>
                                                        <p class="text-xs leading-relaxed italic">{{ $parsedCvSummary }}</p>
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                            </div>
                        @endif
                    </div>
                </x-glass-card>
            </div>
        @else
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
                                    <th scope="col" class="px-6 py-4 font-semibold">État</th>
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
                                        $cvDoc = $app->candidate?->documents->first();
                                        $cvUrlTable = $cvDoc ? \App\Http\Controllers\CandidateWorkspaceController::signedDocumentUrl($cvDoc) : null;
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
                                        <td class="whitespace-nowrap px-6 py-4">
                                            <form action="{{ route('candidates.move-stage', ['application' => $app->id]) }}" method="POST" class="inline">
                                                @csrf
                                                <select name="stage_id" onchange="this.form.submit()" class="text-sm rounded-lg border-slate-200 py-1 pl-2 pr-6">
                                                    @if($app->job && $app->job->pipelineStages)
                                                        @foreach($app->job->pipelineStages as $stage)
                                                            <option value="{{ $stage->id }}" {{ $app->current_stage_id === $stage->id ? 'selected' : '' }}>
                                                                {{ $stage->stage_label }}
                                                            </option>
                                                        @endforeach
                                                    @else
                                                        <option value="">-</option>
                                                    @endif
                                                </select>
                                            </form>
                                        </td>
                                        <td class="whitespace-nowrap px-6 py-4 text-right">
                                            <div class="flex items-center justify-end gap-2">
                                                @if($cvUrlTable)
                                                    <a href="{{ $cvUrlTable }}" target="_blank" class="inline-flex items-center justify-center rounded-lg bg-white p-1 text-slate-500 hover:text-aura-700 hover:bg-aura-50 border border-transparent hover:border-aura-200 transition" title="Voir CV">
                                                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                        </svg>
                                                    </a>
                                                @else
                                                    <button type="button" onclick="alert('Y a pas de CV')" class="inline-flex items-center justify-center rounded-lg bg-white p-1 text-slate-400 hover:text-slate-600 hover:bg-slate-50 border border-transparent transition" title="Pas de CV">
                                                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                                                        </svg>
                                                    </button>
                                                @endif
                                                <a href="{{ route('candidates.index', array_merge(request()->query(), ['application_id' => $app->id])) }}" class="inline-flex items-center rounded-lg bg-white px-3 py-1.5 text-xs font-semibold text-aura-700 border border-aura-200 shadow-sm transition hover:bg-aura-50">
                                                    Voir le profil
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="px-6 py-8 text-center text-slate-500">
                                            Aucun candidat trouvé pour ce filtre.
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
