
<?php if (isset($component)) { $__componentOriginal4169ced201253cf4850c555ce041228e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal4169ced201253cf4850c555ce041228e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.shell-layout','data' => ['title' => __('candidates.title').' | '.config('app.name')]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('shell-layout'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('candidates.title').' | '.config('app.name'))]); ?>
    <div class="space-y-4 pb-28">
        <?php if(session('status')): ?>
            <?php if (isset($component)) { $__componentOriginal20219e5d4a8f384b085d3f31e43f063a = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal20219e5d4a8f384b085d3f31e43f063a = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.toast-alert','data' => ['type' => 'success']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('toast-alert'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['type' => 'success']); ?><?php echo e(session('status')); ?> <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal20219e5d4a8f384b085d3f31e43f063a)): ?>
<?php $attributes = $__attributesOriginal20219e5d4a8f384b085d3f31e43f063a; ?>
<?php unset($__attributesOriginal20219e5d4a8f384b085d3f31e43f063a); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal20219e5d4a8f384b085d3f31e43f063a)): ?>
<?php $component = $__componentOriginal20219e5d4a8f384b085d3f31e43f063a; ?>
<?php unset($__componentOriginal20219e5d4a8f384b085d3f31e43f063a); ?>
<?php endif; ?>
        <?php endif; ?>
        <?php if(session('error')): ?>
            <?php if (isset($component)) { $__componentOriginal20219e5d4a8f384b085d3f31e43f063a = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal20219e5d4a8f384b085d3f31e43f063a = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.toast-alert','data' => ['type' => 'warning']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('toast-alert'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['type' => 'warning']); ?><?php echo e(session('error')); ?> <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal20219e5d4a8f384b085d3f31e43f063a)): ?>
<?php $attributes = $__attributesOriginal20219e5d4a8f384b085d3f31e43f063a; ?>
<?php unset($__attributesOriginal20219e5d4a8f384b085d3f31e43f063a); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal20219e5d4a8f384b085d3f31e43f063a)): ?>
<?php $component = $__componentOriginal20219e5d4a8f384b085d3f31e43f063a; ?>
<?php unset($__componentOriginal20219e5d4a8f384b085d3f31e43f063a); ?>
<?php endif; ?>
        <?php endif; ?>
        <?php if($errors->any()): ?>
            <div class="rounded-xl border border-danger-300/50 bg-danger-50/70 px-3 py-2 text-xs text-danger-800">
                <?php echo e($errors->first()); ?>

            </div>
        <?php endif; ?>

        <?php if($requiresCompanySelection): ?>
            <?php if (isset($component)) { $__componentOriginalf225215015f2c1140bc03ba841300625 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalf225215015f2c1140bc03ba841300625 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.glass-card','data' => ['title' => __('candidates.title'),'subtitle' => __('candidates.subtitle')]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('glass-card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('candidates.title')),'subtitle' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('candidates.subtitle'))]); ?>
                <?php if (isset($component)) { $__componentOriginal074a021b9d42f490272b5eefda63257c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal074a021b9d42f490272b5eefda63257c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.empty-state','data' => ['title' => __('candidates.select_company_title'),'message' => __('candidates.select_company_message')]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('empty-state'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('candidates.select_company_title')),'message' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('candidates.select_company_message'))]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal074a021b9d42f490272b5eefda63257c)): ?>
<?php $attributes = $__attributesOriginal074a021b9d42f490272b5eefda63257c; ?>
<?php unset($__attributesOriginal074a021b9d42f490272b5eefda63257c); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal074a021b9d42f490272b5eefda63257c)): ?>
<?php $component = $__componentOriginal074a021b9d42f490272b5eefda63257c; ?>
<?php unset($__componentOriginal074a021b9d42f490272b5eefda63257c); ?>
<?php endif; ?>
             <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalf225215015f2c1140bc03ba841300625)): ?>
<?php $attributes = $__attributesOriginalf225215015f2c1140bc03ba841300625; ?>
<?php unset($__attributesOriginalf225215015f2c1140bc03ba841300625); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalf225215015f2c1140bc03ba841300625)): ?>
<?php $component = $__componentOriginalf225215015f2c1140bc03ba841300625; ?>
<?php unset($__componentOriginalf225215015f2c1140bc03ba841300625); ?>
<?php endif; ?>
        <?php else: ?>
        <?php if(request()->filled('application_id')): ?>

            <div class="mb-4">
                <a href="<?php echo e(route('candidates.index', array_filter(['job_id' => $filters['job_id'] ?? null]))); ?>" class="inline-flex items-center gap-2 text-sm font-semibold text-slate-600 transition hover:text-aura-600">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                    </svg>
                    Retour à la liste
                </a>
            </div>
            <div class="grid gap-4">

                <?php if (isset($component)) { $__componentOriginalf225215015f2c1140bc03ba841300625 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalf225215015f2c1140bc03ba841300625 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.glass-card','data' => ['bodyClass' => 'flex min-h-0 flex-1 flex-col','class' => 'min-w-0 flex flex-col p-0 2xl:h-[calc(100vh-11rem)] 2xl:overflow-hidden']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('glass-card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['body-class' => 'flex min-h-0 flex-1 flex-col','class' => 'min-w-0 flex flex-col p-0 2xl:h-[calc(100vh-11rem)] 2xl:overflow-hidden']); ?>
                    <div id="candidate-detail-scroll" class="min-h-0 flex-1 overflow-y-auto p-4">
                        <?php if(! $selectedApplication): ?>
                            <div class="space-y-8">
                                <?php $__empty_1 = true; $__currentLoopData = $topCandidatesByJob; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $group): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                    <div>
                                        <h3 class="mb-4 text-lg font-semibold text-slate-800"><?php echo e($group['job']->title); ?> <span class="text-sm font-normal text-slate-500">(<?php echo e(__('candidates.detail.top_three')); ?>)</span></h3>
                                        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                                            <?php $__currentLoopData = $group['applications']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $app): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                <?php
                                                    $cv = $app->cvParsingResults->first();
                                                    $skills = $cv ? collect($cv->parsed_skills ?? [])->take(4)->implode(', ') : __('candidates.detail.not_available');
                                                    $formation = $cv && !empty($cv->parsed_education) ? ($cv->parsed_education[0]['degree_name'] ?? __('candidates.detail.not_available')) : __('candidates.detail.not_available');
                                                    $experience = $cv && $cv->total_years_experience !== null ? number_format((float) $cv->total_years_experience, 1) . ' ans' : __('candidates.detail.not_available');
                                                    $score = $app->global_match_score !== null ? number_format((float) $app->global_match_score, 1) . '/100' : '-';
                                                ?>
                                                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm hover:border-aura-300">
                                                    <div class="mb-3 flex items-center justify-between">
                                                        <p class="font-semibold text-slate-900"><?php echo e($app->candidate?->full_name ?? 'Unknown'); ?></p>
                                                        <span class="rounded-full bg-primary-50 px-2 py-0.5 text-[11px] font-bold text-primary-700 border border-primary-100"><?php echo e($score); ?></span>
                                                    </div>
                                                    <div class="space-y-2 text-xs text-slate-600">
                                                        <p><span class="font-semibold text-slate-700">Compétences:</span> <?php echo e($skills ?: '-'); ?></p>
                                                        <p><span class="font-semibold text-slate-700">Formation:</span> <?php echo e($formation); ?></p>
                                                        <p><span class="font-semibold text-slate-700">Expérience:</span> <?php echo e($experience); ?></p>
                                                    </div>
                                                    <a href="<?php echo e(route('candidates.index', ['application_id' => $app->id])); ?>" class="mt-4 block w-full rounded-lg bg-slate-50 py-1.5 text-center text-xs font-semibold text-slate-700 transition hover:bg-slate-100">
                                                        Voir le profil
                                                    </a>
                                                </div>
                                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                        </div>
                                    </div>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                    <?php if (isset($component)) { $__componentOriginal074a021b9d42f490272b5eefda63257c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal074a021b9d42f490272b5eefda63257c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.empty-state','data' => ['title' => __('candidates.detail.empty_title'),'message' => __('candidates.detail.empty_message')]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('empty-state'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('candidates.detail.empty_title')),'message' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('candidates.detail.empty_message'))]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal074a021b9d42f490272b5eefda63257c)): ?>
<?php $attributes = $__attributesOriginal074a021b9d42f490272b5eefda63257c; ?>
<?php unset($__attributesOriginal074a021b9d42f490272b5eefda63257c); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal074a021b9d42f490272b5eefda63257c)): ?>
<?php $component = $__componentOriginal074a021b9d42f490272b5eefda63257c; ?>
<?php unset($__componentOriginal074a021b9d42f490272b5eefda63257c); ?>
<?php endif; ?>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <?php
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
                            ?>

                            <div class="space-y-4">
                                <div class="rounded-2xl border border-slate-200 bg-white/70 p-4">
                                    <div class="flex flex-wrap items-center justify-between gap-3">
                                        <div>
                                            <div class="flex items-center gap-2">
                                                <?php
                                                    $cvDocument = $selectedApplication->candidate?->documents->first();
                                                    $cvUrl = $cvDocument ? \App\Http\Controllers\CandidateWorkspaceController::signedDocumentUrl($cvDocument) : null;
                                                ?>
                                                <h3 class="text-xl font-semibold text-slate-900 flex items-center gap-2">
                                                    <?php echo e($displayName); ?>

                                                    <?php if($cvUrl): ?>
                                                        <a href="<?php echo e($cvUrl); ?>" target="_blank" class="text-slate-400 hover:text-aura-600 transition-colors" title="View CV">
                                                            <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                              <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                                              <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                            </svg>
                                                        </a>
                                                    <?php endif; ?>
                                                </h3>
                                                <?php if($blind): ?>
                                                    <?php if (isset($component)) { $__componentOriginal2ddbc40e602c342e508ac696e52f8719 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2ddbc40e602c342e508ac696e52f8719 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.badge','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?><?php echo e(__('candidates.detail.blind_mode')); ?> <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal2ddbc40e602c342e508ac696e52f8719)): ?>
