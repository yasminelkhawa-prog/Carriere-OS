<?php

namespace App\Observers;

use App\Models\Job;
use App\Services\Feeds\AggregatorJobsXmlFeedService;

class JobObserver
{
    public function __construct(
        private readonly AggregatorJobsXmlFeedService $feedService
    ) {
    }

    public function created(Job $job): void
    {
        if ($job->status === Job::STATUS_PUBLISHED) {
            $this->feedService->flushCache();
        }
    }

    public function updated(Job $job): void
    {
        if ($job->wasChanged('status')) {
            $this->feedService->flushCache();
        }
    }

    public function deleted(Job $job): void
    {
        $originalStatus = (string) $job->getOriginal('status');

        if ($originalStatus === Job::STATUS_PUBLISHED || $job->status === Job::STATUS_PUBLISHED) {
            $this->feedService->flushCache();
        }
    }
}

