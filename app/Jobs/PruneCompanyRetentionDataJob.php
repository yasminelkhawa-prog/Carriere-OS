<?php

namespace App\Jobs;

use App\Models\AiArtifact;
use App\Models\CompanyRetentionSetting;
use App\Models\VideoResponse;
use App\Support\Audit\SensitiveEventRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class PruneCompanyRetentionDataJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public function __construct(public readonly string $companyId)
    {
        $this->onQueue((string) config('retention.queue', 'default'));
    }

    public function handle(SensitiveEventRecorder $sensitiveEvents): void
    {
        $settings = CompanyRetentionSetting::withoutGlobalScopes()->firstOrCreate(
            ['company_id' => $this->companyId],
            [
                'video_retention_days' => (int) config('retention.defaults.video_retention_days', 365),
                'ai_artifact_retention_days' => (int) config('retention.defaults.ai_artifact_retention_days', 180),
            ]
        );

        $videoDays = $this->normalizeRetentionDays((int) $settings->video_retention_days);
        $aiArtifactDays = $this->normalizeRetentionDays((int) $settings->ai_artifact_retention_days);

        $summary = [
            'video_retention_days' => $videoDays,
            'ai_artifact_retention_days' => $aiArtifactDays,
            'video_rows_deleted' => 0,
            'video_files_deleted' => 0,
            'ai_artifact_rows_deleted' => 0,
            'ai_artifact_files_deleted' => 0,
        ];

        if ($videoDays > 0) {
            $videoCutoff = CarbonImmutable::now()->subDays($videoDays);
            $summary = $this->pruneVideoResponses($videoCutoff, $summary);
        }

        if ($aiArtifactDays > 0) {
            $artifactCutoff = CarbonImmutable::now()->subDays($aiArtifactDays);
            $summary = $this->pruneAiArtifacts($artifactCutoff, $summary);
        }

        $settings->forceFill(['last_pruned_at' => now()])->save();

        $sensitiveEvents->record(
            actionType: 'retention.pruned',
            entityType: 'company',
            entityId: $this->companyId,
            metadata: $summary,
            actor: null
        );
    }

    /**
     * @param array<string, int> $summary
     * @return array<string, int>
     */
    private function pruneVideoResponses(CarbonImmutable $cutoff, array $summary): array
    {
        $chunkSize = max(50, (int) config('retention.chunk_size', 200));

        VideoResponse::withoutGlobalScopes()
            ->where('company_id', $this->companyId)
            ->where('created_at', '<', $cutoff)
            ->orderBy('id')
            ->chunkById($chunkSize, function ($rows) use (&$summary): void {
                $ids = [];
                foreach ($rows as $row) {
                    $ids[] = (string) $row->id;
                    $path = trim((string) $row->video_file_url);
                    if ($path !== '' && Storage::disk('local')->exists($path)) {
                        Storage::disk('local')->delete($path);
                        $summary['video_files_deleted']++;
                    }
                }

                if ($ids !== []) {
                    $summary['video_rows_deleted'] += VideoResponse::withoutGlobalScopes()
                        ->where('company_id', $this->companyId)
                        ->whereIn('id', $ids)
                        ->delete();
                }
            });

        return $summary;
    }

    /**
     * @param array<string, int> $summary
     * @return array<string, int>
     */
    private function pruneAiArtifacts(CarbonImmutable $cutoff, array $summary): array
    {
        $chunkSize = max(50, (int) config('retention.chunk_size', 200));

        AiArtifact::withoutGlobalScopes()
            ->where('company_id', $this->companyId)
            ->where('created_at', '<', $cutoff)
            ->orderBy('id')
            ->chunkById($chunkSize, function ($rows) use (&$summary): void {
                $ids = [];
                foreach ($rows as $row) {
                    $ids[] = (string) $row->id;
                    $path = trim((string) $row->storage_url);
                    if ($path !== '' && Storage::disk('local')->exists($path)) {
                        Storage::disk('local')->delete($path);
                        $summary['ai_artifact_files_deleted']++;
                    }
                }

                if ($ids !== []) {
                    $summary['ai_artifact_rows_deleted'] += AiArtifact::withoutGlobalScopes()
                        ->where('company_id', $this->companyId)
                        ->whereIn('id', $ids)
                        ->delete();
                }
            });

        return $summary;
    }

    private function normalizeRetentionDays(int $days): int
    {
        $min = (int) config('retention.min_days', 7);
        $max = (int) config('retention.max_days', 3650);

        if ($days <= 0) {
            return 0;
        }

        return max($min, min($max, $days));
    }
}
