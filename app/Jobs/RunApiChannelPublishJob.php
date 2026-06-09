<?php

namespace App\Jobs;

use App\Models\JobPosting;
use App\Models\JobPostingPublishAttempt;
use App\Models\User;
use App\Services\Multiposting\LinkedInApiPublisher;
use App\Services\Multiposting\MultipostingService;
use App\Support\Audit\SensitiveEventRecorder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;

class RunApiChannelPublishJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public function __construct(
        public readonly string $jobPostingId,
        public readonly string $publishAttemptId,
        public readonly ?string $actorUserId = null
    ) {
        $this->onQueue((string) config('multiposting.api_push.queue', 'default'));
    }

    public function handle(
        LinkedInApiPublisher $linkedInPublisher,
        MultipostingService $multipostingService,
        SensitiveEventRecorder $sensitiveEvents
    ): void {
        $posting = JobPosting::withoutGlobalScopes()->find($this->jobPostingId);
        $attempt = $multipostingService->resolvePublishAttempt($this->publishAttemptId);

        if (! $posting instanceof JobPosting || ! $attempt instanceof JobPostingPublishAttempt) {
            return;
        }

        $actor = $this->resolveActor();

        $multipostingService->markPublishAttemptRunning($attempt);

        try {
            $result = match ((string) $posting->platform) {
                'linkedin' => $linkedInPublisher->publish($posting),
                default => throw new RuntimeException('No API push publisher is registered for this platform.'),
            };
        } catch (\Throwable $exception) {
            $multipostingService->finalizeQueuedPublishFailure(
                posting: $posting,
                attempt: $attempt,
                actor: $actor,
                metadata: [
                    'error' => $exception->getMessage(),
                    'provider' => (string) $posting->platform,
                    'source' => 'api_push',
                ]
            );

            $sensitiveEvents->record(
                actionType: 'job_posting.api_push_failed',
                entityType: 'job_posting',
                entityId: (string) $posting->id,
                metadata: [
                    'platform' => (string) $posting->platform,
                    'publish_attempt_id' => (string) $attempt->id,
                    'error' => $exception->getMessage(),
                ],
                actor: $actor
            );

            throw $exception;
        }

        if ((bool) ($result['pending_sync'] ?? false) && trim((string) ($result['task_urn'] ?? '')) !== '') {
            $attempt->forceFill([
                'diagnostics_json' => [
                    'provider' => (string) $posting->platform,
                    'source' => 'api_push_submission',
                    'task_urn' => (string) $result['task_urn'],
                    'task_status' => (string) ($result['task_status'] ?? 'IN_PROGRESS'),
                    'payload' => (array) ($result['payload'] ?? []),
                    'response' => (array) ($result['response'] ?? []),
                    'endpoint' => (string) ($result['endpoint'] ?? ''),
                ],
            ])->save();

            if ($multipostingService->shouldAutoProcessApiPushAfterResponse()) {
                $this->runInlineTaskSync(
                    posting: $posting,
                    attempt: $attempt,
                    actor: $actor,
                    taskUrn: (string) $result['task_urn'],
                    linkedInPublisher: $linkedInPublisher,
                    multipostingService: $multipostingService,
                    sensitiveEvents: $sensitiveEvents
                );

                return;
            }

            SyncLinkedInJobPostingStatusJob::dispatch(
                jobPostingId: (string) $posting->id,
                publishAttemptId: (string) $attempt->id,
                taskUrn: (string) $result['task_urn'],
                checkNumber: 1,
                actorUserId: $actor?->id ? (string) $actor->id : null
            )->delay(now()->addSeconds((int) config('services.linkedin.job_posting_task_initial_delay_seconds', 60)));

            $sensitiveEvents->record(
                actionType: 'job_posting.api_push_task_sync_queued',
                entityType: 'job_posting',
                entityId: (string) $posting->id,
                metadata: [
                    'platform' => (string) $posting->platform,
                    'publish_attempt_id' => (string) $attempt->id,
                    'task_urn' => (string) $result['task_urn'],
                ],
                actor: $actor
            );

            return;
        }

        $multipostingService->finalizeQueuedPublishSuccess(
            posting: $posting,
            attempt: $attempt,
            actor: $actor,
            metadata: [
                'provider' => (string) $posting->platform,
                'source' => 'api_push',
                'external_id' => (string) ($result['external_id'] ?? ''),
                'external_url' => (string) ($result['external_url'] ?? ''),
                'payload' => (array) ($result['payload'] ?? []),
                'response' => (array) ($result['response'] ?? []),
                'endpoint' => (string) ($result['endpoint'] ?? ''),
            ]
        );

        $sensitiveEvents->record(
            actionType: 'job_posting.api_push_succeeded',
            entityType: 'job_posting',
            entityId: (string) $posting->id,
            metadata: [
                'platform' => (string) $posting->platform,
                'publish_attempt_id' => (string) $attempt->id,
                'external_id' => (string) ($result['external_id'] ?? ''),
                'external_url' => (string) ($result['external_url'] ?? ''),
            ],
            actor: $actor
        );
    }

    private function runInlineTaskSync(
        JobPosting $posting,
        JobPostingPublishAttempt $attempt,
        ?User $actor,
        string $taskUrn,
        LinkedInApiPublisher $linkedInPublisher,
        MultipostingService $multipostingService,
        SensitiveEventRecorder $sensitiveEvents
    ): void {
        $maxChecks = (int) config('services.linkedin.job_posting_task_max_checks', 5);
        $delaySeconds = (int) config('services.linkedin.job_posting_task_initial_delay_seconds', 60);
        $retryDelaySeconds = (int) config('services.linkedin.job_posting_task_retry_delay_seconds', 120);

        for ($check = 1; $check <= $maxChecks; $check++) {
            sleep(max(1, $check === 1 ? $delaySeconds : $retryDelaySeconds));

            $result = $linkedInPublisher->fetchTaskStatus($posting, $taskUrn);
            $taskStatus = strtoupper((string) ($result['task_status'] ?? ''));

            if (in_array($taskStatus, ['SUCCEEDED', 'PROCESSED'], true)) {
                $multipostingService->finalizeQueuedPublishSuccess(
                    posting: $posting,
                    attempt: $attempt,
                    actor: $actor,
                    metadata: [
                        'provider' => 'linkedin',
                        'source' => 'api_push_task_sync',
                        'external_id' => (string) ($result['external_id'] ?? ''),
                        'external_url' => (string) ($result['external_url'] ?? ''),
                        'response' => (array) ($result['response'] ?? []),
                        'task_urn' => $taskUrn,
                        'task_status' => $taskStatus,
                        'check_number' => $check,
                    ]
                );

                $sensitiveEvents->record(
                    actionType: 'job_posting.api_push_succeeded',
                    entityType: 'job_posting',
                    entityId: (string) $posting->id,
                    metadata: [
                        'platform' => (string) $posting->platform,
                        'publish_attempt_id' => (string) $attempt->id,
                        'task_urn' => $taskUrn,
                        'task_status' => $taskStatus,
                        'mode' => 'after_response_inline',
                    ],
                    actor: $actor
                );

                return;
            }

            if ($taskStatus === 'FAILED') {
                $message = (string) ($result['error_message'] ?? 'LinkedIn task status returned FAILED.');

                $multipostingService->finalizeQueuedPublishFailure(
                    posting: $posting,
                    attempt: $attempt,
                    actor: $actor,
                    metadata: [
                        'error' => $message,
                        'provider' => 'linkedin',
                        'source' => 'api_push_task_sync',
                        'task_urn' => $taskUrn,
                        'task_status' => $taskStatus,
                        'response' => (array) ($result['response'] ?? []),
                    ]
                );

                $sensitiveEvents->record(
                    actionType: 'job_posting.api_push_failed',
                    entityType: 'job_posting',
                    entityId: (string) $posting->id,
                    metadata: [
                        'platform' => (string) $posting->platform,
                        'publish_attempt_id' => (string) $attempt->id,
                        'task_urn' => $taskUrn,
                        'task_status' => $taskStatus,
                        'mode' => 'after_response_inline',
                        'error' => $message,
                    ],
                    actor: $actor
                );

                return;
            }
        }

        $multipostingService->finalizeQueuedPublishFailure(
            posting: $posting,
            attempt: $attempt,
            actor: $actor,
            metadata: [
                'error' => 'LinkedIn accepted the submission but did not confirm final success before the inline task-status checks were exhausted.',
                'provider' => 'linkedin',
                'source' => 'api_push_task_timeout',
                'task_urn' => $taskUrn,
                'task_status' => 'IN_PROGRESS',
            ]
        );
    }

    public function failed(?\Throwable $exception): void
    {
        $multipostingService = app(MultipostingService::class);
        $sensitiveEvents = app(SensitiveEventRecorder::class);
        $posting = JobPosting::withoutGlobalScopes()->find($this->jobPostingId);
        $attempt = $multipostingService->resolvePublishAttempt($this->publishAttemptId);

        if (! $posting instanceof JobPosting || ! $attempt instanceof JobPostingPublishAttempt) {
            return;
        }

        $actor = $this->resolveActor();
        $message = $exception?->getMessage() ?: 'API push worker failed before completion.';

        $multipostingService->finalizeQueuedPublishFailure(
            posting: $posting,
            attempt: $attempt,
            actor: $actor,
            metadata: [
                'error' => $message,
                'source' => 'api_push_queue_failure',
                'provider' => (string) $posting->platform,
            ]
        );

        $sensitiveEvents->record(
            actionType: 'job_posting.api_push_job_failed',
            entityType: 'job_posting',
            entityId: (string) $posting->id,
            metadata: [
                'platform' => (string) $posting->platform,
                'publish_attempt_id' => (string) $attempt->id,
                'error' => $message,
            ],
            actor: $actor
        );
    }

    private function resolveActor(): ?User
    {
        if ($this->actorUserId === null || $this->actorUserId === '') {
            return null;
        }

        $resolved = User::query()->find($this->actorUserId);

        return $resolved instanceof User ? $resolved : null;
    }
}
