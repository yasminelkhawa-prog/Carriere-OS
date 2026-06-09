<?php

namespace App\Jobs;

use App\Models\JobPosting;
use App\Models\JobPostingPublishAttempt;
use App\Models\User;
use App\Services\Multiposting\JobBoardAutomationRunner;
use App\Services\Multiposting\JobBoardAutomationResult;
use App\Services\Multiposting\MultipostingService;
use App\Support\Audit\SensitiveEventRecorder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunJobBoardAutomationJob implements ShouldQueue
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
        $this->onQueue((string) config('multiposting.automation.queue', 'automation'));
    }

    public function handle(
        JobBoardAutomationRunner $automationRunner,
        MultipostingService $multipostingService,
        SensitiveEventRecorder $sensitiveEvents
    ): void {
        $posting = JobPosting::withoutGlobalScopes()->find($this->jobPostingId);
        if (! $posting instanceof JobPosting) {
            return;
        }

        $attempt = $multipostingService->resolvePublishAttempt($this->publishAttemptId);
        if (! $attempt instanceof JobPostingPublishAttempt) {
            return;
        }

        $actor = null;
        if ($this->actorUserId !== null && $this->actorUserId !== '') {
            $resolved = User::query()->find($this->actorUserId);
            if ($resolved instanceof User) {
                $actor = $resolved;
            }
        }

        $multipostingService->markPublishAttemptRunning($attempt);
        $result = $automationRunner->run($posting);

        if ($result->ok) {
            $multipostingService->finalizeAutomatedPublishSuccess(
                posting: $posting,
                attempt: $attempt,
                actor: $actor,
                metadata: [
                    'external_url' => $result->externalUrl,
                    'raw' => $result->raw,
                ]
            );

            $sensitiveEvents->record(
                actionType: 'job_posting.automation_succeeded',
                entityType: 'job_posting',
                entityId: (string) $posting->id,
                metadata: [
                    'platform' => (string) $posting->platform,
                    'external_url' => $result->externalUrl,
                    'automation_role' => $automationRunner->role(),
                ],
                actor: $actor
            );

            return;
        }

        $this->markFailedForManualFallback(
            posting: $posting,
            result: $result,
            actor: $actor,
            multipostingService: $multipostingService,
            sensitiveEvents: $sensitiveEvents
        );
    }

    private function markFailedForManualFallback(
        JobPosting $posting,
        JobBoardAutomationResult $result,
        ?User $actor,
        MultipostingService $multipostingService,
        SensitiveEventRecorder $sensitiveEvents
    ): void {
        $multipostingService->finalizeAutomatedPublishFailure(
            posting: $posting,
            attempt: $attempt,
            actor: $actor,
            metadata: [
                'error' => $result->errorMessage,
                'failure_code' => $result->failureCode,
                'screenshot_path' => $result->screenshotPath,
                'raw' => $result->raw,
            ]
        );

        $sensitiveEvents->record(
            actionType: 'job_posting.automation_manual_fallback_required',
            entityType: 'job_posting',
            entityId: (string) $posting->id,
            metadata: [
                'platform' => (string) $posting->platform,
                'error' => $result->errorMessage,
                'failure_code' => $result->failureCode,
                'screenshot_path' => $result->screenshotPath,
                'manual_action_required' => true,
                'automation_role' => app(JobBoardAutomationRunner::class)->role(),
            ],
            actor: $actor
        );
    }

    public function failed(?\Throwable $exception): void {
        $multipostingService = app(MultipostingService::class);
        $sensitiveEvents = app(SensitiveEventRecorder::class);
        $posting = JobPosting::withoutGlobalScopes()->find($this->jobPostingId);
        $attempt = $multipostingService->resolvePublishAttempt($this->publishAttemptId);

        if (! $posting instanceof JobPosting || ! $attempt instanceof JobPostingPublishAttempt) {
            return;
        }

        $actor = null;
        if ($this->actorUserId !== null && $this->actorUserId !== '') {
            $resolved = User::query()->find($this->actorUserId);
            if ($resolved instanceof User) {
                $actor = $resolved;
            }
        }

        $message = $exception?->getMessage() ?: 'Automation worker failed before completion.';

        $multipostingService->finalizeAutomatedPublishFailure(
            posting: $posting,
            attempt: $attempt,
            actor: $actor,
            metadata: [
                'error' => $message,
                'source' => 'queue_failure',
            ]
        );

        $sensitiveEvents->record(
            actionType: 'job_posting.automation_job_failed',
            entityType: 'job_posting',
            entityId: (string) $posting->id,
            metadata: [
                'platform' => (string) $posting->platform,
                'publish_attempt_id' => (string) $attempt->id,
                'error' => $message,
                'automation_role' => app(JobBoardAutomationRunner::class)->role(),
            ],
            actor: $actor
        );
    }
}
