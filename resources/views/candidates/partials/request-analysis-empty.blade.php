@php
    $applicationId = $applicationId ?? null;
    $query = $query ?? [];
    $status = trim((string) ($status ?? 'pending_analysis'));
    $cvSourceStatus = trim((string) ($cvSourceStatus ?? ''));
@endphp

<div class="space-y-2">
    <p class="text-sm text-slate-600">{{ __('candidates.detail.not_scored') }}</p>
    <p class="text-xs font-semibold text-slate-600">{{ __('candidates.detail.analysis_status') }}: {{ \App\Services\Analysis\CandidateAnalysisService::analysisStatusLabel($status) }}</p>
    @if($cvSourceStatus !== '')
        <p class="text-xs font-semibold text-slate-600">{{ __('candidates.detail.cv_source_status') }}: {{ \App\Services\Analysis\CandidateAnalysisService::sourceStatusLabel($cvSourceStatus, 'cv') }}</p>
    @endif
    <p class="text-xs text-slate-500">{{ __('candidates.detail.analysis_auto_hint') }}</p>
</div>
