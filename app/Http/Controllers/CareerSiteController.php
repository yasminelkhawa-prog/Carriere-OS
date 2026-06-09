<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\ApplicationActivityEvent;
use App\Models\ApplicationStageHistory;
use App\Models\Candidate;
use App\Models\CandidateDocument;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\Job;
use App\Models\JobPipelineStage;
use App\Models\Profile;
use App\Models\User;
use App\Services\Communication\CommunicationEngineService;
use App\Services\Cv\CandidateCvParsingPipeline;
use App\Support\Audit\SensitiveEventRecorder;
use App\Support\Seo\JobPostingSchemaBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use JsonException;
use Illuminate\View\View;
use Throwable;

class CareerSiteController extends Controller
{
    public function __construct(
        private readonly CandidateCvParsingPipeline $cvParsingPipeline,
        private readonly CommunicationEngineService $communicationEngine,
        private readonly SensitiveEventRecorder $sensitiveEvents,
        private readonly JobPostingSchemaBuilder $jobPostingSchemaBuilder
    ) {
    }

    public function index(Request $request, Company $company): View
    {
        abort_unless($company->status === Company::STATUS_ACTIVE, 404);

        $filters = $request->validate([
            'department_id' => ['nullable', 'uuid'],
            'location' => ['nullable', 'string', 'max:255'],
        ]);

        $departmentId = $filters['department_id'] ?? null;
        $location = trim((string) ($filters['location'] ?? ''));

        $jobsQuery = Job::withoutGlobalScopes()
            ->with('department:id,name')
            ->where('company_id', $company->id)
            ->where('status', Job::STATUS_PUBLISHED)
            ->when($departmentId !== null, fn ($query) => $query->where('department_id', $departmentId))
            ->when($location !== '', fn ($query) => $query->where('location', $location));

        $jobs = $jobsQuery
            ->orderByDesc('created_at')
            ->paginate(12)
            ->withQueryString();

        $departments = $company->departments()
            ->whereIn('id', function ($query) use ($company): void {
                $query->select('department_id')
                    ->from('jobs')
                    ->where('company_id', $company->id)
                    ->where('status', Job::STATUS_PUBLISHED)
                    ->whereNotNull('department_id');
            })
            ->orderBy('name')
            ->get(['id', 'name']);

        $locations = Job::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('status', Job::STATUS_PUBLISHED)
            ->whereNotNull('location')
            ->distinct()
            ->orderBy('location')
            ->pluck('location');

        $appliedJobIds = $this->resolveCandidateAppliedJobIds(
            $request->user(),
            $company,
            $jobs->getCollection()->pluck('id')
        );

        return view('career.index', [
            'company' => $company,
            'jobs' => $jobs,
            'departments' => $departments,
            'locations' => $locations,
            'selectedDepartmentId' => $departmentId,
            'selectedLocation' => $location,
            'appliedJobIds' => $appliedJobIds,
        ]);
    }

    public function show(Request $request, Company $company, Job $job): View
    {
        abort_unless($company->status === Company::STATUS_ACTIVE, 404);
        abort_unless((string) $job->company_id === (string) $company->id, 404);
        abort_unless($job->status === Job::STATUS_PUBLISHED, 404);

        $job->load(['department', 'descriptionBlocks']);
        $hasAppliedForJob = $this->hasCandidateAppliedForJob($request->user(), $company, $job);

        return view('career.show', [
            'company' => $company,
            'job' => $job,
            'hasAppliedForJob' => $hasAppliedForJob,
            'jobPostingSchema' => $this->jobPostingSchemaBuilder->build(
                job: $job,
                company: $company,
                publicUrl: route('career.show', ['company' => $company, 'job' => $job])
            ),
        ]);
    }

