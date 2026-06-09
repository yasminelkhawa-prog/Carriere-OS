@php
    $statusTracker = is_array($statusTracker ?? null) ? $statusTracker : [];
    $trackerSteps = collect((array) ($statusTracker['steps'] ?? []));
    $trackerUpdated = (string) ($statusTracker['updated_human'] ?? __('candidate_portal.status_tracker.just_now'));

    $xaiInsight = is_array($xaiInsight ?? null) ? $xaiInsight : [];
    $xaiScore = $xaiInsight['score'] ?? null;
    $xaiSummary = trim((string) ($xaiInsight['summary'] ?? ''));
    $xaiAnalysisStatusLabel = trim((string) ($xaiInsight['analysis_status_label'] ?? ''));
    $xaiCvSourceStatusLabel = trim((string) ($xaiInsight['cv_source_status_label'] ?? ''));
    $xaiReasons = collect((array) ($xaiInsight['reasons'] ?? []))
        ->map(static fn ($reason): string => trim((string) $reason))
        ->filter(static fn (string $reason): bool => $reason !== '')
        ->values();
    $xaiUpdated = (string) ($xaiInsight['updated_human'] ?? __('candidate_portal.xai.just_now'));
@endphp

<div class="mt-4 grid gap-3 xl:grid-cols-2">
    <article class="rounded-xl border border-primary-200/60 bg-primary-50/60 p-4" data-status-tracker-card data-application-id="{{ $application->id }}">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
                <p class="text-xs uppercase tracking-[0.18em] text-primary-800">{{ __('candidate_portal.status_tracker.title') }}</p>
                <p class="mt-1 text-xs text-slate-700">{{ __('candidate_portal.status_tracker.subtitle') }}</p>
            </div>
            <span class="inline-flex rounded-full border border-primary-200 bg-white px-2 py-1 text-[11px] font-semibold text-primary-800">
                {{ __('candidate_portal.status_tracker.live') }}
            </span>
        </div>

        <ol class="mt-3 space-y-2" data-status-step-list>
            @foreach($trackerSteps as $step)
                @php
                    $state = (string) ($step['state'] ?? 'pending');
                    $rowClasses = match ($state) {
                        'completed' => 'border-success-200/70 bg-success-50/70',
                        'current' => 'border-primary-200/70 bg-white',
                        default => 'border-slate-200/80 bg-white/80',
                    };
                    $dotClasses = match ($state) {
                        'completed' => 'bg-success-500',
                        'current' => 'bg-primary-500',
                        default => 'bg-slate-300',
                    };
                @endphp
                <li class="rounded-lg border p-2.5 {{ $rowClasses }}" data-status-step-row data-status-step-key="{{ $step['key'] ?? '' }}" data-status-state="{{ $state }}">
                    <div class="flex items-start gap-2">
                        <span class="mt-1 inline-flex h-2.5 w-2.5 rounded-full {{ $dotClasses }}" aria-hidden="true"></span>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="text-xs font-semibold text-slate-900" data-status-step-label>{{ $step['label'] ?? '' }}</p>
                                <span class="rounded-full border border-slate-200 bg-white px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-700" data-status-step-state>
                                    {{ $step['state_label'] ?? __('candidate_portal.status_tracker.states.pending') }}
                                </span>
                            </div>
                            <p class="mt-1 text-xs text-slate-600" data-status-step-detail>{{ $step['detail'] ?? '' }}</p>
                        </div>
                    </div>
                </li>
            @endforeach
        </ol>

        <p class="mt-3 text-[11px] text-slate-600">
            {{ __('candidate_portal.status_tracker.last_updated') }}:
            <span data-status-updated-text>{{ $trackerUpdated }}</span>
        </p>
    </article>

    <article class="rounded-xl border border-aura-200/70 bg-aura-50/60 p-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
                <p class="text-xs uppercase tracking-[0.18em] text-aura-800">{{ __('candidate_portal.xai.title') }}</p>
                <p class="mt-1 text-xs text-slate-700">{{ __('candidate_portal.xai.subtitle') }}</p>
            </div>
            @if(is_numeric($xaiScore))
                <span class="inline-flex rounded-full border border-aura-200 bg-white px-2 py-1 text-[11px] font-semibold text-aura-800">
                    {{ __('candidate_portal.xai.score_label', ['score' => number_format((float) $xaiScore, 1)]) }}
                </span>
            @else
                <span class="inline-flex rounded-full border border-slate-200 bg-white px-2 py-1 text-[11px] font-semibold text-slate-700">
                    {{ __('candidate_portal.xai.not_scored') }}
                </span>
            @endif
        </div>

        <p class="mt-3 text-sm text-slate-800">
            {{ $xaiSummary !== '' ? $xaiSummary : __('candidate_portal.xai.pending_summary') }}
        </p>

        @if($xaiAnalysisStatusLabel !== '' || $xaiCvSourceStatusLabel !== '')
            <div class="mt-3 space-y-1 rounded-md border border-white/80 bg-white/80 px-2.5 py-2 text-xs text-slate-700">
                @if($xaiAnalysisStatusLabel !== '')
                    <p><span class="font-semibold text-slate-900">{{ __('candidates.detail.analysis_status') }}:</span> {{ $xaiAnalysisStatusLabel }}</p>
                @endif
                @if($xaiCvSourceStatusLabel !== '')
                    <p><span class="font-semibold text-slate-900">{{ __('candidates.detail.cv_source_status') }}:</span> {{ $xaiCvSourceStatusLabel }}</p>
                @endif
            </div>
        @endif

        <ul class="mt-3 space-y-1.5">
            @forelse($xaiReasons as $reason)
                <li class="rounded-md border border-white/80 bg-white/80 px-2.5 py-1.5 text-xs text-slate-700">
                    {{ $reason }}
                </li>
            @empty
                <li class="rounded-md border border-white/80 bg-white/80 px-2.5 py-1.5 text-xs text-slate-700">
                    {{ __('candidate_portal.xai.reasons.pending') }}
                </li>
            @endforelse
        </ul>

        <p class="mt-3 text-[11px] text-slate-600">
            {{ __('candidate_portal.xai.last_updated') }}: {{ $xaiUpdated }}
        </p>
    </article>
</div>