<?php $attributes = $__attributesOriginal2ddbc40e602c342e508ac696e52f8719; ?>
<?php unset($__attributesOriginal2ddbc40e602c342e508ac696e52f8719); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal2ddbc40e602c342e508ac696e52f8719)): ?>
<?php $component = $__componentOriginal2ddbc40e602c342e508ac696e52f8719; ?>
<?php unset($__componentOriginal2ddbc40e602c342e508ac696e52f8719); ?>
<?php endif; ?>
                                                <?php endif; ?>
                                            </div>
                                            <p class="mt-1 text-sm text-slate-600"><?php echo e($job?->title); ?> - <?php echo e(optional($selectedApplication->currentStage)->stage_label); ?></p>
                                            <?php if($blind): ?>
                                                <p class="mt-1 text-xs text-slate-500"><?php echo e(__('candidates.detail.blind_mode_hint')); ?></p>
                                                <p class="mt-1 text-xs font-semibold text-slate-600"><?php echo e(__('candidates.detail.masked_identifier', ['identifier' => $maskedIdentifier])); ?></p>
                                            <?php endif; ?>
                                            <p class="mt-1 text-xs text-slate-500"><?php echo e(__('candidates.detail.missing_feedback')); ?>: <?php echo e($missingFeedbackCount); ?></p>
                                        </div>
                                        <div class="flex flex-wrap gap-2">
                                            <a href="<?php echo e(route('interviews.index', ['company_id' => $selectedCompanyId])); ?>" class="rounded-lg border border-aura-300/50 bg-white px-3 py-1.5 text-xs font-medium text-slate-800">
                                                <?php echo e(__('candidates.detail.view_interviews')); ?>

                                            </a>
                                            <?php if($videoReportFailed): ?>
                                                <form method="POST" action="<?php echo e(route('candidates.video-report.retry', ['application' => $selectedApplication->id])); ?>">
                                                    <?php echo csrf_field(); ?>
                                                    <?php $__currentLoopData = $query; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $value): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                        <input type="hidden" name="<?php echo e($key); ?>" value="<?php echo e(is_scalar($value) ? (string) $value : ''); ?>">
                                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                                    <button type="submit" class="rounded-lg border border-danger-300/60 bg-danger-50 px-3 py-1.5 text-xs font-medium text-danger-700">
                                                        <?php echo e(__('candidates.detail.retry_video_report')); ?>

                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div class="mb-4 grid gap-3 lg:grid-cols-4">
                                    <div class="rounded-xl border border-slate-200 bg-white p-4 lg:col-span-1 flex flex-col justify-center items-center">
                                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-600 mb-2">Score Match</p>
                                        <div class="grid size-16 place-items-center rounded-full text-lg font-semibold text-slate-900" style="background: conic-gradient(#6467f2 <?php echo e($matchPct ?? 0); ?>%, #e2e8f0 <?php echo e($matchPct ?? 0); ?>%);">
                                            <div class="grid size-12 place-items-center rounded-full bg-white"><?php echo e(number_format($matchPct ?? 0, 0)); ?>%</div>
                                        </div>
                                    </div>
                                    <div class="rounded-xl border border-slate-200 bg-white p-4 lg:col-span-3">
                                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-600">Profil Simplifié</p>
                                        <div class="mt-3 space-y-2 text-sm text-slate-700">
                                            <?php
                                                $cv = $latestCvParsingResult;
                                                $skills = $cv && !empty($cv->extracted_skills) ? collect($cv->extracted_skills)->take(8)->implode(', ') : __('candidates.detail.not_available');
                                                $formation = $cv && !empty($cv->education_entries_json) ? ($cv->education_entries_json[0]['degree_name'] ?? __('candidates.detail.not_available')) : __('candidates.detail.not_available');
                                                $experience = $cv && $cv->total_years_experience !== null ? number_format((float) $cv->total_years_experience, 1) . ' ans' : __('candidates.detail.not_available');
                                            ?>
                                            <p><span class="font-semibold text-slate-900">Compétences principales:</span> <?php echo e($skills ?: '-'); ?></p>
                                            <p><span class="font-semibold text-slate-900">Formation:</span> <?php echo e($formation); ?></p>
                                            <p><span class="font-semibold text-slate-900">Années d'expérience:</span> <?php echo e($experience); ?></p>
                                            <?php if($parsedCvSummary): ?>
                                                <div class="mt-3 pt-3 border-t border-slate-100">
                                                    <p class="text-xs text-slate-500 leading-relaxed"><?php echo e($parsedCvSummary); ?></p>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                    <div class="mt-3 grid gap-2 md:grid-cols-2 2xl:grid-cols-4">
                                        <form method="POST" action="<?php echo e(route('candidates.move-stage', ['application' => $selectedApplication->id])); ?>" class="space-y-2 rounded-xl border border-slate-200 bg-white/80 p-2">
                                            <?php echo csrf_field(); ?>
                                            <?php $__currentLoopData = $query; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $value): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                <input type="hidden" name="<?php echo e($key); ?>" value="<?php echo e(is_scalar($value) ? (string) $value : ''); ?>">
                                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                            <select name="stage_id" class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs" required>
                                                <?php $__currentLoopData = ($job?->pipelineStages ?? collect()); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $stageOption): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                    <option value="<?php echo e($stageOption->id); ?>" <?php if((string) $selectedApplication->current_stage_id === (string) $stageOption->id): echo 'selected'; endif; ?>><?php echo e($stageOption->stage_label); ?></option>
                                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                            </select>
                                            <button type="submit" class="w-full rounded-lg border border-primary-300/60 bg-primary-50 px-2 py-1.5 text-xs font-semibold text-primary-800 transition-weightless hover:bg-primary-100/80">
                                                <?php echo e(__('candidates.detail.move_stage')); ?>

                                            </button>
                                        </form>

                                        <form method="POST" action="<?php echo e(route('candidates.schedule-interview', ['application' => $selectedApplication->id])); ?>" class="space-y-2 rounded-xl border border-slate-200 bg-white/80 p-2" data-interview-schedule-form>
                                            <?php echo csrf_field(); ?>
                                            <?php $__currentLoopData = $query; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $value): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                <input type="hidden" name="<?php echo e($key); ?>" value="<?php echo e(is_scalar($value) ? (string) $value : ''); ?>">
                                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

                                            <input type="datetime-local" name="scheduled_for" class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs" required>
                                            <input type="number" name="duration_minutes" min="15" step="15" value="60" class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs" placeholder="<?php echo e(__('interviews.fields.duration_minutes')); ?>">
                                            <input type="hidden" name="timezone" value="<?php echo e(config('app.timezone', 'UTC')); ?>">
                                            <select name="interviewer_user_ids[]" multiple required data-placeholder="<?php echo e(__('interviews.fields.interviewers')); ?>" class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs">
                                                <?php $__currentLoopData = $interviewers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $interviewer): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                    <option value="<?php echo e($interviewer->id); ?>"><?php echo e($interviewer->profile?->full_name ?? $interviewer->email); ?></option>
                                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                            </select>
                                            <div data-interview-meeting-link-group>
                                                <input type="url" name="meeting_link" value="<?php echo e(old('meeting_link', $actorZoomLink)); ?>" placeholder="<?php echo e(__('interviews.fields.meeting_link')); ?>" class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs">
                                            </div>
                                            <div class="hidden" data-interview-location-address-group>
                                                <input type="text" name="location_address" value="<?php echo e(old('location_address')); ?>" placeholder="<?php echo e(__('interviews.fields.location_address')); ?>" class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs">
                                            </div>
                                            <select name="location_type" data-placeholder="<?php echo e(__('interviews.fields.location_type')); ?>" class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs" data-interview-location-select>
                                                <option value="<?php echo e(\App\Models\Interview::LOCATION_ZOOM); ?>" <?php if(old('location_type', \App\Models\Interview::LOCATION_ZOOM) === \App\Models\Interview::LOCATION_ZOOM): echo 'selected'; endif; ?>><?php echo e(__('interviews.location_types.zoom')); ?></option>
                                                <option value="<?php echo e(\App\Models\Interview::LOCATION_IN_PERSON); ?>" <?php if(old('location_type') === \App\Models\Interview::LOCATION_IN_PERSON): echo 'selected'; endif; ?>><?php echo e(__('interviews.location_types.in_person')); ?></option>
                                                <option value="<?php echo e(\App\Models\Interview::LOCATION_OTHER); ?>" <?php if(old('location_type') === \App\Models\Interview::LOCATION_OTHER): echo 'selected'; endif; ?>><?php echo e(__('interviews.location_types.other')); ?></option>
                                            </select>
                                            <input type="text" name="channel" value="<?php echo e(old('channel')); ?>" placeholder="<?php echo e(__('candidates.detail.schedule_channel')); ?>" class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs">
                                            <textarea name="notes" rows="2" placeholder="<?php echo e(__('interviews.fields.notes')); ?>" class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs"></textarea>

                                            <button type="submit" class="w-full rounded-lg border border-primary-300/60 bg-primary-50 px-2 py-1.5 text-xs font-semibold text-primary-800 transition-weightless hover:bg-primary-100/80">
                                                <?php echo e(__('candidates.detail.schedule_interview')); ?>

                                            </button>
                                        </form>

                                        <form method="POST" action="<?php echo e(route('candidates.request-feedback', ['application' => $selectedApplication->id])); ?>" class="space-y-2 rounded-xl border border-slate-200 bg-white/80 p-2">
                                            <?php echo csrf_field(); ?>
                                            <?php $__currentLoopData = $query; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $value): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                <input type="hidden" name="<?php echo e($key); ?>" value="<?php echo e(is_scalar($value) ? (string) $value : ''); ?>">
                                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                            <input type="text" name="message" placeholder="<?php echo e(__('candidates.detail.feedback_message')); ?>" class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs">
                                            <button type="submit" class="w-full rounded-lg border border-primary-300/60 bg-primary-50 px-2 py-1.5 text-xs font-semibold text-primary-800 transition-weightless hover:bg-primary-100/80">
                                                <?php echo e(__('candidates.detail.request_feedback')); ?>

                                            </button>
                                        </form>

                                        <form method="POST" action="<?php echo e(route('candidates.reject', ['application' => $selectedApplication->id])); ?>" class="space-y-2 rounded-xl border border-danger-200 bg-danger-50/60 p-2">
                                            <?php echo csrf_field(); ?>
                                            <?php $__currentLoopData = $query; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $value): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                <input type="hidden" name="<?php echo e($key); ?>" value="<?php echo e(is_scalar($value) ? (string) $value : ''); ?>">
                                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                            <input type="text" name="reason" placeholder="<?php echo e(__('candidates.detail.reject_reason')); ?>" class="w-full rounded-lg border border-danger-200 bg-white px-2 py-1.5 text-xs" required>
                                            <button type="submit" class="w-full rounded-lg border border-danger-200 bg-white px-2 py-1.5 text-xs font-semibold text-danger-700">
                                                <?php echo e(__('candidates.detail.reject')); ?>

                                            </button>
                                        </form>
                                    </div>
                                </div>

                                <div class="space-y-4" data-candidate-tabs-root data-default-tab="<?php echo e($isHiredApplication ? 'onboarding' : 'candidate'); ?>">
                                    <?php if($isHiredApplication): ?>
                                        <div class="rounded-2xl border border-slate-200 bg-white/75 p-3">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <button type="button"
                                                        class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 transition-weightless"
                                                        data-candidate-tab-button="candidate">
                                                    <?php echo e(__('candidates.onboarding.tabs.candidate_detail')); ?>

                                                </button>
                                                <button type="button"
                                                        class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 transition-weightless"
                                                        data-candidate-tab-button="onboarding">
                                                    <?php echo e(__('candidates.onboarding.tabs.onboarding_hub')); ?>

                                                </button>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <div data-candidate-tab-panel="candidate" class="space-y-4 <?php echo e($isHiredApplication ? 'hidden' : ''); ?>">
                                <div class="rounded-2xl border border-slate-200 bg-white/75 p-4">
                                    <div class="flex flex-wrap items-center justify-between gap-3">
                                        <div>
                                            <p class="text-xs uppercase tracking-wide text-slate-600"><?php echo e(__('strategy_lab.recruiter.title')); ?></p>
                                            <p class="mt-1 text-xs text-slate-500"><?php echo e(__('strategy_lab.recruiter.subtitle')); ?></p>
                                        </div>
                                        <?php if($strategyBrief): ?>
                                            <div class="flex items-center gap-2">
                                                <?php if (isset($component)) { $__componentOriginal2ddbc40e602c342e508ac696e52f8719 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2ddbc40e602c342e508ac696e52f8719 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.badge','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?><?php echo e(__('strategy_lab.status.'.$strategyBrief->status)); ?> <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal2ddbc40e602c342e508ac696e52f8719)): ?>
