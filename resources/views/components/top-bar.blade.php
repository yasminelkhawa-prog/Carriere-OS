@php
    $authUser = auth()->user();
    $companyOptions = collect();
    $activeCompanyId = session('active_company_id');
    $activeCompanyName = null;
    $activeCompanySlug = null;
    $isCandidateRole = false;

    if ($authUser && ! $authUser->isSuperadmin()) {
        $companyOptions = $authUser->memberships()
            ->where('membership_status', \App\Models\CompanyMembership::STATUS_ACTIVE)
            ->with('company:id,name,status')
            ->get()
            ->filter(fn ($membership) => $membership->company && $membership->company->status === \App\Models\Company::STATUS_ACTIVE);

        if (is_string($activeCompanyId) && $activeCompanyId !== '') {
            $activeCompanyName = \App\Models\Company::query()
                ->whereKey($activeCompanyId)
                ->value('name');
            $activeCompanySlug = \App\Models\Company::query()
                ->whereKey($activeCompanyId)
                ->value('slug');

            $activeRole = $authUser->memberships()
                ->where('company_id', $activeCompanyId)
                ->where('membership_status', \App\Models\CompanyMembership::STATUS_ACTIVE)
                ->value('company_role');
            $isCandidateRole = $activeRole === \App\Models\CompanyMembership::ROLE_CANDIDATE;
        }
    }

    $searchAction = $isCandidateRole
        ? ($activeCompanySlug ? route('candidate.faq', ['company' => $activeCompanySlug]) : route('profile.edit'))
        : route('candidates.index');
    $searchPlaceholder = $isCandidateRole
        ? __('ui.topbar.candidate_search_placeholder')
        : __('ui.topbar.search_placeholder');
    $currentQuery = (string) request('q', '');
@endphp

