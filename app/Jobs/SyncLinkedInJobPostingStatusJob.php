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

class SyncLinkedInJobPostingStatusJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public function __construct(
        public readonly string $jobPostingId,
        public readonly string $publishAttemptId,
        public readonly string $taskUrn,
        public readonly int $checkNumber = 1,
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
        $result = $linkedInPublisher->fetchTaskStatus($posting, $this->taskUrn);
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
                    'task_urn' => $this->taskUrn,
                    'task_status' => $taskStatus,
                    'check_number' => $this->checkNumber,
                ]
            );

            $sensitiveEvents->record(
                actionType: 'job_posting.api_push_succeeded',
                entityType: 'job_posting',
                entityId: (string) $posting->id,
                metadata: [
                    'platform' => 'linkedin',
                    'publish_attempt_id' => (string) $attempt->id,
                    'task_urn' => $this->taskUrn,
                    'task_status' => $taskStatus,
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
                    'task_urn' => $this->taskUrn,
                    'task_status' => $taskStatus,
                    'response' => (array) ($result['response'] ?? []),
                ]
            );

            $sensitiveEvents->record(
                actionType: 'job_posting.api_push_failed',
                entityType: 'job_posting',
                entityId: (string) $posting->id,
                metadata: [
                    'platform' => 'linkedin',
                    'publish_attempt_id' => (string) $attempt->id,
                    'task_urn' => $this->taskUrn,
                    'task_status' => $taskStatus,
                    'error' => $message,
                ],
                actor: $actor
            );

            return;
        }

        $maxChecks = (int) config('services.linkedin.job_posting_task_max_checks', 5);
        if ($this->checkNumber >= $maxChecks) {
            $message = 'LinkedIn accepted the submission but did not confirm final success before the maximum task-status checks were exhausted.';

            $multipostingService->finalizeQueuedPublishFailure(
                posting: $posting,
                attempt: $attempt,
                actor: $actor,
                metadata: [
                    'error' => $message,
                    'provider' => 'linkedin',
                    'source' => 'api_push_task_timeout',
                    'task_urn' => $this->taskUrn,
                    'task_status' => $taskStatus !== '' ? $taskStatus : 'IN_PROGRESS',
                    'response' => (array) ($result['response'] ?? []),
                ]
            );

            return;
        }

        static::dispatch(
            jobPostingId: $this->jobPostingId,
            publishAttemptId: $this->publishAttemptId,
            taskUrn: $this->taskUrn,
            checkNumber: $this->checkNumber + 1,
            actorUserId: $this->actorUserId
        )->delay(now()->addSeconds((int) config('services.linkedin.job_posting_task_retry_delay_seconds', 120)));
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
