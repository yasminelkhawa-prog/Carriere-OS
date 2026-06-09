<?php

namespace App\Services\Reporting;

use App\Http\Controllers\CandidateWorkspaceController;
use App\Models\Application;
use App\Models\ApplicationActivityEvent;
use App\Models\Export;
use App\Models\Job;
use App\Models\JobPipelineStage;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use RuntimeException;

class ExportDatasetService
{
    /**
     * @return array{
     *   title: string,
     *   columns: array<int, string>,
     *   rows: array<int, array<int, string>>,
     *   metadata: array<string, mixed>
     * }
     */
    public function buildPayload(Export $export): array
    {
        $filters = is_array($export->filters_json) ? $export->filters_json : [];

        return match ((string) $export->export_type) {
            Export::TYPE_DASHBOARD_OVERVIEW => $this->buildOverviewPayload((string) $export->company_id, $filters),
            Export::TYPE_CANDIDATE_LIST => $this->buildCandidatePayload((string) $export->company_id, $filters),
            default => throw new RuntimeException('Unsupported export type: '.$export->export_type),
        };
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{
     *   title: string,
     *   columns: array<int, string>,
     *   rows: array<int, array<int, string>>,
     *   metadata: array<string, mixed>
     * }
     */
    private function buildOverviewPayload(string $companyId, array $filters): array
    {
        $rows = $this->buildOverviewRows($companyId, $filters);

        $formattedRows = $rows->map(static function (object $row): array {
            return [
                (string) $row->application_id,
                (string) $row->candidate_name,
                (string) ($row->department_name ?? ''),
                (string) $row->job_title,
                (string) $row->stage_label,
                (string) $row->status,
                (string) $row->source_type,
                $row->ai_score !== null ? number_format((float) $row->ai_score, 1, '.', '') : '',
                optional($row->created_at)->format('Y-m-d H:i:s') ?: '',
                optional($row->last_activity_at)->format('Y-m-d H:i:s') ?: '',
            ];
        })->values()->all();

        return [
            'title' => 'Overview Export',
            'columns' => [
                'Application ID',
                'Candidate',
                'Department',
                'Job',
                'Stage',
                'Status',
                'Source',
                'AI Score',
                'Created At',
                'Last Activity At',
            ],
            'rows' => $formattedRows,
            'metadata' => [
                'export_type' => Export::TYPE_DASHBOARD_OVERVIEW,
                'filter_snapshot' => $filters,
                'row_count' => count($formattedRows),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{
     *   title: string,
     *   columns: array<int, string>,
     *   rows: array<int, array<int, string>>,
     *   metadata: array<string, mixed>
     * }
     */
    private function buildCandidatePayload(string $companyId, array $filters): array
    {
        $maskedScope = $this->isBlindScreeningScope($companyId, $filters);
        $rows = $this->buildCandidateRows($companyId, $filters, $maskedScope);

        if ($maskedScope) {
            $formattedRows = $rows->map(static function (object $row): array {
                $applicationId = (string) $row->application_id;

                return [
                    CandidateWorkspaceController::maskedCandidateIdentifier($applicationId),
                    $applicationId,
                    (string) $row->job_title,
                    (string) $row->stage_label,
                    (string) $row->status,
                    (string) $row->source_type,
                    $row->ai_score !== null ? number_format((float) $row->ai_score, 1, '.', '') : '',
                    optional($row->created_at)->format('Y-m-d H:i:s') ?: '',
                ];
            })->values()->all();

            return [
                'title' => 'Candidate List Export (Blind Screening Scope)',
                'columns' => [
                    'Masked Identifier',
                    'Application ID',
                    'Job',
                    'Stage',
                    'Status',
                    'Source',
                    'AI Score',
                    'Created At',
                ],
                'rows' => $formattedRows,
                'metadata' => [
                    'export_type' => Export::TYPE_CANDIDATE_LIST,
                    'filter_snapshot' => $filters,
                    'blind_scope' => true,
                    'row_count' => count($formattedRows),
                ],
            ];
        }

        $formattedRows = $rows->map(static function (object $row): array {
            return [
                (string) $row->application_id,
                (string) $row->candidate_name,
                (string) $row->candidate_email,
                (string) ($row->candidate_location ?? ''),
                (string) $row->job_title,
                (string) $row->stage_label,
                (string) $row->status,
                (string) $row->source_type,
                $row->ai_score !== null ? number_format((float) $row->ai_score, 1, '.', '') : '',
                optional($row->created_at)->format('Y-m-d H:i:s') ?: '',
            ];
        })->values()->all();

        return [
            'title' => 'Candidate List Export',
            'columns' => [
                'Application ID',
                'Candidate',
                'Email',
                'Location',
                'Job',
                'Stage',
                'Status',
                'Source',
                'AI Score',
                'Created At',
            ],
            'rows' => $formattedRows,
            'metadata' => [
                'export_type' => Export::TYPE_CANDIDATE_LIST,
                'filter_snapshot' => $filters,
                'blind_scope' => false,
                'row_count' => count($formattedRows),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return Collection<int, object>
     */
    private function buildOverviewRows(string $companyId, array $filters): Collection
    {
        $activitySubquery = ApplicationActivityEvent::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->select('application_id')
            ->selectRaw('MAX(created_at) as last_activity_at')
            ->groupBy('application_id');

        $query = Application::withoutGlobalScopes()
            ->join('candidates', 'candidates.id', '=', 'applications.candidate_id')
            ->join('jobs', 'jobs.id', '=', 'applications.job_id')
            ->leftJoin('departments', 'departments.id', '=', 'jobs.department_id')
            ->join('job_pipeline_stages as stages', 'stages.id', '=', 'applications.current_stage_id')
            ->leftJoin('application_scorings as scoring', function ($join) use ($companyId): void {
                $join->on('scoring.application_id', '=', 'applications.id')
                    ->where('scoring.company_id', '=', $companyId);
            })
            ->leftJoinSub($activitySubquery, 'activity', function ($join): void {
                $join->on('activity.application_id', '=', 'applications.id');
            })
            ->select([
                'applications.id as application_id',
                'candidates.full_name as candidate_name',
                'jobs.title as job_title',
                'departments.name as department_name',
                'stages.stage_label',
                'applications.status',
                'applications.source_type',
                'applications.created_at',
                'scoring.global_match_score as ai_score',
            ])
            ->selectRaw('COALESCE(activity.last_activity_at, applications.updated_at, applications.created_at) as last_activity_at')
            ->orderByDesc('applications.created_at');

        $this->applyOverviewFilters($query, $companyId, $filters);

        return $query->get()->map(static function (object $row): object {
            if (isset($row->created_at)) {
                $row->created_at = CarbonImmutable::parse((string) $row->created_at);
            }
            if (isset($row->last_activity_at)) {
                $row->last_activity_at = CarbonImmutable::parse((string) $row->last_activity_at);
            }

            return $row;
        });
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function applyOverviewFilters(Builder $query, string $companyId, array $filters): void
    {
        $query->where('applications.company_id', $companyId);

        $jobId = trim((string) ($filters['job_id'] ?? ''));
        if ($jobId !== '') {
            $query->where('applications.job_id', $jobId);
        }

        $departmentId = trim((string) ($filters['department_id'] ?? ''));
        if ($departmentId !== '') {
            $query->whereExists(function ($subQuery) use ($departmentId): void {
                $subQuery->selectRaw('1')
                    ->from('jobs')
                    ->whereColumn('jobs.id', 'applications.job_id')
                    ->whereColumn('jobs.company_id', 'applications.company_id')
                    ->where('jobs.department_id', $departmentId);
            });
        }

        $dateFrom = $this->parseDate($filters['date_from'] ?? null, true);
        if ($dateFrom instanceof CarbonImmutable) {
            $query->where('applications.created_at', '>=', $dateFrom);
        }

        $dateTo = $this->parseDate($filters['date_to'] ?? null, false);
        if ($dateTo instanceof CarbonImmutable) {
            $query->where('applications.created_at', '<=', $dateTo);
        }
    }

    /**
     * @param array<string, mixed> $filters
     * @return Collection<int, object>
     */
    private function buildCandidateRows(string $companyId, array $filters, bool $maskedScope): Collection
    {
        $query = Application::withoutGlobalScopes()
            ->join('candidates', 'candidates.id', '=', 'applications.candidate_id')
            ->join('jobs', 'jobs.id', '=', 'applications.job_id')
            ->join('job_pipeline_stages as stages', 'stages.id', '=', 'applications.current_stage_id')
            ->leftJoin('application_scorings as scoring', function ($join) use ($companyId): void {
                $join->on('scoring.application_id', '=', 'applications.id')
                    ->where('scoring.company_id', '=', $companyId);
            })
            ->where('applications.company_id', $companyId)
            ->orderByDesc('applications.updated_at')
            ->orderByDesc('applications.created_at');

        if ($maskedScope) {
            $query->select([
                'applications.id as application_id',
                'jobs.title as job_title',
                'stages.stage_label',
                'applications.status',
                'applications.source_type',
                'applications.created_at',
                'scoring.global_match_score as ai_score',
            ]);
        } else {
            $query->select([
                'applications.id as application_id',
                'candidates.full_name as candidate_name',
                'candidates.email as candidate_email',
                'candidates.location as candidate_location',
                'jobs.title as job_title',
                'stages.stage_label',
                'applications.status',
                'applications.source_type',
                'applications.created_at',
                'scoring.global_match_score as ai_score',
            ]);
        }

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $searchPattern = '%'.Str::lower($search).'%';

            $query->where(function (Builder $sub) use ($searchPattern): void {
                $sub->whereRaw('LOWER(candidates.full_name) LIKE ?', [$searchPattern])
                    ->orWhereRaw('LOWER(candidates.email) LIKE ?', [$searchPattern])
                    ->orWhereRaw('LOWER(jobs.title) LIKE ?', [$searchPattern]);
            });
        }

        $jobId = trim((string) ($filters['job_id'] ?? ''));
        if ($jobId !== '') {
            $query->where('applications.job_id', $jobId);
        }

        $stageId = trim((string) ($filters['stage_id'] ?? ''));
        if ($stageId !== '') {
            $query->where('applications.current_stage_id', $stageId);
        }

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '') {
            $query->where('applications.status', $status);
        }

        $sourceType = trim((string) ($filters['source_type'] ?? ''));
        if ($sourceType !== '') {
            $query->where('applications.source_type', $sourceType);
        }

        $applicationId = trim((string) ($filters['application_id'] ?? ''));
        if ($applicationId !== '') {
            $query->where('applications.id', $applicationId);
        }

        $dateFrom = $this->parseDate($filters['date_from'] ?? null, true);
        if ($dateFrom instanceof CarbonImmutable) {
            $query->whereDate('applications.created_at', '>=', $dateFrom->toDateString());
        }

        $dateTo = $this->parseDate($filters['date_to'] ?? null, false);
        if ($dateTo instanceof CarbonImmutable) {
            $query->whereDate('applications.created_at', '<=', $dateTo->toDateString());
        }

        return $query->get()->map(static function (object $row): object {
            if (isset($row->created_at)) {
                $row->created_at = CarbonImmutable::parse((string) $row->created_at);
            }

            return $row;
        });
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function isBlindScreeningScope(string $companyId, array $filters): bool
    {
        $stageId = trim((string) ($filters['stage_id'] ?? ''));
        if ($stageId === '') {
            return false;
        }

        $stage = JobPipelineStage::withoutGlobalScopes()
            ->where('id', $stageId)
            ->first(['id', 'job_id', 'stage_key', 'stage_label']);

        if (! $stage instanceof JobPipelineStage) {
            return false;
        }

        $job = Job::withoutGlobalScopes()
            ->where('id', (string) $stage->job_id)
            ->where('company_id', $companyId)
            ->first(['id', 'blind_mode_active']);

        if (! $job instanceof Job || ! $job->blind_mode_active) {
            return false;
        }

        return CandidateWorkspaceController::isScreeningStage(
            (string) $stage->stage_key,
            (string) $stage->stage_label
        );
    }

    private function parseDate(mixed $value, bool $startOfDay): ?CarbonImmutable
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        try {
            $date = CarbonImmutable::parse($raw);
        } catch (\Throwable) {
            return null;
        }

        return $startOfDay ? $date->startOfDay() : $date->endOfDay();
    }
}
