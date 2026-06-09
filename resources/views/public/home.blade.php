@extends('layouts.public')

@section('title', __('public_site.home.meta_title').' | '.config('app.name'))

@section('content')
    <section class="relative overflow-hidden rounded-3xl border border-white/70 bg-white/80 px-6 py-10 shadow-[0_30px_80px_-50px_rgba(99,72,255,0.55)] sm:px-10">
        <div class="pointer-events-none absolute -left-16 -top-24 h-56 w-56 rounded-full bg-aura-400/25 blur-3xl"></div>
        <div class="pointer-events-none absolute -right-20 bottom-0 h-56 w-56 rounded-full bg-success-400/20 blur-3xl"></div>
        <div class="relative max-w-3xl">
            <p class="text-xs uppercase tracking-[0.26em] text-aura-700/85">{{ __('public_site.home.eyebrow') }}</p>
            <p class="mt-2 text-xs font-medium uppercase tracking-[0.22em] text-slate-500">{{ __('ui.brand.developed_by') }}</p>
            <h1 class="mt-4 text-4xl font-semibold tracking-tight text-slate-900 sm:text-5xl">{{ __('public_site.home.title') }}</h1>
            <p class="mt-4 text-base leading-relaxed text-slate-700">{{ __('public_site.home.subtitle') }}</p>

            <div class="mt-7 flex flex-wrap gap-3">
                <a href="{{ route('public.jobs.index') }}" class="rounded-xl bg-success-600 px-5 py-2.5 text-sm font-semibold text-white transition-weightless hover:bg-success-700">
                    {{ __('public_site.home.cta_jobs') }}
                </a>
                <a href="{{ route('public.contact') }}" class="rounded-xl border border-aura-300/50 bg-white px-5 py-2.5 text-sm font-semibold text-aura-900 transition-weightless hover:bg-aura-50">
                    {{ __('public_site.home.cta_contact') }}
                </a>
            </div>

            <div class="mt-8 rounded-2xl border border-white/75 bg-white/80 p-4">
                <p class="text-xs uppercase tracking-[0.2em] text-aura-700/85">{{ __('public_site.home.entry_title') }}</p>
                <p class="mt-1 text-sm text-slate-600">{{ __('public_site.home.entry_subtitle') }}</p>
                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    <a href="{{ route('public.entry.company') }}" class="rounded-xl border border-aura-300/60 bg-aura-50/70 px-4 py-3 transition-weightless hover:bg-aura-100/80">
                        <p class="text-sm font-semibold text-aura-900">{{ __('public_site.nav.entry_company') }}</p>
                        <p class="mt-1 text-xs text-slate-700">{{ __('public_site.home.entry_company_description') }}</p>
                    </a>
                    <a href="{{ route('public.entry.candidate') }}" class="rounded-xl border border-success-300/70 bg-success-50/80 px-4 py-3 transition-weightless hover:bg-success-100/80">
                        <p class="text-sm font-semibold text-success-900">{{ __('public_site.nav.entry_candidate') }}</p>
                        <p class="mt-1 text-xs text-slate-700">{{ __('public_site.home.entry_candidate_description') }}</p>
                    </a>
                </div>
            </div>
        </div>

        <div class="relative mt-8 grid gap-3 sm:grid-cols-3">
            <div class="rounded-2xl border border-white/70 bg-white/75 p-4">
                <p class="text-xs uppercase tracking-wider text-slate-500">{{ __('public_site.home.metrics.open_roles') }}</p>
                <p class="mt-2 text-3xl font-semibold text-slate-900">{{ number_format($totalOpenJobs) }}</p>
            </div>
            <div class="rounded-2xl border border-white/70 bg-white/75 p-4">
                <p class="text-xs uppercase tracking-wider text-slate-500">{{ __('public_site.home.metrics.hiring_companies') }}</p>
                <p class="mt-2 text-3xl font-semibold text-slate-900">{{ number_format($activeHiringCompanies) }}</p>
            </div>
            <div class="rounded-2xl border border-white/70 bg-white/75 p-4">
                <p class="text-xs uppercase tracking-wider text-slate-500">{{ __('public_site.home.metrics.featured_company') }}</p>
                <p class="mt-2 text-2xl font-semibold text-slate-900">{{ $featuredCompany?->name ?? __('public_site.common.not_available') }}</p>
            </div>
        </div>
    </section>

    <section class="mt-8">
        <div class="mb-4 flex items-end justify-between gap-4">
            <div>
                <h2 class="text-2xl font-semibold text-slate-900">{{ __('public_site.home.latest_jobs_title') }}</h2>
                <p class="mt-1 text-sm text-slate-600">{{ __('public_site.home.latest_jobs_subtitle') }}</p>
            </div>
            <a href="{{ route('public.jobs.index') }}" class="rounded-xl border border-aura-300/50 bg-white px-4 py-2 text-sm font-semibold text-aura-900 transition-weightless hover:bg-aura-50">
                {{ __('public_site.home.view_all_jobs') }}
            </a>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @forelse($latestJobs as $job)
                <article class="rounded-2xl border border-white/70 bg-white/75 p-5 shadow-[0_18px_45px_-38px_rgba(99,72,255,0.45)]">
                    <p class="text-xs uppercase tracking-[0.2em] text-aura-700/80">{{ $job->company?->name ?? __('public_site.common.not_available') }}</p>
                    <h3 class="mt-2 text-lg font-semibold text-slate-900">{{ $job->title }}</h3>
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
        </div>
    </section>

    <section class="mt-8 rounded-3xl border border-white/70 bg-white/80 p-6 shadow-sm">
        <div class="mb-4">
            <h2 class="text-2xl font-semibold text-slate-900">{{ __('public_site.home.values_title') }}</h2>
            <p class="mt-1 text-sm text-slate-600">
                {{ __('public_site.home.values_subtitle', ['company' => $featuredCompany?->name ?? __('public_site.common.not_available')]) }}
            </p>
        </div>

        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
            @forelse($values as $value)
                <article class="rounded-2xl border border-aura-200/50 bg-aura-50/40 p-4">
                    <p class="text-sm font-semibold text-slate-900">{{ $value->title }}</p>
                    <p class="mt-1 text-sm text-slate-700">{{ $value->description ?: __('public_site.common.not_available') }}</p>
                </article>
            @empty
                <div class="md:col-span-2 xl:col-span-4">
                    <x-empty-state :title="__('public_site.home.values_empty_title')" :message="__('public_site.home.values_empty_message')" />
                </div>
            @endforelse
        </div>
    </section>
@endsection
