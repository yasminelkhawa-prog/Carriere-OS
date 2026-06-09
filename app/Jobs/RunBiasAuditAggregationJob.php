<?php

namespace App\Jobs;

use App\Services\Fairness\FairnessAuditService;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunBiasAuditAggregationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $companyId,
        public readonly string $jobId,
        public readonly string $stageId,
        public readonly string $timeBucketStartIso
    ) {
        $this->onQueue('default');
    }

    public function handle(FairnessAuditService $auditService): void
    {
        $auditService->recompute(
            companyId: $this->companyId,
            jobId: $this->jobId,
            stageId: $this->stageId,
            timeBucketStart: CarbonImmutable::parse($this->timeBucketStartIso)
        );
    }
}

