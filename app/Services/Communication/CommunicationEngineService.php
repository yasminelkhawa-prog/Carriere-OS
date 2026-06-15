<?php

namespace App\Services\Communication;

use App\Jobs\SendEmailOutboxJob;
use App\Models\EmailOutboxLog;
use App\Models\EmailTemplate;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class CommunicationEngineService
{
    /**
     * @return array{ok: bool, log: ?EmailOutboxLog, error: ?string}
     */
    public function queueTemplateEmail(
        string $companyId,
        string $templateKey,
        string $toEmail,
        ?string $toName,
        string $language,
        array $variables,
        ?string $relatedEntityType = null,
        ?string $relatedEntityId = null
    ): array {
        $this->ensureCompanyTemplates($companyId);

        $normalizedLanguage = $this->normalizeLanguage($language);
        $normalizedToEmail = Str::lower(trim($toEmail));
        $normalizedToName = $this->normalizeNullableString($toName);

        if ($normalizedToEmail === '') {
            return [
                'ok' => false,
                'log' => $this->createFailedLog(
                    companyId: $companyId,
                    toEmail: '',
                    toName: $normalizedToName,
                    templateKey: $templateKey,
                    relatedEntityType: $relatedEntityType,
                    relatedEntityId: $relatedEntityId,
                    errorMessage: __('communications.errors.missing_candidate_email')
                ),
                'error' => __('communications.errors.missing_candidate_email'),
            ];
        }

        $template = EmailTemplate::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('template_key', $templateKey)
            ->where('language', $normalizedLanguage)
            ->where('is_active', true)
            ->first();

        if (! $template instanceof EmailTemplate) {
            $error = __('communications.errors.template_not_found');

            return [
                'ok' => false,
                'log' => $this->createFailedLog(
                    companyId: $companyId,
                    toEmail: $normalizedToEmail,
                    toName: $normalizedToName,
                    templateKey: $templateKey,
                    relatedEntityType: $relatedEntityType,
                    relatedEntityId: $relatedEntityId,
                    errorMessage: $error
                ),
                'error' => $error,
            ];
        }

        $normalizedVariables = $this->normalizeVariables($variables);
        $requiredVariables = array_values(array_unique(array_merge(
            $this->extractTemplateVariables((string) $template->subject_template),
            $this->extractTemplateVariables((string) $template->body_template)
        )));

        $missingVariables = collect($requiredVariables)
            ->reject(fn (string $key): bool => array_key_exists($key, $normalizedVariables))
            ->values()
            ->all();

        if ($missingVariables !== []) {
            $error = __('communications.errors.missing_template_variables', [
                'variables' => implode(', ', $missingVariables),
            ]);

            return [
                'ok' => false,
                'log' => $this->createFailedLog(
                    companyId: $companyId,
                    toEmail: $normalizedToEmail,
                    toName: $normalizedToName,
                    templateKey: $templateKey,
                    relatedEntityType: $relatedEntityType,
                    relatedEntityId: $relatedEntityId,
                    errorMessage: $error
                ),
                'error' => $error,
            ];
        }

        $subject = $this->renderTemplate((string) $template->subject_template, $normalizedVariables);
        $body = $this->renderTemplate((string) $template->body_template, $normalizedVariables);

        $log = EmailOutboxLog::withoutGlobalScopes()->create([
            'company_id' => $companyId,
            'to_email' => $normalizedToEmail,
            'to_name' => $normalizedToName,
            'subject' => $subject,
            'body' => $body,
            'status' => EmailOutboxLog::STATUS_QUEUED,
            'template_key' => $templateKey,
            'related_entity_type' => $relatedEntityType,
            'related_entity_id' => $relatedEntityId,
            'created_at' => now(),
            'sent_at' => null,
            'error_message' => null,
        ]);

        $this->dispatchOutboxJob((string) $log->id);

        return [
            'ok' => true,
            'log' => $log,
            'error' => null,
        ];
    }

    public function ensureCompanyTemplates(string $companyId): void
    {
        $defaults = config('communication.template_defaults', []);
        if (! is_array($defaults) || $defaults === []) {
            return;
        }

        foreach ($defaults as $templateKey => $languages) {
            if (! is_array($languages)) {
                continue;
            }

            foreach ($languages as $language => $template) {
                $subject = trim((string) Arr::get($template, 'subject', ''));
                $body = trim((string) Arr::get($template, 'body', ''));
                if ($subject === '' || $body === '') {
                    continue;
                }

                EmailTemplate::withoutGlobalScopes()->firstOrCreate(
                    [
                        'company_id' => $companyId,
                        'template_key' => (string) $templateKey,
                        'language' => $this->normalizeLanguage((string) $language),
                    ],
                    [
                        'subject_template' => $subject,
                        'body_template' => $body,
                        'is_active' => true,
                    ]
                );
            }
        }
    }

    /**
     * @return array<int, string>
     */
    public function templateKeys(): array
    {
        $defaults = config('communication.template_defaults', []);
        if (! is_array($defaults)) {
            return [];
        }

        return array_values(array_map('strval', array_keys($defaults)));
    }

    private function normalizeLanguage(string $language): string
    {
        $normalized = Str::lower(trim($language));
        if (in_array($normalized, EmailTemplate::languages(), true)) {
            return $normalized;
        }

        return EmailTemplate::LANGUAGE_EN;
    }

    private function dispatchOutboxJob(string $logId): void
    {
        $mode = Str::lower(trim((string) config('communication.outbox_dispatch', 'queue')));

        if ($mode === 'sync') {
            try {
                SendEmailOutboxJob::dispatchSync($logId);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error("Failed to send synchronous email in CommunicationEngineService: " . $e->getMessage());
            }
            return;
        }

        if ($mode === 'after_response') {
            SendEmailOutboxJob::dispatch($logId)->afterResponse();
            return;
        }

        SendEmailOutboxJob::dispatch($logId);
    }

    private function normalizeNullableString(?string $value): ?string
    {
        $normalized = trim((string) $value);
        return $normalized !== '' ? $normalized : null;
    }

    /**
     * @param array<string, mixed> $variables
     * @return array<string, string>
     */
    private function normalizeVariables(array $variables): array
    {
        $normalized = [];
        foreach ($variables as $key => $value) {
            $key = trim((string) $key);
            if ($key === '') {
                continue;
            }

            if (is_scalar($value) || $value === null) {
                $normalized[$key] = trim((string) ($value ?? ''));
                continue;
            }

            $normalized[$key] = (string) json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        return $normalized;
    }

    /**
     * @return array<int, string>
     */
    private function extractTemplateVariables(string $template): array
    {
        if (preg_match_all('/{{\s*([a-zA-Z0-9_]+)\s*}}/', $template, $matches) < 1) {
            return [];
        }

        return array_values(array_filter(array_unique(array_map('strval', $matches[1] ?? []))));
    }

    /**
     * @param array<string, string> $variables
     */
    private function renderTemplate(string $template, array $variables): string
    {
        return (string) preg_replace_callback(
            '/{{\s*([a-zA-Z0-9_]+)\s*}}/',
            function (array $matches) use ($variables): string {
                $key = (string) ($matches[1] ?? '');
                return (string) ($variables[$key] ?? '');
            },
            $template
        );
    }

    private function createFailedLog(
        string $companyId,
        string $toEmail,
        ?string $toName,
        string $templateKey,
        ?string $relatedEntityType,
        ?string $relatedEntityId,
        string $errorMessage
    ): EmailOutboxLog {
        return EmailOutboxLog::withoutGlobalScopes()->create([
            'company_id' => $companyId,
            'to_email' => $toEmail,
            'to_name' => $toName,
            'subject' => '',
            'body' => '',
            'status' => EmailOutboxLog::STATUS_FAILED,
            'template_key' => $templateKey,
            'related_entity_type' => $relatedEntityType,
            'related_entity_id' => $relatedEntityId,
            'created_at' => now(),
            'sent_at' => null,
            'error_message' => $errorMessage,
        ]);
    }
}
