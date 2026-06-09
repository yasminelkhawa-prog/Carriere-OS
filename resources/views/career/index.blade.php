<x-career-layout :title="$company->name.' | '.__('career.pages.jobs_title')" :company="$company">
    <x-glass-card :title="__('career.list.title')" :subtitle="__('career.list.subtitle')">
        <form method="GET" action="{{ route('career.index', ['company' => $company]) }}" class="grid gap-3 md:grid-cols-3">
            <x-form-field :label="__('career.filters.department')" name="department_id">
                <select name="department_id" data-placeholder="{{ __('career.filters.department_placeholder') }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-slate-900 shadow-sm">
                    <option value="">{{ __('career.filters.department_placeholder') }}</option>
                    @foreach($departments as $department)
                        <option value="{{ $department->id }}" @selected((string) $selectedDepartmentId === (string) $department->id)>{{ $department->name }}</option>
                    @endforeach
                </select>
            </x-form-field>

            <x-form-field :label="__('career.filters.location')" name="location">
                <select name="location" data-placeholder="{{ __('career.filters.location_placeholder') }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-slate-900 shadow-sm">
                    <option value="">{{ __('career.filters.location_placeholder') }}</option>
                    @foreach($locations as $location)
                        <option value="{{ $location }}" @selected((string) $selectedLocation === (string) $location)>{{ $location }}</option>
                    @endforeach
                </select>
            </x-form-field>

            <div class="flex items-end gap-2">
                <button type="submit" class="rounded-xl bg-success-600 px-4 py-2 text-sm font-semibold text-white transition-weightless hover:bg-success-700">
                    {{ __('career.filters.apply') }}
                </button>
                <a href="{{ route('career.index', ['company' => $company]) }}" class="rounded-xl border border-aura-200/50 bg-white/70 px-4 py-2 text-sm font-medium text-slate-700 transition-weightless hover:bg-white">
                    {{ __('career.filters.reset') }}
                </a>
            </div>
        </form>
    </x-glass-card>

    <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse($jobs as $job)
            @php
                $alreadyApplied = in_array((string) $job->id, $appliedJobIds ?? [], true);
            @endphp
            <div class="rounded-2xl border border-white/70 bg-white/65 p-5 shadow-[0_22px_55px_-40px_rgba(100,103,242,0.65)] backdrop-blur-2xl transition-weightless hover:-translate-y-0.5 hover:bg-white/75">
                <p>
                    <span class="inline-flex rounded-full border border-success-200/70 bg-success-100/70 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.14em] text-success-800">{{ $job->department?->name ?? __('career.list.no_department') }}</span>
                </p>
                <h3 class="mt-2 text-lg font-semibold text-slate-900">{{ $job->title }}</h3>
                <p class="mt-1 text-sm text-slate-600">{{ $job->location ?: __('career.list.location_tbd') }}</p>
                @if($alreadyApplied)
                    <span class="mt-4 inline-flex cursor-not-allowed rounded-xl border border-slate-300 bg-slate-100 px-4 py-2 text-sm font-semibold text-slate-500 opacity-80" aria-disabled="true">
                        {{ __('career.list.already_applied') }}
                    </span>
                @else
                    <a href="{{ route('career.show', ['company' => $company, 'job' => $job]) }}" class="mt-4 inline-flex rounded-xl bg-success-600 px-4 py-2 text-sm font-semibold text-white transition-weightless hover:bg-success-700">
                        {{ __('career.list.view_job') }}
                    </a>
                @endif
            </div>
        @empty
            <div class="md:col-span-2 xl:col-span-3">
                <x-empty-state :title="__('career.list.empty_title')" :message="__('career.list.empty_message')" />
            </div>
        @endforelse
    </div>

    @if($jobs->hasPages())
        <div class="mt-6">
            {{ $jobs->links() }}
        </div>
    @endif
</x-career-layout>
