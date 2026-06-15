@php
    $statusTracker = is_array($statusTracker ?? null) ? $statusTracker : [];
    $trackerSteps = collect((array) ($statusTracker['steps'] ?? []));
    $trackerUpdated = (string) ($statusTracker['updated_human'] ?? __('candidate_portal.status_tracker.just_now'));

    $totalSteps = $trackerSteps->count();
    $completedCount = $trackerSteps->filter(fn ($step) => ($step['state'] ?? 'pending') === 'completed')->count();
    $hasCurrent = $trackerSteps->contains(fn ($step) => ($step['state'] ?? 'pending') === 'current');
    $rejectedIndex = $trackerSteps->search(fn ($step) => ($step['state'] ?? 'pending') === 'rejected');
    $isRejected = $rejectedIndex !== false;
    $progressUnits = $isRejected ? ($rejectedIndex + 1) * 2 : ($completedCount * 2) + ($hasCurrent ? 1 : 0);
    $progressDenominator = $totalSteps > 1 ? ($totalSteps - 1) * 2 : 1;
    $progressPercent = $totalSteps > 1
        ? max(0, min(100, (int) round(($progressUnits / $progressDenominator) * 100)))
        : ($completedCount > 0 ? 100 : 0);
    $progressGradient = $isRejected ? 'from-rose-400 to-rose-500' : 'from-success-400 via-primary-500 to-aura-500';
@endphp

