@extends('layouts.public')

@section('title', __('public_site.jobs.meta_title').' | '.config('app.name'))

@section('content')
    <section class="rounded-3xl border border-white/70 bg-white/80 p-6 shadow-sm">
        <div class="mb-4">
            <h1 class="text-3xl font-semibold tracking-tight text-slate-900">{{ __('public_site.jobs.title') }}</h1>
            <p class="mt-1 text-sm text-slate-600">{{ __('public_site.jobs.subtitle') }}</p>
        </div>

        <form method="GET" action="{{ route('public.jobs.index') }}" class="grid gap-3 lg:grid-cols-4">
            <x-form-field :label="__('public_site.jobs.filters.search')" name="q">
                <input
                    type="text"
                    name="q"
                    value="{{ $filters['q'] ?? '' }}"
                    class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-sm text-slate-900"
                    autocomplete="off"
                >
            </x-form-field>

            <x-form-field :label="__('public_site.jobs.filters.company')" name="company_id">
                <select name="company_id" data-placeholder="{{ __('public_site.jobs.filters.company_placeholder') }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-sm">
                    <option value="">{{ __('public_site.jobs.filters.company_placeholder') }}</option>
                    @foreach($companies as $company)
                        <option value="{{ $company->id }}" @selected((string) ($filters['company_id'] ?? '') === (string) $company->id)>{{ $company->name }}</option>
                    @endforeach
                </select>
            </x-form-field>

            <x-form-field :label="__('public_site.jobs.filters.location')" name="location">
                <select name="location" data-placeholder="{{ __('public_site.jobs.filters.location_placeholder') }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-sm">
                    <option value="">{{ __('public_site.jobs.filters.location_placeholder') }}</option>
                    @foreach($locations as $locationOption)
                        <option value="{{ $locationOption }}" @selected((string) ($filters['location'] ?? '') === (string) $locationOption)>{{ $locationOption }}</option>
                    @endforeach
                </select>
            </x-form-field>

            <div class="flex items-end gap-2">
                <button type="submit" class="rounded-xl bg-success-600 px-4 py-2.5 text-sm font-semibold text-white transition-weightless hover:bg-success-700">
                    {{ __('public_site.jobs.filters.apply') }}
                </button>
                <a href="{{ route('public.jobs.index') }}" class="rounded-xl border border-aura-300/40 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition-weightless hover:bg-slate-50">
                    {{ __('public_site.jobs.filters.reset') }}
                </a>
            </div>
        </form>
    </section>

    <section class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse($jobs as $job)
            <article class="rounded-2xl border border-white/70 bg-white/80 p-5 shadow-[0_18px_45px_-38px_rgba(99,72,255,0.45)]">
                <p class="text-xs uppercase tracking-[0.2em] text-aura-700/80">{{ $job->company?->name ?? __('public_site.common.not_available') }}</p>
                <h2 class="mt-2 text-xl font-semibold text-slate-900">{{ $job->title }}</h2>
                <p class="mt-1 text-sm text-slate-600">{{ $job->location ?: __('public_site.jobs.location_tbd') }}</p>
                <p class="mt-2">
                    <span class="inline-flex rounded-full border border-success-200/70 bg-success-100/70 px-2.5 py-1 text-[11px] font-semibold text-success-800">
                        {{ $job->department?->name ?? __('public_site.jobs.general_department') }}
                    </span>
                </p>
                <a href="{{ route('public.jobs.show', ['job' => $job->id]) }}" class="mt-4 inline-flex rounded-xl bg-success-600 px-4 py-2 text-sm font-semibold text-white transition-weightless hover:bg-success-700">
                    {{ __('public_site.jobs.view_details') }}
                </a>
            </article>
        @empty
            <div class="md:col-span-2 xl:col-span-3">
                <x-empty-state :title="__('public_site.jobs.empty_title')" :message="__('public_site.jobs.empty_message')" />
            </div>
        @endforelse
    </section>

    @if($jobs->hasPages())
        <div class="mt-6">
            {{ $jobs->links() }}
        </div>
    @endif
@endsection