<?php $attributes = $__attributesOriginal2ddbc40e602c342e508ac696e52f8719; ?>
<?php unset($__attributesOriginal2ddbc40e602c342e508ac696e52f8719); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal2ddbc40e602c342e508ac696e52f8719)): ?>
<?php $component = $__componentOriginal2ddbc40e602c342e508ac696e52f8719; ?>
<?php unset($__componentOriginal2ddbc40e602c342e508ac696e52f8719); ?>
<?php endif; ?>
                                                <?php if($hasStrategyFinalDecision): ?>
                                                    <?php if (isset($component)) { $__componentOriginal2ddbc40e602c342e508ac696e52f8719 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2ddbc40e602c342e508ac696e52f8719 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.badge','data' => ['variant' => $strategyDecisionStatus === \App\Models\StrategyLabBrief::DECISION_APPROVED ? 'success' : 'danger']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['variant' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($strategyDecisionStatus === \App\Models\StrategyLabBrief::DECISION_APPROVED ? 'success' : 'danger')]); ?>
                                                        <?php echo e(__('strategy_lab.decision_status.'.$strategyDecisionStatus)); ?>

                                                     <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal2ddbc40e602c342e508ac696e52f8719)): ?>
<?php $attributes = $__attributesOriginal2ddbc40e602c342e508ac696e52f8719; ?>
<?php unset($__attributesOriginal2ddbc40e602c342e508ac696e52f8719); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal2ddbc40e602c342e508ac696e52f8719)): ?>
<?php $component = $__componentOriginal2ddbc40e602c342e508ac696e52f8719; ?>
<?php unset($__componentOriginal2ddbc40e602c342e508ac696e52f8719); ?>
<?php endif; ?>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <?php if(! $strategyEligible): ?>
                                        <p class="mt-3 text-xs text-danger-700"><?php echo e($strategyEligibilityError !== '' ? $strategyEligibilityError : __('strategy_lab.messages.shortlist_required')); ?></p>
                                    <?php elseif(! $strategyBrief): ?>
                                        <form method="POST" action="<?php echo e(route('candidates.strategy-lab.assign', ['application' => $selectedApplication->id])); ?>" class="mt-3 space-y-2">
                                            <?php echo csrf_field(); ?>
                                            <?php $__currentLoopData = $query; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $value): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                <input type="hidden" name="<?php echo e($key); ?>" value="<?php echo e(is_scalar($value) ? (string) $value : ''); ?>">
                                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                            <input type="text" name="brief_title" value="<?php echo e(old('brief_title')); ?>" placeholder="<?php echo e(__('strategy_lab.fields.brief_title')); ?>" class="rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs" required>
                                            <p class="text-xs text-slate-600"><?php echo e(__('strategy_lab.labels.auto_48h_window')); ?></p>
                                            <button type="submit" class="rounded-lg border border-aura-300/50 bg-white px-2 py-1.5 text-xs font-semibold text-slate-800">
                                                <?php echo e(__('strategy_lab.actions.assign_brief')); ?>

                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <div class="mt-3 grid gap-3 md:grid-cols-2">
                                            <div class="rounded-xl border border-slate-200 bg-white p-3">
                                                <p class="text-xs uppercase tracking-wide text-slate-600"><?php echo e(__('strategy_lab.labels.brief')); ?></p>
                                                <p class="mt-1 text-sm font-semibold text-slate-900"><?php echo e($strategyBrief->brief_title); ?></p>
                                                <p class="mt-1 text-xs text-slate-600"><?php echo e(__('strategy_lab.labels.deadline')); ?>: <?php echo e(optional($strategyDeadline)->format('Y-m-d H:i')); ?> UTC</p>
                                                <?php if($strategyPastDeadline): ?>
                                                    <p class="mt-1 text-xs text-danger-700"><?php echo e(__('strategy_lab.messages.deadline_passed')); ?></p>
                                                <?php endif; ?>
                                                <?php if($strategyBrief->brief_pdf_url): ?>
                                                    <a href="<?php echo e(\App\Http\Controllers\StrategyLabController::signedBriefUrl($strategyBrief)); ?>" class="mt-2 inline-flex rounded-md border border-aura-300/50 bg-white px-2 py-1 text-xs text-slate-800">
                                                        <?php echo e(__('strategy_lab.actions.download_brief')); ?>

                                                    </a>
                                                <?php else: ?>
                                                    <p class="mt-2 text-xs text-primary-700"><?php echo e(__('strategy_lab.messages.brief_processing')); ?></p>
                                                <?php endif; ?>
                                            </div>

                                            <div class="rounded-xl border border-slate-200 bg-white p-3">
                                                <p class="text-xs uppercase tracking-wide text-slate-600"><?php echo e(__('strategy_lab.labels.submission')); ?></p>
                                                <?php if($strategySubmission): ?>
                                                    <p class="mt-1 text-xs text-slate-600"><?php echo e($strategySubmission->original_filename); ?> (<?php echo e($strategySubmission->submission_type); ?>)</p>
                                                    <a href="<?php echo e(\App\Http\Controllers\StrategyLabController::signedSubmissionUrl($strategySubmission)); ?>" class="mt-2 inline-flex rounded-md border border-aura-300/50 bg-white px-2 py-1 text-xs text-slate-800">
                                                        <?php echo e(__('strategy_lab.actions.open_submission')); ?>

                                                    </a>
                                                <?php else: ?>
                                                    <p class="mt-1 text-xs text-slate-600"><?php echo e(__('strategy_lab.messages.not_submitted')); ?></p>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <div class="mt-3 grid gap-3 md:grid-cols-3">
                                            <form method="POST" action="<?php echo e(route('candidates.strategy-lab.extend-deadline', ['application' => $selectedApplication->id])); ?>" class="rounded-xl border border-slate-200 bg-white p-3">
                                                <?php echo csrf_field(); ?>
                                                <?php $__currentLoopData = $query; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $value): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                    <input type="hidden" name="<?php echo e($key); ?>" value="<?php echo e(is_scalar($value) ? (string) $value : ''); ?>">
                                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                                <label class="text-xs uppercase tracking-wide text-slate-600"><?php echo e(__('strategy_lab.fields.extend_deadline')); ?></label>
                                                <input type="datetime-local" name="deadline_at" class="mt-2 w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs" required>
                                                <button type="submit" class="mt-2 rounded-lg border border-aura-300/50 bg-white px-3 py-1.5 text-xs font-semibold text-slate-800">
                                                    <?php echo e(__('strategy_lab.actions.extend_deadline')); ?>

                                                </button>
                                            </form>

                                            <form method="POST" action="<?php echo e(route('candidates.strategy-lab.mark-reviewed', ['application' => $selectedApplication->id])); ?>" class="rounded-xl border border-slate-200 bg-white p-3">
                                                <?php echo csrf_field(); ?>
                                                <?php $__currentLoopData = $query; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $value): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                    <input type="hidden" name="<?php echo e($key); ?>" value="<?php echo e(is_scalar($value) ? (string) $value : ''); ?>">
                                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                                <p class="text-xs uppercase tracking-wide text-slate-600"><?php echo e(__('strategy_lab.labels.recruiter_review')); ?></p>
                                                <?php if($strategySummary): ?>
                                                    <?php
                                                        $strategyStrengths = collect((array) $strategySummary->strengths_json)->map(fn ($item) => trim((string) $item))->filter()->values();
                                                        $strategyWeaknesses = collect((array) $strategySummary->weaknesses_json)->map(fn ($item) => trim((string) $item))->filter()->values();
                                                    ?>
                                                    <p class="mt-1 text-xs text-slate-700"><?php echo e($strategySummary->executive_summary_text); ?></p>
                                                    <p class="mt-1 text-xs text-slate-600"><?php echo e(__('strategy_lab.labels.creativity_score')); ?>: <?php echo e(number_format((float) $strategySummary->creativity_score, 2)); ?></p>
                                                    <?php if(trim((string) ($strategySummary->overall_recommendation ?? '')) !== ''): ?>
                                                        <p class="mt-1 text-xs text-slate-600"><?php echo e(__('strategy_lab.labels.overall_recommendation')); ?>: <?php echo e($strategySummary->overall_recommendation); ?></p>
                                                    <?php endif; ?>
                                                    <?php if($strategyStrengths->isNotEmpty()): ?>
                                                        <p class="mt-1 text-xs text-slate-600"><?php echo e(__('strategy_lab.labels.strengths')); ?>: <?php echo e($strategyStrengths->implode(', ')); ?></p>
                                                    <?php endif; ?>
                                                    <?php if($strategyWeaknesses->isNotEmpty()): ?>
                                                        <p class="mt-1 text-xs text-slate-600"><?php echo e(__('strategy_lab.labels.weaknesses')); ?>: <?php echo e($strategyWeaknesses->implode(', ')); ?></p>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <p class="mt-1 text-xs text-primary-700"><?php echo e(__('strategy_lab.messages.summary_processing')); ?></p>
                                                <?php endif; ?>
                                                <button type="submit" class="mt-2 rounded-lg border border-success-300/60 bg-success-50 px-3 py-1.5 text-xs font-semibold text-success-800" <?php if(! $strategySubmission || ! $strategySummary): echo 'disabled'; endif; ?>>
                                                    <?php echo e(__('strategy_lab.actions.mark_reviewed')); ?>

                                                </button>
                                            </form>

                                            <form method="POST" action="<?php echo e(route('candidates.strategy-lab.final-decision', ['application' => $selectedApplication->id])); ?>" class="rounded-xl border border-slate-200 bg-white p-3">
                                                <?php echo csrf_field(); ?>
                                                <?php $__currentLoopData = $query; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $value): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                    <input type="hidden" name="<?php echo e($key); ?>" value="<?php echo e(is_scalar($value) ? (string) $value : ''); ?>">
                                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                                <p class="text-xs uppercase tracking-wide text-slate-600"><?php echo e(__('strategy_lab.labels.final_decision')); ?></p>
                                                <?php if($hasStrategyFinalDecision): ?>
                                                    <p class="mt-1 text-xs text-slate-700"><?php echo e(__('strategy_lab.labels.final_decision_status')); ?>: <?php echo e(__('strategy_lab.decision_status.'.$strategyDecisionStatus)); ?></p>
                                                    <?php if(trim((string) ($strategyBrief?->final_decision_note ?? '')) !== ''): ?>
                                                        <p class="mt-1 text-xs text-slate-600"><?php echo e($strategyBrief->final_decision_note); ?></p>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <p class="mt-1 text-xs text-slate-600"><?php echo e(__('strategy_lab.messages.final_decision_pending')); ?></p>
                                                <?php endif; ?>
                                                <label for="strategy-decision-note-<?php echo e($selectedApplication->id); ?>" class="mt-2 block text-xs uppercase tracking-wide text-slate-600"><?php echo e(__('strategy_lab.fields.final_decision_note')); ?></label>
                                                <textarea id="strategy-decision-note-<?php echo e($selectedApplication->id); ?>" name="decision_note" rows="3" maxlength="2000" class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs" placeholder="<?php echo e(__('strategy_lab.fields.final_decision_note')); ?>"><?php echo e(old('decision_note')); ?></textarea>
                                                <div class="mt-2 grid grid-cols-2 gap-2">
                                                    <button type="submit" name="decision_status" value="<?php echo e(\App\Models\StrategyLabBrief::DECISION_APPROVED); ?>" class="rounded-lg border border-success-300/60 bg-success-50 px-2 py-1.5 text-xs font-semibold text-success-800" <?php if(! $canSetStrategyFinalDecision): echo 'disabled'; endif; ?>>
                                                        <?php echo e(__('strategy_lab.actions.approve_candidate')); ?>

                                                    </button>
                                                    <button type="submit" name="decision_status" value="<?php echo e(\App\Models\StrategyLabBrief::DECISION_REJECTED); ?>" class="rounded-lg border border-danger-300/60 bg-danger-50 px-2 py-1.5 text-xs font-semibold text-danger-800" <?php if(! $canSetStrategyFinalDecision): echo 'disabled'; endif; ?>>
                                                        <?php echo e(__('strategy_lab.actions.reject_candidate')); ?>

                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="rounded-2xl border border-slate-200 bg-white/75 p-4">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <div>
                                            <p class="text-xs uppercase tracking-wide text-slate-600"><?php echo e(__('candidates.detail.ranking_grid')); ?></p>
                                            <p class="mt-1 text-xs text-slate-500"><?php echo e(__('candidates.detail.analysis_auto_hint')); ?></p>
                                        </div>
                                        <?php if($rankingPosition !== null): ?>
                                            <?php if (isset($component)) { $__componentOriginal2ddbc40e602c342e508ac696e52f8719 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2ddbc40e602c342e508ac696e52f8719 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.badge','data' => ['variant' => $isTopThree ? 'success' : 'pending']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['variant' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($isTopThree ? 'success' : 'pending')]); ?>
                                                <?php echo e(__('candidates.detail.rank_position')); ?> #<?php echo e($rankingPosition); ?>

                                             <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal2ddbc40e602c342e508ac696e52f8719)): ?>
