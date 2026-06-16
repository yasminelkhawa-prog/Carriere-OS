<x-shell-layout :title="$company->name.' | '.config('app.name')">
    @php
        $displayName = (string) ($candidate->full_name ?? auth()->user()?->profile?->full_name ?? auth()->user()?->email ?? __('candidate_portal.dashboard.default_name'));
        $firstName = \Illuminate\Support\Str::of($displayName)->trim()->explode(' ')->filter()->first() ?: $displayName;
        $activeApplications = ($applications ?? collect())->where('status', \App\Models\Application::STATUS_ACTIVE)->count();
        $pendingAssessmentCount = collect($videoAssessments ?? [])->filter(fn (array $assessment): bool => (string) ($assessment['next_question_id'] ?? '') !== '')->count()
            + collect($sjtAssessments ?? [])->filter(fn (array $assessment): bool => (int) data_get($assessment, 'progress.answered', 0) < (int) data_get($assessment, 'progress.total', 0))->count();
        $pendingOnboardingTasks = ($applications ?? collect())->sum(fn ($application): int => (int) collect($application->onboardingTasks ?? [])->where('is_completed', false)->count());
        $feedbackReadyCount = ($reverseFeedbackEligibility ?? collect())->filter()->count();
        $upcomingInterview = ($applications ?? collect())
            ->flatMap(fn ($application) => collect($application->interviews ?? [])->map(fn ($interview) => ['application' => $application, 'interview' => $interview]))
            ->filter(fn (array $item): bool => data_get($item, 'interview.scheduled_start_at') instanceof \Illuminate\Support\Carbon && data_get($item, 'interview.scheduled_start_at')->isFuture())
            ->sortBy(fn (array $item) => data_get($item, 'interview.scheduled_start_at'))
            ->first();
        $primaryApplication = ($applications ?? collect())->firstWhere('status', \App\Models\Application::STATUS_ACTIVE) ?? ($applications ?? collect())->first();
        $primaryNextStep = $primaryApplication ? (($nextSteps ?? collect())->get((string) $primaryApplication->id, __('candidate_portal.applications.next_step_default'))) : __('candidate_portal.dashboard.focus.no_active_step');
        $previewNotifications = collect($portalNotifications ?? [])->take(4);
    @endphp

    <div class="space-y-6 pb-16">
        @if(session('status'))
            <x-toast-alert type="success">{{ session('status') }}</x-toast-alert>
        @endif
        @if(session('error'))
            <x-toast-alert type="warning">{{ session('error') }}</x-toast-alert>
        @endif

        <section class="relative overflow-hidden rounded-[2rem] border border-white/70 bg-gradient-to-r from-slate-900 via-primary-800 to-aura-700 p-6 text-white shadow-[0_34px_72px_-34px_rgba(15,23,42,0.7)] sm:p-8">
            <div class="absolute -left-10 top-8 size-32 rounded-full bg-white/10 blur-2xl"></div>
            <div class="absolute -right-6 top-0 size-40 rounded-full bg-success-300/15 blur-3xl"></div>
            <div class="relative grid gap-6 xl:grid-cols-[1.5fr_0.9fr]">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.26em] text-white/75">{{ __('candidate_portal.dashboard.eyebrow') }}</p>
                    <h1 class="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">{{ __('candidate_portal.dashboard.title', ['name' => $firstName]) }}</h1>
                    <p class="mt-3 max-w-3xl text-sm text-white/85 sm:text-base">{{ __('candidate_portal.dashboard.subtitle', ['company' => $company->name]) }}</p>

                    <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <article class="rounded-2xl border border-white/15 bg-white/10 p-4 backdrop-blur-xl">
                            <p class="text-xs uppercase tracking-[0.2em] text-white/70">{{ __('candidate_portal.dashboard.metrics.applications') }}</p>
                            <p class="mt-2 text-3xl font-semibold">{{ ($applications ?? collect())->count() }}</p>
                            <p class="mt-1 text-xs text-white/70">{{ __('candidate_portal.dashboard.metrics.active', ['count' => $activeApplications]) }}</p>
                        </article>
                        <article class="rounded-2xl border border-white/15 bg-white/10 p-4 backdrop-blur-xl">
                            <p class="text-xs uppercase tracking-[0.2em] text-white/70">{{ __('candidate_portal.dashboard.metrics.assessments') }}</p>
                            <p class="mt-2 text-3xl font-semibold">{{ $pendingAssessmentCount }}</p>
                            <p class="mt-1 text-xs text-white/70">{{ __('candidate_portal.dashboard.actions.open_assessments') }}</p>
                        </article>
                        <article class="rounded-2xl border border-white/15 bg-white/10 p-4 backdrop-blur-xl">
                            <p class="text-xs uppercase tracking-[0.2em] text-white/70">{{ __('candidate_portal.dashboard.metrics.onboarding') }}</p>
                            <p class="mt-2 text-3xl font-semibold">{{ $pendingOnboardingTasks }}</p>
                            <p class="mt-1 text-xs text-white/70">{{ __('candidate_portal.dashboard.actions.review_applications') }}</p>
                        </article>
                        <article class="rounded-2xl border border-white/15 bg-white/10 p-4 backdrop-blur-xl">
                            <p class="text-xs uppercase tracking-[0.2em] text-white/70">{{ __('candidate_portal.dashboard.metrics.decisions') }}</p>
                            <p class="mt-2 text-3xl font-semibold">{{ $feedbackReadyCount }}</p>
                            <p class="mt-1 text-xs text-white/70">{{ __('candidate_portal.dashboard.metrics.feedback', ['count' => $feedbackReadyCount]) }}</p>
                        </article>
                    </div>

                    <div class="mt-6 flex flex-wrap gap-3">
                        <a href="{{ route('candidate.applications', ['company' => $company->slug]) }}" class="inline-flex items-center rounded-xl border border-white/30 bg-white/15 px-4 py-2 text-sm font-semibold text-white transition-weightless hover:bg-white/25">{{ __('candidate_portal.dashboard.actions.review_applications') }}</a>
                        <a href="{{ route('candidate.assessments.sjt') }}" class="inline-flex items-center rounded-xl border border-white/20 bg-white px-4 py-2 text-sm font-semibold text-slate-900 transition-weightless hover:bg-slate-100">{{ __('candidate_portal.dashboard.actions.open_assessments') }}</a>

                    </div>
                </div>

                <article class="rounded-[1.75rem] border border-white/15 bg-white/10 p-5 backdrop-blur-xl">
                    <p class="text-xs uppercase tracking-[0.2em] text-white/70">{{ __('candidate_portal.dashboard.focus.eyebrow') }}</p>
                    <h2 class="mt-2 text-xl font-semibold">{{ __('candidate_portal.dashboard.focus.title') }}</h2>

                    @if(($cvTipsCount ?? 0) > 0)
                        <style>
                            @keyframes cvbotPop { 0%{opacity:0;transform:translateY(16px) scale(.8)} 60%{transform:translateY(-4px) scale(1.04)} 100%{opacity:1;transform:none} }
                            @keyframes cvbotBob { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-5px)} }
                            @keyframes cvbotBlink { 0%,90%,100%{transform:scaleY(1)} 95%{transform:scaleY(.12)} }
                            @keyframes cvbotWave { 0%,100%{transform:rotate(-4deg)} 50%{transform:rotate(20deg)} }
                            @keyframes cvbotGlow { 0%,100%{opacity:.45;r:4} 50%{opacity:1;r:5} }
                            .cvbot-pop{animation:cvbotPop .65s cubic-bezier(.18,1.25,.3,1) both}
                            .cvbot-bob{animation:cvbotBob 3.2s ease-in-out infinite;transform-origin:center}
                            .cvbot-eye{animation:cvbotBlink 4.5s infinite;transform-origin:center}
                            .cvbot-arm{animation:cvbotWave 2.2s ease-in-out infinite;transform-origin:13px 60px}
                            .cvbot-glow{animation:cvbotGlow 1.6s ease-in-out infinite}
                            @media (prefers-reduced-motion: reduce){ .cvbot-pop,.cvbot-bob,.cvbot-eye,.cvbot-arm,.cvbot-glow{animation:none} }
                        </style>
                        <div class="cvbot-pop mt-4 flex items-end gap-3">
                            {{-- Mascotte robot --}}
                            <div class="cvbot-bob shrink-0 drop-shadow-lg">
                                <svg width="74" height="82" viewBox="0 0 80 88" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                    <line x1="40" y1="4" x2="40" y2="18" stroke="#34d399" stroke-width="3" stroke-linecap="round"/>
                                    <circle class="cvbot-glow" cx="40" cy="5" r="4.5" fill="#34d399"/>
                                    <rect x="7" y="34" width="7" height="16" rx="3.5" fill="#6d28d9"/>
                                    <rect x="66" y="34" width="7" height="16" rx="3.5" fill="#6d28d9"/>
                                    <rect x="12" y="18" width="56" height="44" rx="16" fill="#7c3aed"/>
                                    <rect x="19" y="26" width="42" height="28" rx="11" fill="#241b46"/>
                                    <g class="cvbot-eye">
                                        <circle cx="32" cy="39" r="4.6" fill="#67e8f9"/>
                                        <circle cx="48" cy="39" r="4.6" fill="#67e8f9"/>
                                    </g>
                                    <path d="M33 47 Q40 51.5 47 47" stroke="#67e8f9" stroke-width="2.5" stroke-linecap="round" fill="none"/>
                                    <rect x="24" y="62" width="32" height="21" rx="9" fill="#6d28d9"/>
                                    <rect class="cvbot-arm" x="9" y="55" width="7" height="19" rx="3.5" fill="#7c3aed"/>
                                </svg>
                            </div>
                            {{-- Bulle de dialogue --}}
                            <div class="relative flex-1 rounded-2xl bg-white p-4 text-slate-800 shadow-xl ring-1 ring-black/5">
                                <span class="absolute -left-1.5 bottom-5 h-4 w-4 rotate-45 rounded-[3px] bg-white"></span>
                                <div class="relative flex flex-wrap items-center justify-between gap-2">
                                    <p class="text-[11px] font-bold uppercase tracking-[0.16em] text-aura-700">{{ __('candidate_portal.dashboard.focus.cv_tip.eyebrow') }}</p>
                                    <span class="inline-flex items-center rounded-full bg-aura-100 px-2.5 py-0.5 text-[11px] font-bold text-aura-700">
                                        {{ __('candidate_portal.dashboard.focus.cv_tip.counter', ['current' => $cvTipNumber, 'total' => $cvTipsCount]) }}
                                    </span>
                                </div>
                                <p class="relative mt-2 text-sm leading-relaxed text-slate-700">💡 {{ $cvTipOfDay }}</p>
                            </div>
                        </div>
                    @endif
                </article>
            </div>
        </section>


    </div>
</x-shell-layout>
