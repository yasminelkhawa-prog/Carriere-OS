<x-shell-layout :title="__('sjt.admin.title').' | '.config('app.name')">
    <x-glass-card :title="__('sjt.admin.title')" :subtitle="__('sjt.admin.subtitle')">
        @if (session('status'))
            <x-toast-alert type="success">{{ session('status') }}</x-toast-alert>
        @endif

        <form method="GET" action="{{ route('admin.sjt-scenarios.index') }}" class="mt-4 grid gap-3 md:grid-cols-4">
            @if(auth()->user()?->isSuperadmin())
                <x-form-field :label="__('master.company_filter.label')" name="company_id">
                    <select name="company_id" data-placeholder="{{ __('master.company_filter.placeholder') }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-slate-900 shadow-sm">
                        <option value="">{{ __('master.company_filter.placeholder') }}</option>
                        @foreach($companies as $company)
                            <option value="{{ $company->id }}" @selected((string) $selectedCompanyId === (string) $company->id)>{{ $company->name }}</option>
                        @endforeach
                    </select>
                </x-form-field>
            @endif
            <x-form-field :label="__('sjt.admin.fields.job')" name="job_id">
                <select name="job_id" data-placeholder="{{ __('sjt.admin.filters.all_jobs') }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-slate-900 shadow-sm">
                    <option value="">{{ __('sjt.admin.filters.all_jobs') }}</option>
                    @foreach($jobs as $job)
                        <option value="{{ $job->id }}" @selected((string) $selectedJobId === (string) $job->id)>{{ $job->title }}</option>
                    @endforeach
                </select>
            </x-form-field>
            <x-form-field :label="__('sjt.admin.fields.status')" name="is_active">
                <select name="is_active" data-placeholder="{{ __('sjt.admin.filters.status_placeholder') }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-slate-900 shadow-sm">
                    <option value="all" @selected($selectedActiveFilter === 'all')>{{ __('sjt.admin.filters.status_all') }}</option>
                    <option value="active" @selected($selectedActiveFilter === 'active')>{{ __('sjt.admin.filters.status_active') }}</option>
                    <option value="inactive" @selected($selectedActiveFilter === 'inactive')>{{ __('sjt.admin.filters.status_inactive') }}</option>
                </select>
            </x-form-field>
            <x-form-field :label="__('sjt.admin.fields.search')" name="q">
                <input type="text" name="q" value="{{ $searchTerm }}" placeholder="{{ __('sjt.admin.filters.search_placeholder') }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-slate-900 shadow-sm">
            </x-form-field>
            <div class="md:col-span-4">
                <button type="submit" class="rounded-xl border border-aura-300/50 bg-white px-4 py-2 text-sm font-medium text-slate-900 transition-weightless hover:bg-white">
                    {{ __('sjt.admin.filters.apply') }}
                </button>
            </div>
        </form>

        @if($requiresCompanySelection)
            <div class="mt-4">
                <x-empty-state :title="__('master.company_filter.label')" :message="__('master.common.company_scope_required')" />
            </div>
        @else
            <form method="POST" action="{{ route('admin.sjt-scenarios.store') }}" class="mt-5 grid gap-3">
                @csrf
                @if(auth()->user()?->isSuperadmin())
                    <input type="hidden" name="company_id" value="{{ $selectedCompanyId }}">
                @endif
                <div class="grid gap-3 md:grid-cols-2">
                    <x-form-field :label="__('sjt.admin.fields.title')" name="title">
                        <input type="text" name="title" value="{{ old('title') }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-slate-900 shadow-sm">
                    </x-form-field>
                    <x-form-field :label="__('sjt.admin.fields.job')" name="job_id">
                        <select name="job_id" data-placeholder="{{ __('sjt.admin.filters.global_scenario') }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-slate-900 shadow-sm">
                            <option value="">{{ __('sjt.admin.filters.global_scenario') }}</option>
                            @foreach($jobs as $job)
                                <option value="{{ $job->id }}" @selected((string) old('job_id') === (string) $job->id)>{{ $job->title }}</option>
                            @endforeach
                        </select>
                    </x-form-field>
                </div>
                <x-form-field :label="__('sjt.admin.fields.media_url')" name="scenario_media_url">
                    <input type="url" name="scenario_media_url" value="{{ old('scenario_media_url') }}" placeholder="https://..." class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-slate-900 shadow-sm">
                </x-form-field>
                <x-form-field :label="__('sjt.admin.fields.scenario_text')" name="scenario_text">
                    <textarea name="scenario_text" rows="4" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-slate-900 shadow-sm">{{ old('scenario_text') }}</textarea>
                </x-form-field>
                <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" name="is_active" value="1" class="rounded border-aura-300 text-aura-600 focus:ring-aura-400" @checked(old('is_active', true))>
                    <span>{{ __('sjt.admin.fields.is_active') }}</span>
                </label>
                <div>
                    <button type="submit" class="rounded-xl bg-success-600 px-4 py-2 text-sm font-medium text-white transition-weightless hover:bg-success-700">
                        {{ __('sjt.admin.actions.create') }}
                    </button>
                </div>
            </form>

            <div class="mt-6 space-y-3">
                @forelse($scenarios as $scenario)
                    <div class="rounded-xl border border-white/80 bg-white/70 p-4">
                        <form method="POST" action="{{ route('admin.sjt-scenarios.update', ['sjtScenario' => $scenario->id]) }}" class="space-y-3">
                            @csrf
                            @method('PATCH')
                            @if(auth()->user()?->isSuperadmin())
                                <input type="hidden" name="company_id" value="{{ $selectedCompanyId }}">
                            @endif
                            <div class="grid gap-3 md:grid-cols-2">
                                <input type="text" name="title" value="{{ old('title', $scenario->title) }}" class="rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2 text-sm text-slate-900 shadow-sm">
                                <select name="job_id" data-placeholder="{{ __('sjt.admin.filters.global_scenario') }}" class="rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2 text-sm text-slate-900 shadow-sm">
                                    <option value="">{{ __('sjt.admin.filters.global_scenario') }}</option>
                                    @foreach($jobs as $job)
                                        <option value="{{ $job->id }}" @selected((string) old('job_id', $scenario->job_id) === (string) $job->id)>{{ $job->title }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <input type="url" name="scenario_media_url" value="{{ old('scenario_media_url', $scenario->scenario_media_url) }}" placeholder="https://..." class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2 text-sm text-slate-900 shadow-sm">
                            <textarea name="scenario_text" rows="4" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2 text-sm text-slate-900 shadow-sm">{{ old('scenario_text', $scenario->scenario_text) }}</textarea>
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div class="flex flex-wrap items-center gap-3 text-xs text-slate-600">
                                    <span>{{ __('sjt.admin.labels.responses', ['count' => (int) $scenario->responses_count]) }}</span>
                                    <span>{{ $scenario->job?->title ?? __('sjt.admin.filters.global_scenario') }}</span>
                                </div>
                                <label class="inline-flex items-center gap-2 text-xs text-slate-700">
                                    <input type="checkbox" name="is_active" value="1" class="rounded border-aura-300 text-aura-600 focus:ring-aura-400" @checked($scenario->is_active)>
                                    <span>{{ __('sjt.admin.fields.is_active') }}</span>
                                </label>
                            </div>
                            <div class="flex items-center gap-2">
                                <button type="submit" class="rounded-xl bg-success-600 px-3 py-2 text-sm text-white transition-weightless hover:bg-success-700">
                                    {{ __('sjt.admin.actions.update') }}
                                </button>
                            </div>
                        </form>
                        <form method="POST" action="{{ route('admin.sjt-scenarios.destroy', ['sjtScenario' => $scenario->id]) }}" class="mt-2">
                            @csrf
                            @method('DELETE')
                            @if(auth()->user()?->isSuperadmin())
                                <input type="hidden" name="company_id" value="{{ $selectedCompanyId }}">
                            @endif
                            <button type="submit" class="rounded-xl border border-danger-300/60 bg-danger-50 px-3 py-2 text-xs text-danger-800 transition-weightless hover:bg-danger-100/80">
                                {{ __('sjt.admin.actions.delete') }}
                            </button>
                        </form>
                    </div>
                @empty
                    <x-empty-state :title="__('sjt.admin.empty_title')" :message="__('sjt.admin.empty_message')" />
                @endforelse
            </div>

            @if($scenarios instanceof \Illuminate\Contracts\Pagination\Paginator)
                <div class="mt-6">{{ $scenarios->links() }}</div>
            @endif
        @endif
    </x-glass-card>
</x-shell-layout>