<?php $attributes = $__attributesOriginal2ddbc40e602c342e508ac696e52f8719; ?>
<?php unset($__attributesOriginal2ddbc40e602c342e508ac696e52f8719); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal2ddbc40e602c342e508ac696e52f8719)): ?>
<?php $component = $__componentOriginal2ddbc40e602c342e508ac696e52f8719; ?>
<?php unset($__componentOriginal2ddbc40e602c342e508ac696e52f8719); ?>
<?php endif; ?>
                                        <?php endif; ?>
                                    </div>

                                    <?php if($analysisRows->isEmpty()): ?>
                                        <p class="mt-3 text-xs text-slate-600"><?php echo e(__('candidates.detail.not_available')); ?></p>
                                    <?php else: ?>
                                        <div class="mt-3 overflow-x-auto rounded-xl border border-slate-200 bg-white/80">
                                            <table class="min-w-full divide-y divide-slate-200 text-xs">
                                                <thead class="bg-slate-50">
                                                    <tr>
                                                        <th class="px-3 py-2 text-left font-semibold uppercase tracking-wide text-slate-600">#</th>
                                                        <th class="px-3 py-2 text-left font-semibold uppercase tracking-wide text-slate-600"><?php echo e(__('candidates.detail.candidate')); ?></th>
                                                        <th class="px-3 py-2 text-left font-semibold uppercase tracking-wide text-slate-600"><?php echo e(__('candidates.detail.total_score')); ?></th>
                                                        <th class="px-3 py-2 text-left font-semibold uppercase tracking-wide text-slate-600"><?php echo e(__('candidates.detail.status_label')); ?></th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-slate-100">
                                                    <?php $__currentLoopData = $analysisRows->take(8); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                        <?php
                                                            $rowStatus = (string) ($row['analysis_status'] ?? 'pending_analysis');
                                                            $rowStatusLabel = \App\Services\Analysis\CandidateAnalysisService::analysisStatusLabel($rowStatus);
                                                            $isSelectedRow = (string) ($row['application_id'] ?? '') === (string) $selectedApplication->id;
                                                            $rowCandidateLabel = $blind
                                                                ? __('candidates.detail.masked_identifier_value', [
                                                                    'identifier' => \App\Http\Controllers\CandidateWorkspaceController::maskedCandidateIdentifier((string) ($row['application_id'] ?? '')),
                                                                ])
                                                                : (string) ($row['candidate_name'] ?? __('candidates.detail.not_available'));
                                                        ?>
                                                        <tr class="<?php echo \Illuminate\Support\Arr::toCssClasses([$isSelectedRow ? 'bg-primary-50/60' : 'bg-white']); ?>">
                                                            <td class="px-3 py-2 text-slate-700">#<?php echo e((int) ($row['ranking_position'] ?? 0)); ?></td>
                                                            <td class="px-3 py-2 text-slate-900"><?php echo e($rowCandidateLabel); ?></td>
                                                            <td class="px-3 py-2 text-slate-700"><?php echo e(is_numeric($row['total_score'] ?? null) ? number_format((float) $row['total_score'], 1).'/100' : __('candidates.detail.not_available')); ?></td>
                                                            <td class="px-3 py-2 text-slate-700"><?php echo e($rowStatusLabel); ?></td>
                                                        </tr>
                                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>

                                    <div class="mt-3 grid gap-3 lg:grid-cols-3">
                                        <div class="rounded-xl border border-success-200/70 bg-success-50/60 p-3">
                                            <p class="text-[11px] font-semibold uppercase tracking-wide text-success-700"><?php echo e(__('candidates.detail.top_three')); ?></p>
                                            <?php if($analysisTopThree->isEmpty()): ?>
                                                <p class="mt-1 text-xs text-slate-600"><?php echo e(__('candidates.detail.not_available')); ?></p>
                                            <?php else: ?>
                                                <ul class="mt-2 space-y-1 text-xs text-slate-700">
                                                    <?php $__currentLoopData = $analysisTopThree; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                        <li>
                                                            #<?php echo e((int) ($row['ranking_position'] ?? 0)); ?> -
                                                            <?php if($blind): ?>
                                                                <?php echo e(__('candidates.detail.masked_identifier_value', [
                                                                    'identifier' => \App\Http\Controllers\CandidateWorkspaceController::maskedCandidateIdentifier((string) ($row['application_id'] ?? '')),
                                                                ])); ?>

                                                            <?php else: ?>
                                                                <?php echo e((string) ($row['candidate_name'] ?? __('candidates.detail.not_available'))); ?>

                                                            <?php endif; ?>
                                                        </li>
                                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                                </ul>
                                            <?php endif; ?>
                                        </div>

                                        <div class="rounded-xl border border-primary-200/70 bg-primary-50/60 p-3">
                                            <p class="text-[11px] font-semibold uppercase tracking-wide text-primary-700"><?php echo e(__('candidates.detail.score_distribution')); ?></p>
                                            <?php if($analysisDistribution->isEmpty()): ?>
                                                <p class="mt-1 text-xs text-slate-600"><?php echo e(__('candidates.detail.not_available')); ?></p>
                                            <?php else: ?>
                                                <div class="mt-2 space-y-1.5">
                                                    <?php $__currentLoopData = $analysisDistribution; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $bucket): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                        <?php
                                                            $bucketCount = (int) ($bucket['count'] ?? 0);
                                                            $bucketWidth = (int) round(($bucketCount / $maxAnalysisDistributionCount) * 100);
                                                        ?>
                                                        <div>
                                                            <div class="flex items-center justify-between text-[11px] text-slate-700">
                                                                <span><?php echo e((string) ($bucket['label'] ?? '')); ?></span>
                                                                <span><?php echo e($bucketCount); ?></span>
                                                            </div>
                                                            <div class="mt-0.5 h-1.5 overflow-hidden rounded-full bg-primary-100">
                                                                <div class="h-1.5 rounded-full bg-primary-500" style="width: <?php echo e(max(4, $bucketWidth)); ?>%"></div>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <div class="rounded-xl border border-aura-200/70 bg-aura-50/60 p-3">
                                            <p class="text-[11px] font-semibold uppercase tracking-wide text-aura-700"><?php echo e(__('candidates.detail.fairness')); ?></p>
                                            <?php if(! is_array($analysisFairness)): ?>
                                                <p class="mt-1 text-xs text-slate-600"><?php echo e(__('candidates.detail.not_available')); ?></p>
                                            <?php else: ?>
                                                <?php
                                                    $genderFairness = (array) ($analysisFairness['gender'] ?? []);
                                                    $schoolFairness = (array) ($analysisFairness['school'] ?? []);
                                                ?>
