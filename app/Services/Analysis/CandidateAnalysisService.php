<?php

namespace App\Services\Analysis;

use App\Models\Application;
use App\Models\ApplicationScoring;
use App\Models\CvParsingResult;
use App\Models\Job;
use App\Models\SjtResponse;
use App\Models\StrategyLabAiSummary;
use App\Models\UnifiedInterviewReport;
use App\Support\Jobs\JobDescriptionContentRenderer;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CandidateAnalysisService
{
    public function __construct(
        private readonly JobDescriptionContentRenderer $descriptionRenderer
    ) {
    }

    public const ANALYSIS_PENDING = 'pending_analysis';
    public const ANALYSIS_PARTIAL = 'partial_ready';
    public const ANALYSIS_READY = 'ready';
    public const ANALYSIS_INVALID_CV = 'invalid_cv';

    public const SOURCE_STATUS_NOT_APPROPRIATE_CV = 'not_appropriate_cv';

    public const FACTOR_SKILLS_MATCH = 'skills_match';
    public const FACTOR_EXPERIENCE_MATCH = 'experience_match';
    public const FACTOR_EDUCATION_MATCH = 'education_match';
    public const FACTOR_CERTIFICATIONS = 'certifications';
    public const FACTOR_LANGUAGE_MATCH = 'language_match';
    public const FACTOR_ASSESSMENT = 'assessment_performance';
    public const FACTOR_INTERVIEW = 'interview_performance';
    public const FACTOR_STRATEGY_LAB = 'strategy_lab';
    public const FACTOR_CULTURE_FIT = 'culture_fit';

    private const REQUIRED_FACTORS = [
        self::FACTOR_SKILLS_MATCH,
        self::FACTOR_EXPERIENCE_MATCH,
        self::FACTOR_EDUCATION_MATCH,
        self::FACTOR_CERTIFICATIONS,
        self::FACTOR_LANGUAGE_MATCH,
        self::FACTOR_ASSESSMENT,
        self::FACTOR_INTERVIEW,
        self::FACTOR_STRATEGY_LAB,
        self::FACTOR_CULTURE_FIT,
    ];

    private const DEFAULT_WEIGHTING = [
        self::FACTOR_SKILLS_MATCH => 20,
        self::FACTOR_EXPERIENCE_MATCH => 15,
        self::FACTOR_EDUCATION_MATCH => 10,
        self::FACTOR_CERTIFICATIONS => 8,
        self::FACTOR_LANGUAGE_MATCH => 8,
        self::FACTOR_ASSESSMENT => 10,
        self::FACTOR_INTERVIEW => 10,
        self::FACTOR_STRATEGY_LAB => 10,
        self::FACTOR_CULTURE_FIT => 9,
    ];

    private const SKILL_KEYWORDS = [
        'php', 'laravel', 'symfony', 'python', 'django', 'flask', 'java', 'spring',
        'kotlin', 'scala', 'javascript', 'typescript', 'react', 'vue', 'angular',
        'node', 'nestjs', 'express', 'go', 'golang', 'rust', 'c++', 'c#', '.net',
        'sql', 'postgresql', 'mysql', 'mongodb', 'redis', 'elasticsearch',
        'docker', 'kubernetes', 'terraform', 'aws', 'azure', 'gcp',
        'graphql', 'rest', 'microservices', 'devops', 'ci/cd', 'git',
        'tableau', 'power bi', 'spark', 'hadoop', 'pandas', 'numpy',
        'machine learning', 'data science', 'nlp', 'computer vision',
        'figma', 'sketch', 'photoshop', 'illustrator', 'product management',
    ];

    private const LANGUAGE_KEYWORDS = [
        'english', 'french', 'german', 'spanish', 'italian', 'arabic',
        'urdu', 'hindi', 'mandarin', 'chinese', 'japanese', 'korean',
        'portuguese', 'dutch', 'turkish', 'russian',
    ];

    private const CERTIFICATION_KEYWORDS = [
        'aws', 'azure', 'gcp', 'pmp', 'scrum', 'cissp', 'ccna', 'cpa',
        'cfa', 'google analytics', 'salesforce', 'itil', 'iso',
    ];

    private const SOFT_SKILL_KEYWORDS = [
        'communication', 'leadership', 'collaboration', 'ownership', 'adaptability',
        'problem solving', 'stakeholder management', 'mentoring', 'teamwork',
        'critical thinking', 'time management', 'creativity', 'resilience',
        'attention to detail', 'decision making', 'empathy', 'negotiation',
        'presentation', 'facilitation', 'analytical thinking',
    ];

    /**
     * @return array<string, int>
     */
    public function defaultWeighting(): array
    {
        return self::DEFAULT_WEIGHTING;
    }

    /**
     * @param array<string, mixed>|null $weighting
     * @return array<string, int>
     */
    public function normalizeWeighting(?array $weighting): array
    {
        $raw = is_array($weighting) ? $weighting : [];
        $normalized = [];

        foreach (self::REQUIRED_FACTORS as $factorKey) {
            $value = data_get($raw, $factorKey);
            if (! is_numeric($value)) {
                $value = null;
            }
            $normalized[$factorKey] = is_numeric($value) ? max(0, min(100, (int) round((float) $value))) : 0;
        }

        if (
            $normalized[self::FACTOR_SKILLS_MATCH] === 0
            && $normalized[self::FACTOR_EXPERIENCE_MATCH] === 0
            && $normalized[self::FACTOR_CULTURE_FIT] === 0
            && $normalized[self::FACTOR_ASSESSMENT] === 0
        ) {
            $legacySkill = data_get($raw, 'skill');
            $legacyExperience = data_get($raw, 'experience');
            $legacyCulture = data_get($raw, 'culture');
            $legacyPotential = data_get($raw, 'potential');

            if (is_numeric($legacySkill)) {
                $normalized[self::FACTOR_SKILLS_MATCH] = max(0, min(100, (int) $legacySkill));
            }
            if (is_numeric($legacyExperience)) {
                $normalized[self::FACTOR_EXPERIENCE_MATCH] = max(0, min(100, (int) $legacyExperience));
            }
            if (is_numeric($legacyCulture)) {
                $normalized[self::FACTOR_CULTURE_FIT] = max(0, min(100, (int) $legacyCulture));
            }
            if (is_numeric($legacyPotential)) {
                $normalized[self::FACTOR_ASSESSMENT] = max(0, min(100, (int) $legacyPotential));
            }
        }

        $total = array_sum($normalized);
        if ($total <= 0) {
            return self::DEFAULT_WEIGHTING;
        }

        if ($total === 100) {
            return $normalized;
        }

        $scaled = [];
        $accumulator = 0;
        $keys = array_values(self::REQUIRED_FACTORS);
        $lastKey = (string) end($keys);
        reset($keys);

        foreach ($keys as $factorKey) {
            if ($factorKey === $lastKey) {
                $scaled[$factorKey] = max(0, 100 - $accumulator);
                continue;
            }

            $value = (int) round(($normalized[$factorKey] / $total) * 100);
            $scaled[$factorKey] = max(0, min(100, $value));
            $accumulator += $scaled[$factorKey];
        }

        return $scaled;
    }

    public function recomputeForApplicationId(string $companyId, string $applicationId): ?ApplicationScoring
    {
        $application = Application::withoutGlobalScopes()
            ->with([
                'candidate:id,full_name,email,location',
                'job:id,company_id,title,description_html,salary_budget_max',
                'job.weightingConfig:id,job_id,weighting_json',
                'job.descriptionBlocks:id,job_id,block_type,block_content_json,display_order',
                'job.persona:id,job_id,persona_json',
                'unifiedInterviewReport:id,application_id,match_percentage,ocean_openness,ocean_conscientiousness,ocean_extraversion,ocean_agreeableness,ocean_neuroticism,generic_motivation,salary_fit_score',
                'strategyLabAiSummary:id,application_id,executive_summary_text,strengths_json,weaknesses_json,creativity_score,overall_recommendation',
                'interviews:id,company_id,application_id',
                'interviews.feedback:id,company_id,interview_id,ratings_json,recommendation,created_at',
            ])
            ->where('company_id', $companyId)
            ->where('id', $applicationId)
            ->first();

        if (! $application instanceof Application) {
            return null;
        }

        return $this->recomputeForApplication($application);
    }

    public function recomputeForApplication(Application $application): ?ApplicationScoring
    {
        $companyId = (string) $application->company_id;
        $applicationId = (string) $application->id;
        $jobId = (string) $application->job_id;
        if ($companyId === '' || $applicationId === '' || $jobId === '') {
            return null;
        }

        $application->loadMissing([
            'job:id,company_id,title,description_html,salary_budget_max',
            'job.weightingConfig:id,job_id,weighting_json',
            'job.descriptionBlocks:id,job_id,block_type,block_content_json,display_order',
            'job.persona:id,job_id,persona_json',
            'unifiedInterviewReport:id,application_id,match_percentage,ocean_openness,ocean_conscientiousness,ocean_extraversion,ocean_agreeableness,ocean_neuroticism,generic_motivation,salary_fit_score',
            'strategyLabAiSummary:id,application_id,executive_summary_text,strengths_json,weaknesses_json,creativity_score,overall_recommendation',
            'interviews:id,company_id,application_id',
            'interviews.feedback:id,company_id,interview_id,ratings_json,recommendation,created_at',
        ]);

        $job = $application->job;
        if (! $job instanceof Job) {
            return null;
        }

        $latestCv = $this->latestCvParseForApplication($companyId, $applicationId);
        $hasUsableCvEvidence = $this->hasUsableCvEvidence($latestCv);
        $weighting = $this->normalizeWeighting((array) ($job->weightingConfig?->weighting_json ?? null));
        $jobContext = $this->buildJobContext($job);

        $skillsComponent = $this->scoreSkillsComponent($latestCv, $jobContext);
        $softSkillsEvaluation = $this->scoreSoftSkillsEvaluation($latestCv, $jobContext);
        $experienceComponent = $this->scoreExperienceComponent($latestCv, $jobContext);
        $educationComponent = $this->scoreEducationComponent($latestCv, $jobContext);
        $certificationsComponent = $this->scoreCertificationsComponent($latestCv, $jobContext);
        $languageComponent = $this->scoreLanguageComponent($latestCv, $jobContext);
        $assessmentComponent = $this->scoreAssessmentComponent($companyId, $applicationId);
        $interviewComponent = $this->scoreInterviewComponent($application);
        $strategyLabComponent = $this->scoreStrategyLabComponent($application->strategyLabAiSummary);
        $cultureComponent = $this->scoreCultureFitComponent($application->unifiedInterviewReport);
        $hasInvalidCv = $latestCv instanceof CvParsingResult && ! $hasUsableCvEvidence;

        $components = [
            self::FACTOR_SKILLS_MATCH => $skillsComponent,
            self::FACTOR_EXPERIENCE_MATCH => $experienceComponent,
            self::FACTOR_EDUCATION_MATCH => $educationComponent,
            self::FACTOR_CERTIFICATIONS => $certificationsComponent,
            self::FACTOR_LANGUAGE_MATCH => $languageComponent,
            self::FACTOR_ASSESSMENT => $assessmentComponent,
            self::FACTOR_INTERVIEW => $interviewComponent,
            self::FACTOR_STRATEGY_LAB => $strategyLabComponent,
            self::FACTOR_CULTURE_FIT => $cultureComponent,
        ];

        $scoreAccumulator = 0.0;
        $weightAccumulator = 0.0;
        foreach ($components as $factorKey => $component) {
            $score = data_get($component, 'score');
            if (! is_numeric($score)) {
                continue;
            }

            $weight = (int) ($weighting[$factorKey] ?? 0);
            if ($weight <= 0) {
                continue;
            }

            $scoreAccumulator += ((float) $score) * $weight;
            $weightAccumulator += $weight;
        }

        $globalScore = $weightAccumulator > 0
            ? round(max(0, min(100, $scoreAccumulator / $weightAccumulator)), 2)
            : 0.0;

        $allScores = collect($components)->pluck('score');
        $hasAnyReady = $allScores->contains(static fn (mixed $score): bool => is_numeric($score));
        $hasAnyMissing = $allScores->contains(static fn (mixed $score): bool => ! is_numeric($score));
        $analysisStatus = match (true) {
            $hasInvalidCv => self::ANALYSIS_INVALID_CV,
            ! $hasAnyReady => self::ANALYSIS_PENDING,
            $hasAnyMissing => self::ANALYSIS_PARTIAL,
            default => self::ANALYSIS_READY,
        };

        if ($hasInvalidCv) {
            $globalScore = 0.0;
        }

        $strengths = $this->buildStrengths($components, $application->strategyLabAiSummary);
        $weaknesses = $this->buildWeaknesses($components, $application->strategyLabAiSummary);
        $recommendation = $this->buildOverallRecommendation($globalScore, $analysisStatus);

        $acquiredSkills = collect((array) data_get($skillsComponent, 'matched', []))
            ->map(static fn (mixed $value): string => trim((string) $value))
            ->filter(static fn (string $value): bool => $value !== '')
            ->values()
            ->all();
        $missingSkills = collect((array) data_get($skillsComponent, 'missing', []))
            ->map(static fn (mixed $value): string => trim((string) $value))
            ->filter(static fn (string $value): bool => $value !== '')
            ->values()
            ->all();
        $evaluationModel = $this->buildEvaluationModel(
            latestCv: $latestCv,
            jobContext: $jobContext,
            skillsComponent: $skillsComponent,
            softSkillsEvaluation: $softSkillsEvaluation,
            experienceComponent: $experienceComponent
        );

        $sourceStatuses = [
            'cv' => $hasInvalidCv
                ? self::SOURCE_STATUS_NOT_APPROPRIATE_CV
                : ($hasUsableCvEvidence ? 'ready' : 'pending_input'),
            'assessment' => (string) data_get($assessmentComponent, 'status', 'pending_input'),
            'interview' => (string) data_get($interviewComponent, 'status', 'pending_input'),
            'strategy_lab' => (string) data_get($strategyLabComponent, 'status', 'unavailable'),
            'ocean' => $this->resolveOceanStatus($latestCv, $application->unifiedInterviewReport),
        ];

        $componentScores = collect($components)
            ->map(function (array $component, string $factorKey) use ($weighting): array {
                $score = data_get($component, 'score');

                return [
                    'label' => Str::headline(str_replace('_', ' ', $factorKey)),
                    'score' => is_numeric($score) ? round((float) $score, 2) : null,
                    'weight' => (int) ($weighting[$factorKey] ?? 0),
                    'status' => (string) data_get($component, 'status', 'pending_input'),
                    'note' => (string) data_get($component, 'note', ''),
                ];
            })
            ->all();

        $xaiSummary = $this->buildXaiSummary(
            score: $globalScore,
            analysisStatus: $analysisStatus,
            strengths: $strengths,
            weaknesses: $weaknesses
        );

        $scoring = ApplicationScoring::withoutGlobalScopes()->updateOrCreate(
            ['application_id' => $applicationId],
            [
                'company_id' => $companyId,
                'global_match_score' => $globalScore,
                'vrin_json' => [
                    'acquired_skills' => $acquiredSkills,
                    'missing_skills' => $missingSkills,
                    'job_required_skills' => (array) data_get($skillsComponent, 'required', []),
                    'evaluation_model' => $evaluationModel,
                ],
                'component_scores_json' => $componentScores,
                'source_status_json' => $sourceStatuses,
                'strengths_json' => $strengths,
                'weaknesses_json' => $weaknesses,
                'xai_summary' => $xaiSummary,
                'overall_recommendation' => $recommendation,
                'analysis_status' => $analysisStatus,
                'updated_at' => now(),
            ]
        );

        $this->recomputeRankingForJob($companyId, $jobId);

        return $scoring->fresh();
    }

    public function recomputeForJob(string $companyId, string $jobId): void
    {
        $applications = Application::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('job_id', $jobId)
            ->whereIn('status', [Application::STATUS_ACTIVE, Application::STATUS_HIRED])
            ->get(['id', 'company_id', 'job_id', 'status']);

        foreach ($applications as $application) {
            $this->recomputeForApplicationId($companyId, (string) $application->id);
        }

        $this->recomputeRankingForJob($companyId, $jobId);
    }

    /**
     * @return array{
     *   job_id: string,
     *   rows: array<int, array<string, mixed>>,
     *   top_three: array<int, array<string, mixed>>,
     *   score_distribution: array<int, array{label: string, count: int}>,
     *   fairness: array<string, array<string, mixed>>
     * }
     */
    public function jobRankingSnapshot(string $companyId, string $jobId, int $limit = 80, bool $synchronize = true): array
    {
        if ($synchronize) {
            $this->recomputeForJob($companyId, $jobId);
        }

        $applications = Application::withoutGlobalScopes()
            ->with([
                'candidate:id,full_name',
                'currentStage:id,stage_label,stage_key',
                'scoring:id,application_id,global_match_score,component_scores_json,analysis_status,ranking_position,is_top_three',
            ])
            ->where('company_id', $companyId)
            ->where('job_id', $jobId)
            ->whereIn('status', [Application::STATUS_ACTIVE, Application::STATUS_HIRED])
            ->orderBy('created_at')
            ->get(['id', 'candidate_id', 'current_stage_id', 'status']);

        $rows = $applications
            ->map(function (Application $application): array {
                $scoring = $application->scoring;
                $componentScores = is_array($scoring?->component_scores_json)
                    ? $scoring->component_scores_json
                    : [];
                $skillsScore = data_get($componentScores, self::FACTOR_SKILLS_MATCH.'.score');
                $experienceScore = data_get($componentScores, self::FACTOR_EXPERIENCE_MATCH.'.score');
                $educationScore = data_get($componentScores, self::FACTOR_EDUCATION_MATCH.'.score');

                return [
                    'application_id' => (string) $application->id,
                    'candidate_name' => (string) ($application->candidate?->full_name ?? __('candidates.detail.not_available')),
                    'status' => (string) $application->status,
                    'stage_label' => (string) ($application->currentStage?->stage_label ?? __('candidates.detail.not_available')),
                    'total_score' => is_numeric($scoring?->global_match_score)
                        ? round((float) $scoring->global_match_score, 2)
                        : null,
                    'ranking_position' => is_numeric($scoring?->ranking_position)
                        ? (int) $scoring->ranking_position
                        : null,
                    'is_top_three' => (bool) ($scoring?->is_top_three ?? false),
                    'analysis_status' => (string) ($scoring?->analysis_status ?? self::ANALYSIS_PENDING),
                    'indicators' => [
                        'skills' => is_numeric($skillsScore) ? round((float) $skillsScore, 1) : null,
                        'experience' => is_numeric($experienceScore) ? round((float) $experienceScore, 1) : null,
                        'education' => is_numeric($educationScore) ? round((float) $educationScore, 1) : null,
                    ],
                ];
            })
            ->sort(function (array $a, array $b): int {
                $rankA = (int) ($a['ranking_position'] ?? PHP_INT_MAX);
                $rankB = (int) ($b['ranking_position'] ?? PHP_INT_MAX);

                if ($rankA !== $rankB) {
                    return $rankA <=> $rankB;
                }

                $scoreA = is_numeric($a['total_score']) ? (float) $a['total_score'] : -1.0;
                $scoreB = is_numeric($b['total_score']) ? (float) $b['total_score'] : -1.0;

                return $scoreB <=> $scoreA;
            })
            ->take(max(1, $limit))
            ->values();

        $topThree = $rows
            ->filter(static fn (array $row): bool => (int) ($row['ranking_position'] ?? 0) > 0 && (int) ($row['ranking_position'] ?? 0) <= 3)
            ->take(3)
            ->values()
            ->all();

        $distribution = $this->scoreDistribution(
            $rows
                ->map(static fn (array $row): ?float => is_numeric($row['total_score']) ? (float) $row['total_score'] : null)
                ->filter(static fn (?float $score): bool => $score !== null)
                ->values()
        );

        $fairness = $this->fairnessSummary($companyId, $applications);

        return [
            'job_id' => $jobId,
            'rows' => $rows->values()->all(),
            'top_three' => $topThree,
            'score_distribution' => $distribution,
            'fairness' => $fairness,
        ];
    }

    /**
     * @param array<int, string> $jobIds
     * @return Collection<int, array{job_id: string, job_title: string, top_three: array<int, array<string, mixed>>}>
     */
    public function topCandidatesByJobs(string $companyId, array $jobIds, int $top = 3): Collection
    {
        $jobs = Job::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereIn('id', $jobIds)
            ->orderBy('title')
            ->get(['id', 'title']);

        return $jobs->map(function (Job $job) use ($companyId, $top): array {
            $snapshot = $this->jobRankingSnapshot(
                companyId: $companyId,
                jobId: (string) $job->id,
                limit: 24,
                synchronize: true
            );

            return [
                'job_id' => (string) $job->id,
                'job_title' => (string) $job->title,
                'top_three' => collect((array) ($snapshot['top_three'] ?? []))
                    ->take(max(1, $top))
                    ->values()
                    ->all(),
            ];
        })->values();
    }

    private function latestCvParseForApplication(string $companyId, string $applicationId): ?CvParsingResult
    {
        return CvParsingResult::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('application_id', $applicationId)
            ->orderByDesc('updated_at')
            ->orderByDesc('created_at')
            ->first();
    }

    /**
     * @return array{
     *   text: string,
     *   required_skills: array<int, string>,
     *   required_languages: array<int, string>,
     *   required_certifications: array<int, string>,
     *   required_years_experience: ?float,
     *   required_education_level: ?string
     * }
     */
    private function buildJobContext(Job $job): array
    {
        $persona = is_array($job->persona?->persona_json) ? $job->persona?->persona_json : [];
        $personaText = collect([
            data_get($persona, 'persona_summary', ''),
            implode(' ', (array) data_get($persona, 'must_haves', [])),
            implode(' ', (array) data_get($persona, 'ideal_traits', [])),
        ])->implode("\n");

        $descriptionText = $this->descriptionRenderer->renderPlainText($job);
        $text = trim($job->title."\n".$descriptionText."\n".$personaText);
        $requiredSkills = $this->extractSkillKeywords($text);
        $requiredLanguages = $this->extractLanguageKeywords($text);
        $requiredCertifications = $this->extractCertificationKeywords($text);

        return [
            'text' => $text,
            'description_text' => $descriptionText,
            'required_skills' => $requiredSkills,
            'required_soft_skills' => $this->extractSoftSkillKeywords($text),
            'required_languages' => $requiredLanguages,
            'required_certifications' => $requiredCertifications,
            'required_years_experience' => $this->extractRequiredYearsFromText($text),
            'required_education_level' => $this->extractRequiredEducationLevel($text),
        ];
    }

    /**
     * @param array<string, mixed> $jobContext
     * @return array<string, mixed>
     */
    private function scoreSkillsComponent(?CvParsingResult $latestCv, array $jobContext): array
    {
        if (! $latestCv instanceof CvParsingResult) {
            return [
                'score' => null,
                'status' => 'pending_input',
                'note' => 'Awaiting CV parsing output.',
                'required' => [],
                'matched' => [],
                'missing' => [],
            ];
        }

        if (! $this->hasUsableCvEvidence($latestCv)) {
            return [
                'score' => null,
                'status' => 'pending_input',
                'note' => 'Uploaded document does not contain enough resume evidence for skill scoring.',
                'required' => [],
                'matched' => [],
                'missing' => [],
            ];
        }

        $required = collect((array) data_get($jobContext, 'required_skills', []))
            ->map(fn (mixed $value): string => Str::lower(trim((string) $value)))
            ->filter(static fn (string $value): bool => $value !== '')
            ->unique()
            ->values();

        $candidateSkills = collect(array_merge(
            (array) ($latestCv->hard_skills_json ?? []),
            (array) ($latestCv->tools_frameworks_json ?? []),
            (array) ($latestCv->keywords_json ?? [])
        ))
            ->map(fn (mixed $value): string => Str::lower(trim((string) $value)))
            ->filter(static fn (string $value): bool => $value !== '')
            ->unique()
            ->values();

        if ($candidateSkills->isEmpty() && $required->isEmpty()) {
            return [
                'score' => null,
                'status' => 'insufficient_data',
                'note' => 'No job skill requirements or candidate skill signals found.',
                'required' => [],
                'matched' => [],
                'missing' => [],
            ];
        }

        if ($required->isEmpty()) {
            return [
                'score' => $candidateSkills->isEmpty() ? 45.0 : 70.0,
                'status' => 'ready',
                'note' => 'No explicit skill constraints in job description; using candidate profile richness.',
                'required' => [],
                'matched' => $candidateSkills->take(8)->values()->all(),
                'missing' => [],
            ];
        }

        $matched = $required
            ->filter(fn (string $requirement): bool => $this->containsKeyword($candidateSkills, $requirement))
            ->values();
        $missing = $required
            ->filter(fn (string $requirement): bool => ! $this->containsKeyword($candidateSkills, $requirement))
            ->values();

        $score = round(($matched->count() / max(1, $required->count())) * 100, 2);

        return [
            'score' => $score,
            'status' => 'ready',
            'note' => 'Skill overlap measured against detected job requirements.',
            'required' => $required->values()->all(),
            'matched' => $matched->values()->all(),
            'missing' => $missing->values()->all(),
        ];
    }

    /**
     * @param array<string, mixed> $jobContext
     * @return array<string, mixed>
     */
    private function scoreSoftSkillsEvaluation(?CvParsingResult $latestCv, array $jobContext): array
    {
        if (! $latestCv instanceof CvParsingResult) {
            return [
                'score' => null,
                'status' => 'pending_input',
                'required' => [],
                'detected' => [],
                'matched' => [],
                'missing' => [],
            ];
        }

        if (! $this->hasUsableCvEvidence($latestCv)) {
            return [
                'score' => null,
                'status' => 'pending_input',
                'required' => [],
                'detected' => [],
                'matched' => [],
                'missing' => [],
            ];
        }

        $required = collect((array) data_get($jobContext, 'required_soft_skills', []))
            ->map(fn (mixed $value): string => Str::lower(trim((string) $value)))
            ->filter(static fn (string $value): bool => $value !== '')
            ->unique()
            ->values();

        $detected = collect((array) ($latestCv->soft_skills_json ?? []))
            ->map(fn (mixed $value): string => Str::lower(trim((string) $value)))
            ->filter(static fn (string $value): bool => $value !== '')
            ->unique()
            ->values();

        if ($required->isEmpty()) {
            return [
                'score' => $detected->isEmpty() ? 45.0 : min(100.0, 55.0 + ($detected->count() * 5.0)),
                'status' => 'ready',
                'required' => [],
                'detected' => $detected->take(8)->values()->all(),
                'matched' => $detected->take(8)->values()->all(),
                'missing' => [],
            ];
        }

        $matched = $required
            ->filter(fn (string $requirement): bool => $this->containsKeyword($detected, $requirement))
            ->values();
        $missing = $required
            ->filter(fn (string $requirement): bool => ! $this->containsKeyword($detected, $requirement))
            ->values();

        return [
            'score' => round(($matched->count() / max(1, $required->count())) * 100, 2),
            'status' => 'ready',
            'required' => $required->values()->all(),
            'detected' => $detected->take(8)->values()->all(),
            'matched' => $matched->values()->all(),
            'missing' => $missing->values()->all(),
        ];
    }

    /**
     * @param array<string, mixed> $jobContext
     * @return array<string, mixed>
     */
    private function scoreExperienceComponent(?CvParsingResult $latestCv, array $jobContext): array
    {
        if (! $latestCv instanceof CvParsingResult) {
            return [
                'score' => null,
                'status' => 'pending_input',
                'note' => 'Awaiting CV parsing output.',
            ];
        }

        if (! $this->hasUsableCvEvidence($latestCv)) {
            return [
                'score' => null,
                'status' => 'pending_input',
                'note' => 'Uploaded document does not contain enough resume evidence for experience scoring.',
            ];
        }

        $candidateYears = is_numeric($latestCv->total_years_experience)
            ? (float) $latestCv->total_years_experience
            : $this->estimateExperienceYearsFromEntries((array) ($latestCv->experience_entries_json ?? []));

        if (! is_numeric($candidateYears)) {
            return [
                'score' => null,
                'status' => 'insufficient_data',
                'note' => 'Experience duration could not be inferred.',
            ];
        }

        $requiredYears = data_get($jobContext, 'required_years_experience');
        if (! is_numeric($requiredYears) || (float) $requiredYears <= 0) {
            return [
                'score' => round(max(0, min(100, (((float) $candidateYears / 10) * 100))), 2),
                'status' => 'ready',
                'note' => 'No explicit years requirement found; normalized by total experience.',
            ];
        }

        $score = round(max(0, min(100, (((float) $candidateYears / (float) $requiredYears) * 100))), 2);

        return [
            'score' => $score,
            'status' => 'ready',
            'note' => 'Compared candidate years with detected job experience requirement.',
        ];
    }

    /**
     * @param array<string, mixed> $jobContext
     * @return array<string, mixed>
     */
    private function scoreEducationComponent(?CvParsingResult $latestCv, array $jobContext): array
    {
        if (! $latestCv instanceof CvParsingResult) {
            return [
                'score' => null,
                'status' => 'pending_input',
                'note' => 'Awaiting CV parsing output.',
            ];
        }

        if (! $this->hasUsableCvEvidence($latestCv)) {
            return [
                'score' => null,
                'status' => 'pending_input',
                'note' => 'Uploaded document does not contain enough resume evidence for education scoring.',
            ];
        }

        $educationEntries = collect((array) ($latestCv->education_entries_json ?? []))
            ->filter(static fn (mixed $entry): bool => is_array($entry))
            ->values();

        if ($educationEntries->isEmpty()) {
            return [
                'score' => 35.0,
                'status' => 'insufficient_data',
                'note' => 'No structured education entries found.',
            ];
        }

        $candidateLevel = $this->highestEducationLevel($educationEntries);
        $requiredLevel = data_get($jobContext, 'required_education_level');

        if (! is_string($requiredLevel) || $requiredLevel === '') {
            return [
                'score' => 70.0,
                'status' => 'ready',
                'note' => 'No explicit education level requirement found.',
            ];
        }

        $candidateRank = $this->educationRank($candidateLevel);
        $requiredRank = $this->educationRank($requiredLevel);

        if ($candidateRank <= 0 || $requiredRank <= 0) {
            return [
                'score' => 55.0,
                'status' => 'insufficient_data',
                'note' => 'Education level comparison is inconclusive.',
            ];
        }

        $ratio = $candidateRank / $requiredRank;
        $score = $ratio >= 1 ? 100.0 : round(max(0, min(100, $ratio * 100)), 2);

        return [
            'score' => $score,
            'status' => 'ready',
            'note' => 'Education level compared with detected role requirement.',
        ];
    }

    /**
     * @param array<string, mixed> $jobContext
     * @return array<string, mixed>
     */
    private function scoreCertificationsComponent(?CvParsingResult $latestCv, array $jobContext): array
    {
        if (! $latestCv instanceof CvParsingResult) {
            return [
                'score' => null,
                'status' => 'pending_input',
                'note' => 'Awaiting CV parsing output.',
            ];
        }

        if (! $this->hasUsableCvEvidence($latestCv)) {
            return [
                'score' => null,
                'status' => 'pending_input',
                'note' => 'Uploaded document does not contain enough resume evidence for certification scoring.',
            ];
        }

        $required = collect((array) data_get($jobContext, 'required_certifications', []))
            ->map(fn (mixed $value): string => Str::lower(trim((string) $value)))
            ->filter(static fn (string $value): bool => $value !== '')
            ->unique()
            ->values();

        $candidateCertifications = collect((array) ($latestCv->certifications_json ?? []))
            ->map(static function (mixed $value): string {
                if (is_array($value)) {
                    return Str::lower(trim((string) data_get($value, 'name', '')));
                }

                return Str::lower(trim((string) $value));
            })
            ->filter(static fn (string $value): bool => $value !== '')
            ->values();

        if ($required->isEmpty()) {
            return [
                'score' => $candidateCertifications->isEmpty() ? 55.0 : 72.0,
                'status' => 'ready',
                'note' => 'No mandatory certification requirement detected.',
            ];
        }

        $matched = $required->filter(
            fn (string $keyword): bool => $candidateCertifications->contains(
                fn (string $cert): bool => str_contains($cert, $keyword)
            )
        )->values();

        $score = round(($matched->count() / max(1, $required->count())) * 100, 2);

        return [
            'score' => $score,
            'status' => 'ready',
            'note' => 'Certification overlap measured against detected role requirements.',
        ];
    }

    /**
     * @param array<string, mixed> $jobContext
     * @return array<string, mixed>
     */
    private function scoreLanguageComponent(?CvParsingResult $latestCv, array $jobContext): array
    {
        if (! $latestCv instanceof CvParsingResult) {
            return [
                'score' => null,
                'status' => 'pending_input',
                'note' => 'Awaiting CV parsing output.',
            ];
        }

        if (! $this->hasUsableCvEvidence($latestCv)) {
            return [
                'score' => null,
                'status' => 'pending_input',
                'note' => 'Uploaded document does not contain enough resume evidence for language scoring.',
            ];
        }

        $required = collect((array) data_get($jobContext, 'required_languages', []))
            ->map(fn (mixed $value): string => Str::lower(trim((string) $value)))
            ->filter(static fn (string $value): bool => $value !== '')
            ->unique()
            ->values();

        $candidateLanguages = collect((array) ($latestCv->languages_json ?? []))
            ->map(fn (mixed $value): string => Str::lower(trim((string) $value)))
            ->filter(static fn (string $value): bool => $value !== '')
            ->unique()
            ->values();

        if ($required->isEmpty()) {
            return [
                'score' => $candidateLanguages->isEmpty() ? 50.0 : 75.0,
                'status' => 'ready',
                'note' => 'No explicit language constraints found in job context.',
            ];
        }

        $matched = $required->filter(
            fn (string $language): bool => $candidateLanguages->contains(
                fn (string $candidateLanguage): bool => str_contains($candidateLanguage, $language)
                    || str_contains($language, $candidateLanguage)
            )
        );

        return [
            'score' => round(($matched->count() / max(1, $required->count())) * 100, 2),
            'status' => 'ready',
            'note' => 'Language fit measured against detected role requirements.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function scoreAssessmentComponent(string $companyId, string $applicationId): array
    {
        $average = SjtResponse::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('application_id', $applicationId)
            ->whereNotNull('ai_score')
            ->avg('ai_score');

        if (! is_numeric($average)) {
            return [
                'score' => null,
                'status' => 'pending_input',
                'note' => 'Awaiting assessment submissions or scoring.',
            ];
        }

        return [
            'score' => round(max(0, min(100, (float) $average)), 2),
            'status' => 'ready',
            'note' => 'Assessment score derived from SJT AI scoring.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function scoreInterviewComponent(Application $application): array
    {
        $feedbackScores = collect();
        $recommendationScores = collect();

        foreach ($application->interviews ?? [] as $interview) {
            foreach ($interview->feedback ?? [] as $feedback) {
                $ratings = collect((array) ($feedback->ratings_json ?? []))
                    ->filter(static fn (mixed $value): bool => is_numeric($value))
                    ->map(static fn (mixed $value): float => (float) $value);

                if ($ratings->isNotEmpty()) {
                    $feedbackScores->push(($ratings->avg() / 5) * 100);
                }

                $recommendation = Str::lower(trim((string) ($feedback->recommendation ?? '')));
                $recommendationScores->push(match ($recommendation) {
                    'hire' => 100.0,
                    'hold' => 60.0,
                    'no' => 20.0,
                    default => 50.0,
                });
            }
        }

        $videoMatch = is_numeric($application->unifiedInterviewReport?->match_percentage)
            ? (float) $application->unifiedInterviewReport?->match_percentage
            : null;

        $inputs = collect([
            $feedbackScores->isNotEmpty() ? (float) $feedbackScores->avg() : null,
            $recommendationScores->isNotEmpty() ? (float) $recommendationScores->avg() : null,
            $videoMatch,
        ])->filter(static fn (mixed $value): bool => is_numeric($value));

        if ($inputs->isEmpty()) {
            return [
                'score' => null,
                'status' => 'pending_input',
                'note' => 'Awaiting interview feedback or interview analysis output.',
            ];
        }

        return [
            'score' => round((float) $inputs->avg(), 2),
            'status' => 'ready',
            'note' => 'Interview score combines interviewer feedback and interview AI signals.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function scoreStrategyLabComponent(?StrategyLabAiSummary $summary): array
    {
        if (! $summary instanceof StrategyLabAiSummary) {
            return [
                'score' => null,
                'status' => 'unavailable',
                'note' => 'Strategy Lab summary is not available for this application yet.',
            ];
        }

        $creativity = is_numeric($summary->creativity_score)
            ? (float) $summary->creativity_score
            : null;

        if (! is_numeric($creativity)) {
            return [
                'score' => null,
                'status' => 'pending_input',
                'note' => 'Strategy Lab summary exists but creativity score is missing.',
            ];
        }

        return [
            'score' => round(max(0, min(100, (float) $creativity)), 2),
            'status' => 'ready',
            'note' => 'Strategy Lab creativity score contributes to overall ranking.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function scoreCultureFitComponent(?UnifiedInterviewReport $report): array
    {
        if (! $report instanceof UnifiedInterviewReport) {
            return [
                'score' => null,
                'status' => 'pending_input',
                'note' => 'Awaiting interview transcript or OCEAN-compatible inputs.',
            ];
        }

        $oceanValues = collect([
            is_numeric($report->ocean_openness) ? (float) $report->ocean_openness : null,
            is_numeric($report->ocean_conscientiousness) ? (float) $report->ocean_conscientiousness : null,
            is_numeric($report->ocean_extraversion) ? (float) $report->ocean_extraversion : null,
            is_numeric($report->ocean_agreeableness) ? (float) $report->ocean_agreeableness : null,
            is_numeric($report->ocean_neuroticism) ? (100.0 - (float) $report->ocean_neuroticism) : null,
        ])->filter(static fn (mixed $value): bool => is_numeric($value));

        $inputs = collect([
            $oceanValues->isNotEmpty() ? (float) $oceanValues->avg() : null,
            is_numeric($report->match_percentage) ? (float) $report->match_percentage : null,
        ])->filter(static fn (mixed $value): bool => is_numeric($value));

        if ($inputs->isEmpty()) {
            return [
                'score' => null,
                'status' => 'pending_input',
                'note' => 'Awaiting OCEAN and interview-fit evidence.',
            ];
        }

        $score = (float) $inputs->avg();
        if ((bool) $report->generic_motivation) {
            $score -= 8;
        }

        return [
            'score' => round(max(0, min(100, $score)), 2),
            'status' => 'ready',
            'note' => 'Culture fit combines OCEAN profile and interview fit signals.',
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $components
     * @return array<int, string>
     */
    private function buildStrengths(array $components, ?StrategyLabAiSummary $summary): array
    {
        $labels = [
            self::FACTOR_SKILLS_MATCH => 'Strong skill alignment',
            self::FACTOR_EXPERIENCE_MATCH => 'Relevant experience depth',
            self::FACTOR_EDUCATION_MATCH => 'Education profile aligns with role',
            self::FACTOR_CERTIFICATIONS => 'Certifications support job requirements',
            self::FACTOR_LANGUAGE_MATCH => 'Language coverage is suitable',
            self::FACTOR_ASSESSMENT => 'Assessment performance is strong',
            self::FACTOR_INTERVIEW => 'Interview signals are positive',
            self::FACTOR_STRATEGY_LAB => 'Strategy Lab output is compelling',
            self::FACTOR_CULTURE_FIT => 'Culture-fit indicators are positive',
        ];

        $strengths = collect($components)
            ->filter(static fn (array $component): bool => is_numeric($component['score'] ?? null) && (float) $component['score'] >= 70)
            ->keys()
            ->map(static fn (string $factor): string => $labels[$factor] ?? Str::headline(str_replace('_', ' ', $factor)))
            ->take(5)
            ->values();

        if ($summary instanceof StrategyLabAiSummary) {
            $strategyStrengths = collect((array) ($summary->strengths_json ?? []))
                ->map(static fn (mixed $value): string => trim((string) $value))
                ->filter(static fn (string $value): bool => $value !== '')
                ->take(3)
                ->values();
            $strengths = $strengths->concat($strategyStrengths);
        }

        return $strengths
            ->map(static fn (string $value): string => Str::limit($value, 180, ''))
            ->unique()
            ->take(8)
            ->values()
            ->all();
    }

    /**
     * @param array<string, mixed> $jobContext
     * @param array<string, mixed> $skillsComponent
     * @param array<string, mixed> $softSkillsEvaluation
     * @param array<string, mixed> $experienceComponent
     * @return array<string, mixed>
     */
    private function buildEvaluationModel(
        ?CvParsingResult $latestCv,
        array $jobContext,
        array $skillsComponent,
        array $softSkillsEvaluation,
        array $experienceComponent
    ): array {
        $technicalSkills = collect((array) data_get($skillsComponent, 'matched', []))
            ->merge((array) ($latestCv?->hard_skills_json ?? []))
            ->merge((array) ($latestCv?->tools_frameworks_json ?? []))
            ->map(static fn (mixed $value): string => trim((string) $value))
            ->filter(static fn (string $value): bool => $value !== '')
            ->unique()
            ->take(8)
            ->values()
            ->all();

        $softSkills = collect((array) data_get($softSkillsEvaluation, 'detected', []))
            ->map(static fn (mixed $value): string => Str::headline(trim((string) $value)))
            ->filter(static fn (string $value): bool => $value !== '')
            ->unique()
            ->take(8)
            ->values()
            ->all();

        $missingCriticalSkills = collect((array) data_get($skillsComponent, 'missing', []))
            ->map(static fn (mixed $value): string => trim((string) $value))
            ->filter(static fn (string $value): bool => $value !== '')
            ->unique()
            ->take(5)
            ->values()
            ->all();

        $relevantExperience = [
            'years' => is_numeric($latestCv?->total_years_experience)
                ? round((float) $latestCv->total_years_experience, 2)
                : $this->estimateExperienceYearsFromEntries((array) ($latestCv?->experience_entries_json ?? [])),
            'highlights' => collect((array) ($latestCv?->experience_entries_json ?? []))
                ->filter(static fn (mixed $entry): bool => is_array($entry))
                ->map(function (array $entry): string {
                    $title = trim((string) data_get($entry, 'job_title', data_get($entry, 'title', '')));
                    $company = trim((string) data_get($entry, 'company_name', data_get($entry, 'company', '')));

                    return trim($title.($company !== '' ? ' at '.$company : ''));
                })
                ->filter(static fn (string $value): bool => $value !== '')
                ->unique()
                ->take(3)
                ->values()
                ->all(),
        ];

        $technicalScore = is_numeric(data_get($skillsComponent, 'score'))
            ? round((float) data_get($skillsComponent, 'score'), 2)
            : null;
        $softScore = is_numeric(data_get($softSkillsEvaluation, 'score'))
            ? round((float) data_get($softSkillsEvaluation, 'score'), 2)
            : null;
        $experienceScore = is_numeric(data_get($experienceComponent, 'score'))
            ? round((float) data_get($experienceComponent, 'score'), 2)
            : null;

        $summaryParts = collect([
            $technicalSkills !== [] ? 'Technical strengths include '.implode(', ', array_slice($technicalSkills, 0, 3)).'.' : null,
            $softSkills !== [] ? 'Observed soft skills include '.implode(', ', array_slice($softSkills, 0, 3)).'.' : null,
            $missingCriticalSkills !== [] ? 'Critical gaps remain in '.implode(', ', array_slice($missingCriticalSkills, 0, 3)).'.' : null,
            $relevantExperience['years'] !== null ? 'Relevant experience is estimated at '.rtrim(rtrim(number_format((float) $relevantExperience['years'], 2), '0'), '.').' years.' : null,
        ])->filter()->implode(' ');

        if ($summaryParts === '') {
            $summaryParts = 'Evaluation summary is still waiting for stronger CV or assessment evidence.';
        }

        return [
            'technical_skills' => $technicalSkills,
            'soft_skills' => $softSkills,
            'missing_critical_skills' => $missingCriticalSkills,
            'relevant_experience' => $relevantExperience,
            'match_scores' => [
                'technical' => $technicalScore,
                'soft' => $softScore,
                'experience' => $experienceScore,
            ],
            'required_soft_skills' => (array) data_get($jobContext, 'required_soft_skills', []),
            'concise_summary' => $summaryParts,
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $components
     * @return array<int, string>
     */
    private function buildWeaknesses(array $components, ?StrategyLabAiSummary $summary): array
    {
        $labels = [
            self::FACTOR_SKILLS_MATCH => 'Skill gaps remain against role requirements',
            self::FACTOR_EXPERIENCE_MATCH => 'Experience depth may be below role expectation',
            self::FACTOR_EDUCATION_MATCH => 'Education alignment is uncertain',
            self::FACTOR_CERTIFICATIONS => 'Required certifications appear limited',
            self::FACTOR_LANGUAGE_MATCH => 'Language requirement alignment is incomplete',
            self::FACTOR_ASSESSMENT => 'Assessment performance needs review',
            self::FACTOR_INTERVIEW => 'Interview signals are not yet strong',
            self::FACTOR_STRATEGY_LAB => 'Strategy Lab evaluation is pending or weak',
            self::FACTOR_CULTURE_FIT => 'Culture-fit evidence is currently limited',
        ];

        $weaknesses = collect($components)
            ->filter(static fn (array $component): bool => ! is_numeric($component['score'] ?? null) || (float) $component['score'] < 45)
            ->keys()
            ->map(static fn (string $factor): string => $labels[$factor] ?? Str::headline(str_replace('_', ' ', $factor)))
            ->take(5)
            ->values();

        if ($summary instanceof StrategyLabAiSummary) {
            $strategyWeaknesses = collect((array) ($summary->weaknesses_json ?? []))
                ->map(static fn (mixed $value): string => trim((string) $value))
                ->filter(static fn (string $value): bool => $value !== '')
                ->take(3)
                ->values();
            $weaknesses = $weaknesses->concat($strategyWeaknesses);
        }

        return $weaknesses
            ->map(static fn (string $value): string => Str::limit($value, 180, ''))
            ->unique()
            ->take(8)
            ->values()
            ->all();
    }

    private function buildOverallRecommendation(float $score, string $analysisStatus): string
    {
        if ($analysisStatus === self::ANALYSIS_INVALID_CV) {
            return self::analysisStatusLabel($analysisStatus);
        }

        if ($analysisStatus === self::ANALYSIS_PENDING) {
            return 'Awaiting core candidate inputs before recommendation.';
        }

        return match (true) {
            $score >= 80 => 'Strong fit. Recommend priority review and fast-track final decision.',
            $score >= 65 => 'Good fit. Proceed with focused human review on flagged weak areas.',
            $score >= 50 => 'Borderline fit. Continue only if role-specific constraints can be addressed.',
            default => 'Low fit based on available data. Human rejection review is recommended.',
        };
    }

    /**
     * @param array<int, string> $strengths
     * @param array<int, string> $weaknesses
     */
    private function buildXaiSummary(float $score, string $analysisStatus, array $strengths, array $weaknesses): string
    {
        if ($analysisStatus === self::ANALYSIS_INVALID_CV) {
            return self::analysisStatusLabel($analysisStatus);
        }

        if ($analysisStatus === self::ANALYSIS_PENDING) {
            return 'Analysis pending. The system is waiting for CV, interview, assessment, or project inputs.';
        }

        $strengthText = $strengths !== []
            ? implode(', ', array_slice($strengths, 0, 3))
            : 'No major strengths were confidently detected yet';
        $weaknessText = $weaknesses !== []
            ? implode(', ', array_slice($weaknesses, 0, 3))
            : 'No material risks detected from current inputs';

        return sprintf(
            'Overall score %.1f/100. Key strengths: %s. Key risks: %s.',
            $score,
            $strengthText,
            $weaknessText
        );
    }

    private function hasUsableCvEvidence(?CvParsingResult $latestCv): bool
    {
        if (! $latestCv instanceof CvParsingResult) {
            return false;
        }

        $evidenceSignals = [
            count((array) ($latestCv->hard_skills_json ?? [])) > 0,
            count((array) ($latestCv->tools_frameworks_json ?? [])) > 0,
            count((array) ($latestCv->keywords_json ?? [])) > 0,
            count((array) ($latestCv->soft_skills_json ?? [])) > 0,
            count((array) ($latestCv->languages_json ?? [])) > 0,
            count((array) ($latestCv->experience_entries_json ?? [])) > 0,
            count((array) ($latestCv->education_entries_json ?? [])) > 0,
            count((array) ($latestCv->certifications_json ?? [])) > 0,
            is_numeric($latestCv->total_years_experience) && (float) $latestCv->total_years_experience > 0,
        ];

        if (collect($evidenceSignals)->contains(true)) {
            return true;
        }

        $missingSections = collect((array) ($latestCv->flags_json['missing_sections'] ?? []))
            ->map(static fn (mixed $value): string => Str::lower(trim((string) $value)))
            ->filter(static fn (string $value): bool => $value !== '')
            ->unique()
            ->values();

        $coreSections = collect([
            'languages',
            'hard_skills',
            'experience',
            'education',
            'role_keywords',
        ]);

        return ! $coreSections->every(
            static fn (string $section): bool => $missingSections->contains($section)
        );
    }

    public static function analysisStatusLabel(?string $status): string
    {
        $normalized = Str::lower(trim((string) $status));

        return match ($normalized) {
            self::ANALYSIS_INVALID_CV => __('candidates.detail.analysis_status_values.invalid_cv'),
            self::ANALYSIS_PENDING => __('candidates.detail.analysis_status_values.pending_analysis'),
            self::ANALYSIS_PARTIAL => __('candidates.detail.analysis_status_values.partial_ready'),
            self::ANALYSIS_READY => __('candidates.detail.analysis_status_values.ready'),
            'insufficient_data' => __('candidates.detail.analysis_status_values.insufficient_data'),
            default => Str::headline(str_replace('_', ' ', $normalized !== '' ? $normalized : self::ANALYSIS_PENDING)),
        };
    }

    public static function sourceStatusLabel(?string $status, string $source = 'generic'): string
    {
        $normalized = Str::lower(trim((string) $status));
        $prefix = $source === 'cv'
            ? 'candidates.detail.cv_source_status_values.'
            : 'candidates.detail.source_status_values.';

        return match ($normalized) {
            self::SOURCE_STATUS_NOT_APPROPRIATE_CV => __($prefix.'not_appropriate_cv'),
            'ready' => __($prefix.'ready'),
            'pending_input' => __($prefix.'pending_input'),
            'awaiting_interview_input' => __($prefix.'awaiting_interview_input'),
            'unavailable' => __($prefix.'unavailable'),
            default => Str::headline(str_replace('_', ' ', $normalized !== '' ? $normalized : 'pending_input')),
        };
    }

    private function resolveOceanStatus(?CvParsingResult $latestCv, ?UnifiedInterviewReport $report): string
    {
        if ($report instanceof UnifiedInterviewReport) {
            $hasOcean = collect([
                $report->ocean_openness,
                $report->ocean_conscientiousness,
                $report->ocean_extraversion,
                $report->ocean_agreeableness,
                $report->ocean_neuroticism,
            ])->contains(static fn (mixed $value): bool => is_numeric($value));

            if ($hasOcean) {
                return 'ready';
            }
        }

        $dependency = Str::lower(trim((string) ($latestCv?->ocean_dependency_status ?? '')));
        if ($dependency !== '') {
            return match ($dependency) {
                'ready_for_analysis' => 'ready',
                'pending_input' => 'awaiting_interview_input',
                'unavailable' => 'unavailable',
                default => 'pending_input',
            };
        }

        return 'pending_input';
    }

    private function recomputeRankingForJob(string $companyId, string $jobId): void
    {
        $rankableApplicationIds = Application::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('job_id', $jobId)
            ->whereIn('status', [Application::STATUS_ACTIVE, Application::STATUS_HIRED])
            ->pluck('id')
            ->map(static fn (mixed $value): string => (string) $value)
            ->values();

        if ($rankableApplicationIds->isEmpty()) {
            $otherIds = Application::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('job_id', $jobId)
                ->pluck('id');

            if ($otherIds->isNotEmpty()) {
                ApplicationScoring::withoutGlobalScopes()
                    ->where('company_id', $companyId)
                    ->whereIn('application_id', $otherIds->all())
                    ->update([
                        'ranking_position' => null,
                        'ranking_percentile' => null,
                        'is_top_three' => false,
                    ]);
            }

            return;
        }

        foreach ($rankableApplicationIds as $applicationId) {
            ApplicationScoring::withoutGlobalScopes()->firstOrCreate(
                ['application_id' => $applicationId],
                [
                    'company_id' => $companyId,
                    'global_match_score' => 0,
                    'vrin_json' => ['acquired_skills' => [], 'missing_skills' => []],
                    'xai_summary' => 'Analysis pending.',
                    'analysis_status' => self::ANALYSIS_PENDING,
                    'updated_at' => now(),
                ]
            );
        }

        $ordered = ApplicationScoring::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereIn('application_id', $rankableApplicationIds->all())
            ->orderByDesc('global_match_score')
            ->orderByDesc('updated_at')
            ->get(['id', 'application_id', 'global_match_score']);

        $total = max(1, $ordered->count());
        foreach ($ordered as $index => $scoring) {
            $rank = $index + 1;
            $percentile = round((($total - $index) / $total) * 100, 2);

            $scoring->forceFill([
                'ranking_position' => $rank,
                'ranking_percentile' => $percentile,
                'is_top_three' => $rank <= 3,
                'updated_at' => now(),
            ])->save();
        }

        $nonRankableApplicationIds = Application::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('job_id', $jobId)
            ->whereNotIn('id', $rankableApplicationIds->all())
            ->pluck('id');

        if ($nonRankableApplicationIds->isNotEmpty()) {
            ApplicationScoring::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->whereIn('application_id', $nonRankableApplicationIds->all())
                ->update([
                    'ranking_position' => null,
                    'ranking_percentile' => null,
                    'is_top_three' => false,
                ]);
        }
    }

    /**
     * @param Collection<int, ?float> $scores
     * @return array<int, array{label: string, count: int}>
     */
    private function scoreDistribution(Collection $scores): array
    {
        $bins = [
            ['label' => '0-20', 'min' => 0, 'max' => 20, 'count' => 0],
            ['label' => '21-40', 'min' => 21, 'max' => 40, 'count' => 0],
            ['label' => '41-60', 'min' => 41, 'max' => 60, 'count' => 0],
            ['label' => '61-80', 'min' => 61, 'max' => 80, 'count' => 0],
            ['label' => '81-100', 'min' => 81, 'max' => 100, 'count' => 0],
        ];

        foreach ($scores as $score) {
            if (! is_numeric($score)) {
                continue;
            }

            $normalized = (float) $score;
            foreach ($bins as &$bin) {
                if ($normalized >= $bin['min'] && $normalized <= $bin['max']) {
                    $bin['count']++;
                    break;
                }
            }
            unset($bin);
        }

        return collect($bins)
            ->map(static fn (array $bin): array => [
                'label' => (string) $bin['label'],
                'count' => (int) $bin['count'],
            ])
            ->values()
            ->all();
    }

    /**
     * @param Collection<int, Application> $applications
     * @return array<string, array<string, mixed>>
     */
    private function fairnessSummary(string $companyId, Collection $applications): array
    {
        $applicationIds = $applications->pluck('id')
            ->map(static fn (mixed $value): string => (string) $value)
            ->filter(static fn (string $value): bool => $value !== '')
            ->values();

        if ($applicationIds->isEmpty()) {
            return [
                'gender' => [
                    'group_a' => 0,
                    'group_b' => 0,
                    'impact_ratio' => null,
                    'insufficient_data' => true,
                ],
                'school' => [
                    'group_a' => 0,
                    'group_b' => 0,
                    'impact_ratio' => null,
                    'insufficient_data' => true,
                ],
            ];
        }

        $latestParses = $this->latestCvParsesForApplications($companyId, $applicationIds);

        $menCount = 0;
        $womenCount = 0;
        $topOrGrande = 0;
        $regularOrFaculty = 0;

        foreach ($applicationIds as $applicationId) {
            $parse = $latestParses->get($applicationId);
            if (! $parse instanceof CvParsingResult) {
                continue;
            }

            $gender = Str::lower(trim((string) ($parse->gender_inference ?? 'unknown')));
            if ($gender === 'male') {
                $menCount++;
            } elseif ($gender === 'female') {
                $womenCount++;
            }

            $tier = Str::lower(trim((string) ($parse->school_background_tier ?? 'unknown')));
            if (in_array($tier, ['top_school', 'grande_ecole'], true)) {
                $topOrGrande++;
            } elseif (in_array($tier, ['regular_university', 'faculty'], true)) {
                $regularOrFaculty++;
            }
        }

        return [
            'gender' => [
                'group_a' => $menCount,
                'group_b' => $womenCount,
                'impact_ratio' => $this->impactRatio($menCount, $womenCount),
                'insufficient_data' => ($menCount + $womenCount) < 4 || $menCount === 0 || $womenCount === 0,
            ],
            'school' => [
                'group_a' => $topOrGrande,
                'group_b' => $regularOrFaculty,
                'impact_ratio' => $this->impactRatio($topOrGrande, $regularOrFaculty),
                'insufficient_data' => ($topOrGrande + $regularOrFaculty) < 4 || $topOrGrande === 0 || $regularOrFaculty === 0,
            ],
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

    private function impactRatio(int $groupA, int $groupB): ?float
    {
        $max = max($groupA, $groupB);
        if ($max <= 0) {
            return null;
        }

        return round(min($groupA, $groupB) / $max, 4);
    }

    private function containsKeyword(Collection $candidateSkills, string $requirement): bool
    {
        return $candidateSkills->contains(
            static fn (string $candidateSkill): bool => str_contains($candidateSkill, $requirement)
                || str_contains($requirement, $candidateSkill)
        );
    }

    /**
     * @return array<int, string>
     */
    private function extractSkillKeywords(string $text): array
    {
        $normalizedText = Str::lower($text);

        return collect(self::SKILL_KEYWORDS)
            ->map(static fn (string $keyword): string => Str::lower(trim($keyword)))
            ->filter(static fn (string $keyword): bool => $keyword !== '' && str_contains($normalizedText, $keyword))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function extractLanguageKeywords(string $text): array
    {
        $normalizedText = Str::lower($text);

        return collect(self::LANGUAGE_KEYWORDS)
            ->map(static fn (string $keyword): string => Str::lower(trim($keyword)))
            ->filter(static fn (string $keyword): bool => $keyword !== '' && str_contains($normalizedText, $keyword))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function extractCertificationKeywords(string $text): array
    {
        $normalizedText = Str::lower($text);

        return collect(self::CERTIFICATION_KEYWORDS)
            ->map(static fn (string $keyword): string => Str::lower(trim($keyword)))
            ->filter(static fn (string $keyword): bool => $keyword !== '' && str_contains($normalizedText, $keyword))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function extractSoftSkillKeywords(string $text): array
    {
        $normalizedText = Str::lower($text);

        return collect(self::SOFT_SKILL_KEYWORDS)
            ->map(static fn (string $keyword): string => Str::lower(trim($keyword)))
            ->filter(static fn (string $keyword): bool => $keyword !== '' && str_contains($normalizedText, $keyword))
            ->unique()
            ->values()
            ->all();
    }

    private function extractRequiredYearsFromText(string $text): ?float
    {
        $normalized = Str::lower($text);
        preg_match_all('/(\d{1,2})\s*(?:\+|plus)?\s*(?:years?|yrs?)/', $normalized, $matches);

        $numbers = collect((array) ($matches[1] ?? []))
            ->filter(static fn (mixed $value): bool => is_numeric($value))
            ->map(static fn (mixed $value): float => (float) $value)
            ->values();

        if ($numbers->isEmpty()) {
            return null;
        }

        return (float) $numbers->max();
    }

    private function extractRequiredEducationLevel(string $text): ?string
    {
        $normalized = Str::lower($text);

        if (str_contains($normalized, 'phd') || str_contains($normalized, 'doctorate')) {
            return 'phd';
        }
        if (str_contains($normalized, 'master') || str_contains($normalized, 'msc') || str_contains($normalized, 'mba')) {
            return 'master';
        }
        if (str_contains($normalized, 'bachelor') || str_contains($normalized, 'bsc') || str_contains($normalized, 'ba')) {
            return 'bachelor';
        }

        return null;
    }

    /**
     * @param array<int, array<string, mixed>> $entries
     */
    private function estimateExperienceYearsFromEntries(array $entries): ?float
    {
        $ranges = collect($entries)
            ->filter(static fn (mixed $entry): bool => is_array($entry))
            ->map(function (array $entry): ?array {
                $start = $this->normalizeDateForCarbon((string) data_get($entry, 'start_date', ''));
                $end = $this->normalizeDateForCarbon((string) data_get($entry, 'end_date', ''));
                if ($start === null) {
                    return null;
                }

                return [
                    'start' => $start,
                    'end' => $end ?? now()->format('Y-m-d'),
                ];
            })
            ->filter()
            ->values();

        if ($ranges->isEmpty()) {
            return null;
        }

        $earliest = $ranges->min('start');
        $latest = $ranges->max('end');

        if (! is_string($earliest) || ! is_string($latest)) {
            return null;
        }

        try {
            $start = now()->parse($earliest);
            $end = now()->parse($latest);
        } catch (\Throwable) {
            return null;
        }

        if ($end->lt($start)) {
            return null;
        }

        return round(((float) $end->diffInDays($start) / 365), 2);
    }

    private function normalizeDateForCarbon(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^\d{4}$/', $value) === 1) {
            return $value.'-01-01';
        }
        if (preg_match('/^\d{4}-\d{2}$/', $value) === 1) {
            return $value.'-01';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
            return $value;
        }

        return null;
    }

    /**
     * @param Collection<int, array<string, mixed>> $educationEntries
     */
    private function highestEducationLevel(Collection $educationEntries): ?string
    {
        $best = null;
        $bestRank = 0;

        foreach ($educationEntries as $entry) {
            $level = Str::lower(trim((string) data_get($entry, 'level', '')));
            if ($level === '') {
                $level = Str::lower(trim((string) data_get($entry, 'degree_name', '')));
            }

            if (str_contains($level, 'phd') || str_contains($level, 'doctorate')) {
                $current = 'phd';
            } elseif (str_contains($level, 'master') || str_contains($level, 'msc') || str_contains($level, 'mba')) {
                $current = 'master';
            } elseif (str_contains($level, 'bachelor') || str_contains($level, 'bsc') || str_contains($level, 'ba')) {
                $current = 'bachelor';
            } elseif (str_contains($level, 'associate') || str_contains($level, 'diploma')) {
                $current = 'associate';
            } else {
                $current = null;
            }

            if (! is_string($current)) {
                continue;
            }

            $rank = $this->educationRank($current);
            if ($rank > $bestRank) {
                $bestRank = $rank;
                $best = $current;
            }
        }

        return $best;
    }

    private function educationRank(?string $level): int
    {
        return match (Str::lower(trim((string) $level))) {
            'phd' => 4,
            'master' => 3,
            'bachelor' => 2,
            'associate' => 1,
            default => 0,
        };
    }
}
