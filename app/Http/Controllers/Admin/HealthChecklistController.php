<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ResolvesManagedCompany;
use App\Http\Controllers\Controller;
use App\Jobs\PruneCompanyRetentionDataJob;
use App\Models\Company;
use App\Models\CompanyRetentionSetting;
use App\Models\User;
use App\Support\Audit\SensitiveEventRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class HealthChecklistController extends Controller
{
    use ResolvesManagedCompany;

    public function __construct(private readonly SensitiveEventRecorder $sensitiveEvents)
    {
    }

    public function index(Request $request): View
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $companies = $actor->isSuperadmin()
            ? Company::query()->where('status', Company::STATUS_ACTIVE)->orderBy('name')->get(['id', 'name'])
            : collect();

        $companyId = $this->managedCompanyId($request, false);
        if ($actor->isSuperadmin() && (! is_string($companyId) || $companyId === '')) {
            return view('admin.health.index', [
                'requiresCompanySelection' => true,
                'companies' => $companies,
                'selectedCompanyId' => null,
                'checks' => collect(),
                'retentionSetting' => null,
                'retentionMinDays' => (int) config('retention.min_days', 7),
                'retentionMaxDays' => (int) config('retention.max_days', 3650),
                'statusSummary' => ['pass' => 0, 'warning' => 0, 'fail' => 0],
            ]);
        }

        abort_unless(is_string($companyId) && $companyId !== '', 403);

        $retentionSetting = CompanyRetentionSetting::withoutGlobalScopes()->firstOrCreate(
            ['company_id' => $companyId],
            [
                'video_retention_days' => (int) config('retention.defaults.video_retention_days', 365),
                'ai_artifact_retention_days' => (int) config('retention.defaults.ai_artifact_retention_days', 180),
            ]
        );

        $checks = $this->buildChecks()->values();
        $statusSummary = [
            'pass' => $checks->where('status', 'pass')->count(),
            'warning' => $checks->where('status', 'warning')->count(),
            'fail' => $checks->where('status', 'fail')->count(),
        ];

        return view('admin.health.index', [
            'requiresCompanySelection' => false,
            'companies' => $companies,
            'selectedCompanyId' => $companyId,
            'checks' => $checks,
            'retentionSetting' => $retentionSetting,
            'retentionMinDays' => (int) config('retention.min_days', 7),
            'retentionMaxDays' => (int) config('retention.max_days', 3650),
            'statusSummary' => $statusSummary,
        ]);
    }

    public function updateRetention(Request $request): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $companyId = $this->managedCompanyId($request, true);
        abort_unless(is_string($companyId) && $companyId !== '', 403);

        $min = (int) config('retention.min_days', 7);
        $max = (int) config('retention.max_days', 3650);

        $validated = $request->validate([
            'video_retention_days' => ['required', 'integer', 'min:'.$min, 'max:'.$max],
            'ai_artifact_retention_days' => ['required', 'integer', 'min:'.$min, 'max:'.$max],
        ]);

        CompanyRetentionSetting::withoutGlobalScopes()->updateOrCreate(
            ['company_id' => $companyId],
            [
                'video_retention_days' => (int) $validated['video_retention_days'],
                'ai_artifact_retention_days' => (int) $validated['ai_artifact_retention_days'],
            ]
        );

        $this->sensitiveEvents->record(
            actionType: 'retention.settings_updated',
            entityType: 'company',
            entityId: $companyId,
            metadata: [
                'video_retention_days' => (int) $validated['video_retention_days'],
                'ai_artifact_retention_days' => (int) $validated['ai_artifact_retention_days'],
            ],
            actor: $actor
        );

        return redirect()
            ->route('admin.health.index', $this->companyQuery($request, $companyId))
            ->with('status', __('ui.health.flash.retention_saved'));
    }

    public function runRetentionPrune(Request $request): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $companyId = $this->managedCompanyId($request, true);
        abort_unless(is_string($companyId) && $companyId !== '', 403);

        PruneCompanyRetentionDataJob::dispatch($companyId);

        $this->sensitiveEvents->record(
            actionType: 'retention.prune_queued',
            entityType: 'company',
            entityId: $companyId,
            metadata: ['queued_by' => (string) $actor->id],
            actor: $actor
        );

        return redirect()
            ->route('admin.health.index', $this->companyQuery($request, $companyId))
            ->with('status', __('ui.health.flash.prune_queued'));
    }

    /**
     * @return Collection<int, array{key: string, title: string, status: string, detail: string}>
     */
    private function buildChecks(): Collection
    {
        $checks = collect();

        $appKeyPresent = trim((string) config('app.key')) !== '';
        $checks->push([
            'key' => 'app_key',
            'title' => __('ui.health.checks.app_key'),
            'status' => $appKeyPresent ? 'pass' : 'fail',
            'detail' => $appKeyPresent
                ? __('ui.health.details.app_key_pass')
                : __('ui.health.details.app_key_fail'),
        ]);

        $isProduction = app()->isProduction();
        $appDebug = (bool) config('app.debug');
        $checks->push([
            'key' => 'app_debug',
            'title' => __('ui.health.checks.debug_mode'),
            'status' => $isProduction && $appDebug ? 'fail' : ($appDebug ? 'warning' : 'pass'),
            'detail' => $isProduction && $appDebug
                ? __('ui.health.details.debug_fail')
                : ($appDebug ? __('ui.health.details.debug_warning') : __('ui.health.details.debug_pass')),
        ]);

        $mailer = (string) config('mail.default', '');
        $smtpHost = trim((string) config('mail.mailers.smtp.host', ''));
        $mailReady = $mailer !== '' && ($mailer !== 'smtp' || $smtpHost !== '');
        $checks->push([
            'key' => 'mail',
            'title' => __('ui.health.checks.mail'),
            'status' => $mailReady ? 'pass' : 'warning',
            'detail' => $mailReady
                ? __('ui.health.details.mail_pass', ['mailer' => $mailer])
                : __('ui.health.details.mail_warning'),
        ]);

        $queueDriver = (string) config('queue.default', '');
        $queueTable = (string) config('queue.connections.database.table', 'jobs');
        $queueReady = $queueDriver !== '' && $queueDriver !== 'sync';
        $queueStatus = $queueReady ? 'pass' : 'warning';
        $queueDetail = $queueReady
            ? __('ui.health.details.queue_pass', ['driver' => $queueDriver])
            : __('ui.health.details.queue_warning');

        if ($queueDriver === 'database') {
            if (! Schema::hasTable($queueTable)) {
                $queueStatus = 'fail';
                $queueDetail = __('ui.health.details.queue_fail_table', ['table' => $queueTable]);
            }
        }

        $checks->push([
            'key' => 'queue',
            'title' => __('ui.health.checks.queue'),
            'status' => $queueStatus,
            'detail' => $queueDetail,
        ]);

        $storageRoot = storage_path('app');
        $storageReady = is_dir($storageRoot) && is_writable($storageRoot);
        $publicStorageReady = is_link(public_path('storage')) || is_dir(public_path('storage'));
        $checks->push([
            'key' => 'storage',
            'title' => __('ui.health.checks.storage'),
            'status' => ($storageReady && $publicStorageReady) ? 'pass' : 'warning',
            'detail' => ($storageReady && $publicStorageReady)
                ? __('ui.health.details.storage_pass')
                : __('ui.health.details.storage_warning'),
        ]);

        $geminiKey = trim((string) config('services.gemini.api_key', ''));
        $geminiModel = trim((string) config('services.gemini.model', ''));
        $localStub = (bool) config('services.gemini.local_stub_enabled', false);
        $aiReady = ($geminiKey !== '' && $geminiModel !== '') || $localStub;
        $checks->push([
            'key' => 'ai',
            'title' => __('ui.health.checks.ai'),
            'status' => $aiReady ? 'pass' : 'warning',
            'detail' => $aiReady
                ? __('ui.health.details.ai_pass', ['mode' => $localStub ? 'stub' : 'provider'])
                : __('ui.health.details.ai_warning'),
        ]);

        return $checks;
    }

    /**
     * @return array<string, string>
     */
    private function companyQuery(Request $request, string $companyId): array
    {
        if ($request->user()?->isSuperadmin()) {
            return ['company_id' => $companyId];
        }

        return [];
    }
}