<?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                                                <div class="grid gap-3 lg:grid-cols-3">
                                    <div class="rounded-2xl border border-slate-200 bg-white/70 p-4">
                                        <p class="text-xs uppercase tracking-wide text-slate-600"><?php echo e(__('candidates.detail.documents')); ?></p>
                                        <div class="mt-3 space-y-2">
                                            <?php $__empty_1 = true; $__currentLoopData = $documents; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $document): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                                <div class="flex items-center justify-between rounded-lg border border-slate-200 bg-white p-2">
                                                    <p class="truncate text-xs text-slate-700"><?php echo e($document->original_filename); ?></p>
                                                    <a href="<?php echo e(\App\Http\Controllers\CandidateWorkspaceController::signedDocumentUrl($document)); ?>" class="rounded-md border border-aura-200 px-2 py-1 text-xs text-aura-700">
                                                        <?php echo e(__('candidates.detail.download')); ?>

                                                    </a>
                                                </div>
                                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                                <p class="text-xs text-slate-600"><?php echo e(__('candidates.detail.not_available')); ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div class="rounded-2xl border border-slate-200 bg-white/70 p-4">
                                        <p class="text-xs uppercase tracking-wide text-slate-600"><?php echo e(__('candidates.detail.comments')); ?></p>
                                        <form method="POST" action="<?php echo e(route('candidates.comments.store', ['application' => $selectedApplication->id])); ?>" class="mt-3 space-y-2">
                                            <?php echo csrf_field(); ?>
                                            <?php $__currentLoopData = $query; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $value): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                <input type="hidden" name="<?php echo e($key); ?>" value="<?php echo e(is_scalar($value) ? (string) $value : ''); ?>">
                                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                            <textarea name="body" rows="4" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2 text-sm" placeholder="<?php echo e(__('candidates.detail.comment_placeholder')); ?>"><?php echo e(old('body')); ?></textarea>
                                            <?php $__errorArgs = ['body'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                                                <p class="text-xs text-danger-700"><?php echo e($message); ?></p>
                                            <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                                            <button type="submit" class="rounded-xl bg-success-600 px-3 py-2 text-xs font-semibold text-white">
                                                <?php echo e(__('candidates.detail.save_comment')); ?>

                                            </button>
                                        </form>
                                    </div>

                                    <div class="rounded-2xl border border-slate-200 bg-white/70 p-4">
                                        <p class="text-xs uppercase tracking-wide text-slate-600"><?php echo e(__('candidates.detail.reverse_feedback_aggregate')); ?></p>
                                        <?php if(! ($canViewReverseFeedbackAggregate ?? false)): ?>
                                            <p class="mt-3 text-xs text-slate-600"><?php echo e(__('candidates.detail.reverse_feedback_restricted')); ?></p>
                                        <?php elseif(is_array($reverseFeedbackAggregate ?? null)): ?>
                                            <div class="mt-3 grid gap-2 text-xs text-slate-700">
                                                <div class="flex items-center justify-between rounded-lg border border-slate-200 bg-white px-2 py-1.5">
                                                    <span><?php echo e(__('candidates.detail.reverse_feedback_total')); ?></span>
                                                    <span class="font-semibold text-slate-900"><?php echo e((int) ($reverseFeedbackAggregate['total'] ?? 0)); ?></span>
                                                </div>
                                                <div class="flex items-center justify-between rounded-lg border border-slate-200 bg-white px-2 py-1.5">
                                                    <span><?php echo e(__('candidates.detail.reverse_feedback_clarity')); ?></span>
                                                    <span class="font-semibold text-slate-900"><?php echo e(number_format((float) ($reverseFeedbackAggregate['avg_clarity'] ?? 0), 1)); ?>/5</span>
                                                </div>
                                                <div class="flex items-center justify-between rounded-lg border border-slate-200 bg-white px-2 py-1.5">
                                                    <span><?php echo e(__('candidates.detail.reverse_feedback_speed')); ?></span>
                                                    <span class="font-semibold text-slate-900"><?php echo e(number_format((float) ($reverseFeedbackAggregate['avg_speed'] ?? 0), 1)); ?>/5</span>
                                                </div>
                                                <div class="flex items-center justify-between rounded-lg border border-slate-200 bg-white px-2 py-1.5">
                                                    <span><?php echo e(__('candidates.detail.reverse_feedback_kindness')); ?></span>
                                                    <span class="font-semibold text-slate-900"><?php echo e(number_format((float) ($reverseFeedbackAggregate['avg_kindness'] ?? 0), 1)); ?>/5</span>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <p class="mt-3 text-xs text-slate-600"><?php echo e(__('candidates.detail.reverse_feedback_none')); ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="rounded-2xl border border-slate-200 bg-white/70 p-4">
                                    <p class="text-xs uppercase tracking-wide text-slate-600"><?php echo e(__('candidates.detail.activity')); ?></p>
                                    <?php
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
                                    ?>
                                    <div class="mt-3 space-y-2">
                                        <?php $__empty_1 = true; $__currentLoopData = $items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                            <div class="rounded-lg border border-slate-200 bg-white p-2">
                                                <div class="flex items-center justify-between gap-2">
                                                    <p class="text-xs font-semibold text-slate-800"><?php echo e($item['label']); ?></p>
                                                    <?php if($item['download_url']): ?>
                                                        <a href="<?php echo e($item['download_url']); ?>" class="rounded-md border border-aura-200 px-2 py-1 text-[11px] text-aura-700">
                                                            <?php echo e(__('candidates.detail.download')); ?>

                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                                <p class="mt-1 text-xs text-slate-600"><?php echo e($item['body']); ?></p>
                                                <p class="mt-1 text-[11px] text-slate-500">
                                                    <?php echo e(optional($item['created_at'])->diffForHumans()); ?>

                                                    <?php if($item['author']): ?>
                                                        - <?php echo e($item['author']); ?>

                                                    <?php endif; ?>
                                                </p>
                                            </div>
                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                            <p class="text-xs text-slate-600"><?php echo e(__('candidates.detail.not_available')); ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                    </div>

                                    <?php if($isHiredApplication): ?>
                                        <div data-candidate-tab-panel="onboarding" class="space-y-4">
                                            <div class="rounded-2xl border border-slate-200 bg-white/75 p-4">
                                                <p class="text-xs uppercase tracking-wide text-slate-600"><?php echo e(__('candidates.onboarding.title')); ?></p>
                                                <p class="mt-1 text-sm text-slate-700"><?php echo e(__('candidates.onboarding.subtitle')); ?></p>
                                            </div>

                                            <div class="grid gap-3 xl:grid-cols-2">
                                                <div class="rounded-2xl border border-slate-200 bg-white/75 p-4">
                                                    <p class="text-xs uppercase tracking-wide text-slate-600"><?php echo e(__('candidates.onboarding.offer.title')); ?></p>
                                                    <form method="POST" action="<?php echo e(route('candidates.onboarding.offer.save', ['application' => $selectedApplication->id])); ?>" class="mt-3 grid gap-2 md:grid-cols-2">
                                                        <?php echo csrf_field(); ?>
                                                        <?php $__currentLoopData = $query; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $value): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                            <input type="hidden" name="<?php echo e($key); ?>" value="<?php echo e(is_scalar($value) ? (string) $value : ''); ?>">
                                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                                        <select name="offer_status" data-placeholder="<?php echo e(__('candidates.onboarding.offer.status')); ?>" class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs" required>
                                                            <?php $__currentLoopData = \App\Models\Offer::statuses(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $offerStatusOption): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                                <option value="<?php echo e($offerStatusOption); ?>" <?php if((string) old('offer_status', $offer?->offer_status) === $offerStatusOption): echo 'selected'; endif; ?>>
                                                                    <?php echo e(__('candidates.onboarding.offer.statuses.'.$offerStatusOption)); ?>

                                                                </option>
                                                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                                        </select>
                                                        <input type="number" name="salary_amount" step="0.01" min="0" value="<?php echo e(old('salary_amount', $offer?->salary_amount)); ?>" placeholder="<?php echo e(__('candidates.onboarding.offer.salary_amount')); ?>" class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs">
                                                        <input type="text" name="currency" value="<?php echo e(old('currency', $offer?->currency ?? 'USD')); ?>" placeholder="<?php echo e(__('candidates.onboarding.offer.currency')); ?>" class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs" required>
                                                        <input type="date" name="start_date" value="<?php echo e(old('start_date', $offer?->start_date?->toDateString())); ?>" class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs">
                                                        <button type="submit" class="md:col-span-2 rounded-lg border border-success-300/60 bg-success-50 px-3 py-1.5 text-xs font-semibold text-success-800 transition-weightless hover:bg-success-100/80">
                                                            <?php echo e(__('candidates.onboarding.offer.save')); ?>

                                                        </button>
                                                    </form>
                                                </div>

                                                <div class="rounded-2xl border border-slate-200 bg-white/75 p-4">
                                                    <p class="text-xs uppercase tracking-wide text-slate-600"><?php echo e(__('candidates.onboarding.contract.title')); ?></p>
                                                    <?php if($contract): ?>
                                                        <div class="mt-2 flex flex-wrap items-center gap-2 text-xs text-slate-700">
                                                            <?php if (isset($component)) { $__componentOriginal2ddbc40e602c342e508ac696e52f8719 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2ddbc40e602c342e508ac696e52f8719 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.badge','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?><?php echo e(__('candidates.onboarding.contract.statuses.'.$contract->contract_status)); ?> <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal2ddbc40e602c342e508ac696e52f8719)): ?>
