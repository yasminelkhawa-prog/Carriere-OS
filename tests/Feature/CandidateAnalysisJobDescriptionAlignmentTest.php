<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Candidate;
use App\Models\CandidateDocument;
use App\Models\Company;
use App\Models\CvParsingResult;
use App\Models\Job;
use App\Models\JobDescriptionBlock;
use App\Models\JobPipelineStage;
use App\Services\Analysis\CandidateAnalysisService;
use App\Services\Cv\CandidateCvParsingPipeline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CandidateAnalysisJobDescriptionAlignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_candidate_analysis_uses_description_html_as_job_requirement_source_of_truth(): void
    {
        $company = Company::query()->create([
            'name' => 'Analysis Alignment Co',
            'slug' => 'analysis-alignment-co',
            'status' => Company::STATUS_ACTIVE,
        ]);

        $job = Job::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'title' => 'Senior Laravel Engineer',
            'status' => Job::STATUS_PUBLISHED,
            'description_html' => '<p>Required: Laravel, PHP, AWS, communication, and 5 years experience.</p>',
        ]);

        $stage = JobPipelineStage::withoutGlobalScopes()->create([
            'job_id' => $job->id,
            'stage_key' => 'screening',
            'stage_label' => 'Screening',
            'display_order' => 1,
            'is_terminal' => false,
        ]);

        $candidate = Candidate::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'full_name' => 'Aligned Candidate',
            'email' => 'aligned-candidate@example.test',
        ]);

        $application = Application::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'candidate_id' => $candidate->id,
            'job_id' => $job->id,
            'current_stage_id' => $stage->id,
            'status' => Application::STATUS_ACTIVE,
            'source_type' => 'career_page',
        ]);

        CvParsingResult::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'candidate_id' => $candidate->id,
            'application_id' => $application->id,
            'parser_version' => CandidateCvParsingPipeline::PARSER_VERSION,
            'parse_status' => 'complete',
            'hard_skills_json' => ['Laravel', 'PHP'],
            'soft_skills_json' => ['Communication'],
            'tools_frameworks_json' => ['AWS'],
            'keywords_json' => ['cloud'],
            'total_years_experience' => 6,
            'languages_json' => [],
            'certifications_json' => [],
            'experience_entries_json' => [],
            'education_entries_json' => [],
            'parsed_payload_json' => [],
            'flags_json' => [],
            'parse_errors_json' => [],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $scoring = app(CandidateAnalysisService::class)
            ->recomputeForApplicationId((string) $company->id, (string) $application->id);

        $this->assertNotNull($scoring);
        $this->assertContains('laravel', (array) data_get($scoring?->vrin_json, 'job_required_skills', []));
        $this->assertContains('laravel', (array) data_get($scoring?->vrin_json, 'acquired_skills', []));
        $this->assertContains('communication', (array) data_get($scoring?->vrin_json, 'evaluation_model.required_soft_skills', []));
        $this->assertContains('Communication', (array) data_get($scoring?->vrin_json, 'evaluation_model.soft_skills', []));
        $this->assertSame([], (array) data_get($scoring?->vrin_json, 'evaluation_model.missing_critical_skills', []));
        $this->assertSame(6.0, (float) data_get($scoring?->vrin_json, 'evaluation_model.relevant_experience.years'));
        $this->assertSame(100.0, (float) data_get($scoring?->vrin_json, 'evaluation_model.match_scores.technical'));
        $this->assertSame(100.0, (float) data_get($scoring?->vrin_json, 'evaluation_model.match_scores.soft'));
        $evaluationSummary = (string) data_get($scoring?->vrin_json, 'evaluation_model.concise_summary', '');
        $this->assertStringContainsString('Technical strengths include', $evaluationSummary);
        $this->assertStringContainsString('php', $evaluationSummary);
        $this->assertStringContainsString('laravel', $evaluationSummary);
        $this->assertStringContainsString('aws', $evaluationSummary);
        $this->assertGreaterThan(0, (float) ($scoring?->global_match_score ?? 0));
    }

    public function test_cv_parsing_prompt_uses_same_description_fallback_as_blocks_renderer(): void
    {
        Storage::fake('local');

        $company = Company::query()->create([
            'name' => 'Prompt Alignment Co',
            'slug' => 'prompt-alignment-co',
            'status' => Company::STATUS_ACTIVE,
        ]);

        $job = Job::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'title' => 'Data Platform Analyst',
            'status' => Job::STATUS_PUBLISHED,
        ]);

        JobDescriptionBlock::withoutGlobalScopes()->create([
            'job_id' => $job->id,
            'block_type' => 'overview',
            'block_content_json' => ['text' => 'Work closely with SQL, Tableau, and product analytics.'],
            'display_order' => 1,
        ]);

        JobDescriptionBlock::withoutGlobalScopes()->create([
            'job_id' => $job->id,
            'block_type' => 'requirements',
            'block_content_json' => ['text' => 'Need 3 years experience with experimentation and dashboards.'],
            'display_order' => 2,
        ]);

        $stage = JobPipelineStage::withoutGlobalScopes()->create([
            'job_id' => $job->id,
            'stage_key' => 'screening',
            'stage_label' => 'Screening',
            'display_order' => 1,
            'is_terminal' => false,
        ]);

        $candidate = Candidate::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'full_name' => 'Prompt Candidate',
            'email' => 'prompt-candidate@example.test',
        ]);

        $application = Application::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'candidate_id' => $candidate->id,
            'job_id' => $job->id,
            'current_stage_id' => $stage->id,
            'status' => Application::STATUS_ACTIVE,
            'source_type' => 'career_page',
        ]);

        $resumePath = 'private/candidates/'.(string) $company->id.'/'.(string) $candidate->id.'/resume.txt';
        Storage::disk('local')->put($resumePath, 'Prompt Candidate resume text');

        CandidateDocument::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'candidate_id' => $candidate->id,
            'document_type' => CandidateDocument::TYPE_RESUME,
            'file_url' => $resumePath,
            'original_filename' => 'resume.txt',
            'mime_type' => 'text/plain',
            'file_size_bytes' => Storage::disk('local')->size($resumePath),
            'created_at' => now(),
        ]);

        $request = \App\Models\AiRequest::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('request_type', 'cv_parsing')
            ->latest('created_at')
            ->first();

        $this->assertNotNull($request);
        $prompt = (string) data_get($request?->request_payload, 'prompt', '');
        $this->assertStringContainsString('BLIND EVALUATION: Ignore candidate name, gender, age, nationality, university prestige, and geographic location when assessing role fit.', $prompt);
        $this->assertStringContainsString('SEMANTIC RECOGNITION: Recognize synonyms and contextual evidence of skills, ownership, leadership, collaboration, and domain experience.', $prompt);
        $this->assertStringContainsString('Deconstruct the job description into must-have technical requirements, likely soft-skill expectations, and experience signals.', $prompt);
        $this->assertStringContainsString('Overview: Work closely with SQL, Tableau, and product analytics.', $prompt);
        $this->assertStringContainsString('Requirements: Need 3 years experience with experimentation and dashboards.', $prompt);
    }
}