<div class="mt-4">
    <article class="relative overflow-hidden rounded-2xl border border-primary-200/50 bg-gradient-to-br from-white via-primary-50/50 to-aura-50/30 p-4 shadow-sm sm:p-5" data-status-tracker-card data-application-id="{{ $application->id }}">
        <div class="pointer-events-none absolute -right-12 -top-12 size-40 rounded-full bg-primary-200/30 blur-3xl" aria-hidden="true"></div>
        <div class="pointer-events-none absolute -bottom-16 -left-10 size-36 rounded-full bg-aura-200/20 blur-3xl" aria-hidden="true"></div>

        <div class="relative flex flex-wrap items-start justify-between gap-2">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.18em] text-primary-800">{{ __('candidate_portal.status_tracker.title') }}</p>
                <p class="mt-1 text-[11px] text-slate-500">{{ __('candidate_portal.status_tracker.subtitle') }}</p>
            </div>
            <span class="inline-flex items-center gap-1.5 rounded-full border border-success-200 bg-white px-2.5 py-1 text-[11px] font-semibold text-success-700 shadow-sm">
                <span class="relative flex h-2 w-2">
                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-success-400 opacity-75"></span>
                    <span class="relative inline-flex h-2 w-2 rounded-full bg-success-500"></span>
                </span>
                {{ __('candidate_portal.status_tracker.live') }}
            </span>
        </div>

        <div class="relative mt-4 flex items-center gap-3" data-status-progress>
            <div class="h-2 flex-1 overflow-hidden rounded-full bg-slate-200/80">
                <div
                    x-data="{ width: '0%' }"
                    x-init="setTimeout(() => width = '{{ $progressPercent }}%', 150)"
                    class="h-full rounded-full bg-gradient-to-r {{ $progressGradient }} transition-all duration-[1200ms] ease-out"
                    :style="`width: ${width}`"
                    data-status-progress-fill
                    data-rejected="{{ $isRejected ? '1' : '0' }}"
                ></div>
            </div>
            <span class="text-[11px] font-bold text-slate-600 tabular-nums" data-status-progress-label>{{ $progressPercent }}%</span>
        </div>

        <ol class="relative mt-6 flex flex-col lg:flex-row w-full gap-y-3 lg:gap-y-0" data-status-step-list>
            @foreach($trackerSteps as $step)
                @php
                    $state = (string) ($step['state'] ?? 'pending');
                    $stepNumber = $loop->iteration;

                    $dotClasses = match ($state) {
                        'completed' => 'bg-success-500 shadow-[0_0_0_4px_rgba(34,197,94,0.15)]',
                        'current' => 'bg-primary-500 shadow-[0_0_0_4px_rgba(124,58,237,0.18)]',
                        'rejected' => 'bg-rose-500 shadow-[0_0_0_4px_rgba(244,63,94,0.15)]',
                        default => 'bg-white border-2 border-slate-200',
                    };

                    $badgeClasses = match ($state) {
                        'completed' => 'border-success-200 bg-success-50 text-success-700',
                        'current' => 'border-primary-200 bg-primary-50 text-primary-700',
                        'rejected' => 'border-rose-200 bg-rose-50 text-rose-700',
                        default => 'border-slate-200 bg-slate-50 text-slate-500',
                    };

                    $nextStep = $trackerSteps->values()->get($loop->index + 1);
                    $nextState = $nextStep ? ($nextStep['state'] ?? 'pending') : 'pending';

                    $liquidH = match($nextState) {
                        'current' => 'bg-gradient-to-r from-success-400 to-primary-400',
                        'completed' => 'bg-gradient-to-r from-success-400 to-success-400',
                        'rejected' => 'bg-gradient-to-r from-success-400 to-rose-400',
                        default => 'bg-gradient-to-r from-success-400 to-slate-300'
                    };

                    $liquidV = match($nextState) {
                        'current' => 'bg-gradient-to-b from-success-400 to-primary-400',
                        'completed' => 'bg-gradient-to-b from-success-400 to-success-400',
                        'rejected' => 'bg-gradient-to-b from-success-400 to-rose-400',
                        default => 'bg-gradient-to-b from-success-400 to-slate-300'
                    };

                    $delayDot = $loop->index * 800;
                    $delayLine = $delayDot;
                @endphp
                <li class="relative flex-1 lg:min-w-0" data-status-step-row data-status-step-key="{{ $step['key'] ?? '' }}" data-status-state="{{ $state }}">
                    <!-- Vertical line for mobile -->
                    @if(!$loop->last)
                        <div class="absolute left-[15px] top-[32px] bottom-[-12px] w-[3px] lg:hidden bg-slate-200 rounded-full overflow-hidden">
                            @if($state === 'completed')
                                <div x-data="{ fill: false }" x-init="setTimeout(() => fill = true, {{ $delayLine }})" class="w-full {{ $liquidV }} transition-all duration-[800ms] ease-in-out h-0" :class="fill ? '!h-full' : ''"></div>
                            @endif
                        </div>
                    @endif

                    <!-- Horizontal line for desktop -->
                    @if(!$loop->last)
                        <div class="hidden lg:block absolute left-1/2 top-[15px] w-full h-[3px] bg-slate-200 rounded-full overflow-hidden z-0">
                            @if($state === 'completed')
                                <div x-data="{ fill: false }" x-init="setTimeout(() => fill = true, {{ $delayLine }})" class="h-full {{ $liquidH }} transition-all duration-[800ms] ease-in-out w-0" :class="fill ? '!w-full' : ''"></div>
                            @endif
                        </div>
                    @endif

                    <div class="flex lg:flex-col items-start lg:items-center gap-4 lg:gap-3">
                        <div x-data="{ active: false }" x-init="setTimeout(() => active = true, {{ $delayDot }})" class="relative z-10 flex h-8 w-8 shrink-0 items-center justify-center rounded-full ring-4 ring-white transition-all duration-500" :class="active ? 'scale-110 {{ $dotClasses }}' : 'scale-90 bg-slate-200'">
                            @if($state === 'current')
                                <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-primary-400 opacity-50" :class="active ? '' : 'hidden'" aria-hidden="true"></span>
                            @endif

                            <span class="relative transition-opacity duration-300" :class="active ? 'opacity-100' : 'opacity-0'">
                                @switch($state)
                                    @case('completed')
                                        <svg class="h-4 w-4 text-white" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" />
                                        </svg>
                                        @break

                                    @case('rejected')
                                        <svg class="h-4 w-4 text-white" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path fill-rule="evenodd" d="M5.28 4.22a.75.75 0 0 0-1.06 1.06L8.94 10l-4.72 4.72a.75.75 0 1 0 1.06 1.06L10 11.06l4.72 4.72a.75.75 0 1 0 1.06-1.06L11.06 10l4.72-4.72a.75.75 0 0 0-1.06-1.06L10 8.94 5.28 4.22Z" clip-rule="evenodd" />
                                        </svg>
                                        @break

                                    @case('current')
                                        <span class="h-2.5 w-2.5 rounded-full bg-white"></span>
                                        @break

                                    @default
                                        <span class="text-[11px] font-bold text-slate-400">{{ $stepNumber }}</span>
                                @endswitch
                            </span>
                        </div>

                        <div class="min-w-0 flex-1 pb-6 lg:pb-0 lg:px-2 pt-0.5 lg:pt-1.5 lg:text-center" x-data="{ show: false }" x-init="setTimeout(() => show = true, {{ $delayDot + 100 }})">
                            <div class="flex flex-col gap-1.5 lg:items-center transition-all duration-500 transform" :class="show ? 'translate-y-0 opacity-100' : 'translate-y-2 opacity-0'">
                                <p class="text-[13px] font-semibold text-slate-900" data-status-step-label>{{ $step['label'] ?? '' }}</p>
                                <div>
                                    <span class="inline-flex rounded-full border px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider {{ $badgeClasses }}" data-status-step-state>
                                        {{ $step['state_label'] ?? __('candidate_portal.status_tracker.states.pending') }}
                                    </span>
                                </div>
                            </div>
                            <p class="mt-2 text-[11px] leading-relaxed text-slate-600 lg:mx-auto lg:max-w-[140px] transition-all duration-500 delay-100 transform" :class="show ? 'translate-y-0 opacity-100' : 'translate-y-2 opacity-0'" data-status-step-detail>{{ $step['detail'] ?? '' }}</p>
                        </div>
                    </div>
                </li>
            @endforeach
        </ol>

        <p class="relative mt-3 flex items-center gap-1.5 text-[11px] text-slate-500">
            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm.75-13a.75.75 0 0 0-1.5 0v5c0 .414.336.75.75.75h4a.75.75 0 0 0 0-1.5h-3.25V5Z" clip-rule="evenodd" />
            </svg>
            {{ __('candidate_portal.status_tracker.last_updated') }}:
            <span class="font-medium text-slate-700" data-status-updated-text>{{ $trackerUpdated }}</span>
        </p>
    </article>
</div>