<?php $attributes = $__attributesOriginal2ddbc40e602c342e508ac696e52f8719; ?>
<?php unset($__attributesOriginal2ddbc40e602c342e508ac696e52f8719); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal2ddbc40e602c342e508ac696e52f8719)): ?>
<?php $component = $__componentOriginal2ddbc40e602c342e508ac696e52f8719; ?>
<?php unset($__componentOriginal2ddbc40e602c342e508ac696e52f8719); ?>
<?php endif; ?>
                                                            <?php if($contract->signed_at): ?>
                                                                <span><?php echo e(__('candidates.onboarding.contract.signed_at')); ?>: <?php echo e($contract->signed_at->diffForHumans()); ?></span>
                                                            <?php endif; ?>
                                                            <a href="<?php echo e(\App\Http\Controllers\CandidateWorkspaceController::signedContractUrl($contract)); ?>" class="rounded-md border border-aura-200 px-2 py-1 text-aura-700">
                                                                <?php echo e(__('candidates.onboarding.contract.download')); ?>

                                                            </a>
                                                        </div>
                                                    <?php endif; ?>

                                                    <form method="POST" action="<?php echo e(route('candidates.onboarding.contract.save', ['application' => $selectedApplication->id])); ?>" enctype="multipart/form-data" class="mt-3 grid gap-2 md:grid-cols-2">
                                                        <?php echo csrf_field(); ?>
                                                        <?php $__currentLoopData = $query; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $value): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                            <input type="hidden" name="<?php echo e($key); ?>" value="<?php echo e(is_scalar($value) ? (string) $value : ''); ?>">
                                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                                        <select name="contract_status" data-placeholder="<?php echo e(__('candidates.onboarding.contract.status')); ?>" class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs" required>
                                                            <option value="<?php echo e(\App\Models\Contract::STATUS_DRAFT); ?>" <?php if((string) old('contract_status', $contract?->contract_status ?? \App\Models\Contract::STATUS_DRAFT) === \App\Models\Contract::STATUS_DRAFT): echo 'selected'; endif; ?>>
                                                                <?php echo e(__('candidates.onboarding.contract.statuses.draft')); ?>

                                                            </option>
                                                            <option value="<?php echo e(\App\Models\Contract::STATUS_SENT); ?>" <?php if((string) old('contract_status', $contract?->contract_status ?? \App\Models\Contract::STATUS_DRAFT) === \App\Models\Contract::STATUS_SENT): echo 'selected'; endif; ?>>
                                                                <?php echo e(__('candidates.onboarding.contract.statuses.sent')); ?>

                                                            </option>
                                                        </select>
                                                        <select name="signature_method" data-placeholder="<?php echo e(__('candidates.onboarding.contract.signature_method')); ?>" class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs" required>
                                                            <option value="<?php echo e(\App\Models\Contract::SIGNATURE_METHOD_TYPED); ?>" <?php if((string) old('signature_method', $contract?->signature_method ?? \App\Models\Contract::SIGNATURE_METHOD_TYPED) === \App\Models\Contract::SIGNATURE_METHOD_TYPED): echo 'selected'; endif; ?>>
                                                                <?php echo e(__('candidates.onboarding.contract.signature_typed')); ?>

                                                            </option>
                                                        </select>
                                                        <input type="file" name="contract_file" accept=".pdf,.doc,.docx" class="md:col-span-2 w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs">
                                                        <button type="submit" class="md:col-span-2 rounded-lg border border-success-300/60 bg-success-50 px-3 py-1.5 text-xs font-semibold text-success-800 transition-weightless hover:bg-success-100/80">
                                                            <?php echo e(__('candidates.onboarding.contract.save')); ?>

                                                        </button>
                                                    </form>
                                                </div>
                                            </div>

                                            <div class="grid gap-3 xl:grid-cols-2">
                                                <div class="rounded-2xl border border-slate-200 bg-white/75 p-4">
                                                    <p class="text-xs uppercase tracking-wide text-slate-600"><?php echo e(__('candidates.onboarding.documents.title')); ?></p>
                                                    <form method="POST" action="<?php echo e(route('candidates.onboarding.documents.store', ['application' => $selectedApplication->id])); ?>" enctype="multipart/form-data" class="mt-3 space-y-2">
                                                        <?php echo csrf_field(); ?>
                                                        <?php $__currentLoopData = $query; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $value): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                            <input type="hidden" name="<?php echo e($key); ?>" value="<?php echo e(is_scalar($value) ? (string) $value : ''); ?>">
                                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                                        <select name="doc_type" data-placeholder="<?php echo e(__('candidates.onboarding.documents.doc_type')); ?>" class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs" required>
                                                            <?php $__currentLoopData = \App\Models\OnboardingDocument::types(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $docTypeOption): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                                <option value="<?php echo e($docTypeOption); ?>" <?php if((string) old('doc_type') === $docTypeOption): echo 'selected'; endif; ?>>
                                                                    <?php echo e(__('candidates.onboarding.documents.types.'.$docTypeOption)); ?>

                                                                </option>
                                                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                                        </select>
                                                        <input type="file" name="file" accept=".pdf,.doc,.docx,.png,.jpg,.jpeg" class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs" required>
                                                        <button type="submit" class="rounded-lg border border-success-300/60 bg-success-50 px-3 py-1.5 text-xs font-semibold text-success-800 transition-weightless hover:bg-success-100/80">
                                                            <?php echo e(__('candidates.onboarding.documents.upload')); ?>

                                                        </button>
                                                    </form>

                                                    <div class="mt-3 space-y-2">
                                                        <?php $__empty_1 = true; $__currentLoopData = $onboardingDocuments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $onboardingDocument): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                                            <div class="flex items-center justify-between rounded-lg border border-slate-200 bg-white p-2">
                                                                <p class="truncate text-xs text-slate-700"><?php echo e(__('candidates.onboarding.documents.types.'.$onboardingDocument->doc_type)); ?></p>
                                                                <a href="<?php echo e(\App\Http\Controllers\CandidateWorkspaceController::signedOnboardingDocumentUrl($onboardingDocument)); ?>" class="rounded-md border border-aura-200 px-2 py-1 text-xs text-aura-700">
                                                                    <?php echo e(__('candidates.detail.download')); ?>

                                                                </a>
                                                            </div>
                                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                                            <p class="text-xs text-slate-600"><?php echo e(__('candidates.detail.not_available')); ?></p>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>

                                                <div class="rounded-2xl border border-slate-200 bg-white/75 p-4">
                                                    <p class="text-xs uppercase tracking-wide text-slate-600"><?php echo e(__('candidates.onboarding.calendar.title')); ?></p>
                                                    <form method="POST" action="<?php echo e(route('candidates.onboarding.schedule.store', ['application' => $selectedApplication->id])); ?>" class="mt-3 grid gap-2">
                                                        <?php echo csrf_field(); ?>
                                                        <?php $__currentLoopData = $query; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $value): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                            <input type="hidden" name="<?php echo e($key); ?>" value="<?php echo e(is_scalar($value) ? (string) $value : ''); ?>">
                                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                                        <input type="text" name="title" placeholder="<?php echo e(__('candidates.onboarding.calendar.fields.title')); ?>" class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs" required>
                                                        <input type="datetime-local" name="start_at" class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs" required>
                                                        <input type="datetime-local" name="end_at" class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs" required>
                                                        <input type="text" name="location" placeholder="<?php echo e(__('candidates.onboarding.calendar.fields.location')); ?>" class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs">
                                                        <button type="submit" class="rounded-lg border border-success-300/60 bg-success-50 px-3 py-1.5 text-xs font-semibold text-success-800 transition-weightless hover:bg-success-100/80">
                                                            <?php echo e(__('candidates.onboarding.calendar.save')); ?>

                                                        </button>
                                                    </form>

                                                    <div class="mt-3 space-y-2">
                                                        <?php $__empty_1 = true; $__currentLoopData = $onboardingScheduleItems; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $scheduleItem): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                                            <div class="rounded-lg border border-slate-200 bg-white p-2 text-xs text-slate-700">
                                                                <p class="font-semibold text-slate-800"><?php echo e($scheduleItem->title); ?></p>
                                                                <p><?php echo e(optional($scheduleItem->start_at)->format('Y-m-d H:i')); ?> - <?php echo e(optional($scheduleItem->end_at)->format('Y-m-d H:i')); ?> UTC</p>
                                                                <?php if($scheduleItem->location): ?>
                                                                    <p><?php echo e($scheduleItem->location); ?></p>
                                                                <?php endif; ?>
                                                            </div>
                                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                                            <p class="text-xs text-slate-600"><?php echo e(__('candidates.detail.not_available')); ?></p>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="rounded-2xl border border-slate-200 bg-white/75 p-4">
                                                <p class="text-xs uppercase tracking-wide text-slate-600"><?php echo e(__('candidates.onboarding.tasks.title')); ?></p>
                                                <form method="POST" action="<?php echo e(route('candidates.onboarding.tasks.store', ['application' => $selectedApplication->id])); ?>" class="mt-3 grid gap-2 md:grid-cols-3">
                                                    <?php echo csrf_field(); ?>
                                                    <?php $__currentLoopData = $query; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $value): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                        <input type="hidden" name="<?php echo e($key); ?>" value="<?php echo e(is_scalar($value) ? (string) $value : ''); ?>">
                                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                                    <input type="text" name="task_name" placeholder="<?php echo e(__('candidates.onboarding.tasks.fields.task_name')); ?>" class="rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs md:col-span-2" required>
                                                    <input type="datetime-local" name="due_at" class="rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs">
                                                    <button type="submit" class="md:col-span-3 rounded-lg border border-success-300/60 bg-success-50 px-3 py-1.5 text-xs font-semibold text-success-800 transition-weightless hover:bg-success-100/80">
                                                        <?php echo e(__('candidates.onboarding.tasks.add')); ?>

                                                    </button>
                                                </form>

                                                <div class="mt-3 space-y-2">
                                                    <?php $__empty_1 = true; $__currentLoopData = $onboardingTasks; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $onboardingTask): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                                        <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-slate-200 bg-white p-2">
                                                            <div>
                                                                <p class="text-xs font-semibold text-slate-800"><?php echo e($onboardingTask->task_name); ?></p>
                                                                <p class="text-[11px] text-slate-600">
                                                                    <?php echo e(__('candidates.onboarding.tasks.due_at')); ?>:
                                                                    <?php echo e($onboardingTask->due_at ? $onboardingTask->due_at->format('Y-m-d H:i').' UTC' : __('candidates.detail.not_available')); ?>

                                                                </p>
                                                            </div>
                                                            <form method="POST" action="<?php echo e(route('candidates.onboarding.tasks.toggle', ['application' => $selectedApplication->id, 'onboardingTask' => $onboardingTask->id])); ?>">
                                                                <?php echo csrf_field(); ?>
                                                                <?php $__currentLoopData = $query; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $value): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                                    <input type="hidden" name="<?php echo e($key); ?>" value="<?php echo e(is_scalar($value) ? (string) $value : ''); ?>">
                                                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                                                <button type="submit" class="rounded-md border px-2 py-1 text-xs <?php echo e($onboardingTask->is_completed ? 'border-success-200 bg-success-50 text-success-800' : 'border-slate-200 bg-white text-slate-700'); ?>">
                                                                    <?php echo e($onboardingTask->is_completed ? __('candidates.onboarding.tasks.mark_open') : __('candidates.onboarding.tasks.mark_done')); ?>

                                                                </button>
                                                            </form>
                                                        </div>
                                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                                        <p class="text-xs text-slate-600"><?php echo e(__('candidates.detail.not_available')); ?></p>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalf225215015f2c1140bc03ba841300625)): ?>
