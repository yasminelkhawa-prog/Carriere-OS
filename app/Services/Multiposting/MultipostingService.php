<?php

namespace App\Services\Multiposting;

use App\Jobs\RunApiChannelPublishJob;
use App\Jobs\RunJobBoardAutomationJob;
use App\Models\AiRequest;
use App\Models\CompanyIntegration;
use App\Models\Job;
use App\Models\JobDescriptionBlock;
use App\Models\JobPosting;
use App\Models\JobPostingPublishAttempt;
use App\Models\User;
use App\Services\Ai\AiRequestService;
use App\Support\Multiposting\MultipostingChannelRegistry;
use App\Support\Tracking\JobApplicationUrlGenerator;
use App\Support\Audit\SensitiveEventRecorder;
use Illuminate\Support\Carbon;
use RuntimeException;

class MultipostingService
{
    public function __construct(
        private readonly AiRequestService $aiRequestService,
        private readonly SensitiveEventRecorder $sensitiveEvents,
        private readonly JobApplicationUrlGenerator $trackingUrlGenerator,
        private readonly JobBoardAutomationRunner $automationRunner,
        private readonly MultipostingChannelRegistry $channels,
        private readonly LinkedInApiPublisher $linkedInApiPublisher
    ) {
    }

    public function isSupportedPlatform(string $platform): bool
    {
        return $this->channels->has($platform) && $this->channels->isActive($platform);
    }

    /**
     * @return array<string, mixed>
     */
    public function channelDetail(string $platform): array
    {
        return $this->channels->detail($platform);
    }

    public function supportsCapability(string $platform, string $capability): bool
    {
        return $this->channels->supports($platform, $capability);
    }

    public function deliveryType(string $platform): string
    {
        return $this->channels->deliveryType($platform);
    }

    public function publishMethod(string $platform): string
    {
        return $this->channels->publishMethod($platform);
    }

    public function authMethod(string $platform): string
    {
        return $this->channels->authMethod($platform);
    }

