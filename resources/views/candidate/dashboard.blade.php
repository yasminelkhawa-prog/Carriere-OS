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
                        <a href="{{ route('candidate.updates', ['company' => $company->slug]) }}" class="inline-flex items-center rounded-xl border border-white/30 bg-transparent px-4 py-2 text-sm font-semibold text-white transition-weightless hover:bg-white/10">{{ __('ui.nav.candidate_updates') }}</a>
                    </div>
                </div>

                <article class="rounded-[1.75rem] border border-white/15 bg-white/10 p-5 backdrop-blur-xl">
                    <p class="text-xs uppercase tracking-[0.2em] text-white/70">{{ __('candidate_portal.dashboard.focus.eyebrow') }}</p>
                    <h2 class="mt-2 text-xl font-semibold">{{ __('candidate_portal.dashboard.focus.title') }}</h2>

                    @if($upcomingInterview)
                        <div class="mt-4 rounded-2xl border border-white/15 bg-slate-950/20 p-4">
                            <p class="text-xs uppercase tracking-[0.18em] text-white/65">{{ __('candidate_portal.dashboard.focus.next_interview') }}</p>
                            <p class="mt-2 text-lg font-semibold">{{ data_get($upcomingInterview, 'application.job.title', __('sjt.messages.unknown_job')) }}</p>
                            <p class="mt-1 text-sm text-white/80">{{ data_get($upcomingInterview, 'interview.scheduled_start_at')?->format('M j, Y g:i A') }}</p>
                        </div>
                    @else
                        <div class="mt-4 rounded-2xl border border-dashed border-white/20 bg-slate-950/15 p-4 text-sm text-white/80">{{ __('candidate_portal.dashboard.focus.no_interview') }}</div>
                    @endif

                    <div class="mt-4 rounded-2xl border border-white/15 bg-slate-950/15 p-4">
                        <p class="text-xs uppercase tracking-[0.18em] text-white/65">{{ __('candidate_portal.dashboard.focus.next_step') }}</p>
                        <p class="mt-2 text-sm text-white/85">{{ $primaryNextStep }}</p>
                    </div>
                </article>
            </div>
        </section>

        <div class="grid gap-6 xl:grid-cols-[1.05fr_0.95fr]">
            <x-glass-card :title="__('ui.nav.candidate_updates')" :subtitle="__('candidate_portal.notifications.subtitle')">
                @if($previewNotifications->isEmpty())
                    <x-empty-state :title="__('candidate_portal.notifications.empty_title')" :message="__('candidate_portal.notifications.empty_message')" />
                @else
                    <div class="space-y-3">
                        @foreach($previewNotifications as $notification)
                            <article class="rounded-xl border border-white/80 bg-white/75 p-4">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <span class="inline-flex rounded-full border border-aura-200/70 bg-aura-50 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide text-aura-800">{{ __('candidate_portal.notifications.types.'.($notification['type'] ?? 'application')) }}</span>
                                    <span class="text-xs text-slate-500">{{ \Illuminate\Support\Carbon::parse($notification['created_at'])->diffForHumans() }}</span>
                                </div>
                                <p class="mt-2 text-sm font-semibold text-slate-900">{{ $notification['title'] }}</p>
                                <p class="mt-1 text-sm text-slate-700">{{ $notification['message'] }}</p>
                            </article>
                        @endforeach
                    </div>
                @endif
            </x-glass-card>

            <x-glass-card :title="__('candidate_portal.social_hub.preview_title')" :subtitle="__('candidate_portal.social_hub.preview_description')">
                <div class="space-y-3">
                    @forelse(($socialHubPreviewPosts ?? collect()) as $post)
                        <div class="rounded-xl border border-slate-200/80 bg-slate-50/75 p-3">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('social_hub.types.'.$post->type) }}</p>
                                <p class="text-[11px] text-slate-500">{{ $post->created_at?->diffForHumans() }}</p>
                            </div>
                            <p class="mt-2 text-sm font-semibold text-slate-900">{{ \Illuminate\Support\Str::limit((string) $post->content_text, 110) }}</p>
                        </div>
                    @empty
                        <x-empty-state :title="__('candidate_portal.social_hub.preview_title')" :message="__('candidate_portal.social_hub.preview_empty')" />
                    @endforelse
                </div>
            </x-glass-card>
        </div>
    </div>
</x-shell-layout>
