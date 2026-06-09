<?php

namespace App\Console\Commands;

use App\Jobs\RunJobBoardAutomationJob;
use App\Models\JobPosting;
use App\Models\JobPostingPublishAttempt;
use App\Models\User;
use App\Services\Multiposting\JobBoardAutomationRunner;
use App\Services\Multiposting\MultipostingService;
use App\Support\Audit\SensitiveEventRecorder;
use Illuminate\Console\Command;

class RunJobBoardAutomationCommand extends Command
{
    protected $signature = 'multiposting:automation-run
        {job_posting_id : UUID of the job_postings row}
        {--actor_id= : Optional actor user UUID}
        {--sync : Execute immediately instead of queueing}';

    protected $description = 'Execute or queue Playwright automation fallback for a job board posting.';

    public function handle(
        JobBoardAutomationRunner $automationRunner,
        MultipostingService $multipostingService,
        SensitiveEventRecorder $sensitiveEvents
    ): int {
        $jobPostingId = trim((string) $this->argument('job_posting_id'));
        $actorId = trim((string) $this->option('actor_id'));
        $runSync = (bool) $this->option('sync');

        $posting = JobPosting::withoutGlobalScopes()->find($jobPostingId);
        if (! $posting instanceof JobPosting) {
            $this->error('Job posting not found.');
            return self::FAILURE;
        }

        if (! $automationRunner->isEnabledForPlatform((string) $posting->platform)) {
            $this->error('Automation is disabled for platform: '.$posting->platform);
            return self::FAILURE;
        }

        if (! $runSync) {
            $attempt = JobPostingPublishAttempt::query()->create([
                'company_id' => (string) $posting->company_id,
                'job_posting_id' => (string) $posting->id,
                'initiated_by_user_id' => $actorId !== '' ? $actorId : null,
                'platform' => (string) $posting->platform,
                'attempt_number' => (int) JobPostingPublishAttempt::query()
                    ->where('job_posting_id', (string) $posting->id)
                    ->max('attempt_number') + 1,
                'status' => JobPostingPublishAttempt::STATUS_QUEUED,
                'execution_mode' => 'async_automation',
                'queued_at' => now(),
            ]);

            RunJobBoardAutomationJob::dispatch(
                jobPostingId: (string) $posting->id,
                publishAttemptId: (string) $attempt->id,
                actorUserId: $actorId !== '' ? $actorId : null
            );

            $this->info('Automation fallback job queued for posting '.$posting->id.'.');
            return self::SUCCESS;
        }

        $actor = null;
        if ($actorId !== '') {
            $resolved = User::query()->find($actorId);
            if ($resolved instanceof User) {
                $actor = $resolved;
            }
        }

        $attempt = JobPostingPublishAttempt::query()->create([
            'company_id' => (string) $posting->company_id,
            'job_posting_id' => (string) $posting->id,
            'initiated_by_user_id' => $actor?->id ? (string) $actor->id : null,
            'platform' => (string) $posting->platform,
            'attempt_number' => (int) JobPostingPublishAttempt::query()
                ->where('job_posting_id', (string) $posting->id)
                ->max('attempt_number') + 1,
            'status' => JobPostingPublishAttempt::STATUS_RUNNING,
            'execution_mode' => 'async_automation',
            'queued_at' => now(),
            'started_at' => now(),
        ]);

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

            $this->info('Automation succeeded and posting marked as published.');
            return self::SUCCESS;
        }

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
                'automation_role' => $automationRunner->role(),
            ],
            actor: $actor
        );

        $this->error('Automation fallback failed. Manual posting required.');
        if ($result->errorMessage !== null) {
            $this->line('Error: '.$result->errorMessage);
        }
        if ($result->screenshotPath !== null) {
            $this->line('Screenshot: '.$result->screenshotPath);
        }

        return self::FAILURE;
    }
}
