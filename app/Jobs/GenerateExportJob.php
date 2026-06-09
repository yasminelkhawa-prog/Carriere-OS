<?php

namespace App\Jobs;

use App\Models\Export;
use App\Services\Reporting\ExportGenerationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class GenerateExportJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    public function __construct(public readonly string $exportId)
    {
        $this->onQueue('default');
    }

    public function handle(ExportGenerationService $generationService): void
    {
        $export = Export::withoutGlobalScopes()->find($this->exportId);
        if (! $export instanceof Export) {
            return;
        }

        if ($export->status === Export::STATUS_COMPLETED) {
            return;
        }

        $export->forceFill([
            'status' => Export::STATUS_PROCESSING,
            'updated_at' => now(),
        ])->save();

        try {
            $generationService->generate($export->fresh());
        } catch (Throwable $exception) {
            $generationService->markFailed($export, $exception);
        }
    }
}
