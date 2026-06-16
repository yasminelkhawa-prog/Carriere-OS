<x-shell-layout :title="$company->name.' | '.config('app.name')">
    <div class="space-y-8 pb-28" data-status-tracker-root data-status-endpoint="{{ route('candidate.status-tracker', ['company' => $company->slug]) }}">
        @if(session('status'))
            <x-toast-alert type="success">{{ session('status') }}</x-toast-alert>
        @endif
        @if(session('error'))
            <x-toast-alert type="warning">{{ session('error') }}</x-toast-alert>
        @endif
        @if($errors->any())
            <x-toast-alert type="warning">{{ $errors->first() }}</x-toast-alert>
        @endif

        @php
            $feedbackReadyApplication = ($applications ?? collect())->first(function ($application) use ($reverseFeedbackEligibility) {
                return (bool) (($reverseFeedbackEligibility ?? collect())->get((string) $application->id, false));
            });
            $socialHubPreviewPosts = $socialHubPreviewPosts ?? collect();
            $displayName = (string) ($candidate->full_name ?? auth()->user()?->profile?->full_name ?? auth()->user()?->email ?? __('candidate_portal.dashboard.default_name'));
            $firstName = \Illuminate\Support\Str::of($displayName)->trim()->explode(' ')->filter()->first() ?: $displayName;
            $activeApplications = ($applications ?? collect())->where('status', \App\Models\Application::STATUS_ACTIVE)->count();
            $hiredApplications = ($applications ?? collect())->where('status', \App\Models\Application::STATUS_HIRED)->count();
            $closedApplications = ($applications ?? collect())->filter(
                fn ($application) => in_array((string) $application->status, [
                    \App\Models\Application::STATUS_REJECTED,
                    \App\Models\Application::STATUS_WITHDRAWN,
                ], true)
            )->count();
            $pendingAssessmentCount = collect($videoAssessments ?? [])
                ->filter(fn (array $assessment): bool => (string) ($assessment['next_question_id'] ?? '') !== '')
                ->count()
                + collect($sjtAssessments ?? [])
                    ->filter(function (array $assessment): bool {
                        $total = (int) data_get($assessment, 'progress.total', 0);
                        $answered = (int) data_get($assessment, 'progress.answered', 0);

                        return $total > 0 && $answered < $total;
                    })
                    ->count();
            $completedAssessmentCount = collect($videoAssessments ?? [])
                ->filter(function (array $assessment): bool {
                    $total = (int) data_get($assessment, 'progress.total', 0);
                    $answered = (int) data_get($assessment, 'progress.answered', 0);

                    return $total > 0 && $answered >= $total;
                })
                ->count()
                + collect($sjtAssessments ?? [])
                    ->filter(function (array $assessment): bool {
                        $total = (int) data_get($assessment, 'progress.total', 0);
                        $answered = (int) data_get($assessment, 'progress.answered', 0);

                        return $total > 0 && $answered >= $total;
                    })
                    ->count();
            $pendingOnboardingTasks = ($applications ?? collect())->sum(function ($application): int {
                return (int) collect($application->onboardingTasks ?? [])->where('is_completed', false)->count();
            });
            $upcomingInterview = ($applications ?? collect())
                ->flatMap(fn ($application) => collect($application->interviews ?? [])->map(fn ($interview) => ['application' => $application, 'interview' => $interview]))
                ->filter(function (array $item): bool {
                    $start = data_get($item, 'interview.scheduled_start_at');

                    return $start instanceof \Illuminate\Support\Carbon && $start->isFuture();
                })
                ->sortBy(fn (array $item) => data_get($item, 'interview.scheduled_start_at'))
                ->first();
            $dashboardPrimaryApplication = $feedbackReadyApplication
                ?? ($applications ?? collect())->firstWhere('status', \App\Models\Application::STATUS_ACTIVE)
                ?? ($applications ?? collect())->first();
            $dashboardPrimaryTracker = $dashboardPrimaryApplication
                ? (array) (($statusTrackers ?? collect())->get((string) $dashboardPrimaryApplication->id, []))
                : [];
        @endphp

        <section class="relative overflow-hidden rounded-[2rem] border border-white/70 bg-gradient-to-r from-slate-900 via-primary-800 to-aura-700 p-6 text-white shadow-[0_34px_72px_-34px_rgba(15,23,42,0.7)] sm:p-8">
            <div class="absolute -left-10 top-8 size-32 rounded-full bg-white/10 blur-2xl"></div>
            <div class="absolute -right-6 top-0 size-40 rounded-full bg-success-300/15 blur-3xl"></div>
            <div class="absolute bottom-0 left-1/3 size-48 rounded-full bg-danger-300/10 blur-3xl"></div>

            <div class="relative grid gap-6 xl:grid-cols-[1.5fr_0.9fr]">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.26em] text-white/75">{{ __('candidate_portal.dashboard.eyebrow') }}</p>
                    <h1 class="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">
                        {{ __('candidate_portal.dashboard.title', ['name' => $firstName]) }}
                    </h1>
                    <p class="mt-3 max-w-3xl text-sm text-white/85 sm:text-base">
                        {{ __('candidate_portal.dashboard.subtitle', ['company' => $company->name]) }}
                    </p>

                    <div class="mt-5 max-w-sm">
                        <article class="rounded-2xl border border-white/15 bg-white/10 p-4 backdrop-blur-xl">
                            <p class="text-xs uppercase tracking-[0.2em] text-white/70">{{ __('candidate_portal.dashboard.metrics.applications') }}</p>
                            <p class="mt-2 text-3xl font-semibold">{{ ($applications ?? collect())->count() }}</p>
                            <p class="mt-1 text-xs text-white/70">{{ __('candidate_portal.dashboard.metrics.active', ['count' => $activeApplications]) }}</p>
                        </article>
                    </div>

                    <div class="mt-6 flex flex-wrap gap-3">
                        <a href="#candidate-applications" class="inline-flex items-center rounded-xl border border-white/30 bg-white/15 px-4 py-2 text-sm font-semibold text-white transition-weightless hover:bg-white/25">
                            {{ __('candidate_portal.dashboard.actions.review_applications') }}
                        </a>
                        <a href="#candidate-assessments" class="inline-flex items-center rounded-xl border border-white/20 bg-white px-4 py-2 text-sm font-semibold text-slate-900 transition-weightless hover:bg-slate-100">
                            {{ __('candidate_portal.dashboard.actions.open_assessments') }}
                        </a>
                        <a href="{{ route('candidate.faq', ['company' => $company->slug]) }}" class="inline-flex items-center rounded-xl border border-white/30 bg-transparent px-4 py-2 text-sm font-semibold text-white transition-weightless hover:bg-white/10">
                            {{ __('candidate_portal.dashboard.actions.open_faq') }}
                        </a>
                    </div>
                </div>

                <div class="grid gap-4">
                    <article class="rounded-[1.75rem] border border-white/15 bg-white/10 p-5 backdrop-blur-xl">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-xs uppercase tracking-[0.2em] text-white/70">{{ __('candidate_portal.dashboard.focus.eyebrow') }}</p>
                                <h2 class="mt-2 text-xl font-semibold">{{ __('candidate_portal.dashboard.focus.title') }}</h2>
                            </div>
                            <span class="rounded-full border border-white/20 bg-white/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.16em] text-white/80">
                                {{ __('candidate_portal.dashboard.focus.live') }}
                            </span>
                        </div>

                        <div class="mt-4 space-y-3">
                            <div class="rounded-2xl border border-white/15 bg-slate-950/15 p-4">
                                <p class="text-xs uppercase tracking-[0.18em] text-white/65">{{ __('candidate_portal.dashboard.focus.status') }}</p>
                                <p class="mt-2 text-sm text-white/85">
                                    {{ (string) ($dashboardPrimaryTracker['updated_human'] ?? __('candidate_portal.dashboard.focus.status_ready')) }}
                                </p>
                            </div>
                        </div>
                    </article>
                </div>
            </div>
        </section>

        <x-glass-card
            :title="__('candidate_portal.hub.title', ['company' => $company->name])"
            :subtitle="__('candidate_portal.hub.subtitle')">
            <div class="grid gap-4 xl:grid-cols-[1.35fr_1fr]">
                <div class="grid gap-3 md:grid-cols-2">
                    <article class="rounded-2xl border border-primary-200/70 bg-primary-50/70 p-4">
                        <p class="text-xs uppercase tracking-[0.22em] text-primary-800">{{ __('candidate_portal.hub.cards.tracker.eyebrow') }}</p>
                        <h3 class="mt-2 text-base font-semibold text-slate-900">{{ __('candidate_portal.hub.cards.tracker.title') }}</h3>
                        <p class="mt-2 text-sm text-slate-700">{{ __('candidate_portal.hub.cards.tracker.description') }}</p>
                        <p class="mt-3 text-xs text-slate-600">{{ __('candidate_portal.hub.cards.tracker.meta', ['count' => ($applications ?? collect())->count()]) }}</p>
                    </article>

                    <article class="rounded-2xl border border-danger-200/70 bg-danger-50/65 p-4">
                        <p class="text-xs uppercase tracking-[0.22em] text-danger-800">{{ __('candidate_portal.hub.cards.social.eyebrow') }}</p>
                        <h3 class="mt-2 text-base font-semibold text-slate-900">{{ __('candidate_portal.hub.cards.social.title') }}</h3>
                        <p class="mt-2 text-sm text-slate-700">
                            {{ $canAccessSocialHub ? __('candidate_portal.hub.cards.social.unlocked') : __('candidate_portal.hub.cards.social.preview_only') }}
                        </p>
                        @if($canAccessSocialHub)
                            <a href="{{ route('candidate.social-hub.index', ['company' => $company->slug]) }}"
                               class="mt-4 inline-flex rounded-lg bg-danger-600 px-3 py-1.5 text-sm font-semibold text-white transition-weightless hover:bg-danger-700">
                                {{ __('candidate_portal.social_hub.open') }}
                            </a>
                        @else
                            <p class="mt-3 text-xs text-slate-600">{{ __('candidate_portal.social_hub.preview_hint') }}</p>
                        @endif
                    </article>

                    <article class="rounded-2xl border border-success-200/70 bg-success-50/70 p-4">
                        <p class="text-xs uppercase tracking-[0.22em] text-success-800">{{ __('candidate_portal.hub.cards.feedback.eyebrow') }}</p>
                        <h3 class="mt-2 text-base font-semibold text-slate-900">{{ __('candidate_portal.hub.cards.feedback.title') }}</h3>
                        <p class="mt-2 text-sm text-slate-700">
                            {{ $feedbackReadyApplication ? __('candidate_portal.hub.cards.feedback.ready') : __('candidate_portal.hub.cards.feedback.locked') }}
                        </p>
                        @if($feedbackReadyApplication)
                            <a href="#reverse-feedback-{{ $feedbackReadyApplication->id }}"
                               class="mt-4 inline-flex rounded-lg border border-success-300/60 bg-white px-3 py-1.5 text-sm font-semibold text-success-800 transition-weightless hover:bg-success-100/80">
                                {{ __('candidate_portal.feedback.secure_link_label') }}
                            </a>
                        @else
                            <p class="mt-3 text-xs text-slate-600">{{ __('candidate_portal.feedback.unlock_hint') }}</p>
                        @endif
                    </article>

                    <article id="candidate-guide" class="rounded-2xl border border-aura-200/70 bg-aura-50/65 p-4">
                        <p class="text-xs uppercase tracking-[0.22em] text-aura-800">{{ __('candidate_portal.hub.cards.guide.eyebrow') }}</p>
                        <h3 class="mt-2 text-base font-semibold text-slate-900">{{ __('candidate_portal.hub.cards.guide.title') }}</h3>
                        <p class="mt-2 text-sm text-slate-700">{{ __('candidate_portal.hub.cards.guide.description') }}</p>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <a href="{{ route('candidate.faq', ['company' => $company->slug]) }}"
                               class="inline-flex rounded-lg border border-aura-300/50 bg-white px-3 py-1.5 text-sm font-semibold text-aura-800 transition-weightless hover:bg-white">
                                {{ __('candidate_portal.faq.open_faq_page') }}
                            </a>
                            <span class="inline-flex rounded-lg border border-aura-200/60 bg-white/70 px-3 py-1.5 text-xs font-medium text-slate-700">
                                {{ __('candidate_portal.guider.privacy_badge') }}
                            </span>
                        </div>
                    </article>
                </div>

                <article class="rounded-2xl border border-white/80 bg-white/75 p-4" data-social-hub-preview>
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-xs uppercase tracking-[0.22em] text-danger-800">{{ __('candidate_portal.social_hub.preview_eyebrow') }}</p>
                            <h3 class="mt-2 text-base font-semibold text-slate-900">{{ __('candidate_portal.social_hub.preview_title') }}</h3>
                            <p class="mt-2 text-sm text-slate-700">{{ __('candidate_portal.social_hub.preview_description') }}</p>
                        </div>
                        <span class="inline-flex rounded-full border border-danger-200 bg-danger-50 px-2.5 py-1 text-[11px] font-semibold text-danger-800">
                            {{ __('candidate_portal.social_hub.preview_badge') }}
                        </span>
                    </div>

                    <div class="mt-4 space-y-3">
                        @forelse($socialHubPreviewPosts as $post)
                            <div class="rounded-xl border border-slate-200/80 bg-slate-50/75 p-3">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('social_hub.types.'.$post->type) }}</p>
                                    <p class="text-[11px] text-slate-500">{{ $post->created_at?->diffForHumans() }}</p>
                                </div>
                                <p class="mt-2 text-sm font-semibold text-slate-900">{{ \Illuminate\Support\Str::limit((string) $post->content_text, 110) }}</p>
                                <p class="mt-1 text-xs text-slate-600">
                                    {{ __('candidate_portal.social_hub.preview_author', ['name' => (string) ($post->author?->profile?->full_name ?? $post->author?->email ?? __('candidate_portal.social_hub.team_label'))]) }}
                                </p>
                            </div>
                        @empty
                            <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50/70 px-4 py-5 text-sm text-slate-600">
                                {{ __('candidate_portal.social_hub.preview_empty') }}
                            </div>
                        @endforelse
                    </div>
                </article>
            </div>
        </x-glass-card>

        <x-glass-card
            :title="__('candidate_portal.workflow.title')"
            :subtitle="__('candidate_portal.workflow.subtitle')">
            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
                <a href="#candidate-applications"
                   class="rounded-2xl border border-primary-200/70 bg-primary-50/65 p-4 transition-weightless hover:-translate-y-0.5 hover:bg-primary-50"
                   data-candidate-portal-shortcut="status">
                    <p class="text-xs uppercase tracking-[0.22em] text-primary-800">{{ __('candidate_portal.workflow.items.status.eyebrow') }}</p>
                    <h3 class="mt-2 text-sm font-semibold text-slate-900">{{ __('candidate_portal.workflow.items.status.title') }}</h3>
                    <p class="mt-2 text-xs text-slate-700">{{ __('candidate_portal.workflow.items.status.description') }}</p>
                </a>

                <a href="#candidate-assessments"
                   class="rounded-2xl border border-success-200/70 bg-success-50/65 p-4 transition-weightless hover:-translate-y-0.5 hover:bg-success-50"
                   data-candidate-portal-shortcut="assessments">
                    <p class="text-xs uppercase tracking-[0.22em] text-success-800">{{ __('candidate_portal.workflow.items.assessments.eyebrow') }}</p>
                    <h3 class="mt-2 text-sm font-semibold text-slate-900">{{ __('candidate_portal.workflow.items.assessments.title') }}</h3>
                    <p class="mt-2 text-xs text-slate-700">{{ __('candidate_portal.workflow.items.assessments.description') }}</p>
                </a>

                <a href="{{ route('candidate.social-hub.index', ['company' => $company->slug]) }}"
                   class="rounded-2xl border border-danger-200/70 bg-danger-50/65 p-4 transition-weightless hover:-translate-y-0.5 hover:bg-danger-50"
                   data-candidate-portal-shortcut="social-hub">
                    <p class="text-xs uppercase tracking-[0.22em] text-danger-800">{{ __('candidate_portal.workflow.items.social.eyebrow') }}</p>
                    <h3 class="mt-2 text-sm font-semibold text-slate-900">{{ __('candidate_portal.workflow.items.social.title') }}</h3>
                    <p class="mt-2 text-xs text-slate-700">{{ __('candidate_portal.workflow.items.social.description') }}</p>
                </a>

                <a href="#candidate-guide"
                   class="rounded-2xl border border-aura-200/70 bg-aura-50/65 p-4 transition-weightless hover:-translate-y-0.5 hover:bg-aura-50"
                   data-candidate-portal-shortcut="guide">
                    <p class="text-xs uppercase tracking-[0.22em] text-aura-800">{{ __('candidate_portal.workflow.items.guide.eyebrow') }}</p>
                    <h3 class="mt-2 text-sm font-semibold text-slate-900">{{ __('candidate_portal.workflow.items.guide.title') }}</h3>
                    <p class="mt-2 text-xs text-slate-700">{{ __('candidate_portal.workflow.items.guide.description') }}</p>
                </a>

                <a href="#candidate-security"
                   class="rounded-2xl border border-slate-200/80 bg-white/75 p-4 transition-weightless hover:-translate-y-0.5 hover:bg-white"
                   data-candidate-portal-shortcut="security">
                    <p class="text-xs uppercase tracking-[0.22em] text-slate-700">{{ __('candidate_portal.workflow.items.security.eyebrow') }}</p>
                    <h3 class="mt-2 text-sm font-semibold text-slate-900">{{ __('candidate_portal.workflow.items.security.title') }}</h3>
                    <p class="mt-2 text-xs text-slate-700">{{ __('candidate_portal.workflow.items.security.description') }}</p>
                </a>
            </div>
        </x-glass-card>

        <x-glass-card
            id="candidate-security"
            :title="__('candidate_portal.security.title')"
            :subtitle="__('candidate_portal.security.subtitle')">
            <form method="POST" action="{{ route('candidate.password.update', ['company' => $company->slug]) }}" class="grid gap-3 lg:grid-cols-3">
                @csrf

                <div>
                    <label for="candidate-current-password" class="block text-xs font-semibold uppercase tracking-[0.18em] text-slate-700">
                        {{ __('candidate_portal.security.current_password') }}
                    </label>
                    <div class="relative mt-1.5">
                        <input id="candidate-current-password" type="password" name="current_password" required autocomplete="current-password" class="w-full rounded-xl border border-slate-200/70 bg-white px-3 py-2.5 pr-12 text-sm text-slate-900 shadow-sm focus:border-aura-400 focus:ring-aura-300">
                        <button
                            type="button"
                            data-password-toggle
                            data-password-target="candidate-current-password"
                            data-show-label="{{ __('candidate_portal.security.toggle_show') }}"
                            data-hide-label="{{ __('candidate_portal.security.toggle_hide') }}"
                            aria-label="{{ __('candidate_portal.security.toggle_show') }}"
                            class="absolute inset-y-0 right-0 my-1 mr-1 inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 transition-weightless hover:bg-slate-50 hover:text-slate-900"
                        >
                            <svg data-eye-open xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z" />
                                <circle cx="12" cy="12" r="3" />
                            </svg>
                            <svg data-eye-closed xmlns="http://www.w3.org/2000/svg" class="hidden h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M17.94 17.94A10.94 10.94 0 0 1 12 20C5 20 1 12 1 12a21.66 21.66 0 0 1 5.06-6.94" />
                                <path d="M9.9 4.24A10.96 10.96 0 0 1 12 4c7 0 11 8 11 8a21.58 21.58 0 0 1-2.16 3.19" />
                                <path d="M14.12 14.12a3 3 0 1 1-4.24-4.24" />
                                <path d="m1 1 22 22" />
                            </svg>
                            <span class="sr-only" data-password-toggle-label>{{ __('candidate_portal.security.toggle_show') }}</span>
                        </button>
                    </div>
                    @error('current_password')
                        <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="candidate-new-password" class="block text-xs font-semibold uppercase tracking-[0.18em] text-slate-700">
                        {{ __('candidate_portal.security.new_password') }}
                    </label>
                    <div class="relative mt-1.5">
                        <input id="candidate-new-password" type="password" name="password" required autocomplete="new-password" class="w-full rounded-xl border border-slate-200/70 bg-white px-3 py-2.5 pr-12 text-sm text-slate-900 shadow-sm focus:border-aura-400 focus:ring-aura-300">
                        <button
                            type="button"
                            data-password-toggle
                            data-password-target="candidate-new-password"
                            data-show-label="{{ __('candidate_portal.security.toggle_show') }}"
                            data-hide-label="{{ __('candidate_portal.security.toggle_hide') }}"
                            aria-label="{{ __('candidate_portal.security.toggle_show') }}"
                            class="absolute inset-y-0 right-0 my-1 mr-1 inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 transition-weightless hover:bg-slate-50 hover:text-slate-900"
                        >
                            <svg data-eye-open xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z" />
                                <circle cx="12" cy="12" r="3" />
                            </svg>
                            <svg data-eye-closed xmlns="http://www.w3.org/2000/svg" class="hidden h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M17.94 17.94A10.94 10.94 0 0 1 12 20C5 20 1 12 1 12a21.66 21.66 0 0 1 5.06-6.94" />
                                <path d="M9.9 4.24A10.96 10.96 0 0 1 12 4c7 0 11 8 11 8a21.58 21.58 0 0 1-2.16 3.19" />
                                <path d="M14.12 14.12a3 3 0 1 1-4.24-4.24" />
                                <path d="m1 1 22 22" />
                            </svg>
                            <span class="sr-only" data-password-toggle-label>{{ __('candidate_portal.security.toggle_show') }}</span>
                        </button>
                    </div>
                    @error('password')
                        <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="candidate-confirm-password" class="block text-xs font-semibold uppercase tracking-[0.18em] text-slate-700">
                        {{ __('candidate_portal.security.confirm_password') }}
                    </label>
                    <div class="relative mt-1.5">
                        <input id="candidate-confirm-password" type="password" name="password_confirmation" required autocomplete="new-password" class="w-full rounded-xl border border-slate-200/70 bg-white px-3 py-2.5 pr-12 text-sm text-slate-900 shadow-sm focus:border-aura-400 focus:ring-aura-300">
                        <button
                            type="button"
                            data-password-toggle
                            data-password-target="candidate-confirm-password"
                            data-show-label="{{ __('candidate_portal.security.toggle_show') }}"
                            data-hide-label="{{ __('candidate_portal.security.toggle_hide') }}"
                            aria-label="{{ __('candidate_portal.security.toggle_show') }}"
                            class="absolute inset-y-0 right-0 my-1 mr-1 inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 transition-weightless hover:bg-slate-50 hover:text-slate-900"
                        >
                            <svg data-eye-open xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z" />
                                <circle cx="12" cy="12" r="3" />
                            </svg>
                            <svg data-eye-closed xmlns="http://www.w3.org/2000/svg" class="hidden h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M17.94 17.94A10.94 10.94 0 0 1 12 20C5 20 1 12 1 12a21.66 21.66 0 0 1 5.06-6.94" />
                                <path d="M9.9 4.24A10.96 10.96 0 0 1 12 4c7 0 11 8 11 8a21.58 21.58 0 0 1-2.16 3.19" />
                                <path d="M14.12 14.12a3 3 0 1 1-4.24-4.24" />
                                <path d="m1 1 22 22" />
                            </svg>
                            <span class="sr-only" data-password-toggle-label>{{ __('candidate_portal.security.toggle_show') }}</span>
                        </button>
                    </div>
                </div>

                <div class="lg:col-span-3 flex flex-wrap items-center justify-between gap-3">
                    <p class="text-xs text-slate-600">{{ __('candidate_portal.security.helper') }}</p>
                    <button type="submit" class="rounded-xl bg-aura-700 px-4 py-2 text-sm font-semibold text-white transition-weightless hover:bg-aura-800">
                        {{ __('candidate_portal.security.submit') }}
                    </button>
                </div>
            </form>
        </x-glass-card>

        @if($canAccessSocialHub ?? false)
            <x-glass-card
                :title="__('candidate_portal.social_hub.title')"
                :subtitle="__('candidate_portal.social_hub.subtitle')">
                <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-danger-200/70 bg-danger-50/65 p-4">
                    <div>
                        <p class="text-sm text-slate-700">{{ __('candidate_portal.social_hub.description') }}</p>
                        @php
                            $socialHubCount = (int) ($socialHubEligibleCount ?? 0);
                            $socialHubSource = $socialHubPrimarySource ?? null;
                        @endphp
                        @if($socialHubCount > 1)
                            <p class="mt-1 text-xs text-slate-600">
                                {{ __('candidate_portal.social_hub.access_from_multiple', ['count' => $socialHubCount]) }}
                            </p>
                        @elseif($socialHubSource instanceof \App\Models\Application)
                            <p class="mt-1 text-xs text-slate-600">
                                {{ __('candidate_portal.social_hub.access_from_single', [
                                    'job' => (string) ($socialHubSource->job?->title ?? __('sjt.messages.unknown_job')),
                                    'stage' => (string) ($socialHubSource->currentStage?->stage_label ?? __('candidate_portal.applications.unknown_stage')),
                                ]) }}
                            </p>
                        @else
                            <p class="mt-1 text-xs text-slate-600">
                                {{ __('candidate_portal.social_hub.access_general') }}
                            </p>
                        @endif
                    </div>
                    <a href="{{ route('candidate.social-hub.index', ['company' => $company->slug]) }}"
                       class="rounded-xl bg-danger-600 px-4 py-2 text-sm font-semibold text-white transition-weightless hover:bg-danger-700">
                        {{ __('candidate_portal.social_hub.open') }}
                    </a>
                </div>
            </x-glass-card>
        @endif

        <x-glass-card
            :title="__('candidate_portal.notifications.title')"
            :subtitle="__('candidate_portal.notifications.subtitle')">
            @if(($portalNotifications ?? collect())->isEmpty())
                <x-empty-state
                    :title="__('candidate_portal.notifications.empty_title')"
                    :message="__('candidate_portal.notifications.empty_message')" />
            @else
                <div class="space-y-3">
                    @foreach($portalNotifications as $notification)
                        @php
                            $type = (string) ($notification['type'] ?? 'application');
                            $badgeClass = match ($type) {
                                'interview' => 'border-primary-200/60 bg-primary-50 text-primary-800',
                                'assessment' => 'border-success-200/70 bg-success-50 text-success-800',
                                'social' => 'border-danger-200/70 bg-danger-50 text-danger-800',
                                default => 'border-aura-200/70 bg-aura-50 text-aura-800',
                            };
                        @endphp
                        <article class="rounded-xl border border-white/80 bg-white/75 p-4">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <span class="inline-flex rounded-full border px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide {{ $badgeClass }}">
                                    {{ __('candidate_portal.notifications.types.'.$type) }}
                                </span>
                                <span class="text-xs text-slate-500">
                                    {{ \Illuminate\Support\Carbon::parse($notification['created_at'])->diffForHumans() }}
                                </span>
                            </div>
                            <p class="mt-2 text-sm font-semibold text-slate-900">{{ $notification['title'] }}</p>
                            <p class="mt-1 text-sm text-slate-700">{{ $notification['message'] }}</p>
                        </article>
                    @endforeach
                </div>
            @endif
        </x-glass-card>

        @php
            $assessmentAnchorApplication = $applications->first();
            $firstVideoAssessment = ($videoAssessments ?? collect())->first();
            $firstSjtAssessment = ($sjtAssessments ?? collect())->first();
            $videoApplication = is_array($firstVideoAssessment) ? ($firstVideoAssessment['application'] ?? null) : null;
            $videoQuestionId = (string) (is_array($firstVideoAssessment) ? ($firstVideoAssessment['next_question_id'] ?? '') : '');
            $sjtApplication = is_array($firstSjtAssessment)
                ? ($firstSjtAssessment['application'] ?? $assessmentAnchorApplication)
                : $assessmentAnchorApplication;
            $storiesAvailable = $videoApplication && $videoQuestionId !== '';
            $sjtAvailable = $sjtApplication !== null;
        @endphp
        <x-glass-card
            id="candidate-assessments"
            :title="__('candidate_portal.assessments.title')"
            :subtitle="__('candidate_portal.assessments.subtitle')">
            <div class="grid gap-4 lg:grid-cols-2">
                <article class="rounded-2xl border border-white/75 bg-white/75 p-5">
                    <p class="text-xs uppercase tracking-[0.2em] text-aura-700/85">{{ __('candidate_portal.assessments.modules.situational_badge') }}</p>
                    <h3 class="mt-2 text-base font-semibold text-slate-900">{{ __('candidate_portal.assessments.modules.situational_title') }}</h3>
                    <p class="mt-2 text-sm text-slate-700">{{ __('candidate_portal.assessments.modules.situational_description') }}</p>
                    <p class="mt-3 text-xs font-semibold uppercase tracking-wide text-success-700">{{ __('candidate_portal.assessments.modules.response_zone_title') }}</p>
                    <p class="mt-1 text-sm text-slate-700">{{ __('candidate_portal.assessments.modules.response_zone_description') }}</p>
                    @if($sjtAvailable)
                        <a href="{{ route('candidate.assessments.sjt', ['application_id' => $sjtApplication->id]) }}"
                           class="mt-4 inline-flex rounded-lg border border-aura-300/50 bg-white px-3 py-1.5 text-sm font-medium text-slate-800 transition-weightless hover:bg-white">
                            {{ __('candidate_portal.assessments.modules.open_action') }}
                        </a>
                    @else
                        <span class="mt-4 inline-flex rounded-lg border border-slate-300 bg-slate-100 px-3 py-1.5 text-sm font-medium text-slate-500">
                            {{ __('candidate_portal.assessments.modules.pending_action') }}
                        </span>
                    @endif
                </article>

                <article class="rounded-2xl border border-white/75 bg-white/75 p-5">
                    <p class="text-xs uppercase tracking-[0.2em] text-danger-700/85">{{ __('candidate_portal.assessments.modules.stories_badge') }}</p>
                    <h3 class="mt-2 text-base font-semibold text-slate-900">{{ __('candidate_portal.assessments.modules.stories_title') }}</h3>
                    <p class="mt-2 text-sm text-slate-700">{{ __('candidate_portal.assessments.modules.stories_description') }}</p>
                    @if($storiesAvailable)
                        <a href="{{ route('candidate.video-stories', ['company' => $company->slug, 'application' => $videoApplication->id, 'question_id' => $videoQuestionId]) }}"
                           class="mt-4 inline-flex rounded-lg border border-danger-300/50 bg-danger-50 px-3 py-1.5 text-sm font-medium text-danger-800 transition-weightless hover:bg-danger-100/80">
                            {{ __('candidate_portal.assessments.modules.open_action') }}
                        </a>
                    @else
                        <span class="mt-4 inline-flex rounded-lg border border-slate-300 bg-slate-100 px-3 py-1.5 text-sm font-medium text-slate-500">
                            {{ __('candidate_portal.assessments.modules.pending_action') }}
                        </span>
                    @endif
                </article>
            </div>
        </x-glass-card>

        <x-glass-card
            id="candidate-applications"
            :title="__('candidate_portal.applications.title')"
            :subtitle="__('candidate_portal.applications.subtitle')">
            @if(($applications ?? collect())->isEmpty())
                <x-empty-state
                    :title="__('candidate_portal.applications.empty_title')"
                    :message="__('candidate_portal.applications.empty_message')" />
            @else
                <div class="space-y-4">
                    @foreach($applications as $application)
                        @php
                            $stageLabel = (string) ($application->currentStage?->stage_label ?? __('candidate_portal.applications.unknown_stage'));
                            $isHired = (bool) (($hiredFlowApplications ?? collect())->get(
                                (string) $application->id,
                                (string) $application->status === \App\Models\Application::STATUS_HIRED
                            ));
                            $feedbackEligible = (bool) (($reverseFeedbackEligibility ?? collect())->get((string) $application->id, false));
                            $reverseFeedback = $application->reverseFeedback;
                            $contract = $application->contract;
                            $onboardingDocuments = $application->onboardingDocuments ?? collect();
                            $uploadedDocTypes = $onboardingDocuments
                                ->pluck('doc_type')
                                ->map(static fn ($value) => (string) $value)
                                ->filter(static fn ($value) => $value !== '')
                                ->unique()
                                ->values();
                            $docTypeOptions = collect(\App\Models\OnboardingDocument::types());
                            $selectedDocType = (string) old('doc_type', (string) ($docTypeOptions->first() ?? ''));
                            $allDocTypesUploaded = $docTypeOptions->isNotEmpty()
                                && $docTypeOptions->every(static fn (string $docType): bool => $uploadedDocTypes->contains($docType));
                            $selectedDocTypeUploaded = $selectedDocType !== '' && $uploadedDocTypes->contains($selectedDocType);
                            $uploadDisabled = $allDocTypesUploaded || $selectedDocTypeUploaded;
                            $onboardingScheduleItems = $application->onboardingScheduleItems ?? collect();
                            $onboardingTasks = $application->onboardingTasks ?? collect();
                            $nextStep = (string) (($nextSteps ?? collect())->get((string) $application->id, __('candidate_portal.applications.next_step_default')));
                            $statusTracker = (array) (($statusTrackers ?? collect())->get((string) $application->id, []));
                        @endphp
                        <section class="rounded-2xl border border-white/70 bg-white/70 p-5">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <p class="text-xs uppercase tracking-[0.24em] text-aura-700/85">
                                        {{ $application->job?->department?->name ?? __('career.list.no_department') }}
                                    </p>
                                    <h3 class="mt-1 text-lg font-semibold text-slate-900">{{ $application->job?->title ?? __('sjt.messages.unknown_job') }}</h3>
                                    <p class="mt-1 text-sm text-slate-600">
                                        {{ __('candidate_portal.applications.stage_label') }}: <span class="font-medium text-slate-800">{{ $stageLabel }}</span>
                                    </p>
                                    <p class="mt-1 text-sm text-slate-600">
                                        {{ __('candidate_portal.applications.next_step_label') }}: <span class="font-medium text-slate-800">{{ $nextStep }}</span>
                                    </p>
                                    @if($feedbackEligible && ! $reverseFeedback)
                                        <a href="#reverse-feedback-{{ $application->id }}"
                                           class="mt-2 inline-flex items-center rounded-lg border border-aura-300/60 bg-aura-50/70 px-2.5 py-1 text-xs font-semibold text-aura-800"
                                           data-reverse-feedback-secure-link>
                                            {{ __('candidate_portal.feedback.secure_link_label') }}
                                        </a>
                                    @endif
                                </div>
                                <x-badge>{{ __('candidates.list.status.'.$application->status) }}</x-badge>
                            </div>

                            @include('candidate.partials.transparency-insights', [
                                'application' => $application,
                                'statusTracker' => $statusTracker,
                            ])

                            @if($isHired)
                                <div class="mt-4 rounded-2xl border border-aura-200/60 bg-aura-50/65 p-4" data-onboarding-hub>
                                    <div class="flex flex-wrap items-start justify-between gap-3">
                                        <div>
                                            <p class="text-xs uppercase tracking-[0.24em] text-aura-700/85">{{ __('candidate_portal.onboarding.title') }}</p>
                                            <p class="mt-1 text-xs text-slate-600">{{ __('candidate_portal.onboarding.subtitle') }}</p>
                                        </div>
                                    </div>

                                    <div class="mt-3 grid gap-3 xl:grid-cols-2">
                                        <div class="rounded-xl border border-slate-200 bg-white/85 p-3">
                                            <p class="text-xs uppercase tracking-wide text-slate-600">{{ __('candidate_portal.onboarding.contract.title') }}</p>
                                            @if($contract)
                                                <div class="mt-2 flex flex-wrap items-center gap-2 text-xs text-slate-700">
                                                    <x-badge>{{ __('candidate_portal.onboarding.contract.statuses.'.$contract->contract_status) }}</x-badge>
                                                    <a href="{{ \App\Http\Controllers\CandidatePortalController::signedContractUrl($contract) }}"
                                                       class="rounded-md border border-aura-200 px-2 py-1 text-aura-700">
                                                        {{ __('candidate_portal.onboarding.contract.download') }}
                                                    </a>
                                                </div>

                                                @if($contract->contract_status === \App\Models\Contract::STATUS_SIGNED || $contract->signed_at)
                                                    <div class="mt-3 rounded-lg border border-success-200 bg-success-50 px-3 py-2 text-xs text-success-800">
                                                        {{ __('candidate_portal.onboarding.contract.already_signed') }}
                                                    </div>
                                                @else
                                                    <form method="POST"
                                                          action="{{ route('candidate.contract.sign', ['company' => $company->slug, 'application' => $application->id]) }}"
                                                          class="mt-3 space-y-2">
                                                        @csrf
                                                        <x-form-field :label="__('candidate_portal.onboarding.contract.typed_signature')" name="typed_signature">
                                                            <input type="text"
                                                                   name="typed_signature"
                                                                   value="{{ old('typed_signature') }}"
                                                                   placeholder="{{ __('candidate_portal.onboarding.contract.typed_signature_placeholder') }}"
                                                                   class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2 text-sm text-slate-900 shadow-sm"
                                                                   required>
                                                        </x-form-field>

                                                        <label class="inline-flex items-start gap-2 text-xs text-slate-700">
                                                            <input type="checkbox" name="acknowledgement" value="1" class="mt-0.5 rounded border-aura-300 text-aura-600 focus:ring-aura-400" required>
                                                            <span>{{ __('candidate_portal.onboarding.contract.acknowledgement') }}</span>
                                                        </label>

                                                        <div>
                                                            <button type="submit"
                                                                    class="rounded-xl bg-success-600 px-4 py-2 text-sm font-semibold text-white transition-weightless hover:bg-success-700">
                                                                {{ __('candidate_portal.onboarding.contract.sign_action') }}
                                                            </button>
                                                        </div>
                                                    </form>
                                                @endif
                                            @else
                                                <p class="mt-2 text-sm text-slate-600">{{ __('candidate_portal.onboarding.contract.empty') }}</p>
                                            @endif
                                        </div>

                                        <div class="rounded-xl border border-slate-200 bg-white/85 p-3">
                                            <p class="text-xs uppercase tracking-wide text-slate-600">{{ __('candidate_portal.onboarding.documents.title') }}</p>
                                            <p class="mt-1 text-xs text-slate-600">{{ __('candidate_portal.onboarding.documents.upload_hint') }}</p>
                                            <p class="mt-1 text-[11px] text-aura-700/90">{{ __('candidate_portal.onboarding.documents.security_note') }}</p>
                                            <form method="POST"
                                                  action="{{ route('candidate.onboarding-documents.store', ['company' => $company->slug, 'application' => $application->id]) }}"
                                                  enctype="multipart/form-data"
                                                  class="mt-3 space-y-2"
                                                  data-onboarding-document-form
                                                  data-all-uploaded="{{ $allDocTypesUploaded ? '1' : '0' }}">
                                                @csrf
                                                <select name="doc_type"
                                                        data-placeholder="{{ __('candidate_portal.onboarding.documents.doc_type') }}"
                                                        data-onboarding-doc-type-select
                                                        class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2 text-sm text-slate-900 shadow-sm"
                                                        required
                                                        @disabled($allDocTypesUploaded)>
                                                    @foreach(\App\Models\OnboardingDocument::types() as $docTypeOption)
                                                        @php
                                                            $isUploadedType = $uploadedDocTypes->contains($docTypeOption);
                                                        @endphp
                                                        <option value="{{ $docTypeOption }}"
                                                                data-uploaded="{{ $isUploadedType ? '1' : '0' }}"
                                                                @selected($selectedDocType === $docTypeOption)>
                                                            {{ __('candidate_portal.onboarding.documents.types.'.$docTypeOption) }}{{ $isUploadedType ? ' ('.__('candidate_portal.onboarding.documents.already_uploaded_label').')' : '' }}
                                                        </option>
                                                    @endforeach
                                                </select>

                                                <div class="rounded-2xl border border-dashed border-aura-300/60 bg-aura-50/65 p-3">
                                                    <div class="rounded-xl border border-dashed border-aura-300/60 bg-white/80 px-4 py-5 text-center text-sm text-slate-600"
                                                         data-onboarding-dropzone
                                                         aria-disabled="{{ $uploadDisabled ? 'true' : 'false' }}">
                                                        <p class="font-medium text-slate-700">{{ __('candidate_portal.onboarding.documents.upload_file') }}</p>
                                                    </div>
                                                    <input type="file"
                                                           name="file"
                                                           accept=".pdf,.doc,.docx,.png,.jpg,.jpeg"
                                                           data-onboarding-file-input
                                                           class="mt-2 w-full rounded-xl border border-aura-200/40 bg-white/85 px-3 py-2 text-sm text-slate-900 shadow-sm file:mr-4 file:rounded-lg file:border-0 file:bg-aura-100 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-aura-700"
                                                           required
                                                           @disabled($uploadDisabled)>
                                                </div>

                                                <button type="submit"
                                                        data-onboarding-upload-submit
                                                        data-upload-label="{{ __('candidate_portal.onboarding.documents.upload_action') }}"
                                                        data-uploaded-label="{{ __('candidate_portal.onboarding.documents.uploaded_action') }}"
                                                        class="rounded-xl bg-success-600 px-4 py-2 text-sm font-semibold text-white transition-weightless hover:bg-success-700 disabled:cursor-not-allowed disabled:bg-slate-400"
                                                        @disabled($uploadDisabled)>
                                                    {{ $uploadDisabled ? __('candidate_portal.onboarding.documents.uploaded_action') : __('candidate_portal.onboarding.documents.upload_action') }}
                                                </button>

                                                <p data-onboarding-upload-status
                                                   data-empty-hint=""
                                                   data-already-uploaded-hint="{{ __('candidate_portal.onboarding.documents.already_uploaded') }}"
                                                   data-all-uploaded-hint="{{ __('candidate_portal.onboarding.documents.all_uploaded') }}"
                                                   class="text-xs text-success-700 {{ $uploadDisabled ? '' : 'hidden' }}">
                                                    {{ $allDocTypesUploaded ? __('candidate_portal.onboarding.documents.all_uploaded') : ($selectedDocTypeUploaded ? __('candidate_portal.onboarding.documents.already_uploaded') : '') }}
                                                </p>
                                            </form>

                                            <div class="mt-3 space-y-2">
                                                @forelse($onboardingDocuments as $document)
                                                    <div class="flex items-center justify-between rounded-lg border border-slate-200 bg-white p-2">
                                                        <p class="truncate text-xs text-slate-700">{{ __('candidate_portal.onboarding.documents.types.'.$document->doc_type) }}</p>
                                                        <a href="{{ \App\Http\Controllers\CandidatePortalController::signedOnboardingDocumentUrl($document) }}"
                                                           class="rounded-md border border-aura-200 px-2 py-1 text-xs text-aura-700">
                                                            {{ __('candidates.detail.download') }}
                                                        </a>
                                                    </div>
                                                @empty
                                                    <p class="text-xs text-slate-600">{{ __('candidates.detail.not_available') }}</p>
                                                @endforelse
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mt-3 grid gap-3 xl:grid-cols-2">
                                        <div class="rounded-xl border border-slate-200 bg-white/85 p-3">
                                            <p class="text-xs uppercase tracking-wide text-slate-600">{{ __('candidate_portal.onboarding.calendar.title') }}</p>
                                            @php
                                                $sortedScheduleItems = $onboardingScheduleItems
                                                    ->sortBy(static fn ($item): int => $item?->start_at?->getTimestamp() ?? PHP_INT_MAX)
                                                    ->values();
                                                $startDateItem = $sortedScheduleItems
                                                    ->first(static fn ($item): bool => $item?->start_at !== null);
                                                $introItem = $sortedScheduleItems
                                                    ->first(static function ($item): bool {
                                                        $title = \Illuminate\Support\Str::lower((string) ($item->title ?? ''));

                                                        return \Illuminate\Support\Str::contains($title, [
                                                            'intro',
                                                            'introduction',
                                                            'welcome',
                                                            'team',
                                                        ]);
                                                    });
                                                $trainingItem = $sortedScheduleItems
                                                    ->first(static function ($item): bool {
                                                        $title = \Illuminate\Support\Str::lower((string) ($item->title ?? ''));

                                                        return \Illuminate\Support\Str::contains($title, [
                                                            'training',
                                                            'session',
                                                            'workshop',
                                                            'bootcamp',
                                                        ]);
                                                    });
                                                $formatScheduleWindow = static function ($item): string {
                                                    if (! $item || $item->start_at === null) {
                                                        return __('candidate_portal.onboarding.calendar.not_scheduled');
                                                    }

                                                    $start = $item->start_at->format('Y-m-d H:i');
                                                    if ($item->end_at === null) {
                                                        return $start.' UTC';
                                                    }

                                                    return $start.' - '.$item->end_at->format('H:i').' UTC';
                                                };
                                            @endphp

                                            <div class="mt-2 grid gap-2 sm:grid-cols-3" data-onboarding-calendar>
                                                <article class="rounded-lg border border-rose-200 bg-rose-50/80 p-2 text-xs" data-onboarding-calendar-start-date>
                                                    <p class="font-semibold uppercase tracking-wide text-rose-800">{{ __('candidate_portal.onboarding.calendar.start_date') }}</p>
                                                    <p class="mt-1 text-rose-900">{{ $formatScheduleWindow($startDateItem) }}</p>
                                                    <p class="mt-1 text-rose-800/90">{{ (string) ($startDateItem?->title ?? __('candidate_portal.onboarding.calendar.not_scheduled')) }}</p>
                                                </article>
                                                <article class="rounded-lg border border-emerald-200 bg-emerald-50/80 p-2 text-xs" data-onboarding-calendar-team-introductions>
                                                    <p class="font-semibold uppercase tracking-wide text-emerald-800">{{ __('candidate_portal.onboarding.calendar.team_introductions') }}</p>
                                                    <p class="mt-1 text-emerald-900">{{ $formatScheduleWindow($introItem) }}</p>
                                                    <p class="mt-1 text-emerald-800/90">{{ (string) ($introItem?->title ?? __('candidate_portal.onboarding.calendar.not_scheduled')) }}</p>
                                                </article>
                                                <article class="rounded-lg border border-sky-200 bg-sky-50/80 p-2 text-xs" data-onboarding-calendar-training-sessions>
                                                    <p class="font-semibold uppercase tracking-wide text-sky-800">{{ __('candidate_portal.onboarding.calendar.training_sessions') }}</p>
                                                    <p class="mt-1 text-sky-900">{{ $formatScheduleWindow($trainingItem) }}</p>
                                                    <p class="mt-1 text-sky-800/90">{{ (string) ($trainingItem?->title ?? __('candidate_portal.onboarding.calendar.not_scheduled')) }}</p>
                                                </article>
                                            </div>

                                            <p class="mt-3 text-[11px] uppercase tracking-wide text-slate-600">{{ __('candidate_portal.onboarding.calendar.timeline') }}</p>
                                            <div class="mt-2 space-y-2">
                                                @forelse($sortedScheduleItems as $scheduleItem)
                                                    @php
                                                        $scheduleTitle = \Illuminate\Support\Str::lower((string) $scheduleItem->title);
                                                        $eventBadge = __('candidate_portal.onboarding.calendar.timeline');
                                                        $eventClasses = 'border-violet-200/70 bg-violet-50/70 text-violet-900';

                                                        if (\Illuminate\Support\Str::contains($scheduleTitle, ['day 1', 'start', 'kickoff'])) {
                                                            $eventBadge = __('candidate_portal.onboarding.calendar.start_date');
                                                            $eventClasses = 'border-rose-200/80 bg-rose-50/80 text-rose-900';
                                                        } elseif (\Illuminate\Support\Str::contains($scheduleTitle, ['intro', 'introduction', 'welcome', 'team'])) {
                                                            $eventBadge = __('candidate_portal.onboarding.calendar.team_introductions');
                                                            $eventClasses = 'border-emerald-200/80 bg-emerald-50/80 text-emerald-900';
                                                        } elseif (\Illuminate\Support\Str::contains($scheduleTitle, ['training', 'session', 'workshop', 'bootcamp'])) {
                                                            $eventBadge = __('candidate_portal.onboarding.calendar.training_sessions');
                                                            $eventClasses = 'border-sky-200/80 bg-sky-50/80 text-sky-900';
                                                        }
                                                    @endphp
                                                    <div class="rounded-lg border p-2 text-xs {{ $eventClasses }}">
                                                        <div class="flex flex-wrap items-center justify-between gap-2">
                                                            <p class="font-semibold">{{ $scheduleItem->title }}</p>
                                                            <span class="rounded-full border border-current/25 bg-white/70 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide">
                                                                {{ $eventBadge }}
                                                            </span>
                                                        </div>
                                                        <p class="mt-1">{{ $formatScheduleWindow($scheduleItem) }}</p>
                                                        <p class="mt-1">{{ (string) ($scheduleItem->location ?: __('candidate_portal.onboarding.calendar.location_tbd')) }}</p>
                                                    </div>
                                                @empty
                                                    <p class="text-xs text-slate-600">{{ __('candidate_portal.onboarding.calendar.empty') }}</p>
                                                @endforelse
                                            </div>
                                        </div>

                                        <div class="rounded-xl border border-slate-200 bg-white/85 p-3">
                                            <p class="text-xs uppercase tracking-wide text-slate-600">{{ __('candidate_portal.onboarding.tasks.title') }}</p>
                                            <div class="mt-2 space-y-2">
                                                @forelse($onboardingTasks as $task)
                                                    <div class="flex items-center justify-between rounded-lg border border-slate-200 bg-white p-2 text-xs">
                                                        <div>
                                                            <p class="font-semibold text-slate-800">{{ $task->task_name }}</p>
                                                            <p class="text-slate-600">
                                                                {{ __('candidate_portal.onboarding.tasks.due_at') }}:
                                                                {{ $task->due_at ? $task->due_at->format('Y-m-d H:i').' UTC' : __('candidates.detail.not_available') }}
                                                            </p>
                                                        </div>
                                                        <form method="POST"
                                                              action="{{ route('candidate.onboarding-tasks.toggle', ['company' => $company->slug, 'application' => $application->id, 'onboardingTask' => $task->id]) }}">
                                                            @csrf
                                                            <button type="submit"
                                                                    class="rounded-md border px-2 py-1 text-xs {{ $task->is_completed ? 'border-success-200 bg-success-50 text-success-800' : 'border-aura-200 bg-aura-50 text-aura-800' }}">
                                                                {{ $task->is_completed ? __('candidate_portal.onboarding.tasks.mark_open') : __('candidate_portal.onboarding.tasks.mark_done') }}
                                                            </button>
                                                        </form>
                                                    </div>
                                                @empty
                                                    <p class="text-xs text-slate-600">{{ __('candidate_portal.onboarding.tasks.empty') }}</p>
                                                @endforelse
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            @if($feedbackEligible || $reverseFeedback)
                                @if($reverseFeedback)
                                    <div class="mt-4 rounded-xl border border-success-200 bg-success-50/70 px-3 py-2 text-sm text-success-900">
                                        {{ __('candidate_portal.feedback.already_submitted') }}
                                    </div>
                                @else
                                    <form method="POST"
                                          action="{{ route('candidate.reverse-feedback.store', ['company' => $company->slug, 'application' => $application->id]) }}"
                                          class="mt-4 space-y-3 rounded-2xl border border-slate-200 bg-white/85 p-4"
                                          id="reverse-feedback-{{ $application->id }}"
                                          data-reverse-feedback-form>
                                        @csrf
                                        <input type="hidden" name="is_anonymous" value="1">

                                        <p class="text-sm font-semibold text-slate-900">{{ __('candidate_portal.feedback.form_title') }}</p>
                                        <p class="text-xs text-slate-600">{{ __('candidate_portal.feedback.form_hint') }}</p>
                                        <p class="rounded-lg border border-success-200/80 bg-success-50/70 px-2.5 py-2 text-xs text-success-900">
                                            {{ __('candidate_portal.feedback.privacy_notice') }}
                                        </p>

                                        <div class="grid gap-3 md:grid-cols-3">
                                            <x-form-field :label="__('candidate_portal.feedback.rating_clarity')" name="rating_clarity">
                                                <select name="rating_clarity" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2 text-sm text-slate-900 shadow-sm">
                                                    <option value="">{{ __('candidate_portal.feedback.rating_placeholder') }}</option>
                                                    @for($i = 1; $i <= 5; $i++)
                                                        <option value="{{ $i }}" @selected((string) old('rating_clarity') === (string) $i)>{{ $i }}</option>
                                                    @endfor
                                                </select>
                                                @error('rating_clarity')
                                                    <p class="mt-1 text-xs text-danger-700">{{ $message }}</p>
                                                @enderror
                                            </x-form-field>

                                            <x-form-field :label="__('candidate_portal.feedback.rating_speed')" name="rating_speed">
                                                <select name="rating_speed" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2 text-sm text-slate-900 shadow-sm">
                                                    <option value="">{{ __('candidate_portal.feedback.rating_placeholder') }}</option>
                                                    @for($i = 1; $i <= 5; $i++)
                                                        <option value="{{ $i }}" @selected((string) old('rating_speed') === (string) $i)>{{ $i }}</option>
                                                    @endfor
                                                </select>
                                                @error('rating_speed')
                                                    <p class="mt-1 text-xs text-danger-700">{{ $message }}</p>
                                                @enderror
                                            </x-form-field>

                                            <x-form-field :label="__('candidate_portal.feedback.rating_kindness')" name="rating_kindness">
                                                <select name="rating_kindness" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2 text-sm text-slate-900 shadow-sm">
                                                    <option value="">{{ __('candidate_portal.feedback.rating_placeholder') }}</option>
                                                    @for($i = 1; $i <= 5; $i++)
                                                        <option value="{{ $i }}" @selected((string) old('rating_kindness') === (string) $i)>{{ $i }}</option>
                                                    @endfor
                                                </select>
                                                @error('rating_kindness')
                                                    <p class="mt-1 text-xs text-danger-700">{{ $message }}</p>
                                                @enderror
                                            </x-form-field>
                                        </div>

                                        <x-form-field :label="__('candidate_portal.feedback.comment_label')" name="comment">
                                            <textarea
                                                name="comment"
                                                rows="3"
                                                class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2 text-sm text-slate-900 shadow-sm"
                                                placeholder="{{ __('candidate_portal.feedback.comment_placeholder') }}">{{ old('comment') }}</textarea>
                                            @error('comment')
                                                <p class="mt-1 text-xs text-danger-700">{{ $message }}</p>
                                            @enderror
                                        </x-form-field>

                                        <div>
                                            <button type="submit"
                                                    class="rounded-xl bg-success-600 px-4 py-2 text-sm font-semibold text-white transition-weightless hover:bg-success-700">
                                                {{ __('candidate_portal.feedback.submit_action') }}
                                            </button>
                                        </div>
                                    </form>
                                @endif
                            @endif
                        </section>
                    @endforeach
                </div>
            @endif
        </x-glass-card>

        @if(($strategyLabBriefs ?? collect())->isNotEmpty())
            <x-glass-card
                :title="__('strategy_lab.candidate.title')"
                :subtitle="__('strategy_lab.candidate.subtitle')">
                <div class="space-y-4">
                    @foreach($strategyLabBriefs as $brief)
                        @php
                            $application = $brief->application;
                            $submission = $brief->submission;
                            $summary = $brief->aiSummary;
                            $deadlineIso = $brief->deadline_at?->toIso8601String();
                            $isPastDeadline = $brief->deadline_at && $brief->deadline_at->isPast();
                        @endphp
                        <div class="rounded-2xl border border-white/70 bg-white/70 p-5">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <p class="text-xs uppercase tracking-[0.24em] text-aura-700/85">{{ __('strategy_lab.labels.strategy_lab') }}</p>
                                    <h3 class="mt-1 text-lg font-semibold text-slate-900">{{ $brief->brief_title }}</h3>
                                    <p class="mt-1 text-sm text-slate-600">{{ $application?->job?->title ?? __('strategy_lab.labels.unknown_job') }}</p>
                                </div>
                                <x-badge>{{ __('strategy_lab.status.'.$brief->status) }}</x-badge>
                            </div>

                            <div class="mt-3 grid gap-3 md:grid-cols-2">
                                <div class="rounded-xl border border-slate-200 bg-white/80 p-3">
                                    <p class="text-xs uppercase tracking-wide text-slate-600">{{ __('strategy_lab.labels.deadline') }}</p>
                                    <p class="mt-1 text-sm font-medium text-slate-800">{{ optional($brief->deadline_at)->format('Y-m-d H:i') }} UTC</p>
                                    <p class="mt-1 text-xs uppercase tracking-wide text-aura-700/85">{{ __('strategy_lab.labels.countdown_48h') }}</p>
                                    <p class="mt-1 text-xs text-slate-600"
                                       data-strategy-countdown
                                       data-deadline="{{ $deadlineIso }}"
                                       data-expired-label="{{ __('strategy_lab.labels.deadline_expired') }}">
                                        {{ __('strategy_lab.labels.countdown_loading') }}
                                    </p>
                                </div>

                                <div class="rounded-xl border border-slate-200 bg-white/80 p-3">
                                    <p class="text-xs uppercase tracking-wide text-slate-600">{{ __('strategy_lab.labels.brief') }}</p>
                                    @if($brief->brief_pdf_url)
                                        <a href="{{ \App\Http\Controllers\StrategyLabController::signedBriefUrl($brief) }}"
                                           class="mt-2 inline-flex rounded-lg border border-aura-300/50 bg-white px-3 py-1.5 text-sm font-medium text-slate-800 transition-weightless hover:bg-white">
                                            {{ __('strategy_lab.actions.download_brief') }}
                                        </a>
                                    @else
                                        <p class="mt-2 text-sm text-primary-700">{{ __('strategy_lab.messages.brief_processing') }}</p>
                                    @endif
                                </div>
                            </div>

                            @if($submission)
                                <div class="mt-4 rounded-xl border border-success-200 bg-success-50/70 p-3">
                                    <p class="text-sm font-semibold text-success-900">{{ __('strategy_lab.messages.submission_received') }}</p>
                                    <p class="mt-1 text-xs text-slate-700">
                                        {{ __('strategy_lab.labels.submitted_at') }}: {{ optional($submission->submitted_at)->format('Y-m-d H:i') }} UTC
                                    </p>
                                    @if($summary)
                                        <p class="mt-2 text-sm text-slate-700">{{ $summary->executive_summary_text }}</p>
                                    @else
                                        <p class="mt-2 text-sm text-primary-700">{{ __('strategy_lab.messages.summary_processing') }}</p>
                                    @endif
                                </div>
                            @else
                                @php
                                    $briefReady = is_string($brief->brief_pdf_url) && trim($brief->brief_pdf_url) !== '';
                                    $submissionDisabled = $isPastDeadline || ! $briefReady;
                                @endphp
                                <form method="POST"
                                      action="{{ route('candidate.strategy-lab.submit', ['company' => $company, 'application' => $application->id]) }}"
                                      enctype="multipart/form-data"
                                      class="mt-4 space-y-3">
                                    @csrf

                                    <x-form-field :label="__('strategy_lab.fields.submission_type')" name="submission_type">
                                        <select name="submission_type" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-sm text-slate-900 shadow-sm" @disabled($submissionDisabled)>
                                            <option value="{{ \App\Models\StrategyLabSubmission::TYPE_DOCUMENT }}" @selected(old('submission_type') === \App\Models\StrategyLabSubmission::TYPE_DOCUMENT)>
                                                {{ __('strategy_lab.submission.types.document') }}
                                            </option>
                                            <option value="{{ \App\Models\StrategyLabSubmission::TYPE_PRESENTATION }}" @selected(old('submission_type') === \App\Models\StrategyLabSubmission::TYPE_PRESENTATION)>
                                                {{ __('strategy_lab.submission.types.presentation') }}
                                            </option>
                                        </select>
                                        @error('submission_type')
                                            <p class="mt-1 text-xs text-danger-700">{{ $message }}</p>
                                        @enderror
                                    </x-form-field>

                                    <div class="rounded-2xl border border-dashed border-aura-300/60 bg-aura-50/65 p-4">
                                        <label class="block text-xs uppercase tracking-wide text-aura-800">{{ __('strategy_lab.fields.submission_file') }}</label>
                                        <div
                                            class="mt-2 rounded-xl border border-dashed border-aura-300/60 bg-white/80 px-4 py-6 text-center text-sm text-slate-600"
                                            data-strategy-dropzone
                                            @class(['opacity-60' => $submissionDisabled])
                                            @if($submissionDisabled) aria-disabled="true" @endif
                                        >
                                            <p class="font-medium text-slate-700">{{ __('strategy_lab.labels.dropzone_prompt') }}</p>
                                            <p class="mt-1 text-xs text-slate-500">{{ __('strategy_lab.labels.dropzone_hint') }}</p>
                                        </div>
                                        <input type="file"
                                               name="submission_file"
                                               accept=".pdf,.doc,.docx,.ppt,.pptx,.odp,.key,.txt"
                                               data-strategy-file-input
                                               class="mt-2 w-full rounded-xl border border-aura-200/40 bg-white/85 px-3 py-2 text-sm text-slate-900 shadow-sm file:mr-4 file:rounded-lg file:border-0 file:bg-aura-100 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-aura-700"
                                               @disabled($submissionDisabled)
                                               required>
                                        <p class="mt-2 text-xs text-slate-600">{{ __('strategy_lab.labels.file_rules') }}</p>
                                        @error('submission_file')
                                            <p class="mt-1 text-xs text-danger-700">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    @if(! $briefReady)
                                        <p class="text-xs text-danger-700">{{ __('strategy_lab.messages.brief_processing') }}</p>
                                    @endif
                                    @if($isPastDeadline)
                                        <p class="text-xs text-danger-700">{{ __('strategy_lab.messages.deadline_passed') }}</p>
                                    @endif

                                    <button type="submit"
                                            class="rounded-xl bg-success-600 px-4 py-2 text-sm font-semibold text-white transition-weightless hover:bg-success-700 disabled:cursor-not-allowed disabled:opacity-50"
                                            @disabled($submissionDisabled)>
                                        {{ __('strategy_lab.actions.submit_solution') }}
                                    </button>
                                </form>
                            @endif
                        </div>
                    @endforeach
                </div>
            </x-glass-card>
        @endif

        @if(($videoAssessments ?? collect())->isNotEmpty())
            <x-glass-card
                :title="__('video_assessment.portal.title')"
                :subtitle="__('video_assessment.portal.subtitle')">
                <div class="space-y-4">
                    @foreach($videoAssessments as $assessment)
                        @php
                            $application = $assessment['application'];
                            $config = $assessment['config'];
                            $total = (int) ($assessment['total'] ?? 0);
                            $answered = (int) ($assessment['answered'] ?? 0);
                            $percent = (int) ($assessment['percent'] ?? 0);
                            $nextQuestionId = (string) ($assessment['next_question_id'] ?? '');
                            $latestRequest = $assessment['latest_unified_request'] ?? null;
                            $latestStatus = (string) ($latestRequest?->status ?? '');
                            $processing = in_array($latestStatus, [\App\Models\AiRequest::STATUS_QUEUED, \App\Models\AiRequest::STATUS_RUNNING], true);
                            $failed = $latestStatus === \App\Models\AiRequest::STATUS_FAILED;
                            $completed = $total > 0 && $answered >= $total;
                        @endphp

                        <div class="rounded-2xl border border-white/70 bg-white/70 p-5">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <p class="text-xs uppercase tracking-[0.24em] text-aura-700/85">{{ $application->job?->title ?? __('strategy_lab.labels.unknown_job') }}</p>
                                    <h3 class="mt-1 text-lg font-semibold text-slate-900">{{ $config->name }}</h3>
                                    <p class="mt-1 text-xs text-slate-600">{{ __('video_assessment.portal.labels.progress', ['current' => $answered, 'total' => $total]) }}</p>
                                </div>
                                @if($processing)
                                    <x-badge>{{ __('video_assessment.portal.labels.processing') }}</x-badge>
                                @elseif($failed)
                                    <x-badge>{{ __('video_assessment.portal.labels.processing_failed') }}</x-badge>
                                @elseif($completed)
                                    <x-badge>{{ __('video_assessment.portal.labels.completed') }}</x-badge>
                                @else
                                    <x-badge>{{ __('video_assessment.portal.labels.not_started') }}</x-badge>
                                @endif
                            </div>

                            <div class="mt-3 h-2 rounded-full bg-aura-100/80">
                                <div class="h-2 rounded-full bg-success-600" style="width: {{ $percent }}%"></div>
                            </div>

                            <div class="mt-4">
                                <a href="{{ route('candidate.video-stories', ['company' => $company->slug, 'application' => $application->id, 'question_id' => $nextQuestionId]) }}"
                                   class="inline-flex rounded-lg border border-aura-300/50 bg-white px-3 py-1.5 text-sm font-medium text-slate-800 transition-weightless hover:bg-white">
                                    {{ $failed ? __('video_assessment.portal.actions.retry_failed') : ($answered > 0 ? __('video_assessment.portal.actions.continue') : __('video_assessment.portal.actions.open')) }}
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-glass-card>
        @endif

        @if(($sjtAssessments ?? collect())->isNotEmpty())
            <x-glass-card
                :title="__('sjt.portal.title')"
                :subtitle="__('sjt.portal.subtitle')">
                <div class="space-y-4">
                    @foreach($sjtAssessments as $assessment)
                        @php
                            $application = $assessment['application'];
                            $answered = (int) ($assessment['answered'] ?? 0);
                            $scored = (int) ($assessment['scored'] ?? 0);
                            $total = (int) ($assessment['total'] ?? 0);
                            $percent = (int) ($assessment['percent'] ?? 0);
                            $status = (string) ($assessment['status'] ?? 'not_started');
                        @endphp
                        <div class="rounded-2xl border border-white/70 bg-white/70 p-5">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <p class="text-xs uppercase tracking-[0.24em] text-aura-700/85">{{ $application->job?->title ?? __('sjt.messages.unknown_job') }}</p>
                                    <p class="mt-1 text-sm text-slate-700">{{ __('sjt.portal.counts', ['answered' => $answered, 'total' => $total]) }}</p>
                                    <p class="mt-1 text-xs text-slate-600">{{ __('sjt.portal.scores', ['scored' => $scored]) }}</p>
                                </div>
                                <x-badge>{{ __('sjt.portal.status_'.$status) }}</x-badge>
                            </div>
                            <div class="mt-3 h-2 rounded-full bg-aura-100/80">
                                <div class="h-2 rounded-full bg-success-600" style="width: {{ $percent }}%"></div>
                            </div>
                            <div class="mt-4">
                                <a href="{{ route('candidate.assessments.sjt', ['application_id' => $application->id]) }}"
                                   class="inline-flex rounded-lg border border-aura-300/50 bg-white px-3 py-1.5 text-sm font-medium text-slate-800 transition-weightless hover:bg-white">
                                    {{ __('sjt.portal.open_action') }}
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-glass-card>
        @endif

        @if(($psyTests ?? collect())->isNotEmpty())
            <x-glass-card
                title="Tests Psychologiques"
                subtitle="Vos tests de personnalité et compétences comportementales">
                <div class="space-y-4">
                    @foreach($psyTests as $psyData)
                        @php
                            $application = $psyData['application'];
                            $test = $psyData['test'];
                            $status = $psyData['status'];
                        @endphp
                        <div class="rounded-2xl border border-white/70 bg-white/70 p-5">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <p class="text-xs uppercase tracking-[0.24em] text-aura-700/85">{{ $application->job?->title ?? 'Poste inconnu' }}</p>
                                    <h3 class="mt-1 text-lg font-semibold text-slate-900">Test Profil: {{ ucfirst($test->profile) }}</h3>
                                    @if($status === 'completed')
                                        <p class="mt-1 text-xs text-slate-600">Score: {{ $test->score }} / 100</p>
                                    @elseif($status === 'pending')
                                        <p class="mt-1 text-xs text-slate-600">Expire le: {{ $test->expires_at?->format('d/m/Y H:i') }}</p>
                                    @endif
                                </div>
                                @if($status === 'completed')
                                    <x-badge class="bg-success-100 text-success-800">Complété</x-badge>
                                @elseif($status === 'expired')
                                    <x-badge class="bg-red-100 text-red-800">Expiré</x-badge>
                                @else
                                    <x-badge class="bg-warning-100 text-warning-800">En attente</x-badge>
                                @endif
                            </div>
                            @if($status === 'pending')
                                <div class="mt-4">
                                    <a href="{{ route('public.psy-test.show', $test->token) }}"
                                       class="inline-flex rounded-lg border border-aura-300/50 bg-white px-3 py-1.5 text-sm font-medium text-slate-800 transition-weightless hover:bg-white">
                                        Passer le test
                                    </a>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </x-glass-card>
        @endif

        {{-- Open Roles --}}
        <x-glass-card
            :title="__('master.candidate.open_roles_title')"
            :subtitle="__('master.candidate.open_roles_subtitle', ['company' => $company->name])">

            @if($openJobs->isNotEmpty())
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    @foreach($openJobs as $job)
                        @php
                            $alreadyApplied = in_array((string) $job->id, $appliedJobIds ?? [], true);
                        @endphp
                        <div class="rounded-2xl border border-white/70 bg-white/65 p-5 shadow-[0_22px_55px_-40px_rgba(100,103,242,0.65)] backdrop-blur-2xl transition-weightless hover:-translate-y-0.5 hover:bg-white/75">
                            <p class="text-xs uppercase tracking-[0.22em] text-aura-700/85">{{ $job->department?->name ?? __('career.list.no_department') }}</p>
                            <h3 class="mt-2 text-lg font-semibold text-slate-900">{{ $job->title }}</h3>
                            <p class="mt-1 text-sm text-slate-600">{{ $job->location ?: __('career.list.location_tbd') }}</p>
                            @if($alreadyApplied)
                                <span class="mt-4 inline-flex cursor-not-allowed rounded-xl border border-slate-300 bg-slate-100 px-4 py-2 text-sm font-semibold text-slate-500 opacity-80" aria-disabled="true">
                                    {{ __('career.list.already_applied') }}
                                </span>
                            @else
                                <a href="{{ route('career.show', ['company' => $company, 'job' => $job]) }}"
                                   class="mt-4 inline-flex rounded-xl bg-success-600 px-4 py-2 text-sm font-semibold text-white transition-weightless hover:bg-success-700">
                                    {{ __('master.candidate.view_job') }}
                                </a>
                            @endif
                        </div>
                    @endforeach
                </div>

                @if($totalOpenJobs > $openJobs->count())
                    <div class="mt-5 text-center">
                        <a href="{{ route('career.index', ['company' => $company]) }}"
                           class="inline-flex items-center gap-2 rounded-xl border border-aura-300/50 bg-white/85 px-5 py-2.5 text-sm font-medium text-slate-900 transition-weightless hover:bg-white">
                            {{ __('master.candidate.view_all_jobs') }}
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
                        </a>
                    </div>
                @endif
            @else
                <div class="py-4">
                    <x-empty-state
                        :title="__('master.candidate.no_roles_title')"
                        :message="__('master.candidate.no_roles_message')" />
                </div>
            @endif
        </x-glass-card>

        {{-- Culture & Values --}}
        @include('candidate.partials.culture-values', ['values' => $values])

        {{-- FAQs --}}
        @if($faqs->isNotEmpty())
            <x-glass-card :title="__('master.candidate.faq_title')">
                <div class="mb-4">
                    <a href="{{ route('candidate.faq', ['company' => $company->slug]) }}"
                       class="inline-flex rounded-lg border border-aura-300/50 bg-white px-3 py-1.5 text-xs font-medium text-slate-800 transition-weightless hover:bg-white">
                        {{ __('candidate_portal.faq.open_faq_page') }}
                    </a>
                </div>
                <div class="space-y-3">
                    @foreach($faqs as $faq)
                        <div class="rounded-xl border border-white/80 bg-white/70 p-4">
                            <p class="text-xs uppercase tracking-wider text-aura-700/80">{{ $faq->category }}</p>
                            <h4 class="mt-1 text-sm font-semibold text-slate-900">{{ $faq->question }}</h4>
                            <p class="mt-2 text-sm text-slate-700">{{ $faq->answer }}</p>
                        </div>
                    @endforeach
                </div>
            </x-glass-card>
        @endif

    </div>

    @include('candidate.partials.guide-bot', ['company' => $company])

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const countdownNodes = document.querySelectorAll('[data-strategy-countdown]');
            if (countdownNodes.length > 0) {
                const formatRemaining = function (totalSeconds) {
                    if (totalSeconds <= 0) {
                        return null;
                    }

                    const days = Math.floor(totalSeconds / 86400);
                    const hours = Math.floor((totalSeconds % 86400) / 3600);
                    const minutes = Math.floor((totalSeconds % 3600) / 60);
                    const seconds = totalSeconds % 60;

                    return `${days}d ${hours}h ${minutes}m ${seconds}s`;
                };

                const tick = function () {
                    const now = new Date();

                    countdownNodes.forEach(function (node) {
                        const deadlineRaw = node.getAttribute('data-deadline');
                        const expiredLabel = node.getAttribute('data-expired-label') || 'Deadline passed';
                        if (!deadlineRaw) {
                            return;
                        }

                        const deadline = new Date(deadlineRaw);
                        if (Number.isNaN(deadline.getTime())) {
                            return;
                        }

                        const seconds = Math.floor((deadline.getTime() - now.getTime()) / 1000);
                        const rendered = formatRemaining(seconds);
                        node.textContent = rendered ? rendered : expiredLabel;
                    });
                };

                tick();
                setInterval(tick, 1000);
            }

            document.querySelectorAll('[data-strategy-dropzone]').forEach(function (dropzone) {
                const form = dropzone.closest('form');
                if (!form) {
                    return;
                }

                const fileInput = form.querySelector('[data-strategy-file-input]');
                if (!(fileInput instanceof HTMLInputElement)) {
                    return;
                }

                const disabled = dropzone.getAttribute('aria-disabled') === 'true' || fileInput.disabled;
                if (disabled) {
                    return;
                }

                const preventDefaults = function (event) {
                    event.preventDefault();
                    event.stopPropagation();
                };

                ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(function (eventName) {
                    dropzone.addEventListener(eventName, preventDefaults, false);
                });

                ['dragenter', 'dragover'].forEach(function (eventName) {
                    dropzone.addEventListener(eventName, function () {
                        dropzone.classList.add('border-aura-500', 'bg-aura-100/70');
                    }, false);
                });

                ['dragleave', 'drop'].forEach(function (eventName) {
                    dropzone.addEventListener(eventName, function () {
                        dropzone.classList.remove('border-aura-500', 'bg-aura-100/70');
                    }, false);
                });

                dropzone.addEventListener('click', function () {
                    fileInput.click();
                });

                dropzone.addEventListener('drop', function (event) {
                    const droppedFiles = event.dataTransfer?.files;
                    if (!droppedFiles || droppedFiles.length === 0) {
                        return;
                    }

                    fileInput.files = droppedFiles;
                    fileInput.dispatchEvent(new Event('change', { bubbles: true }));
                });
            });

            document.querySelectorAll('[data-onboarding-dropzone]').forEach(function (dropzone) {
                const form = dropzone.closest('form');
                if (!form) {
                    return;
                }

                const fileInput = form.querySelector('[data-onboarding-file-input]');
                if (!(fileInput instanceof HTMLInputElement)) {
                    return;
                }

                const select = form.querySelector('[data-onboarding-doc-type-select]');
                const submitButton = form.querySelector('[data-onboarding-upload-submit]');
                const statusNode = form.querySelector('[data-onboarding-upload-status]');
                const syncUploadState = function () {
                    const option = select instanceof HTMLSelectElement
                        ? select.options[select.selectedIndex]
                        : null;
                    const optionUploaded = option?.dataset.uploaded === '1';
                    const allUploaded = form.getAttribute('data-all-uploaded') === '1';
                    const shouldDisable = allUploaded || optionUploaded;

                    fileInput.disabled = shouldDisable;

                    if (submitButton instanceof HTMLButtonElement) {
                        submitButton.disabled = shouldDisable;
                        const uploadLabel = submitButton.getAttribute('data-upload-label') || '';
                        const uploadedLabel = submitButton.getAttribute('data-uploaded-label') || uploadLabel;
                        submitButton.textContent = shouldDisable ? uploadedLabel : uploadLabel;
                    }

                    if (statusNode instanceof HTMLElement) {
                        const allUploadedHint = statusNode.getAttribute('data-all-uploaded-hint') || '';
                        const alreadyUploadedHint = statusNode.getAttribute('data-already-uploaded-hint') || '';
                        const emptyHint = statusNode.getAttribute('data-empty-hint') || '';
                        const nextHint = allUploaded ? allUploadedHint : (optionUploaded ? alreadyUploadedHint : emptyHint);
                        statusNode.textContent = nextHint;
                        statusNode.classList.toggle('hidden', nextHint === '');
                    }

                    dropzone.setAttribute('aria-disabled', shouldDisable ? 'true' : 'false');
                    dropzone.classList.toggle('opacity-60', shouldDisable);
                    dropzone.classList.toggle('cursor-not-allowed', shouldDisable);
                };

                if (select instanceof HTMLSelectElement) {
                    select.addEventListener('change', syncUploadState);
                }

                const preventDefaults = function (event) {
                    event.preventDefault();
                    event.stopPropagation();
                };

                ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(function (eventName) {
                    dropzone.addEventListener(eventName, preventDefaults, false);
                });

                ['dragenter', 'dragover'].forEach(function (eventName) {
                    dropzone.addEventListener(eventName, function () {
                        if (fileInput.disabled) {
                            return;
                        }
                        dropzone.classList.add('border-aura-500', 'bg-aura-100/70');
                    }, false);
                });

                ['dragleave', 'drop'].forEach(function (eventName) {
                    dropzone.addEventListener(eventName, function () {
                        dropzone.classList.remove('border-aura-500', 'bg-aura-100/70');
                    }, false);
                });

                dropzone.addEventListener('click', function () {
                    if (fileInput.disabled) {
                        return;
                    }
                    fileInput.click();
                });

                dropzone.addEventListener('drop', function (event) {
                    if (fileInput.disabled) {
                        return;
                    }
                    const droppedFiles = event.dataTransfer?.files;
                    if (!droppedFiles || droppedFiles.length === 0) {
                        return;
                    }

                    fileInput.files = droppedFiles;
                    fileInput.dispatchEvent(new Event('change', { bubbles: true }));
                });

                syncUploadState();
            });

            const trackerRoot = document.querySelector('[data-status-tracker-root]');
            const trackerEndpoint = trackerRoot?.getAttribute('data-status-endpoint') || '';
            if (trackerRoot instanceof HTMLElement && trackerEndpoint !== '') {
                const escapeHtml = function (value) {
                    const text = String(value ?? '');
                    const div = document.createElement('div');
                    div.textContent = text;
                    return div.innerHTML;
                };

                const VALID_STATES = ['completed', 'current', 'rejected', 'pending'];

                const dotClassMap = {
                    completed: 'bg-success-500 shadow-[0_0_0_4px_rgba(34,197,94,0.15)]',
                    current: 'bg-primary-500 shadow-[0_0_0_4px_rgba(124,58,237,0.18)]',
                    rejected: 'bg-rose-500 shadow-[0_0_0_4px_rgba(244,63,94,0.15)]',
                    pending: 'bg-white border-2 border-slate-200',
                };

                const badgeClassMap = {
                    completed: 'border-success-200 bg-success-50 text-success-700',
                    current: 'border-primary-200 bg-primary-50 text-primary-700',
                    rejected: 'border-rose-200 bg-rose-50 text-rose-700',
                    pending: 'border-slate-200 bg-slate-50 text-slate-500',
                };

                const liquidStopsMap = {
                    current: ['from-success-400', 'to-primary-400'],
                    completed: ['from-success-400', 'to-success-400'],
                    rejected: ['from-success-400', 'to-rose-400'],
                    pending: ['from-success-400', 'to-slate-300'],
                };

                const stepIconHtml = function (state, stepNumber) {
                    switch (state) {
                        case 'completed':
                            return '<svg class="h-4 w-4 text-white" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" /></svg>';
                        case 'rejected':
                            return '<svg class="h-4 w-4 text-white" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.28 4.22a.75.75 0 0 0-1.06 1.06L8.94 10l-4.72 4.72a.75.75 0 1 0 1.06 1.06L10 11.06l4.72 4.72a.75.75 0 1 0 1.06-1.06L11.06 10l4.72-4.72a.75.75 0 0 0-1.06-1.06L10 8.94 5.28 4.22Z" clip-rule="evenodd" /></svg>';
                        case 'current':
                            return '<span class="h-2.5 w-2.5 rounded-full bg-white"></span>';
                        default:
                            return `<span class="text-[11px] font-bold text-slate-400">${stepNumber}</span>`;
                    }
                };

                const computeProgress = function (steps) {
                    const total = steps.length;
                    const completedCount = steps.filter((step) => step?.state === 'completed').length;
                    const hasCurrent = steps.some((step) => step?.state === 'current');
                    const rejectedIndex = steps.findIndex((step) => step?.state === 'rejected');
                    const isRejected = rejectedIndex !== -1;
                    const progressUnits = isRejected
                        ? (rejectedIndex + 1) * 2
                        : (completedCount * 2) + (hasCurrent ? 1 : 0);
                    const progressDenominator = total > 1 ? (total - 1) * 2 : 1;
                    let percent = total > 1
                        ? Math.round((progressUnits / progressDenominator) * 100)
                        : (completedCount > 0 ? 100 : 0);
                    percent = Math.max(0, Math.min(100, percent));

                    return { percent, isRejected };
                };

                const renderStep = function (step, index, steps) {
                    const state = VALID_STATES.includes(step?.state) ? step.state : 'pending';
                    const label = escapeHtml(step?.label || '');
                    const detail = escapeHtml(step?.detail || '');
                    const stateLabel = escapeHtml(step?.state_label || '');
                    const key = escapeHtml(step?.key || '');
                    const dotClasses = dotClassMap[state] || dotClassMap.pending;
                    const badgeClasses = badgeClassMap[state] || badgeClassMap.pending;

                    const isLast = index === steps.length - 1;
                    const nextState = !isLast && VALID_STATES.includes(steps[index + 1]?.state) ? steps[index + 1].state : 'pending';
                    const liquidStops = liquidStopsMap[nextState] || liquidStopsMap.pending;
                    const lineFilled = state === 'completed';

                    const verticalLine = !isLast ? `
                        <div class="absolute left-[15px] top-[32px] bottom-[-12px] w-[3px] lg:hidden bg-slate-200 rounded-full overflow-hidden">
                            ${lineFilled ? `<div class="h-full w-full bg-gradient-to-b ${liquidStops[0]} ${liquidStops[1]}"></div>` : ''}
                        </div>
                    ` : '';

                    const horizontalLine = !isLast ? `
                        <div class="hidden lg:block absolute left-1/2 top-[15px] w-full h-[3px] bg-slate-200 rounded-full overflow-hidden z-0">
                            ${lineFilled ? `<div class="h-full w-full bg-gradient-to-r ${liquidStops[0]} ${liquidStops[1]}"></div>` : ''}
                        </div>
                    ` : '';

                    const pingRing = state === 'current'
                        ? '<span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-primary-400 opacity-50" aria-hidden="true"></span>'
                        : '';

                    return `
                        <li class="relative flex-1 lg:min-w-0" data-status-step-row data-status-step-key="${key}" data-status-state="${state}">
                            ${verticalLine}
                            ${horizontalLine}
                            <div class="flex lg:flex-col items-start lg:items-center gap-4 lg:gap-3">
                                <div class="relative z-10 flex h-8 w-8 shrink-0 scale-110 items-center justify-center rounded-full ring-4 ring-white ${dotClasses}">
                                    ${pingRing}
                                    <span class="relative">${stepIconHtml(state, index + 1)}</span>
                                </div>
                                <div class="min-w-0 flex-1 pb-6 lg:pb-0 lg:px-2 pt-0.5 lg:pt-1.5 lg:text-center">
                                    <div class="flex flex-col gap-1.5 lg:items-center">
                                        <p class="text-[13px] font-semibold text-slate-900" data-status-step-label>${label}</p>
                                        <div>
                                            <span class="inline-flex rounded-full border px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider ${badgeClasses}" data-status-step-state>${stateLabel}</span>
                                        </div>
                                    </div>
                                    <p class="mt-2 text-[11px] leading-relaxed text-slate-600 lg:mx-auto lg:max-w-[140px]" data-status-step-detail>${detail}</p>
                                </div>
                            </div>
                        </li>
                    `;
                };

                const applyTrackerPayload = function (payload) {
                    const trackers = Array.isArray(payload?.trackers) ? payload.trackers : [];
                    trackers.forEach(function (tracker) {
                        const applicationId = String(tracker?.application_id || '');
                        if (!applicationId) {
                            return;
                        }

                        const card = trackerRoot.querySelector(`[data-status-tracker-card][data-application-id="${applicationId}"]`);
                        if (!(card instanceof HTMLElement)) {
                            return;
                        }

                        const steps = Array.isArray(tracker?.steps) ? tracker.steps : [];

                        const list = card.querySelector('[data-status-step-list]');
                        if (list instanceof HTMLElement) {
                            list.innerHTML = steps.map((step, index) => renderStep(step, index, steps)).join('');
                        }

                        const { percent, isRejected } = computeProgress(steps);

                        const progressFill = card.querySelector('[data-status-progress-fill]');
                        if (progressFill instanceof HTMLElement) {
                            progressFill.style.width = `${percent}%`;
                            progressFill.classList.remove('from-success-400', 'via-primary-500', 'to-aura-500', 'from-rose-400', 'to-rose-500');
                            if (isRejected) {
                                progressFill.classList.add('from-rose-400', 'to-rose-500');
                            } else {
                                progressFill.classList.add('from-success-400', 'via-primary-500', 'to-aura-500');
                            }
                            progressFill.setAttribute('data-rejected', isRejected ? '1' : '0');
                        }

                        const progressLabel = card.querySelector('[data-status-progress-label]');
                        if (progressLabel instanceof HTMLElement) {
                            progressLabel.textContent = `${percent}%`;
                        }

                        const updatedText = card.querySelector('[data-status-updated-text]');
                        if (updatedText instanceof HTMLElement) {
                            updatedText.textContent = String(tracker?.updated_human || '');
                        }
                    });
                };

                const refreshStatusTracker = async function () {
                    try {
                        const response = await fetch(trackerEndpoint, {
                            headers: {
                                Accept: 'application/json',
                            },
                        });
                        if (!response.ok) {
                            return;
                        }

                        const payload = await response.json().catch(() => ({}));
                        applyTrackerPayload(payload);
                    } catch (error) {
                        console.warn('Status tracker refresh failed.', error);
                    }
                };

                refreshStatusTracker();
                window.setInterval(refreshStatusTracker, 20000);
            }

            document.querySelectorAll('[data-password-toggle]').forEach(function (button) {
                const targetId = button.getAttribute('data-password-target') || '';
                if (targetId === '') {
                    return;
                }

                const input = document.getElementById(targetId);
                if (!(input instanceof HTMLInputElement)) {
                    return;
                }

                const showLabel = button.getAttribute('data-show-label') || 'Show';
                const hideLabel = button.getAttribute('data-hide-label') || 'Hide';
                const eyeOpen = button.querySelector('[data-eye-open]');
                const eyeClosed = button.querySelector('[data-eye-closed]');
                const srLabel = button.querySelector('[data-password-toggle-label]');

                button.addEventListener('click', function () {
                    const shouldShow = input.type === 'password';
                    input.type = shouldShow ? 'text' : 'password';

                    if (eyeOpen instanceof SVGElement && eyeClosed instanceof SVGElement) {
                        eyeOpen.classList.toggle('hidden', shouldShow);
                        eyeClosed.classList.toggle('hidden', !shouldShow);
                    }

                    const nextLabel = shouldShow ? hideLabel : showLabel;
                    button.setAttribute('aria-label', nextLabel);
                    if (srLabel instanceof HTMLElement) {
                        srLabel.textContent = nextLabel;
                    }
                });
            });

            const shouldCelebrate = @json((bool) session('onboarding_confetti'));
            if (shouldCelebrate) {
                const colors = ['#5B7CFA', '#F5B041', '#28B463', '#EC7063', '#8E44AD'];
                const container = document.createElement('div');
                container.style.position = 'fixed';
                container.style.inset = '0';
                container.style.pointerEvents = 'none';
                container.style.zIndex = '9999';
                document.body.appendChild(container);

                for (let i = 0; i < 120; i += 1) {
                    const piece = document.createElement('span');
                    piece.style.position = 'absolute';
                    piece.style.width = '8px';
                    piece.style.height = '14px';
                    piece.style.left = `${Math.random() * 100}%`;
                    piece.style.top = '-24px';
                    piece.style.opacity = '0.95';
                    piece.style.borderRadius = '2px';
                    piece.style.background = colors[i % colors.length];
                    piece.style.transform = `rotate(${Math.random() * 360}deg)`;
                    piece.style.transition = `transform 2200ms ease-out, top 2200ms ease-out, opacity 2200ms linear`;
                    container.appendChild(piece);

                    requestAnimationFrame(() => {
                        piece.style.top = `${60 + Math.random() * 45}%`;
                        piece.style.transform = `translateX(${(Math.random() - 0.5) * 220}px) rotate(${Math.random() * 960}deg)`;
                        piece.style.opacity = '0';
                    });
                }

                window.setTimeout(() => {
                    container.remove();
                }, 2400);
            }
        });
    </script>
</x-shell-layout>
