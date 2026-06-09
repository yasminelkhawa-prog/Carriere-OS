<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Admin\Concerns\ResolvesManagedCompany;
use App\Jobs\GenerateExportJob;
use App\Models\Application;
use App\Models\CompanyMembership;
use App\Models\Export;
use App\Models\Job;
use App\Models\User;
use App\Support\Audit\SensitiveEventRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;

class ReportingExportController extends Controller
{
    use ResolvesManagedCompany;

    public function __construct(private readonly SensitiveEventRecorder $sensitiveEvents)
    {
    }

    private const DATE_RANGE_OPTIONS = [
        '7d' => 7,
        '30d' => 30,
        '90d' => 90,
        '180d' => 180,
        '365d' => 365,
        'all' => null,
    ];

    public function storeOverview(Request $request): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $companyId = $this->managedCompanyId($request, true);
        abort_unless(is_string($companyId) && $companyId !== '', 403);
        abort_unless($this->canRequestExport($actor, $companyId), 403);

        $validated = $request->validate([
            'department_id' => [
                'nullable',
                'uuid',
                Rule::exists('departments', 'id')->where(
                    static fn ($query) => $query->where('company_id', $companyId)
                ),
            ],
            'job_id' => [
                'nullable',
                'uuid',
                Rule::exists('jobs', 'id')->where(
                    static fn ($query) => $query->where('company_id', $companyId)
                ),
            ],
            'date_range' => ['nullable', Rule::in(array_keys(self::DATE_RANGE_OPTIONS))],
            'format' => ['required', Rule::in(Export::formats())],
        ]);

        $dateRange = (string) ($validated['date_range'] ?? '30d');
        if (! array_key_exists($dateRange, self::DATE_RANGE_OPTIONS)) {
            $dateRange = '30d';
        }

        $days = self::DATE_RANGE_OPTIONS[$dateRange];
        $now = CarbonImmutable::now();
        $dateFrom = is_int($days) ? $now->subDays($days)->startOfDay() : null;
        $dateTo = is_int($days) ? $now->endOfDay() : null;

        $filters = [
            'department_id' => isset($validated['department_id']) ? (string) $validated['department_id'] : null,
            'job_id' => isset($validated['job_id']) ? (string) $validated['job_id'] : null,
            'date_range' => $dateRange,
            'date_from' => $dateFrom?->toDateTimeString(),
            'date_to' => $dateTo?->toDateTimeString(),
        ];

        $this->queueExport(
            companyId: $companyId,
            requestedByUserId: (string) $actor->id,
            exportType: Export::TYPE_DASHBOARD_OVERVIEW,
            filters: $filters,
            format: (string) $validated['format'],
            actor: $actor
        );

