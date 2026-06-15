<aside {{ $attributes->class([
    'hidden lg:fixed lg:inset-y-0 lg:left-0 lg:z-30 lg:flex lg:p-4 lg:transform-gpu lg:transition-[width] lg:ease-out',
])->merge([
    'x-bind:class' => "(leftSidebarResizing ? 'lg:duration-75 ' : 'lg:duration-300 ') + (leftSidebarCollapsed ? 'pointer-events-none lg:opacity-0' : 'pointer-events-auto lg:opacity-100')",
    'x-bind:style' => 'leftSidebarWidthStyle()',
]) }}>
    <div class="flex h-full w-full flex-col rounded-3xl border border-white/80 bg-white/75 shadow-[0_24px_70px_-36px_rgba(76,29,149,0.55)] backdrop-blur-2xl">
        <div class="shrink-0 border-b border-slate-200/80 px-6 py-6 transition-all duration-300 flex items-center justify-center" x-bind:class="leftSidebarCollapsed ? 'px-3 py-4' : 'px-6 py-6'">
            <a
                href="{{ route('auth.company.dispatch') }}"
                class="inline-flex items-center justify-center transition-weightless w-full"
                aria-label="numa portal home"
            >
                <img
                    src="{{ asset('images/numa-logo-clean.png') }}"
                    alt="{{ config('app.name') }} logo"
                    class="h-10 w-auto object-contain transition-all duration-300"
                    x-bind:class="leftSidebarCollapsed ? 'h-8' : 'h-10'"
                    style="height: 40px; width: auto;"
                    loading="eager"
                    decoding="async"
                >
            </a>
        </div>

        @php
            $activeCompanyId = session('active_company_id');
            $activeCompanySlug = null;
            $activeRole = null;
            $isCandidateRole = false;

            if (auth()->check() && ! auth()->user()->isSuperadmin() && is_string($activeCompanyId) && $activeCompanyId !== '') {
                $activeCompanySlug = \App\Models\Company::query()
                    ->whereKey($activeCompanyId)
                    ->value('slug');

                $activeRole = auth()->user()->memberships()
                    ->where('company_id', $activeCompanyId)
                    ->where('membership_status', \App\Models\CompanyMembership::STATUS_ACTIVE)
                    ->value('company_role');

                $isCandidateRole = $activeRole === \App\Models\CompanyMembership::ROLE_CANDIDATE;
            }

            $menuNotificationDots = collect(session('sidebar_notification_dots', []));
            $overviewDot = (bool) $menuNotificationDots->get('overview', session()->has('status') || session()->has('error'));

            $candidateDashboardRoutePatterns = [
                'candidate.portal',
            ];
            $candidateApplicationsRoutePatterns = [
                'candidate.applications',
                'candidate.guide.ask',
                'candidate.reverse-feedback.*',
                'candidate.contract.*',
                'candidate.onboarding-documents.*',
                'candidate.onboarding-tasks.*',
                'candidate.strategy-lab.*',
            ];
            $candidateCvRoutePatterns = [
                'candidate.cv',
                'candidate.cv.*',
            ];
            $candidateUpdatesRoutePatterns = [
                'candidate.updates',
            ];
            $candidateFaqRoutePatterns = [
                'candidate.faq',
            ];
            $candidateSocialRoutePatterns = [
                'candidate.social-hub.*',
            ];
            $candidateAssessmentsRoutePatterns = [
                'candidate.assessments.*',
                'candidate.video-stories*',
            ];
            $candidateAccountRoutePatterns = [
                'candidate.account',
                'candidate.profile.*',
                'candidate.notification-preferences.*',
                'candidate.locale.*',
                'candidate.account.*',
            ];
        @endphp

        <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-3 py-4 transition-all duration-300" x-bind:class="leftSidebarCollapsed ? 'px-2' : 'px-3'">
            <nav class="space-y-2.5">
                @auth
                    @if(auth()->user()->isSuperadmin())
                        <x-ui.nav-link :href="route('platform.console')" :label="__('ui.nav.platform_console')" icon="platform_console" :active="request()->routeIs('platform.console')" collapsible />
                        <x-ui.nav-link :href="route('platform.company-approvals')" :label="__('ui.nav.company_approvals')" icon="company_approvals" :active="request()->routeIs('platform.company-approvals*')" collapsible />
                        <x-ui.nav-link :href="route('superadmin.contact-inquiries.index')" :label="__('ui.nav.contact_inquiries')" icon="contact_inquiries" :active="request()->routeIs('superadmin.contact-inquiries*')" collapsible />
                    @else
                        @if($isCandidateRole)
                            @if($activeCompanySlug)
                                <x-ui.nav-link
                                    :href="route('candidate.portal', ['company' => $activeCompanySlug])"
                                    :label="__('ui.nav.candidate_dashboard')"
                                    icon="overview"
                                    :active="request()->routeIs(...$candidateDashboardRoutePatterns)"
                                    collapsible
                                />
                                <x-ui.nav-link
                                    :href="route('candidate.applications', ['company' => $activeCompanySlug])"
                                    :label="__('ui.nav.candidate_applications')"
                                    icon="candidates"
                                    :active="request()->routeIs(...$candidateApplicationsRoutePatterns)"
                                    collapsible
                                />

                                <x-ui.nav-link
                                    :href="route('candidate.cv', ['company' => $activeCompanySlug])"
                                    :label="__('ui.nav.candidate_cv')"
                                    icon="cv"
                                    :active="request()->routeIs(...$candidateCvRoutePatterns)"
                                    collapsible
                                />
                                <x-ui.nav-link
                                    :href="route('candidate.social-hub.index', ['company' => $activeCompanySlug])"
                                    :label="__('ui.nav.social_hub')"
                                    icon="social_hub"
                                    :active="request()->routeIs(...$candidateSocialRoutePatterns)"
                                    collapsible
                                />
                                <x-ui.nav-link
                                    :href="route('candidate.account', ['company' => $activeCompanySlug])"
                                    :label="__('ui.nav.profile')"
                                    icon="profile"
                                    :active="request()->routeIs(...$candidateAccountRoutePatterns)"
                                    collapsible
                                />
                            @endif
                            @if(auth()->user()->can('access-candidate-assessments'))
                                <x-ui.nav-link
                                    :href="route('candidate.assessments.sjt')"
                                    :label="__('ui.nav.assessments')"
                                    icon="assessments"
                                    :active="request()->routeIs(...$candidateAssessmentsRoutePatterns)"
                                    collapsible
                                />
                            @endif
                            @if($activeCompanySlug)
                                <x-ui.nav-link
                                    :href="route('candidate.faq', ['company' => $activeCompanySlug])"
                                    :label="__('ui.nav.faqs')"
                                    icon="faqs"
                                    :active="request()->routeIs(...$candidateFaqRoutePatterns)"
                                    collapsible
                                />
                            @endif
                        @else
                            <x-ui.nav-link :href="route('home')" label="Dashboard" icon="overview" :active="request()->routeIs('home')" :dot="$overviewDot" collapsible />
                            @can('access-admin-pages')
                                <x-ui.nav-link :href="route('admin.recruitment-needs.index')" label="TB Recrutement" icon="company_approvals" :active="request()->routeIs('admin.recruitment-needs.*')" collapsible />
                                <x-ui.nav-link :href="route('jobs.index')" :label="__('ui.nav.jobs')" icon="jobs" :active="request()->routeIs('jobs.*')" collapsible />
                                <x-ui.nav-link :href="route('admin.psy-tests.index')" label="Tests psychologiques" icon="assessments" :active="request()->routeIs('admin.psy-tests.*')" collapsible />
                                <x-ui.nav-link :href="route('admin.sjt-scenarios.index')" label="Tests techniques" icon="sjt_scenarios" :active="request()->routeIs('admin.sjt-scenarios.*')" collapsible />
                            @endcan
                            <x-ui.nav-link :href="route('candidates.index')" :label="__('ui.nav.candidates')" icon="candidates" :active="request()->routeIs('candidates.index')" collapsible />
                            <x-ui.nav-link :href="route('candidates.kanban')" :label="__('ui.nav.candidates_kanban')" icon="candidates_kanban" :active="request()->routeIs('candidates.kanban*')" collapsible />
                            <x-ui.nav-link :href="route('interviews.index')" :label="__('ui.nav.interviews')" icon="interviews" :active="request()->routeIs('interviews.*')" collapsible />
                            <x-ui.nav-link :href="route('social-hub.index')" :label="__('ui.nav.social_hub')" icon="social_hub" :active="request()->routeIs('social-hub.*', 'candidate.social-hub.*')" collapsible />
                        @endif
                    @endif
                    @if(!$isCandidateRole)
                        <x-ui.nav-link :href="route('profile.edit')" :label="__('ui.nav.profile')" icon="profile" :active="request()->routeIs('profile.*')" collapsible />
                    @endif
                @endauth
            </nav>
        </div>

        @auth
            @php
                $profile = auth()->user()->profile;
                $displayName = $profile?->full_name ?? auth()->user()->email;
                $initials = collect(explode(' ', trim($displayName)))->filter()->map(fn ($part) => strtoupper(substr($part, 0, 1)))->take(2)->implode('');
                $avatarUrl = $profile?->avatar_url
                    ? \Illuminate\Support\Facades\URL::temporarySignedRoute('media.avatar', now()->addMinutes(10), ['profile' => $profile->getKey()])
                    : null;
            @endphp
            <div class="shrink-0 border-t border-slate-200/80 p-4 transition-all duration-300" x-bind:class="leftSidebarCollapsed ? 'p-2' : 'p-4'">
                <div class="flex items-center gap-3 rounded-2xl border border-slate-200/80 bg-white/85 p-3 transition-all duration-300" x-bind:class="leftSidebarCollapsed ? 'justify-center p-2' : 'p-3'">
                    @if($avatarUrl)
                        <img src="{{ $avatarUrl }}" alt="{{ $displayName }}" class="size-10 rounded-full object-cover" style="width: 40px; height: 40px;">
                    @else
                        <div class="flex size-10 items-center justify-center rounded-full bg-aura-100 text-sm font-semibold text-aura-800">{{ $initials }}</div>
                    @endif
                    <div class="min-w-0" x-cloak x-show="!leftSidebarCollapsed">
                        <p class="truncate text-sm font-semibold text-slate-900">{{ $displayName }}</p>
                        <p class="text-xs uppercase tracking-wide text-slate-600">
                            {{ auth()->user()->isSuperadmin() ? __('ui.nav.platform_console') : __('admin.roles.'.($activeRole ?? \App\Models\User::ROLE_CANDIDATE)) }}
                        </p>
                    </div>
                </div>
            </div>
        @endauth
    </div>

    <button
        type="button"
        class="absolute inset-y-6 -right-2 z-40 hidden w-4 cursor-col-resize items-center justify-center rounded-full lg:flex"
        x-bind:class="leftSidebarCollapsed ? 'pointer-events-none opacity-0' : 'opacity-100'"
        @mousedown.prevent="startLeftSidebarResize($event)"
        @touchstart.prevent="startLeftSidebarResize($event)"
        aria-label="Resize sidebar"
        title="Resize sidebar"
    >
        <span
            class="h-24 w-1 rounded-full border transition-weightless"
            x-bind:class="leftSidebarResizing ? 'border-primary-300 bg-primary-100 shadow-[0_0_0_4px_rgba(59,130,246,0.10)]' : 'border-white/90 bg-white/95 shadow-[0_10px_24px_-12px_rgba(30,41,59,0.55)]'"
        ></span>
    </button>

</aside>