    public function apply(Request $request, Company $company, Job $job): RedirectResponse
    {
        abort_unless($company->status === Company::STATUS_ACTIVE, 404);
        abort_unless((string) $job->company_id === (string) $company->id, 404);
        abort_unless($job->status === Job::STATUS_PUBLISHED, 404);

        if ($this->hasCandidateAppliedForJob($request->user(), $company, $job)) {
            throw ValidationException::withMessages([
                'email' => __('career.apply.errors.already_applied'),
            ]);
        }

        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'phone' => ['nullable', 'string', 'max:60'],
            'location' => ['nullable', 'string', 'max:255'],
            'referral_code' => ['nullable', 'string', 'max:120'],
            'assistant_answers_json' => ['nullable', 'string', 'max:10000'],
            'resume' => ['required', 'file', 'mimes:pdf', 'mimetypes:application/pdf', 'max:5120'],
            'portfolio' => ['nullable', 'file', 'extensions:pdf,doc,docx,rtf,txt,zip', 'max:10240'],
            'consent' => ['required', 'accepted'],
            'utm_source' => ['nullable', 'string', 'max:255'],
            'utm_medium' => ['nullable', 'string', 'max:255'],
            'utm_campaign' => ['nullable', 'string', 'max:255'],
        ], [
            'consent.required' => __('career.apply.consent.required'),
            'consent.accepted' => __('career.apply.consent.required'),
            'password.required' => __('career.apply.errors.password_required'),
            'password.min' => __('career.apply.errors.password_min'),
            'password.confirmed' => __('career.apply.errors.password_confirmed'),
            'resume.required' => __('career.apply.errors.resume_required'),
            'resume.file' => __('career.apply.errors.resume_file'),
            'resume.mimes' => __('career.apply.errors.resume_mimes'),
            'resume.mimetypes' => __('career.apply.errors.resume_mimes'),
            'resume.max' => __('career.apply.errors.resume_max'),
            'portfolio.file' => __('career.apply.errors.portfolio_file'),
            'portfolio.extensions' => __('career.apply.errors.portfolio_mimes'),
            'portfolio.max' => __('career.apply.errors.portfolio_max'),
        ]);

        $firstNonTerminalStage = $this->firstApplicationStageFor($job);

        $normalizedEmail = Str::lower(trim((string) $validated['email']));
        $resumeFile = $request->file('resume');
        $portfolioFile = $request->file('portfolio');
        $assistantTranscript = $this->decodeAssistantTranscript($validated['assistant_answers_json'] ?? null);

        $application = DB::transaction(function () use ($company, $job, $firstNonTerminalStage, $validated, $normalizedEmail, $resumeFile, $portfolioFile, $assistantTranscript): Application {
            $user = User::query()->whereRaw('LOWER(email) = ?', [$normalizedEmail])->first();

            if (! $user instanceof User) {
                $user = User::query()->create([
                    'email' => $normalizedEmail,
                    'password' => Hash::make((string) $validated['password']),
                    'platform_role' => User::PLATFORM_NONE,
                    'active' => true,
                    'email_verified_at' => null,
                ]);
            }

            Profile::query()->firstOrCreate(
                ['user_id' => $user->id],
                [
                    'full_name' => $validated['full_name'],
                    'avatar_url' => null,
                    'locale' => in_array(app()->getLocale(), ['en', 'fr'], true) ? app()->getLocale() : 'en',
                ]
            );

            CompanyMembership::query()->firstOrCreate(
                ['company_id' => $company->id, 'user_id' => $user->id],
                [
                    'company_role' => CompanyMembership::ROLE_CANDIDATE,
                    'membership_status' => CompanyMembership::STATUS_ACTIVE,
                ]
            );

            $candidate = Candidate::withoutGlobalScopes()->firstOrCreate(
                ['company_id' => $company->id, 'email' => $normalizedEmail],
                [
                    'user_id' => $user->id,
                    'full_name' => $validated['full_name'],
                    'phone' => $validated['phone'] ?? null,
                    'location' => $validated['location'] ?? null,
                ]
            );

            $candidate->forceFill([
                'user_id' => $candidate->user_id ?: $user->id,
                'full_name' => $validated['full_name'],
                'phone' => $validated['phone'] ?? null,
                'location' => $validated['location'] ?? null,
            ])->save();

            $activeDuplicate = Application::withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->where('candidate_id', $candidate->id)
                ->where('job_id', $job->id)
                ->where('status', Application::STATUS_ACTIVE)
                ->exists();

            if ($activeDuplicate) {
                throw ValidationException::withMessages([
                    'email' => __('career.apply.errors.duplicate_active'),
                ]);
            }

            $application = Application::withoutGlobalScopes()->create([
                'company_id' => $company->id,
                'candidate_id' => $candidate->id,
                'job_id' => $job->id,
                'current_stage_id' => $firstNonTerminalStage->id,
                'status' => Application::STATUS_ACTIVE,
                'source_type' => ($validated['referral_code'] ?? null)
                    ? 'referral'
                    : (($validated['utm_source'] ?? null) ? 'job_board' : 'career_page'),
                'source_detail' => ($validated['referral_code'] ?? null) ?: null,
                'utm_source' => $validated['utm_source'] ?? null,
                'utm_campaign' => $validated['utm_campaign'] ?? null,
                'utm_medium' => $validated['utm_medium'] ?? null,
            ]);

            $resumePath = $resumeFile->store("private/candidates/{$company->id}/{$candidate->id}", 'local');

            $resumeDocument = CandidateDocument::withoutGlobalScopes()->create([
                'company_id' => $company->id,
                'candidate_id' => $candidate->id,
                'document_type' => CandidateDocument::TYPE_RESUME,
                'file_url' => $resumePath,
                'original_filename' => (string) $resumeFile->getClientOriginalName(),
                'mime_type' => (string) $resumeFile->getMimeType(),
                'file_size_bytes' => (int) $resumeFile->getSize(),
                'created_at' => now(),
            ]);

            if ($portfolioFile !== null) {
                $portfolioPath = $portfolioFile->store("private/candidates/{$company->id}/{$candidate->id}", 'local');

                CandidateDocument::withoutGlobalScopes()->create([
                    'company_id' => $company->id,
                    'candidate_id' => $candidate->id,
                    'document_type' => CandidateDocument::TYPE_PORTFOLIO,
                    'file_url' => $portfolioPath,
                    'original_filename' => (string) $portfolioFile->getClientOriginalName(),
                    'mime_type' => (string) $portfolioFile->getMimeType(),
                    'file_size_bytes' => (int) $portfolioFile->getSize(),
                    'created_at' => now(),
                ]);
            }

            ApplicationActivityEvent::withoutGlobalScopes()->create([
                'company_id' => $company->id,
                'application_id' => $application->id,
                'event_type' => 'application.created',
                'payload' => ['source_type' => 'career_site'],
                'actor_user_id' => null,
                'created_at' => now(),
            ]);

            ApplicationStageHistory::withoutGlobalScopes()->create([
                'company_id' => $company->id,
                'application_id' => $application->id,
                'from_stage_id' => null,
                'to_stage_id' => $firstNonTerminalStage->id,
                'actor_user_id' => null,
                'reason' => null,
                'created_at' => now(),
            ]);

            ApplicationActivityEvent::withoutGlobalScopes()->create([
                'company_id' => $company->id,
                'application_id' => $application->id,
                'event_type' => 'document.uploaded',
                'payload' => ['document_id' => $resumeDocument->id, 'document_type' => CandidateDocument::TYPE_RESUME],
                'actor_user_id' => null,
                'created_at' => now(),
            ]);

            if ($assistantTranscript !== []) {
                ApplicationActivityEvent::withoutGlobalScopes()->create([
                    'company_id' => $company->id,
                    'application_id' => $application->id,
                    'event_type' => 'application.assistant_completed',
                    'payload' => [
                        'transcript' => $assistantTranscript,
                    ],
                    'actor_user_id' => null,
                    'created_at' => now(),
                ]);
            }

            $this->queueCvParsing(
                application: $application,
                resumeDocument: $resumeDocument
            );

            $this->sensitiveEvents->stageChanged(
                applicationId: (string) $application->id,
                metadata: [
                    'from_stage_id' => null,
                    'to_stage_id' => (string) $firstNonTerminalStage->id,
                    'source' => 'career_site_apply',
                ],
                actor: null
            );

            return $application;
        });

        $application->loadMissing('candidate');
        $this->sendApplicationAcknowledgement(
            company: $company,
            job: $job,
            application: $application
        );

        return redirect()->route('career.apply.confirmation', [
            'company' => $company,
            'job' => $job,
            'application' => $application->id,
        ]);
    }

    public function confirmation(Request $request, Company $company, Job $job): View
    {
        abort_unless($company->status === Company::STATUS_ACTIVE, 404);
        abort_unless((string) $job->company_id === (string) $company->id, 404);

        $applicationId = (string) $request->query('application', '');
        $application = null;

        if ($applicationId !== '') {
            $application = Application::withoutGlobalScopes()
                ->where('id', $applicationId)
                ->where('company_id', $company->id)
                ->where('job_id', $job->id)
                ->first();
        }

        return view('career.confirmation', [
            'company' => $company,
            'job' => $job,
            'application' => $application,
        ]);
    }

    private function queueCvParsing(Application $application, CandidateDocument $resumeDocument): void
    {
        try {
            $this->cvParsingPipeline->queueForApplication(
                application: $application,
                resumeDocument: $resumeDocument,
                trigger: 'cv_upload'
            );
        } catch (Throwable $exception) {
            Log::warning('CV parsing queue failed after application submission.', [
                'company_id' => (string) $application->company_id,
                'candidate_id' => (string) $application->candidate_id,
                'application_id' => (string) $application->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function firstApplicationStageFor(Job $job): JobPipelineStage
    {
        $stage = JobPipelineStage::query()
            ->where('job_id', $job->id)
            ->where('is_terminal', false)
            ->orderBy('display_order')
            ->first();

        if ($stage instanceof JobPipelineStage) {
            return $stage;
        }

        return DB::transaction(function () use ($job): JobPipelineStage {
            $stage = JobPipelineStage::withoutGlobalScopes()->firstOrCreate(
                [
                    'job_id' => (string) $job->id,
                    'stage_key' => 'applied',
                ],
                [
                    'stage_label' => __('jobs.default_stages.applied'),
                    'display_order' => 1,
                    'is_terminal' => false,
                ]
            );

            JobPipelineStage::withoutGlobalScopes()->firstOrCreate(
                [
                    'job_id' => (string) $job->id,
                    'stage_key' => 'rejected',
                ],
                [
                    'stage_label' => __('candidates.statuses.rejected'),
                    'display_order' => 99,
                    'is_terminal' => true,
                ]
            );

            return $stage;
        });
    }

    private function sendApplicationAcknowledgement(Company $company, Job $job, Application $application): void
    {
        $application->loadMissing('candidate.user.profile');

        $candidateEmail = trim((string) ($application->candidate?->email ?? ''));
        if ($candidateEmail === '') {
            Log::warning('Application acknowledgement skipped because candidate email is missing.', [
                'company_id' => (string) $company->id,
                'application_id' => (string) $application->id,
            ]);
            return;
        }

        try {
            $messageId = (string) Str::uuid();
            $verificationUrl = $this->candidateVerificationAccessUrl($company, $application);
            $locale = (string) ($application->candidate?->user?->profile?->locale ?? config('app.locale', 'en'));
            $outcome = $this->communicationEngine->queueTemplateEmail(
                companyId: (string) $company->id,
                templateKey: 'application_portal_verification',
                toEmail: $candidateEmail,
                toName: (string) ($application->candidate?->full_name ?? ''),
                language: $locale,
                variables: [
                    'candidate_name' => (string) ($application->candidate?->full_name ?? ''),
                    'job_title' => (string) $job->title,
                    'company_name' => (string) $company->name,
                    'application_reference' => (string) $application->id,
                    'verification_url' => $verificationUrl,
                ],
                relatedEntityType: 'application',
                relatedEntityId: (string) $application->id
            );

            if (! $outcome['ok']) {
                Log::warning('Failed to queue candidate application acknowledgement email.', [
                    'company_id' => (string) $company->id,
                    'application_id' => (string) $application->id,
                    'candidate_email' => $candidateEmail,
                    'error' => (string) ($outcome['error'] ?? 'Unknown communication failure'),
                ]);
                return;
            }

            $payload = [
                'message_id' => $messageId,
                'template' => 'application_portal_verification',
                'recipient' => $candidateEmail,
                'application_id' => (string) $application->id,
                'outbox_id' => (string) ($outcome['log']?->id ?? ''),
            ];

            ApplicationActivityEvent::withoutGlobalScopes()->create([
                'company_id' => (string) $application->company_id,
                'application_id' => (string) $application->id,
                'event_type' => 'email.sent',
                'payload' => $payload,
                'actor_user_id' => null,
                'created_at' => now(),
            ]);

            $this->sensitiveEvents->emailSent($messageId, $payload, null);
        } catch (Throwable $exception) {
            Log::warning('Failed to queue candidate application acknowledgement email.', [
                'company_id' => (string) $company->id,
                'application_id' => (string) $application->id,
                'candidate_email' => $candidateEmail,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function candidateVerificationAccessUrl(Company $company, Application $application): string
    {
        $candidateUser = $application->candidate?->user;
        if (! $candidateUser instanceof User) {
            return route('login');
        }

        return URL::temporarySignedRoute(
            'candidate.email.verify-login',
            now()->addMinutes((int) config('auth.verification.expire', 60)),
            [
                'user' => (string) $candidateUser->id,
                'company' => (string) $company->id,
                'application' => (string) $application->id,
                'hash' => sha1($candidateUser->getEmailForVerification()),
            ]
        );
    }

    /**
     * @return array<int, array{question: string, answer: string}>
     */
    private function decodeAssistantTranscript(?string $rawTranscript): array
    {
        $raw = trim((string) $rawTranscript);
        if ($raw === '') {
            return [];
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw ValidationException::withMessages([
                'assistant_answers_json' => __('career.apply.errors.assistant_answers_invalid'),
            ]);
        }

        if (! is_array($decoded)) {
            throw ValidationException::withMessages([
                'assistant_answers_json' => __('career.apply.errors.assistant_answers_invalid'),
            ]);
        }

        return collect($decoded)
            ->filter(fn ($item): bool => is_array($item))
            ->map(function (array $item): ?array {
                $question = trim((string) ($item['question'] ?? ''));
                $answer = trim((string) ($item['answer'] ?? ''));

                if ($question === '' || $answer === '') {
                    return null;
                }

                return [
                    'question' => Str::limit($question, 200, ''),
                    'answer' => Str::limit($answer, 1000, ''),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param Collection<int, string>|null $jobIds
     * @return array<int, string>
     */
    private function resolveCandidateAppliedJobIds(?User $user, Company $company, ?Collection $jobIds = null): array
    {
        $candidate = $this->resolveCandidateActor($user, $company);
        if (! $candidate instanceof Candidate) {
            return [];
        }

        $query = Application::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('candidate_id', $candidate->id);

        if ($jobIds instanceof Collection && $jobIds->isNotEmpty()) {
            $query->whereIn('job_id', $jobIds->all());
        }

        return $query
            ->pluck('job_id')
            ->map(fn ($jobId): string => (string) $jobId)
            ->unique()
            ->values()
            ->all();
    }

    private function hasCandidateAppliedForJob(?User $user, Company $company, Job $job): bool
    {
        $candidate = $this->resolveCandidateActor($user, $company);
        if (! $candidate instanceof Candidate) {
            return false;
        }

        return Application::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('candidate_id', $candidate->id)
            ->where('job_id', $job->id)
            ->exists();
    }

    private function resolveCandidateActor(?User $user, Company $company): ?Candidate
    {
        if (! $user instanceof User) {
            return null;
        }

        $hasCandidateMembership = CompanyMembership::query()
            ->where('company_id', $company->id)
            ->where('user_id', $user->id)
            ->where('membership_status', CompanyMembership::STATUS_ACTIVE)
            ->where('company_role', CompanyMembership::ROLE_CANDIDATE)
            ->exists();

        if (! $hasCandidateMembership) {
            return null;
        }

        return Candidate::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('user_id', $user->id)
            ->first();
    }
}