        return $this->queuedRedirect(
            request: $request,
            actor: $actor,
            exportType: Export::TYPE_DASHBOARD_OVERVIEW,
            fallbackRoute: 'home',
            fallbackParams: array_filter(array_merge(
                $this->companyQuery($request),
                [
                    'department_id' => (string) ($filters['department_id'] ?? ''),
                    'job_id' => (string) ($filters['job_id'] ?? ''),
                    'date_range' => (string) ($filters['date_range'] ?? ''),
                ]
            ), static fn (string $value): bool => $value !== '')
        );
    }

    public function storeCandidates(Request $request): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $companyId = $this->managedCompanyId($request, true);
        abort_unless(is_string($companyId) && $companyId !== '', 403);
        abort_unless($this->canRequestExport($actor, $companyId), 403);

        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
            'job_id' => [
                'nullable',
                'uuid',
                Rule::exists('jobs', 'id')->where(
                    static fn ($query) => $query->where('company_id', $companyId)
                ),
            ],
            'stage_id' => [
                'nullable',
                'uuid',
                Rule::exists('job_pipeline_stages', 'id')->where(
                    static fn ($query) => $query->whereIn(
                        'job_id',
                        Job::withoutGlobalScopes()->select('id')->where('company_id', $companyId)
                    )
                ),
            ],
            'status' => ['nullable', Rule::in(Application::statuses())],
            'source_type' => ['nullable', 'string', 'max:100'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'application_id' => [
                'nullable',
                'uuid',
                Rule::exists('applications', 'id')->where(
                    static fn ($query) => $query->where('company_id', $companyId)
                ),
            ],
            'format' => ['required', Rule::in(Export::formats())],
        ]);

        $filters = [
            'q' => isset($validated['q']) ? trim((string) $validated['q']) : null,
            'job_id' => isset($validated['job_id']) ? (string) $validated['job_id'] : null,
            'stage_id' => isset($validated['stage_id']) ? (string) $validated['stage_id'] : null,
            'status' => isset($validated['status']) ? (string) $validated['status'] : null,
            'source_type' => isset($validated['source_type']) ? trim((string) $validated['source_type']) : null,
            'date_from' => isset($validated['date_from']) ? CarbonImmutable::parse((string) $validated['date_from'])->toDateString() : null,
            'date_to' => isset($validated['date_to']) ? CarbonImmutable::parse((string) $validated['date_to'])->toDateString() : null,
            'application_id' => isset($validated['application_id']) ? (string) $validated['application_id'] : null,
        ];

        $this->queueExport(
            companyId: $companyId,
            requestedByUserId: (string) $actor->id,
            exportType: Export::TYPE_CANDIDATE_LIST,
            filters: $filters,
            format: (string) $validated['format'],
            actor: $actor
        );

        return $this->queuedRedirect(
            request: $request,
            actor: $actor,
            exportType: Export::TYPE_CANDIDATE_LIST,
            fallbackRoute: 'candidates.index',
            fallbackParams: array_filter(array_merge(
                $this->companyQuery($request),
                [
                    'q' => (string) ($filters['q'] ?? ''),
                    'job_id' => (string) ($filters['job_id'] ?? ''),
                    'stage_id' => (string) ($filters['stage_id'] ?? ''),
                    'status' => (string) ($filters['status'] ?? ''),
                    'source_type' => (string) ($filters['source_type'] ?? ''),
                    'date_from' => (string) ($filters['date_from'] ?? ''),
                    'date_to' => (string) ($filters['date_to'] ?? ''),
                    'application_id' => (string) ($filters['application_id'] ?? ''),
                ]
            ), static fn (string $value): bool => $value !== '')
        );
    }

    public function download(Request $request, Export $export): Response|RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        if (! $request->hasValidSignature()) {
            $this->sensitiveEvents->record(
                actionType: 'export.download_denied',
                entityType: 'export',
                entityId: (string) $export->id,
                metadata: ['reason' => 'invalid_signature'],
                actor: $actor
            );

            return redirect()
                ->route('home')
                ->with('error', __('ui.exports.flash.download_forbidden'));
        }

        if (! $actor->can('view', $export)) {
            $this->sensitiveEvents->record(
                actionType: 'export.download_denied',
                entityType: 'export',
                entityId: (string) $export->id,
                metadata: ['reason' => 'authorization_failed'],
                actor: $actor
            );

            return redirect()
                ->route('home')
                ->with('error', __('ui.exports.flash.download_forbidden'));
        }

        if (
            $export->status !== Export::STATUS_COMPLETED
            || ! is_string($export->file_url)
            || trim($export->file_url) === ''
            || ! Storage::disk('local')->exists($export->file_url)
        ) {
            $this->sensitiveEvents->record(
                actionType: 'export.download_denied',
                entityType: 'export',
                entityId: (string) $export->id,
                metadata: ['reason' => 'file_not_ready'],
                actor: $actor
            );

            return redirect()
                ->route('home')
                ->with('error', __('ui.exports.flash.file_not_ready'));
        }

        $this->sensitiveEvents->record(
            actionType: 'export.downloaded',
            entityType: 'export',
            entityId: (string) $export->id,
            metadata: ['format' => (string) $export->format],
            actor: $actor
        );

        $path = (string) $export->file_url;
        $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
        $contentType = $extension === 'pdf' ? 'application/pdf' : 'text/csv; charset=UTF-8';

        return response(Storage::disk('local')->get($path), 200, [
            'Content-Type' => $contentType,
            'Content-Disposition' => 'attachment; filename="'.addslashes('export-'.$export->id.'.'.$extension).'"',
        ]);
    }

    public static function signedDownloadUrl(Export $export): string
    {
        return URL::temporarySignedRoute(
            'exports.download',
            now()->addMinutes(20),
            ['export' => (string) $export->id]
        );
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function queueExport(
        string $companyId,
        string $requestedByUserId,
        string $exportType,
        array $filters,
        string $format,
        ?User $actor = null
    ): void {
        $export = Export::withoutGlobalScopes()->create([
            'company_id' => $companyId,
            'export_type' => $exportType,
            'requested_by_user_id' => $requestedByUserId,
            'filters_json' => $filters,
            'format' => $format,
            'status' => Export::STATUS_QUEUED,
            'file_url' => null,
        ]);

        $this->sensitiveEvents->record(
            actionType: 'export.requested',
            entityType: 'export',
            entityId: (string) $export->id,
            metadata: [
                'export_type' => $exportType,
                'format' => $format,
            ],
            actor: $actor
        );

        GenerateExportJob::dispatch((string) $export->id);
    }

    private function canRequestExport(User $actor, string $companyId): bool
    {
        if ($actor->isSuperadmin()) {
            return true;
        }

        return $actor->memberships()
            ->where('company_id', $companyId)
            ->where('membership_status', CompanyMembership::STATUS_ACTIVE)
            ->whereIn('company_role', [
                CompanyMembership::ROLE_COMPANY_ADMIN,
                CompanyMembership::ROLE_RECRUITER,
                CompanyMembership::ROLE_MANAGER,
                CompanyMembership::ROLE_EMPLOYEE,
            ])->exists();
    }

    /**
     * @return array<string, string>
     */
    private function companyQuery(Request $request): array
    {
        $companyId = (string) $request->input('company_id', $request->query('company_id', ''));

        return $companyId !== '' ? ['company_id' => $companyId] : [];
    }

    /**
     * @param array<string, string> $fallbackParams
     */
    private function queuedRedirect(
        Request $request,
        User $actor,
        string $exportType,
        string $fallbackRoute,
        array $fallbackParams
    ): RedirectResponse {
        if ($actor->can('access-admin-pages')) {
            return redirect()
                ->route('admin.exports.index', array_filter(array_merge(
                    $this->companyQuery($request),
                    ['export_type' => $exportType]
                ), static fn (string $value): bool => $value !== ''))
                ->with('status', __('ui.exports.flash.queued'));
        }

        return redirect()
            ->route($fallbackRoute, $fallbackParams)
            ->with('status', __('ui.exports.flash.queued'));
    }
}