    public function executionMode(string $platform): string
    {
        if ($this->automationRunner->isEnabledForPlatform($platform)) {
            return 'async_automation';
        }

        return $this->channels->executionMode($platform);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function automationDiagnostics(string $platform): ?array
    {
        if (! $this->supportsCapability($platform, 'automation_fallback')) {
            return null;
        }

        return $this->automationRunner->diagnosticsForPlatform($platform);
    }

    /**
     * @return array<string, mixed>
     */
    public function readiness(string $platform, string $companyId): array
    {
        $detail = $this->channelDetail($platform);
        $authMethod = (string) ($detail['auth_method'] ?? 'unknown');

        $payload = [
            'platform' => $platform,
            'ready' => true,
            'reason_key' => 'ready',
            'reason_message' => null,
            'auth_method' => $authMethod,
            'requires_oauth' => $authMethod === 'oauth',
            'integration_status' => null,
            'integration_provider' => $authMethod === 'oauth' ? $platform : null,
        ];

        if (! $this->isSupportedPlatform($platform)) {
            $payload['ready'] = false;
            $payload['reason_key'] = 'unsupported';
            $payload['reason_message'] = 'This channel is not active in the current multiposting scope.';

            return $payload;
        }

        if ($authMethod !== 'oauth') {
            return $payload;
        }

        $integration = CompanyIntegration::query()
            ->where('company_id', $companyId)
            ->where('provider', $platform)
            ->first();

        $payload['integration_status'] = (string) ($integration?->status ?? CompanyIntegration::STATUS_DISCONNECTED);

        if (! $integration instanceof CompanyIntegration || ! $integration->isConnected()) {
            $payload['ready'] = false;
            $payload['reason_key'] = 'oauth_required';
            $payload['reason_message'] = 'A connected company integration is required before this channel can be used.';
        }

        return $payload;
    }

    public function getOrCreatePosting(Job $job, string $platform): JobPosting
    {
        return JobPosting::withoutGlobalScopes()->firstOrCreate(
            [
                'job_id' => (string) $job->id,
                'platform' => $platform,
            ],
            [
                'company_id' => (string) $job->company_id,
                'status' => JobPosting::STATUS_DISABLED,
                'clicks_count' => 0,
            ]
        );
    }

    public function enable(JobPosting $posting, ?User $actor = null): JobPosting
    {
        if ($posting->status === JobPosting::STATUS_DISABLED) {
            return $this->transitionStatus(
                $posting,
                JobPosting::STATUS_DRAFT,
                ['source' => 'toggle_enable'],
                $actor
            );
        }

        return $posting;
    }

    public function disable(JobPosting $posting, ?User $actor = null): JobPosting
    {
        return $this->transitionStatus(
            $posting,
            JobPosting::STATUS_DISABLED,
            ['source' => 'toggle_disable'],
            $actor,
            [
                'tracking_url' => null,
                'posted_at' => null,
            ]
        );
    }

    public function generateContent(JobPosting $posting, ?User $actor = null): JobPosting
    {
        $posting->loadMissing('job.descriptionBlocks');

        if (! $posting->job instanceof Job) {
            throw new RuntimeException('Job context not found for posting generation.');
        }

        $this->assertChannelReadyForUse((string) $posting->platform, (string) $posting->company_id);

        if ($posting->status === JobPosting::STATUS_DISABLED) {
            $posting = $this->enable($posting, $actor);
        }

        $posting = $this->transitionStatus(
            $posting,
            JobPosting::STATUS_GENERATING,
            ['source' => 'generate'],
            $actor
        );

        $request = $this->aiRequestService->queueRequest(
            companyId: (string) $posting->company_id,
            requestType: 'job_multipost_content_adapter',
            requestPayload: [
                'job_id' => (string) $posting->job_id,
                'platform' => (string) $posting->platform,
                'output_mode' => 'json_schema',
                'json_schema' => [
                    'required' => ['adapted_content'],
                    'properties' => [
                        'adapted_content' => ['type' => 'string'],
                    ],
                ],
                'prompt' => $this->buildAdaptationPrompt($posting->job, (string) $posting->platform),
            ],
            promptVersion: 'jobs_multiposting_v1'
        );

        $this->aiRequestService->process($request);
        $request->refresh();

        if ($request->status !== AiRequest::STATUS_SUCCEEDED) {
            $this->transitionStatus(
                $posting,
                JobPosting::STATUS_FAILED,
                [
                    'source' => 'generate',
                    'ai_request_id' => (string) $request->id,
                    'error' => (string) ($request->error_message ?? 'AI content generation failed.'),
                ],
                $actor
            );

            throw new RuntimeException((string) ($request->error_message ?? 'AI content generation failed.'));
        }

        $content = trim((string) data_get($request->response_payload, 'output.adapted_content', ''));
        if ($content === '') {
            $output = data_get($request->response_payload, 'output');
            if (is_string($output)) {
                $content = trim($output);
            }
        }

        if ($content === '') {
            $this->transitionStatus(
                $posting,
                JobPosting::STATUS_FAILED,
                [
                    'source' => 'generate',
                    'ai_request_id' => (string) $request->id,
                    'error' => 'AI content generation returned empty output.',
                ],
                $actor
            );

            throw new RuntimeException('AI content generation returned empty output.');
        }

        return $this->transitionStatus(
            $posting,
            JobPosting::STATUS_READY,
            [
                'source' => 'generate',
                'ai_request_id' => (string) $request->id,
            ],
            $actor,
            ['ai_generated_content' => $content]
        );
    }

    public function saveEditedContent(JobPosting $posting, string $content, ?User $actor = null): JobPosting
    {
        $content = trim($content);
        if ($content === '') {
            throw new RuntimeException('Adapted content is required before publishing.');
        }

        $this->assertChannelReadyForUse((string) $posting->platform, (string) $posting->company_id);

        $attributes = ['ai_generated_content' => $content];
        if ($posting->status === JobPosting::STATUS_PUBLISHED) {
            $attributes['tracking_url'] = null;
            $attributes['posted_at'] = null;
        }

        return $this->transitionStatus(
            $posting,
            JobPosting::STATUS_READY,
            ['source' => 'manual_edit'],
            $actor,
            $attributes
        );
    }

    public function publish(JobPosting $posting, ?User $actor = null): JobPosting
    {
        $posting->loadMissing('job.company');
        $executionMode = $this->executionMode((string) $posting->platform);
        $publishAttempt = null;
        $publishStarted = false;

        try {
            if ($posting->status === JobPosting::STATUS_DISABLED) {
                throw new RuntimeException('Enable this mirror before publishing.');
            }

            $this->assertChannelReadyForUse((string) $posting->platform, (string) $posting->company_id);

            if (trim((string) $posting->ai_generated_content) === '') {
                throw new RuntimeException('Generate or add adapted content before publishing.');
            }

            $publishAttempt = $this->createPublishAttempt(
                posting: $posting,
                executionMode: $executionMode,
                actor: $actor,
                initialStatus: $executionMode === 'async_automation'
                    ? JobPostingPublishAttempt::STATUS_QUEUED
                    : JobPostingPublishAttempt::STATUS_RUNNING
            );

            $posting = $this->transitionStatus(
                $posting,
                JobPosting::STATUS_PUBLISHING,
                [
                    'source' => 'publish',
                    'publish_attempt_id' => (string) $publishAttempt->id,
                ],
                $actor
            );
            $publishStarted = true;

            if (! $posting->job instanceof Job) {
                throw new RuntimeException('Job context not found for publishing.');
            }

            if ($this->supportsNativeApiPush($posting)) {
                $this->dispatchApiPushJob(
                    jobPostingId: (string) $posting->id,
                    publishAttemptId: (string) $publishAttempt->id,
                    actorUserId: $actor?->id ? (string) $actor->id : null
                );

                $this->sensitiveEvents->record(
                    actionType: 'job_posting.api_push_queued',
                    entityType: 'job_posting',
                    entityId: (string) $posting->id,
                    metadata: [
                        'job_id' => (string) $posting->job_id,
                        'platform' => (string) $posting->platform,
                        'publish_attempt_id' => (string) $publishAttempt->id,
                        'execution_mode' => $executionMode,
                    ],
                    actor: $actor
                );

                return $posting->refresh();
            }

            if ($this->automationRunner->isEnabledForPlatform((string) $posting->platform)) {
                RunJobBoardAutomationJob::dispatch(
                    jobPostingId: (string) $posting->id,
                    publishAttemptId: (string) $publishAttempt->id,
                    actorUserId: $actor?->id ? (string) $actor->id : null
                );

                $this->sensitiveEvents->record(
                    actionType: 'job_posting.automation_queued',
                    entityType: 'job_posting',
                    entityId: (string) $posting->id,
                    metadata: [
                        'job_id' => (string) $posting->job_id,
                        'platform' => (string) $posting->platform,
                        'publish_attempt_id' => (string) $publishAttempt->id,
                        'execution_mode' => $executionMode,
                    ],
                    actor: $actor
                );

                return $posting->refresh();
            }

            if ($this->channels->deliveryType((string) $posting->platform) === 'push'
                && $this->channels->supports((string) $posting->platform, 'api_push')
            ) {
                throw new RuntimeException('This push channel requires a dedicated integration flow before publishing.');
            }

            $trackingUrl = $this->buildTrackingUrl($posting);

            $posting = $this->transitionStatus(
                $posting,
                JobPosting::STATUS_PUBLISHED,
                [
                    'source' => 'publish',
                    'publish_attempt_id' => (string) $publishAttempt->id,
                ],
                $actor,
                [
                    'tracking_url' => $trackingUrl,
                    'posted_at' => now(),
                ]
            );

            $this->markPublishAttemptSucceeded(
                posting: $posting,
                attempt: $publishAttempt,
                actor: $actor,
                diagnostics: [
                    'tracking_url' => $trackingUrl,
                ]
            );

            return $posting;
        } catch (\Throwable $exception) {
            $attempt = $publishAttempt instanceof JobPostingPublishAttempt
                ? $publishAttempt
                : $this->createPublishAttempt(
                    posting: $posting,
                    executionMode: $executionMode,
                    actor: $actor,
                    initialStatus: JobPostingPublishAttempt::STATUS_FAILED
                );

            $this->markPublishAttemptFailed(
                posting: $posting,
                attempt: $attempt,
                errorMessage: $exception->getMessage(),
                actor: $actor,
                errorPayload: [
                    'source' => 'publish',
                    'exception' => $exception::class,
                ]
            );

            if ($publishStarted) {
                $this->transitionStatus(
                    $posting,
                    JobPosting::STATUS_FAILED,
                    [
                        'source' => 'publish',
                        'publish_attempt_id' => (string) $attempt->id,
                        'error' => $exception->getMessage(),
                    ],
                    $actor
                );
            }

            throw $exception;
        }
    }

    public function retry(JobPosting $posting, ?User $actor = null): JobPosting
    {
        $latestPublishAttempt = $posting->publishAttempts()->latest('created_at')->first();

        if ($posting->status === JobPosting::STATUS_FAILED
            && trim((string) $posting->ai_generated_content) !== ''
            && $latestPublishAttempt instanceof JobPostingPublishAttempt
        ) {
            return $this->publish($posting, $actor);
        }

        return $this->generateContent($posting, $actor);
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function finalizeQueuedPublishSuccess(
        JobPosting $posting,
        JobPostingPublishAttempt $attempt,
        ?User $actor = null,
        array $metadata = []
    ): JobPosting {
        $trackingUrl = $this->buildTrackingUrl($posting);

        $posting = $this->transitionStatus(
            $posting,
            JobPosting::STATUS_PUBLISHED,
            array_merge([
                'source' => (string) ($metadata['source'] ?? 'queued_publish'),
                'publish_attempt_id' => (string) $attempt->id,
            ], $metadata),
            $actor,
            [
                'tracking_url' => $trackingUrl,
                'posted_at' => now(),
            ]
        );

        $this->markPublishAttemptSucceeded(
            posting: $posting,
            attempt: $attempt,
            actor: $actor,
            diagnostics: array_merge($metadata, [
                'tracking_url' => $trackingUrl,
            ])
        );

        return $posting;
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function finalizeQueuedPublishFailure(
        JobPosting $posting,
        JobPostingPublishAttempt $attempt,
        ?User $actor = null,
        array $metadata = []
    ): JobPosting {
        $posting = $this->transitionStatus(
            $posting,
            JobPosting::STATUS_FAILED,
            array_merge([
                'source' => (string) ($metadata['source'] ?? 'queued_publish'),
                'publish_attempt_id' => (string) $attempt->id,
            ], $metadata),
            $actor
        );

        $this->markPublishAttemptFailed(
            posting: $posting,
            attempt: $attempt,
            errorMessage: (string) ($metadata['error'] ?? 'Automation worker failed.'),
            actor: $actor,
            errorPayload: $metadata
        );

        return $posting;
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function finalizeAutomatedPublishSuccess(
        JobPosting $posting,
        JobPostingPublishAttempt $attempt,
        ?User $actor = null,
        array $metadata = []
    ): JobPosting {
        return $this->finalizeQueuedPublishSuccess($posting, $attempt, $actor, array_merge([
            'source' => 'automation_publish',
        ], $metadata));
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function finalizeAutomatedPublishFailure(
        JobPosting $posting,
        JobPostingPublishAttempt $attempt,
        ?User $actor = null,
        array $metadata = []
    ): JobPosting {
        return $this->finalizeQueuedPublishFailure($posting, $attempt, $actor, array_merge([
            'source' => 'automation_publish',
        ], $metadata));
    }

    public function buildTrackingUrl(JobPosting $posting): string
    {
        return $this->trackingUrlGenerator->forJobPostingTracking($posting, 'job_board');
    }

    public function resolvePublishAttempt(string $attemptId): ?JobPostingPublishAttempt
    {
        return JobPostingPublishAttempt::query()->find($attemptId);
    }

    public function markPublishAttemptRunning(JobPostingPublishAttempt $attempt): JobPostingPublishAttempt
    {
        $attempt->forceFill([
            'status' => JobPostingPublishAttempt::STATUS_RUNNING,
            'started_at' => $attempt->started_at ?? now(),
        ])->save();

        return $attempt->refresh();
    }

    private function supportsNativeApiPush(JobPosting $posting): bool
    {
        $platform = (string) $posting->platform;

        if ($this->channels->deliveryType($platform) !== 'push' || ! $this->channels->supports($platform, 'api_push')) {
            return false;
        }

        return match ($platform) {
            'linkedin' => $this->linkedInApiPublisher->isConfigured() && $this->linkedInApiPublisher->isReadyForPosting($posting),
            default => false,
        };
    }

    private function dispatchApiPushJob(string $jobPostingId, string $publishAttemptId, ?string $actorUserId = null): void
    {
        $job = new RunApiChannelPublishJob($jobPostingId, $publishAttemptId, $actorUserId);

        if (! $this->shouldAutoProcessApiPushAfterResponse()) {
            dispatch($job);

            return;
        }

        app()->terminating(function () use ($job): void {
            app()->call([$job, 'handle']);
        });
    }

    public function shouldAutoProcessApiPushAfterResponse(): bool
    {
        return (bool) config('multiposting.api_push.auto_after_response', true);
    }

    private function assertChannelReadyForUse(string $platform, string $companyId): void
    {
        $readiness = $this->readiness($platform, $companyId);

        if (! (bool) ($readiness['ready'] ?? false)) {
            throw new RuntimeException((string) ($readiness['reason_message'] ?? 'This channel is not ready for use.'));
        }
    }

    /**
     * @param array<string, mixed> $metadata
     * @param array<string, mixed> $attributes
     */
    private function transitionStatus(
        JobPosting $posting,
        string $toStatus,
        array $metadata = [],
        ?User $actor = null,
        array $attributes = []
    ): JobPosting {
        $fromStatus = (string) ($posting->status ?? '');

        $posting->forceFill(array_merge($attributes, ['status' => $toStatus]))->save();

        if ($fromStatus !== $toStatus) {
            $this->sensitiveEvents->jobPostingStatusChanged(
                jobPostingId: (string) $posting->id,
                metadata: array_merge($metadata, [
                    'job_id' => (string) $posting->job_id,
                    'platform' => (string) $posting->platform,
                    'from_status' => $fromStatus,
                    'to_status' => $toStatus,
                ]),
                actor: $actor
            );
        }

        return $posting->refresh();
    }

    private function createPublishAttempt(
        JobPosting $posting,
        string $executionMode,
        ?User $actor,
        string $initialStatus
    ): JobPostingPublishAttempt {
        $attemptNumber = (int) JobPostingPublishAttempt::query()
            ->where('job_posting_id', (string) $posting->id)
            ->max('attempt_number') + 1;

        $timestamp = now();

        $attempt = JobPostingPublishAttempt::query()->create([
            'company_id' => (string) $posting->company_id,
            'job_posting_id' => (string) $posting->id,
            'initiated_by_user_id' => $actor?->id ? (string) $actor->id : null,
            'platform' => (string) $posting->platform,
            'attempt_number' => $attemptNumber,
            'status' => $initialStatus,
            'execution_mode' => $executionMode,
            'queued_at' => $timestamp,
            'started_at' => $initialStatus === JobPostingPublishAttempt::STATUS_RUNNING ? $timestamp : null,
            'finished_at' => in_array($initialStatus, [JobPostingPublishAttempt::STATUS_SUCCEEDED, JobPostingPublishAttempt::STATUS_FAILED], true)
                ? $timestamp
                : null,
        ]);

        $this->updatePostingObservabilitySnapshot(
            posting: $posting,
            status: $initialStatus,
            executionMode: $executionMode,
            errorMessage: null,
            attemptedAt: $timestamp,
            succeededAt: $initialStatus === JobPostingPublishAttempt::STATUS_SUCCEEDED ? $timestamp : null
        );

        return $attempt;
    }

    /**
     * @param array<string, mixed> $diagnostics
     */
    private function markPublishAttemptSucceeded(
        JobPosting $posting,
        JobPostingPublishAttempt $attempt,
        ?User $actor = null,
        array $diagnostics = []
    ): void {
        $timestamp = now();

        $attempt->forceFill([
            'status' => JobPostingPublishAttempt::STATUS_SUCCEEDED,
            'started_at' => $attempt->started_at ?? $timestamp,
            'finished_at' => $timestamp,
            'error_message' => null,
            'error_payload_json' => null,
            'external_url' => data_get($diagnostics, 'external_url'),
            'diagnostics_json' => $diagnostics !== [] ? $diagnostics : null,
        ])->save();

        $this->updatePostingObservabilitySnapshot(
            posting: $posting,
            status: JobPostingPublishAttempt::STATUS_SUCCEEDED,
            executionMode: (string) ($attempt->execution_mode ?? $this->executionMode((string) $posting->platform)),
            errorMessage: null,
            attemptedAt: $attempt->queued_at instanceof Carbon ? $attempt->queued_at : $timestamp,
            succeededAt: $timestamp
        );

        if ($actor instanceof User) {
            $this->sensitiveEvents->record(
                actionType: 'job_posting.publish_attempt_succeeded',
                entityType: 'job_posting_publish_attempt',
                entityId: (string) $attempt->id,
                metadata: [
                    'job_posting_id' => (string) $posting->id,
                    'platform' => (string) $posting->platform,
                    'attempt_number' => (int) $attempt->attempt_number,
                    'execution_mode' => (string) ($attempt->execution_mode ?? ''),
                ],
                actor: $actor
            );
        }
    }

    /**
     * @param array<string, mixed> $errorPayload
     */
    private function markPublishAttemptFailed(
        JobPosting $posting,
        JobPostingPublishAttempt $attempt,
        string $errorMessage,
        ?User $actor = null,
        array $errorPayload = []
    ): void {
        $timestamp = now();

        $attempt->forceFill([
            'status' => JobPostingPublishAttempt::STATUS_FAILED,
            'started_at' => $attempt->started_at ?? $timestamp,
            'finished_at' => $timestamp,
            'error_message' => $errorMessage,
            'error_payload_json' => $errorPayload !== [] ? $errorPayload : null,
            'diagnostics_json' => $errorPayload !== [] ? $errorPayload : null,
        ])->save();

        $this->updatePostingObservabilitySnapshot(
            posting: $posting,
            status: JobPostingPublishAttempt::STATUS_FAILED,
            executionMode: (string) ($attempt->execution_mode ?? $this->executionMode((string) $posting->platform)),
            errorMessage: $errorMessage,
            attemptedAt: $attempt->queued_at instanceof Carbon ? $attempt->queued_at : $timestamp,
            succeededAt: null
        );

        if ($actor instanceof User) {
            $this->sensitiveEvents->record(
                actionType: 'job_posting.publish_attempt_failed',
                entityType: 'job_posting_publish_attempt',
                entityId: (string) $attempt->id,
                metadata: [
                    'job_posting_id' => (string) $posting->id,
                    'platform' => (string) $posting->platform,
                    'attempt_number' => (int) $attempt->attempt_number,
                    'execution_mode' => (string) ($attempt->execution_mode ?? ''),
                    'error' => $errorMessage,
                ],
                actor: $actor
            );
        }
    }

    private function updatePostingObservabilitySnapshot(
        JobPosting $posting,
        string $status,
        string $executionMode,
        ?string $errorMessage,
        Carbon $attemptedAt,
        ?Carbon $succeededAt
    ): void {
        $posting->forceFill([
            'last_publish_attempted_at' => $attemptedAt,
            'last_publish_succeeded_at' => $succeededAt ?? $posting->last_publish_succeeded_at,
            'last_publish_status' => $status,
            'last_execution_mode' => $executionMode,
            'last_publish_error' => $errorMessage,
        ])->save();
    }

    private function buildAdaptationPrompt(Job $job, string $platform): string
    {
        $platformHints = [
            'linkedin' => 'Use a professional tone with concise, scannable bullets and a strong employer brand angle.',
            'indeed' => 'Use plain, direct language focused on responsibilities, requirements, and clear apply motivation.',
            'glassdoor' => 'Use transparent language with role expectations and candidate-centric clarity.',
            'monster' => 'Use energetic, action-oriented language and clear fit criteria.',
        ];

        $job->loadMissing('descriptionBlocks');

        $blocks = $job->descriptionBlocks
            ->map(function (JobDescriptionBlock $block): string {
                $text = data_get($block->block_content_json, 'text');
                if (! is_string($text) || trim($text) === '') {
                    $text = json_encode($block->block_content_json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }

                return strtoupper($block->block_type).': '.trim((string) $text);
            })
            ->implode("\n");

        return trim(implode("\n\n", [
            'Adapt the following job content for job board multiposting.',
            'Target platform: '.$platform,
            $platformHints[$platform] ?? 'Keep the language clear, concise, and conversion-focused.',
            'Output requirements:',
            '- Return strict JSON with key adapted_content only.',
            '- Keep the content truthful to the source role and do not fabricate details.',
            '- Keep the final output under 1800 characters.',
            'Source job:',
            'Title: '.$job->title,
            'Location: '.((string) ($job->location ?: 'Not specified')),
            $blocks !== '' ? $blocks : 'No structured description blocks available.',
        ]));
    }
}
