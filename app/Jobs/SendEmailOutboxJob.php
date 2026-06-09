<?php

namespace App\Jobs;

use App\Models\EmailOutboxLog;
use App\Models\RejectionDraft;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

class SendEmailOutboxJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly string $emailOutboxLogId)
    {
        $this->onQueue((string) config('communication.outbox_queue', 'default'));
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function handle(): void
    {
        $log = EmailOutboxLog::withoutGlobalScopes()->find($this->emailOutboxLogId);
        if (! $log instanceof EmailOutboxLog) {
            return;
        }

        if ($log->status === EmailOutboxLog::STATUS_SENT) {
            return;
        }

        try {
            $toEmail = trim((string) $log->to_email);
            if ($toEmail === '') {
                throw new RuntimeException('Recipient email is missing.');
            }

            Mail::raw((string) $log->body, function ($message) use ($log, $toEmail): void {
                $toName = trim((string) ($log->to_name ?? ''));
                $message->to($toEmail, $toName !== '' ? $toName : null)
                    ->subject((string) $log->subject);
            });

            $log->forceFill([
                'status' => EmailOutboxLog::STATUS_SENT,
                'sent_at' => now(),
                'error_message' => null,
            ])->save();

            if (
                (string) $log->related_entity_type === 'rejection_draft'
                && is_string($log->related_entity_id)
                && $log->related_entity_id !== ''
            ) {
                RejectionDraft::withoutGlobalScopes()
                    ->where('id', $log->related_entity_id)
                    ->where('company_id', $log->company_id)
                    ->update([
                        'status' => RejectionDraft::STATUS_SENT,
                        'updated_at' => now(),
                    ]);
            }
        } catch (Throwable $exception) {
            $log->forceFill([
                'status' => EmailOutboxLog::STATUS_FAILED,
                'error_message' => mb_substr($exception->getMessage(), 0, 2000),
            ])->save();

            throw $exception;
        }
    }
}