<header data-app-top-bar class="z-20 px-4 pt-4 sm:px-6 lg:px-8">
    <div class="mx-auto w-full max-w-[1480px] rounded-3xl border border-white/80 bg-white/85 p-3 shadow-[0_22px_44px_-30px_rgba(30,41,59,0.45)] backdrop-blur-2xl">
        <div class="flex flex-wrap items-center gap-2 sm:gap-3">
            <button
                type="button"
                class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-800 transition-weightless hover:bg-slate-50"
                @click="toggleLeftSidebar()"
            >
                <svg class="size-4 text-slate-700" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                    <path d="M3 5h14M3 10h14M3 15h14" stroke="currentColor" stroke-linecap="round" />
                </svg>
                <span class="hidden sm:inline">{{ __('ui.topbar.left_toggle') }}</span>
            </button>

            <form method="GET" action="{{ $searchAction }}" class="relative min-w-[200px] flex-1">
                @if(auth()->user()?->isSuperadmin() && request('company_id'))
                    <input type="hidden" name="company_id" value="{{ request('company_id') }}">
                @endif
                <input
                    type="search"
                    name="q"
                    value="{{ $currentQuery }}"
                    placeholder="{{ $searchPlaceholder }}"
                    class="w-full rounded-xl border border-slate-200 bg-white/90 py-2.5 pl-10 pr-4 text-sm text-slate-700 focus:border-primary-400 focus:ring-primary-300"
                >
                <svg class="pointer-events-none absolute left-3 top-3 size-4 text-slate-400" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                    <path d="M9 15a6 6 0 1 1 0-12 6 6 0 0 1 0 12Zm0 0 6 2" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </form>

            <div class="hidden lg:flex items-center gap-2">
                <button 
                    type="button" 
                    @click="toggleRightSidebar()" 
                    class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50 hover:text-slate-900 focus:outline-none"
                    :class="rightSidebarOpen && rightSidebarEnabled ? 'ring-2 ring-primary-500 border-primary-500' : ''"
                >
                    {{ now()->format('D, M j') }}
                </button>
            </div>

            @auth
                @if(!$isCandidateRole && !$authUser?->isSuperadmin())
                    @can('access-admin-pages')
                        <div class="relative" x-data="{ settingsOpen: false }" @click.away="settingsOpen = false">
                            <button
                                type="button"
                                class="inline-flex items-center justify-center rounded-xl border px-2.5 py-2 transition-weightless"
                                :class="settingsOpen ? 'border-[#004d3d]/30 bg-[#004d3d]/5 text-[#004d3d]' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50 hover:text-slate-800'"
                                @click="settingsOpen = !settingsOpen"
                                aria-label="Settings"
                            >
                                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.43l-1.003.828c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.43l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                </svg>
                            </button>

                            <div
                                x-show="settingsOpen"
                                x-transition:enter="transition ease-out duration-150"
                                x-transition:enter-start="opacity-0 scale-95 -translate-y-1"
                                x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                                x-transition:leave="transition ease-in duration-100"
                                x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                                x-transition:leave-end="opacity-0 scale-95 -translate-y-1"
                                x-cloak
                                class="absolute right-0 top-full mt-2 w-56 origin-top-right rounded-2xl border border-slate-200/80 bg-white/95 p-1.5 shadow-[0_20px_50px_-20px_rgba(30,41,59,0.35)] backdrop-blur-xl z-50"
                            >
                                <p class="px-3 py-1.5 text-[10px] font-bold uppercase tracking-wider text-slate-400">Paramètres</p>

                                @can('viewAny', \App\Models\User::class)
                                    <a href="{{ route('admin.users.index') }}" class="flex items-center gap-2.5 rounded-xl px-3 py-2 text-sm font-medium transition-all {{ request()->routeIs('admin.users.*') ? 'bg-[#004d3d]/5 text-[#004d3d]' : 'text-slate-700 hover:bg-slate-50 hover:text-slate-900' }}" @click="settingsOpen = false">
                                        <svg class="size-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" /></svg>
                                        {{ __('ui.nav.user_management') }}
                                    </a>
                                @endcan

                                <a href="{{ route('admin.email-templates.index') }}" class="flex items-center gap-2.5 rounded-xl px-3 py-2 text-sm font-medium transition-all {{ request()->routeIs('admin.email-templates.*') ? 'bg-[#004d3d]/5 text-[#004d3d]' : 'text-slate-700 hover:bg-slate-50 hover:text-slate-900' }}" @click="settingsOpen = false">
                                    <svg class="size-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" /></svg>
                                    {{ __('ui.nav.communication_engine') }}
                                </a>



                                <div class="my-1 border-t border-slate-100"></div>

                                <a href="{{ route('admin.departments.index') }}" class="flex items-center gap-2.5 rounded-xl px-3 py-2 text-sm font-medium transition-all {{ request()->routeIs('admin.departments.*') ? 'bg-[#004d3d]/5 text-[#004d3d]' : 'text-slate-700 hover:bg-slate-50 hover:text-slate-900' }}" @click="settingsOpen = false">
                                    <svg class="size-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" /></svg>
                                    {{ __('ui.nav.departments') }}
                                </a>

                                <a href="{{ route('admin.values.index') }}" class="flex items-center gap-2.5 rounded-xl px-3 py-2 text-sm font-medium transition-all {{ request()->routeIs('admin.values.*') ? 'bg-[#004d3d]/5 text-[#004d3d]' : 'text-slate-700 hover:bg-slate-50 hover:text-slate-900' }}" @click="settingsOpen = false">
                                    <svg class="size-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12Z" /></svg>
                                    {{ __('ui.nav.company_values') }}
                                </a>

                                <a href="{{ route('admin.faqs.index') }}" class="flex items-center gap-2.5 rounded-xl px-3 py-2 text-sm font-medium transition-all {{ request()->routeIs('admin.faqs.*') ? 'bg-[#004d3d]/5 text-[#004d3d]' : 'text-slate-700 hover:bg-slate-50 hover:text-slate-900' }}" @click="settingsOpen = false">
                                    <svg class="size-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 5.25h.008v.008H12v-.008Z" /></svg>
                                    {{ __('ui.nav.faqs') }}
                                </a>
                            </div>
                        </div>
                    @endcan
                @endif
            @endauth


        </div>

        <div class="mt-3 flex flex-wrap items-center justify-between gap-2">
            <div class="flex flex-wrap items-center gap-2">
                @auth
                    @if($authUser->isSuperadmin())
                        <a href="{{ route('platform.console') }}" class="rounded-xl border border-aura-300/50 bg-white px-3 py-1.5 text-xs font-semibold text-aura-700 transition-weightless hover:bg-aura-50">{{ __('ui.topbar.platform_console') }}</a>
                    @elseif($companyOptions->count() > 1)
                        <form method="POST" action="{{ route('company.switch') }}" class="min-w-[220px]">
                            @csrf
                            <select name="company_id" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm" onchange="this.form.submit()">
                                @foreach($companyOptions as $membership)
                                    <option value="{{ $membership->company_id }}" @selected((string) session('active_company_id') === (string) $membership->company_id)>
                                        {{ $membership->company->name }}
                                    </option>
                                @endforeach
                            </select>
                        </form>
                    @endif


                @endauth
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('locale.switch', ['locale' => 'en']) }}" class="rounded-lg border border-aura-200/50 bg-white px-3 py-1.5 text-xs font-medium text-slate-800 transition-weightless hover:bg-slate-50">{{ __('ui.locale.en') }}</a>
                <a href="{{ route('locale.switch', ['locale' => 'fr']) }}" class="rounded-lg border border-aura-200/50 bg-white px-3 py-1.5 text-xs font-medium text-slate-800 transition-weightless hover:bg-slate-50">{{ __('ui.locale.fr') }}</a>
                @guest
                    <a href="{{ route('login') }}" class="rounded-lg border border-aura-200/50 bg-white px-3 py-1.5 text-xs font-medium text-slate-800 transition-weightless hover:bg-slate-50">{{ __('auth.login_title') }}</a>
                @else
                    <span class="text-xs font-medium text-slate-700 sm:text-sm">{{ $authUser->profile?->full_name ?: $authUser->email }}</span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="rounded-lg border border-aura-200/50 bg-white px-3 py-1.5 text-xs font-medium text-slate-800 transition-weightless hover:bg-slate-50">{{ __('auth.logout') }}</button>
                    </form>
                @endguest
            </div>
        </div>
    </div>
</header>
