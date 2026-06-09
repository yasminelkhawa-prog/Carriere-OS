<?php

namespace App\Jobs;

use App\Models\AiRequest;
use App\Services\Ai\AiRequestService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessAiRequestJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly string $aiRequestId)
    {
        $this->onQueue('default');
    }

    public function handle(AiRequestService $aiRequestService): void
    {
        $request = AiRequest::withoutGlobalScopes()->find($this->aiRequestId);

        if (! $request instanceof AiRequest) {
            return;
        }

        $aiRequestService->process($request);
    }
}
