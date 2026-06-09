<?php

namespace App\Services\Reporting;

use App\Models\Export;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Throwable;

class ExportGenerationService
{
    public function __construct(
        private readonly ExportDatasetService $datasetService,
        private readonly ExportFileRenderer $renderer
    ) {
    }

    public function generate(Export $export): void
    {
        $payload = $this->datasetService->buildPayload($export);
        $format = (string) $export->format;

        $content = match ($format) {
            Export::FORMAT_CSV => $this->renderer->renderCsv($payload['columns'], $payload['rows']),
            Export::FORMAT_PDF => $this->renderer->renderPdf($payload['title'], $payload['columns'], $payload['rows'], $payload['metadata']),
            default => '',
        };

        if ($content === '') {
            throw new \RuntimeException('Generated export content is empty.');
        }

        $extension = $format === Export::FORMAT_PDF ? 'pdf' : 'csv';
        $path = sprintf('private/exports/%s/%s.%s', (string) $export->company_id, (string) $export->id, $extension);

        Storage::disk('local')->put($path, $content);

        $export->forceFill([
            'status' => Export::STATUS_COMPLETED,
            'file_url' => $path,
            'updated_at' => now(),
        ])->save();

        $this->notifyCompletion($export, (int) ($payload['metadata']['row_count'] ?? 0));
    }

    public function markFailed(Export $export, Throwable $exception): void
    {
        $export->forceFill([
            'status' => Export::STATUS_FAILED,
            'file_url' => null,
            'updated_at' => now(),
        ])->save();

        Log::warning('Export generation failed.', [
            'export_id' => (string) $export->id,
            'company_id' => (string) $export->company_id,
            'requested_by_user_id' => (string) $export->requested_by_user_id,
            'error' => $exception->getMessage(),
        ]);

        $this->notifyFailure($export);
    }

    private function notifyCompletion(Export $export, int $rowCount): void
    {
        $requester = $export->requestedBy()->first();
        $email = trim((string) ($requester?->email ?? ''));
        if ($email === '') {
            return;
        }

        try {
            $downloadUrl = URL::temporarySignedRoute(
                'exports.download',
                now()->addHours(12),
                ['export' => (string) $export->id]
            );

            $subject = 'Your export is ready';
            $body = implode("\n", [
                'Your requested export is complete.',
                'Export ID: '.(string) $export->id,
                'Type: '.(string) $export->export_type,
                'Format: '.strtoupper((string) $export->format),
                'Rows: '.(string) $rowCount,
                'Download (signed): '.$downloadUrl,
            ]);

            Mail::raw($body, static function ($message) use ($email, $subject): void {
                $message->to($email)->subject($subject);
            });
        } catch (Throwable $exception) {
            Log::warning('Failed to send export completion notification.', [
                'export_id' => (string) $export->id,
                'requested_by_user_id' => (string) $export->requested_by_user_id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function notifyFailure(Export $export): void
    {
        $requester = $export->requestedBy()->first();
        $email = trim((string) ($requester?->email ?? ''));
        if ($email === '') {
            return;
        }

        try {
            Mail::raw(
                implode("\n", [
                    'Your requested export failed to generate.',
                    'Export ID: '.(string) $export->id,
                    'Type: '.(string) $export->export_type,
                    'Please retry from the dashboard.',
                ]),
                static function ($message) use ($email): void {
                    $message->to($email)->subject('Your export failed');
                }
            );
        } catch (Throwable $exception) {
            Log::warning('Failed to send export failure notification.', [
                'export_id' => (string) $export->id,
                'requested_by_user_id' => (string) $export->requested_by_user_id,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