<?php $attributes = $__attributesOriginalf225215015f2c1140bc03ba841300625; ?>
<?php unset($__attributesOriginalf225215015f2c1140bc03ba841300625); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalf225215015f2c1140bc03ba841300625)): ?>
<?php $component = $__componentOriginalf225215015f2c1140bc03ba841300625; ?>
<?php unset($__componentOriginalf225215015f2c1140bc03ba841300625); ?>
<?php endif; ?>
            </div>
﻿        <?php else: ?>
            <!-- NEW LAYOUT: JOB CARDS & RANKED LIST -->
            <?php if (isset($component)) { $__componentOriginalf225215015f2c1140bc03ba841300625 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalf225215015f2c1140bc03ba841300625 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.glass-card','data' => ['class' => 'mb-4 p-0 overflow-hidden']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('glass-card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'mb-4 p-0 overflow-hidden']); ?>
                <div class="p-4 border-b border-white/60 bg-white/55">
                    <form method="GET" action="<?php echo e(route('candidates.index')); ?>" class="flex flex-wrap items-end gap-4">
                        <?php if(auth()->user()->isSuperadmin()): ?>
                            <?php if (isset($component)) { $__componentOriginalf4c8ecf26ef77d4de25edf56eae3a34d = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalf4c8ecf26ef77d4de25edf56eae3a34d = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.form-field','data' => ['label' => __('jobs.company'),'name' => 'company_id','class' => 'w-48']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('form-field'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('jobs.company')),'name' => 'company_id','class' => 'w-48']); ?>
                                <select name="company_id" data-placeholder="<?php echo e(__('jobs.company_placeholder')); ?>" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2 text-sm">
                                    <option value=""><?php echo e(__('jobs.company_placeholder')); ?></option>
                                    <?php $__currentLoopData = $companies; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $company): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <option value="<?php echo e($company->id); ?>" <?php if((string) $selectedCompanyId === (string) $company->id): echo 'selected'; endif; ?>><?php echo e($company->name); ?></option>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                </select>
                             <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalf4c8ecf26ef77d4de25edf56eae3a34d)): ?>
<?php $attributes = $__attributesOriginalf4c8ecf26ef77d4de25edf56eae3a34d; ?>
<?php unset($__attributesOriginalf4c8ecf26ef77d4de25edf56eae3a34d); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalf4c8ecf26ef77d4de25edf56eae3a34d)): ?>
<?php $component = $__componentOriginalf4c8ecf26ef77d4de25edf56eae3a34d; ?>
<?php unset($__componentOriginalf4c8ecf26ef77d4de25edf56eae3a34d); ?>
<?php endif; ?>
                        <?php endif; ?>
                        <?php if (isset($component)) { $__componentOriginalf4c8ecf26ef77d4de25edf56eae3a34d = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalf4c8ecf26ef77d4de25edf56eae3a34d = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.form-field','data' => ['label' => __('candidates.filters.search'),'name' => 'q','class' => 'w-48']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('form-field'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('candidates.filters.search')),'name' => 'q','class' => 'w-48']); ?>
                            <input type="text" name="q" value="<?php echo e($filters['q'] ?? ''); ?>" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2 text-sm" autocomplete="off">
                         <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalf4c8ecf26ef77d4de25edf56eae3a34d)): ?>
<?php $attributes = $__attributesOriginalf4c8ecf26ef77d4de25edf56eae3a34d; ?>
<?php unset($__attributesOriginalf4c8ecf26ef77d4de25edf56eae3a34d); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalf4c8ecf26ef77d4de25edf56eae3a34d)): ?>
<?php $component = $__componentOriginalf4c8ecf26ef77d4de25edf56eae3a34d; ?>
<?php unset($__componentOriginalf4c8ecf26ef77d4de25edf56eae3a34d); ?>
<?php endif; ?>
                        <?php if (isset($component)) { $__componentOriginalf4c8ecf26ef77d4de25edf56eae3a34d = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalf4c8ecf26ef77d4de25edf56eae3a34d = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.form-field','data' => ['label' => __('candidates.filters.job'),'name' => 'job_id','class' => 'w-48']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('form-field'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('candidates.filters.job')),'name' => 'job_id','class' => 'w-48']); ?>
                            <select name="job_id" data-placeholder="<?php echo e(__('candidates.filters.job_placeholder')); ?>" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2 text-sm">
                                <option value=""><?php echo e(__('candidates.filters.job_placeholder')); ?></option>
                                <?php $__currentLoopData = $jobs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $job): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <option value="<?php echo e($job->id); ?>" <?php if(($filters['job_id'] ?? null) === $job->id): echo 'selected'; endif; ?>><?php echo e($job->title); ?></option>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </select>
                         <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalf4c8ecf26ef77d4de25edf56eae3a34d)): ?>
