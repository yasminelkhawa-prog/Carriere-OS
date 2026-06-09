<?php

namespace Tests\Feature;

use App\Models\AiRequest;
use App\Models\Application;
use App\Models\Candidate;
use App\Models\CandidateDocument;
use App\Models\Company;
use App\Models\Job;
use App\Models\JobPipelineStage;
use App\Models\User;
use App\Services\Ai\GeminiClient;
use App\Services\Ai\AiRequestService;
use App\Services\Cv\CandidateCvParsingPipeline;
use App\Services\Referral\ReferralService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class CvParsingPipelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_resume_document_creation_queues_cv_parsing_automatically(): void
    {
        Storage::fake('local');

        $company = Company::query()->create([
            'name' => 'CV Pipeline Co',
            'slug' => 'cv-pipeline-co',
            'status' => Company::STATUS_ACTIVE,
        ]);

        $job = Job::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'title' => 'Backend Engineer',
            'status' => Job::STATUS_PUBLISHED,
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
            'full_name' => 'Parser Candidate',
            'email' => 'parser-candidate@example.test',
            'phone' => '+1-555-0202',
            'location' => 'Remote',
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
        Storage::disk('local')->put($resumePath, implode("\n", [
            'Parser Candidate',
            'Email: parser-candidate@example.test',
            'Backend Engineer with 5 years experience',
        ]));

        $resume = CandidateDocument::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'candidate_id' => $candidate->id,
            'document_type' => CandidateDocument::TYPE_RESUME,
            'file_url' => $resumePath,
            'original_filename' => 'resume.txt',
            'mime_type' => 'text/plain',
            'file_size_bytes' => Storage::disk('local')->size($resumePath),
            'created_at' => now(),
        ]);

        $request = AiRequest::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('request_type', 'cv_parsing')
            ->first();

        $this->assertNotNull($request);
        $this->assertSame((string) $candidate->id, (string) data_get($request?->request_payload, 'candidate_id'));
        $this->assertSame((string) $application->id, (string) data_get($request?->request_payload, 'application_id'));
        $this->assertSame((string) $resume->id, (string) data_get($request?->request_payload, 'resume_document_id'));
        $this->assertSame(CandidateCvParsingPipeline::PARSER_VERSION, (string) data_get($request?->request_payload, 'parser_version'));
    }

    public function test_cv_parsing_persistence_is_structured_and_idempotent(): void
    {
        Storage::fake('local');

        $company = Company::query()->create([
            'name' => 'CV Structured Co',
            'slug' => 'cv-structured-co',
            'status' => Company::STATUS_ACTIVE,
        ]);

        $job = Job::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'title' => 'Data Engineer',
            'status' => Job::STATUS_PUBLISHED,
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
            'full_name' => 'Structured Candidate',
            'email' => 'structured-candidate@example.test',
            'phone' => '+1-555-0303',
            'location' => 'Paris',
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
        Storage::disk('local')->put($resumePath, 'Structured Candidate CV text');

        $resume = CandidateDocument::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'candidate_id' => $candidate->id,
            'document_type' => CandidateDocument::TYPE_RESUME,
            'file_url' => $resumePath,
            'original_filename' => 'resume.txt',
            'mime_type' => 'text/plain',
            'file_size_bytes' => Storage::disk('local')->size($resumePath),
            'created_at' => now(),
        ]);

        $service = app(AiRequestService::class);

        $requestOne = AiRequest::withoutGlobalScopes()->create([
            'company_id' => (string) $company->id,
            'request_type' => 'cv_parsing',
            'input_hash' => hash('sha256', Str::uuid()->toString()),
            'status' => AiRequest::STATUS_QUEUED,
            'model_name' => 'gemini-test',
            'prompt_version' => CandidateCvParsingPipeline::PARSER_VERSION,
            'request_payload' => $this->cvParsingPayload(
                candidateId: (string) $candidate->id,
                applicationId: (string) $application->id,
                jobId: (string) $job->id,
                resumeId: (string) $resume->id,
                parseSignature: 'signature-1'
            ),
            'created_at' => now(),
        ]);

        $service->process($requestOne);

        $result = \App\Models\CvParsingResult::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('candidate_id', $candidate->id)
            ->where('application_id', $application->id)
            ->first();

        $this->assertNotNull($result);
        $this->assertNotNull($result?->hard_skills_json);
        $this->assertIsArray($result?->experience_entries_json);
        $this->assertIsArray($result?->education_entries_json);
        $this->assertIsArray($result?->parsed_payload_json);
        $this->assertSame('pending_input', (string) $result?->ocean_dependency_status);

        $requestTwo = AiRequest::withoutGlobalScopes()->create([
            'company_id' => (string) $company->id,
            'request_type' => 'cv_parsing',
            'input_hash' => hash('sha256', Str::uuid()->toString()),
            'status' => AiRequest::STATUS_QUEUED,
            'model_name' => 'gemini-test',
            'prompt_version' => CandidateCvParsingPipeline::PARSER_VERSION,
            'request_payload' => $this->cvParsingPayload(
                candidateId: (string) $candidate->id,
                applicationId: (string) $application->id,
                jobId: (string) $job->id,
                resumeId: (string) $resume->id,
                parseSignature: 'signature-2'
            ),
            'created_at' => now(),
        ]);

        $service->process($requestTwo);

        $this->assertSame(
            1,
            \App\Models\CvParsingResult::withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->where('candidate_id', $candidate->id)
                ->where('application_id', $application->id)
                ->count()
        );
    }

    public function test_cv_parsing_recovers_from_missing_required_keys_in_ai_output(): void
    {
        Storage::fake('local');

        $company = Company::query()->create([
            'name' => 'CV Resilience Co',
            'slug' => 'cv-resilience-co',
            'status' => Company::STATUS_ACTIVE,
        ]);

        $job = Job::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'title' => 'Backend Engineer',
            'status' => Job::STATUS_PUBLISHED,
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
            'full_name' => 'Fallback Candidate',
            'email' => 'fallback-candidate@example.test',
            'phone' => '+1-555-0909',
            'location' => 'Lyon',
        ]);

        $application = Application::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'candidate_id' => $candidate->id,
            'job_id' => $job->id,
            'current_stage_id' => $stage->id,
            'status' => Application::STATUS_ACTIVE,
            'source_type' => 'career_page',
        ]);

        $resumePath = 'private/candidates/'.(string) $company->id.'/'.(string) $candidate->id.'/fallback-resume.txt';
        Storage::disk('local')->put($resumePath, 'Fallback resume text');

        $resume = CandidateDocument::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'candidate_id' => $candidate->id,
            'document_type' => CandidateDocument::TYPE_RESUME,
            'file_url' => $resumePath,
            'original_filename' => 'fallback-resume.txt',
            'mime_type' => 'text/plain',
            'file_size_bytes' => Storage::disk('local')->size($resumePath),
            'created_at' => now(),
        ]);

        $this->app->instance(GeminiClient::class, new class extends GeminiClient
        {
            public function __construct()
            {
            }

            public function generate(string $prompt, string $modelName): string
            {
                return (string) json_encode([
                    'hard_skills' => ['Laravel', 'PHP'],
                    'total_years_experience' => '6 years',
                ], JSON_UNESCAPED_UNICODE);
            }
        });

        $request = AiRequest::withoutGlobalScopes()->create([
            'company_id' => (string) $company->id,
            'request_type' => 'cv_parsing',
            'input_hash' => hash('sha256', Str::uuid()->toString()),
            'status' => AiRequest::STATUS_QUEUED,
            'model_name' => 'gemini-test',
            'prompt_version' => CandidateCvParsingPipeline::PARSER_VERSION,
            'request_payload' => array_merge(
                $this->cvParsingPayload(
                    candidateId: (string) $candidate->id,
                    applicationId: (string) $application->id,
                    jobId: (string) $job->id,
                    resumeId: (string) $resume->id,
                    parseSignature: 'resilience-signature-1'
                ),
                [
                    'candidate_snapshot' => [
                        'full_name' => (string) $candidate->full_name,
                        'email' => (string) $candidate->email,
                        'phone' => (string) $candidate->phone,
                        'location' => (string) $candidate->location,
                    ],
                ]
            ),
            'created_at' => now(),
        ]);

        app(AiRequestService::class)->process($request);

        $request->refresh();
        $this->assertSame(AiRequest::STATUS_SUCCEEDED, (string) $request->status);

        $result = \App\Models\CvParsingResult::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('candidate_id', $candidate->id)
            ->where('application_id', $application->id)
            ->first();

        $this->assertNotNull($result);
        $this->assertSame('Fallback Candidate', (string) $result?->parsed_full_name);
        $this->assertSame('fallback-candidate@example.test', (string) $result?->parsed_email);
        $this->assertContains('Laravel', (array) $result?->hard_skills_json);
        $this->assertTrue((bool) data_get($result?->parsed_metadata_json, 'schema_repair_applied', false));
        $this->assertSame('partial', (string) $result?->parse_status);
    }

    public function test_resume_replacement_triggers_reparse_automatically(): void
    {
        Storage::fake('local');

        $company = Company::query()->create([
            'name' => 'CV Replace Co',
            'slug' => 'cv-replace-co',
            'status' => Company::STATUS_ACTIVE,
        ]);

        $job = Job::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'title' => 'Product Manager',
            'status' => Job::STATUS_PUBLISHED,
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
            'full_name' => 'Replace Candidate',
            'email' => 'replace-candidate@example.test',
            'phone' => '+1-555-0404',
            'location' => 'Berlin',
        ]);

        Application::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'candidate_id' => $candidate->id,
            'job_id' => $job->id,
            'current_stage_id' => $stage->id,
            'status' => Application::STATUS_ACTIVE,
            'source_type' => 'career_page',
        ]);

        $pathOne = 'private/candidates/'.(string) $company->id.'/'.(string) $candidate->id.'/resume-v1.txt';
        Storage::disk('local')->put($pathOne, 'Resume v1 content');

        $resume = CandidateDocument::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'candidate_id' => $candidate->id,
            'document_type' => CandidateDocument::TYPE_RESUME,
            'file_url' => $pathOne,
            'original_filename' => 'resume-v1.txt',
            'mime_type' => 'text/plain',
            'file_size_bytes' => Storage::disk('local')->size($pathOne),
            'created_at' => now(),
        ]);

        $this->assertSame(
            1,
            AiRequest::withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->where('request_type', 'cv_parsing')
                ->count()
        );

        $pathTwo = 'private/candidates/'.(string) $company->id.'/'.(string) $candidate->id.'/resume-v2.txt';
        Storage::disk('local')->put($pathTwo, 'Resume v2 updated content with additional experience');

        $resume->forceFill([
            'file_url' => $pathTwo,
            'original_filename' => 'resume-v2.txt',
            'file_size_bytes' => Storage::disk('local')->size($pathTwo),
        ])->save();

        $this->assertSame(
            2,
            AiRequest::withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->where('request_type', 'cv_parsing')
                ->count()
        );

        $this->assertTrue(
            AiRequest::withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->where('request_type', 'cv_parsing')
                ->where('request_payload->parse_trigger', 'cv_update')
                ->exists()
        );
    }

    public function test_referral_conversion_attaches_resume_and_queues_cv_parsing(): void
    {
        Storage::fake('local');

        $company = Company::query()->create([
            'name' => 'Referral Parse Co',
            'slug' => 'referral-parse-co',
            'status' => Company::STATUS_ACTIVE,
        ]);

        $job = Job::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'title' => 'Marketing Lead',
            'status' => Job::STATUS_PUBLISHED,
        ]);

        JobPipelineStage::withoutGlobalScopes()->create([
            'job_id' => $job->id,
            'stage_key' => 'terminal',
            'stage_label' => 'Terminal',
            'display_order' => 1,
            'is_terminal' => true,
        ]);

        JobPipelineStage::withoutGlobalScopes()->create([
            'job_id' => $job->id,
            'stage_key' => 'screening',
            'stage_label' => 'Screening',
            'display_order' => 2,
            'is_terminal' => false,
        ]);

        $actor = User::factory()->create([
            'email_verified_at' => now(),
            'active' => true,
        ]);

        $resumePath = 'private/referrals/'.(string) $company->id.'/'.(string) $actor->id.'/resume.txt';
        Storage::disk('local')->put($resumePath, 'Referral CV for conversion');

        $referral = \App\Models\Referral::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'referrer_user_id' => $actor->id,
            'candidate_email' => 'referral-candidate@example.test',
            'candidate_name' => 'Referral Candidate',
            'candidate_linkedin_url' => null,
            'resume_file_url' => $resumePath,
            'status' => \App\Models\Referral::STATUS_SUBMITTED,
        ]);

        $application = app(ReferralService::class)->convertToApplication($referral, $job, $actor);

        $resume = CandidateDocument::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('candidate_id', $application->candidate_id)
            ->where('document_type', CandidateDocument::TYPE_RESUME)
            ->first();

        $this->assertNotNull($resume);
        $this->assertSame($resumePath, (string) $resume?->file_url);
        $this->assertTrue(
            AiRequest::withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->where('request_type', 'cv_parsing')
                ->where('request_payload->application_id', (string) $application->id)
                ->exists()
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function cvParsingPayload(
        string $candidateId,
        string $applicationId,
        string $jobId,
        string $resumeId,
        string $parseSignature
    ): array {
        return [
            'output_mode' => 'json_schema',
            'candidate_id' => $candidateId,
            'application_id' => $applicationId,
            'job_id' => $jobId,
            'resume_document_id' => $resumeId,
            'source_document_sha256' => hash('sha256', $resumeId),
            'parser_version' => CandidateCvParsingPipeline::PARSER_VERSION,
            'parse_signature' => $parseSignature,
            'parse_trigger' => 'test',
            'prompt' => 'Parse this CV.',
            'json_schema' => [
                'required' => [
                    'summary',
                    'profile',
                    'languages',
                    'hard_skills',
                    'soft_skills',
                    'tools_frameworks',
                    'total_years_experience',
                    'experience',
                    'employment_chronology',
                    'certifications',
                    'projects',
                    'education',
                    'honors',
                    'role_keywords',
                    'parsed_metadata',
                    'flags',
                ],
                'properties' => [
                    'summary' => ['type' => 'string'],
                    'profile' => ['type' => 'object'],
                    'languages' => ['type' => 'array'],
                    'hard_skills' => ['type' => 'array'],
                    'soft_skills' => ['type' => 'array'],
                    'tools_frameworks' => ['type' => 'array'],
                    'total_years_experience' => ['type' => 'number'],
                    'experience' => ['type' => 'array'],
                    'employment_chronology' => ['type' => 'array'],
                    'certifications' => ['type' => 'array'],
                    'projects' => ['type' => 'array'],
                    'education' => ['type' => 'array'],
                    'honors' => ['type' => 'array'],
                    'role_keywords' => ['type' => 'array'],
                    'parsed_metadata' => ['type' => 'object'],
                    'flags' => ['type' => 'object'],
                ],
            ],
        ];
    }
}
