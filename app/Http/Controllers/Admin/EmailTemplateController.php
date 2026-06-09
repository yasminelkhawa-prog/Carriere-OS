<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ResolvesManagedCompany;
use App\Http\Controllers\Controller;
use App\Jobs\SendEmailOutboxJob;
use App\Models\Company;
use App\Models\EmailOutboxLog;
use App\Models\EmailTemplate;
use App\Models\User;
use App\Services\Communication\CommunicationEngineService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EmailTemplateController extends Controller
{
    use ResolvesManagedCompany;

    public function __construct(
        private readonly CommunicationEngineService $communicationEngine
    ) {
    }

    public function index(Request $request): View|RedirectResponse
    {
        $actor = $request->user();
        if (! $actor instanceof User) {
            return redirect()->route('login');
        }

        $companyId = $this->managedCompanyId($request, true);
        $templateKeys = $this->communicationEngine->templateKeys();
        $availableStatuses = EmailOutboxLog::statuses();

        if ($actor->isSuperadmin() && $companyId === null) {
            return view('admin.email-templates.index', [
                'requiresCompanySelection' => true,
                'companies' => Company::query()->orderBy('name')->get(['id', 'name']),
                'selectedCompanyId' => null,
                'templateKeys' => $templateKeys,
                'selectedTemplateKey' => $templateKeys[0] ?? '',
                'selectedLanguage' => app()->getLocale() === 'fr' ? 'fr' : 'en',
                'selectedTemplate' => null,
                'logs' => collect(),
                'selectedStatus' => null,
                'sampleVariablesJson' => '{}',
                'availableStatuses' => $availableStatuses,
            ]);
        }

        if (! $actor->isSuperadmin() && $companyId === null) {
            return redirect()->route('auth.company.dispatch');
        }

        $validated = $request->validate([
            'template_key' => ['nullable', Rule::in($templateKeys)],
            'language' => ['nullable', Rule::in(EmailTemplate::languages())],
            'status' => ['nullable', Rule::in($availableStatuses)],
        ]);

        $this->communicationEngine->ensureCompanyTemplates((string) $companyId);

        $selectedTemplateKey = (string) ($validated['template_key'] ?? ($templateKeys[0] ?? 'application_acknowledgement'));
        $selectedLanguage = (string) ($validated['language'] ?? (app()->getLocale() === 'fr' ? 'fr' : 'en'));
        $selectedStatus = $validated['status'] ?? null;

        $selectedTemplate = EmailTemplate::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('template_key', $selectedTemplateKey)
            ->where('language', $selectedLanguage)
            ->first();

        $logs = EmailOutboxLog::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->when($selectedTemplateKey !== '', fn ($query) => $query->where('template_key', $selectedTemplateKey))
            ->when($selectedStatus !== null, fn ($query) => $query->where('status', $selectedStatus))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.email-templates.index', [
            'requiresCompanySelection' => false,
            'companies' => $actor->isSuperadmin() ? Company::query()->orderBy('name')->get(['id', 'name']) : collect(),
            'selectedCompanyId' => $companyId,
            'templateKeys' => $templateKeys,
            'selectedTemplateKey' => $selectedTemplateKey,
            'selectedLanguage' => $selectedLanguage,
            'selectedTemplate' => $selectedTemplate,
            'logs' => $logs,
            'selectedStatus' => $selectedStatus,
            'sampleVariablesJson' => json_encode($this->sampleVariables($selectedTemplateKey), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            'availableStatuses' => $availableStatuses,
        ]);
    }

    public function upsert(Request $request): RedirectResponse
    {
        $companyId = $this->managedCompanyId($request, true);
        abort_unless(is_string($companyId) && $companyId !== '', 403);

        $templateKeys = $this->communicationEngine->templateKeys();

        $validated = $request->validate([
            'template_key' => ['required', Rule::in($templateKeys)],
            'language' => ['required', Rule::in(EmailTemplate::languages())],
            'subject_template' => ['required', 'string', 'max:5000'],
            'body_template' => ['required', 'string', 'max:50000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        EmailTemplate::withoutGlobalScopes()->updateOrCreate(
            [
                'company_id' => $companyId,
                'template_key' => (string) $validated['template_key'],
                'language' => (string) $validated['language'],
            ],
            [
                'subject_template' => trim((string) $validated['subject_template']),
                'body_template' => trim((string) $validated['body_template']),
                'is_active' => (bool) $request->boolean('is_active'),
            ]
        );

        return redirect()
            ->route('admin.email-templates.index', array_filter([
                'company_id' => (string) $request->input('company_id', $request->query('company_id', '')),
                'template_key' => (string) $validated['template_key'],
                'language' => (string) $validated['language'],
                'status' => (string) $request->input('status', $request->query('status', '')),
            ], fn ($value) => $value !== ''))
            ->with('status', __('communications.flash.template_saved'));
    }

    public function retryOutbox(Request $request, EmailOutboxLog $emailOutboxLog): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $companyId = $this->managedCompanyId($request, true);
        abort_unless(is_string($companyId) && $companyId !== '', 403);
        abort_unless((string) $emailOutboxLog->company_id === (string) $companyId, 403);

        if ((string) $emailOutboxLog->status !== EmailOutboxLog::STATUS_FAILED) {
            return redirect()
                ->route('admin.email-templates.index', array_filter([
                    'company_id' => (string) $request->input('company_id', $request->query('company_id', '')),
                    'template_key' => (string) $request->input('template_key', $request->query('template_key', '')),
                    'language' => (string) $request->input('language', $request->query('language', '')),
                    'status' => (string) $request->input('status', $request->query('status', '')),
                ], fn ($value) => $value !== ''))
                ->with('status', __('communications.flash.retry_not_failed'));
        }

        $emailOutboxLog->forceFill([
            'status' => EmailOutboxLog::STATUS_QUEUED,
            'error_message' => null,
            'sent_at' => null,
        ])->save();

        SendEmailOutboxJob::dispatch((string) $emailOutboxLog->id)->afterResponse();

        return redirect()
            ->route('admin.email-templates.index', array_filter([
                'company_id' => (string) $request->input('company_id', $request->query('company_id', '')),
                'template_key' => (string) $request->input('template_key', $request->query('template_key', '')),
                'language' => (string) $request->input('language', $request->query('language', '')),
                'status' => (string) $request->input('status', $request->query('status', '')),
            ], fn ($value) => $value !== ''))
            ->with('status', __('communications.flash.retry_queued'));
    }

    /**
     * @return array<string, string>
     */
    private function sampleVariables(string $templateKey): array
    {
        return match ($templateKey) {
            'application_acknowledgement' => [
                'candidate_name' => 'Alex Morgan',
                'job_title' => 'Senior Product Manager',
                'company_name' => 'Malik and Co',
                'application_reference' => 'APP-123456',
            ],
            'application_portal_verification' => [
                'candidate_name' => 'Alex Morgan',
                'job_title' => 'Senior Product Manager',
                'company_name' => 'Malik and Co',
                'application_reference' => 'APP-123456',
                'verification_url' => 'https://example.test/candidate/email-verify/...',
            ],
            'interview_confirmation' => [
                'candidate_name' => 'Alex Morgan',
                'job_title' => 'Senior Product Manager',
                'scheduled_for' => '2026-03-15 14:30 UTC',
                'channel' => 'In person',
                'meeting_link' => 'https://zoom.us/j/1234567890',
                'location_label' => 'Address',
                'location_value' => '221B Baker Street, London',
            ],
            'onboarding_welcome_after_signing' => [
                'candidate_name' => 'Alex Morgan',
                'company_name' => 'Malik and Co',
                'job_title' => 'Senior Product Manager',
            ],
            'rejection_decision' => [
                'candidate_name' => 'Alex Morgan',
                'job_title' => 'Senior Product Manager',
                'draft_body' => 'Thank you for your time and interest. We are moving forward with another profile.',
                'xai_reason' => 'Current role requires deeper ownership in cross-functional launch metrics.',
            ],
            default => [],
        };
    }
}