<?php $attributes = $__attributesOriginalf4c8ecf26ef77d4de25edf56eae3a34d; ?>
<?php unset($__attributesOriginalf4c8ecf26ef77d4de25edf56eae3a34d); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalf4c8ecf26ef77d4de25edf56eae3a34d)): ?>
<?php $component = $__componentOriginalf4c8ecf26ef77d4de25edf56eae3a34d; ?>
<?php unset($__componentOriginalf4c8ecf26ef77d4de25edf56eae3a34d); ?>
<?php endif; ?>
                        <?php if (isset($component)) { $__componentOriginalf4c8ecf26ef77d4de25edf56eae3a34d = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalf4c8ecf26ef77d4de25edf56eae3a34d = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.form-field','data' => ['label' => __('candidates.filters.status'),'name' => 'status','class' => 'w-48']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('form-field'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('candidates.filters.status')),'name' => 'status','class' => 'w-48']); ?>
                            <select name="status" data-placeholder="<?php echo e(__('candidates.filters.status_placeholder')); ?>" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2 text-sm">
                                <option value=""><?php echo e(__('candidates.filters.status_placeholder')); ?></option>
                                <?php $__currentLoopData = $statuses; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $status): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <option value="<?php echo e($status); ?>" <?php if(($filters['status'] ?? null) === $status): echo 'selected'; endif; ?>><?php echo e(__('candidates.list.status.'.$status)); ?></option>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </select>
                         <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalf4c8ecf26ef77d4de25edf56eae3a34d)): ?>
<?php $attributes = $__attributesOriginalf4c8ecf26ef77d4de25edf56eae3a34d; ?>
<?php unset($__attributesOriginalf4c8ecf26ef77d4de25edf56eae3a34d); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalf4c8ecf26ef77d4de25edf56eae3a34d)): ?>
<?php $component = $__componentOriginalf4c8ecf26ef77d4de25edf56eae3a34d; ?>
<?php unset($__componentOriginalf4c8ecf26ef77d4de25edf56eae3a34d); ?>
<?php endif; ?>
                        <div class="flex items-center gap-2 pb-1">
                            <button type="submit" class="rounded-xl bg-success-600 px-4 py-2 text-sm font-semibold text-white transition-weightless hover:bg-success-700">
                                <?php echo e(__('candidates.filters.apply')); ?>

                            </button>
                            <a href="<?php echo e(route('candidates.index', array_filter(['company_id' => $selectedCompanyId]))); ?>" class="rounded-xl border border-aura-300/40 bg-white/80 px-4 py-2 text-center text-sm font-semibold text-slate-700 transition-weightless hover:bg-white">
                                <?php echo e(__('candidates.filters.reset')); ?>

                            </a>
                        </div>
                    </form>
                </div>
             <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalf225215015f2c1140bc03ba841300625)): ?>
<?php $attributes = $__attributesOriginalf225215015f2c1140bc03ba841300625; ?>
<?php unset($__attributesOriginalf225215015f2c1140bc03ba841300625); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalf225215015f2c1140bc03ba841300625)): ?>
<?php $component = $__componentOriginalf225215015f2c1140bc03ba841300625; ?>
<?php unset($__componentOriginalf225215015f2c1140bc03ba841300625); ?>
<?php endif; ?>

            <?php if(!request()->filled('job_id') && !request()->filled('q')): ?>
                <!-- JOB CARDS GRID -->
                <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    <?php $__empty_1 = true; $__currentLoopData = $jobs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $job): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <a href="<?php echo e(route('candidates.index', array_merge(request()->query(), ['job_id' => $job->id]))); ?>" class="group block rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition hover:border-aura-400 hover:shadow-md">
                            <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-aura-50 text-aura-600 group-hover:bg-aura-100">
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 0 0 .75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 0 0-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0 1 12 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 0 1-.673-.38m0 0A2.18 2.18 0 0 1 3 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 0 1 3.413-.387m7.5 0V5.25A2.25 2.25 0 0 0 13.5 3h-3a2.25 2.25 0 0 0-2.25 2.25v.894m7.5 0a48.667 48.667 0 0 0-7.5 0M12 12.75h.008v.008H12v-.008Z" />
                                </svg>
                            </div>
                            <h3 class="truncate text-lg font-bold text-slate-800"><?php echo e($job->title); ?></h3>
                            <p class="mt-1 text-sm font-medium text-slate-500">
                                <?php echo e($job->applications_count); ?> candidat<?php echo e($job->applications_count !== 1 ? 's' : ''); ?>

                            </p>
                        </a>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <div class="col-span-full">
                            <?php if (isset($component)) { $__componentOriginal074a021b9d42f490272b5eefda63257c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal074a021b9d42f490272b5eefda63257c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.empty-state','data' => ['title' => __('candidates.list.empty_title'),'message' => __('candidates.list.empty_message')]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('empty-state'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('candidates.list.empty_title')),'message' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('candidates.list.empty_message'))]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal074a021b9d42f490272b5eefda63257c)): ?>
<?php $attributes = $__attributesOriginal074a021b9d42f490272b5eefda63257c; ?>
<?php unset($__attributesOriginal074a021b9d42f490272b5eefda63257c); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal074a021b9d42f490272b5eefda63257c)): ?>
<?php $component = $__componentOriginal074a021b9d42f490272b5eefda63257c; ?>
<?php unset($__componentOriginal074a021b9d42f490272b5eefda63257c); ?>
<?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <!-- RANKED CANDIDATES LIST -->
                <?php if (isset($component)) { $__componentOriginalf225215015f2c1140bc03ba841300625 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalf225215015f2c1140bc03ba841300625 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.glass-card','data' => ['class' => 'p-0 overflow-hidden']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('glass-card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'p-0 overflow-hidden']); ?>
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
                                <?php $__empty_1 = true; $__currentLoopData = $applications; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $app): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                    <?php
                                        $cv = $app->cvParsingResults->first();
                                        $school = $cv && !empty($cv->education_entries_json) ? ($cv->education_entries_json[0]['institution_name'] ?? $cv->education_entries_json[0]['degree_name'] ?? '-') : '-';
                                        $experience = $cv && $cv->total_years_experience !== null ? number_format((float) $cv->total_years_experience, 1) . ' ans' : '-';
                                        $lastCompany = $cv && !empty($cv->experience_entries_json) ? ($cv->experience_entries_json[0]['company_name'] ?? $cv->experience_entries_json[0]['job_title'] ?? '-') : '-';
                                        $score = $app->global_match_score !== null ? number_format((float) $app->global_match_score, 1) : '-';
                                    ?>
                                    <tr class="transition hover:bg-slate-50">
                                        <td class="whitespace-nowrap px-6 py-4 font-medium text-slate-900">
                                            <?php echo e($app->candidate?->full_name ?? 'Inconnu'); ?>

                                        </td>
                                        <td class="whitespace-nowrap px-6 py-4">
                                            <?php if($score !== '-'): ?>
                                                <span class="inline-flex items-center rounded-full bg-primary-50 px-2.5 py-1 text-xs font-bold text-primary-700 border border-primary-100">
                                                    <?php echo e($score); ?>%
                                                </span>
                                            <?php else: ?>
                                                <span class="text-slate-400">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 truncate max-w-[200px]" title="<?php echo e($school); ?>">
                                            <?php echo e($school); ?>

                                        </td>
                                        <td class="whitespace-nowrap px-6 py-4">
                                            <?php echo e($experience); ?>

                                        </td>
                                        <td class="px-6 py-4 truncate max-w-[200px]" title="<?php echo e($lastCompany); ?>">
                                            <?php echo e($lastCompany); ?>

                                        </td>
                                        <td class="whitespace-nowrap px-6 py-4 text-right">
                                            <a href="<?php echo e(route('candidates.index', array_merge(request()->query(), ['application_id' => $app->id]))); ?>" class="inline-flex items-center rounded-lg bg-white px-3 py-1.5 text-xs font-semibold text-aura-700 border border-aura-200 shadow-sm transition hover:bg-aura-50">
                                                Voir le profil
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                    <tr>
                                        <td colspan="6" class="px-6 py-8 text-center text-slate-500">
                                            Aucun candidat trouvÃ© pour ce filtre.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if($applications instanceof \Illuminate\Pagination\LengthAwarePaginator && $applications->hasPages()): ?>
                        <div class="border-t border-slate-200 bg-white px-6 py-4">
                            <?php echo e($applications->links()); ?>

                        </div>
                    <?php endif; ?>
                 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalf225215015f2c1140bc03ba841300625)): ?>
<?php $attributes = $__attributesOriginalf225215015f2c1140bc03ba841300625; ?>
<?php unset($__attributesOriginalf225215015f2c1140bc03ba841300625); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalf225215015f2c1140bc03ba841300625)): ?>
<?php $component = $__componentOriginalf225215015f2c1140bc03ba841300625; ?>
<?php unset($__componentOriginalf225215015f2c1140bc03ba841300625); ?>
<?php endif; ?>
            <?php endif; ?>

        <?php endif; ?>
    </div>

    <?php if(! $requiresCompanySelection): ?>
        <?php echo $__env->make('candidates.partials.recruiter-assistant', [
            'selectedCompanyId' => $selectedCompanyId,
            'selectedApplication' => $selectedApplication,
            'applications' => $applications,
        ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php endif; ?>

    <?php if(! $requiresCompanySelection): ?>
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
                        const isInPerson = locationSelect.value === <?php echo json_encode(\App\Models\Interview::LOCATION_IN_PERSON, 15, 512) ?>;

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
    <?php endif; ?>
    
    <?php endif; ?>
 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal4169ced201253cf4850c555ce041228e)): ?>
<?php $attributes = $__attributesOriginal4169ced201253cf4850c555ce041228e; ?>
<?php unset($__attributesOriginal4169ced201253cf4850c555ce041228e); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal4169ced201253cf4850c555ce041228e)): ?>
<?php $component = $__componentOriginal4169ced201253cf4850c555ce041228e; ?>
<?php unset($__componentOriginal4169ced201253cf4850c555ce041228e); ?>
<?php endif; ?><?php /**PATH C:\Users\ADMIN\Desktop\CarriereOS (5)\CarriereOS\resources\views/candidates/index.blade.php ENDPATH**/ ?>