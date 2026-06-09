@extends('layouts.public')

@section('title', $job->title.' | '.config('app.name'))

@section('content')
    @if(! empty($jobPostingSchema ?? null))
        <script type="application/ld+json">{!! json_encode($jobPostingSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
    @endif

    <section class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <article class="rounded-3xl border border-white/70 bg-white/80 p-6 shadow-sm">
            <p class="text-xs uppercase tracking-[0.2em] text-aura-700/80">{{ $company->name }}</p>
            <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-900">{{ $job->title }}</h1>
            <p class="mt-2 text-sm text-slate-700">{{ $job->location ?: __('public_site.jobs.location_tbd') }}</p>
            <p class="mt-2">
                <span class="inline-flex rounded-full border border-success-200/70 bg-success-100/70 px-2.5 py-1 text-[11px] font-semibold text-success-800">
                    {{ $job->department?->name ?? __('public_site.jobs.general_department') }}
                </span>
            </p>

            @php
                $blocks = $job->descriptionBlocks->sortBy('display_order')->values();
                $fallbackOrderedTypes = ['overview', 'company_intro', 'responsibilities', 'requirements', 'benefits', 'custom'];
                $allBlocksMarkedOverview = $blocks->isNotEmpty() && $blocks->every(fn ($descriptionBlock) => $descriptionBlock->block_type === 'overview');
            @endphp

            <div class="mt-6 space-y-4">
                @forelse($blocks as $index => $block)
                    @php
                        $resolvedType = $allBlocksMarkedOverview
                            ? ($fallbackOrderedTypes[$index] ?? 'custom')
                            : (string) $block->block_type;
                        $typeLabel = __('career.detail.block_types.'.$resolvedType);
                    @endphp
                    <section class="rounded-2xl border border-slate-200 bg-white/70 p-4">
                        <h2 class="text-sm font-semibold uppercase tracking-wider text-aura-700/90">{{ __('career.detail.block_label', ['type' => $typeLabel]) }}</h2>
                        <p class="mt-2 whitespace-pre-line text-sm leading-relaxed text-slate-700">{{ data_get($block->block_content_json, 'text', json_encode($block->block_content_json, JSON_UNESCAPED_UNICODE)) }}</p>
                    </section>
                @empty
                    <x-empty-state :title="__('career.detail.empty_description_title')" :message="__('career.detail.empty_description_message')" />
                @endforelse
            </div>
        </article>

        <aside class="space-y-4">
            <section class="rounded-2xl border border-white/70 bg-white/80 p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-slate-900">{{ __('public_site.jobs.apply_card_title') }}</h2>
                <p class="mt-2 text-sm text-slate-600">{{ __('public_site.jobs.apply_card_subtitle') }}</p>
                <a href="{{ route('career.show', ['company' => $company->slug, 'job' => $job->id]) }}" class="mt-4 inline-flex w-full justify-center rounded-xl bg-success-600 px-4 py-2.5 text-sm font-semibold text-white transition-weightless hover:bg-success-700">
                    {{ __('public_site.jobs.apply_cta') }}
                </a>
            </section>

            <section class="rounded-2xl border border-white/70 bg-white/80 p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-slate-900">{{ __('public_site.jobs.related_title') }}</h2>
                <div class="mt-3 space-y-2">
                    @forelse($relatedJobs as $relatedJob)
                        <a href="{{ route('public.jobs.show', ['job' => $relatedJob->id]) }}" class="block rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-800 transition-weightless hover:border-aura-300 hover:bg-aura-50/50">
                            <p class="font-semibold">{{ $relatedJob->title }}</p>
                            <p class="mt-0.5 text-xs text-slate-500">{{ $relatedJob->location ?: __('public_site.jobs.location_tbd') }}</p>
                        </a>
                    @empty
                        <p class="text-sm text-slate-600">{{ __('public_site.jobs.related_empty') }}</p>
                    @endforelse
                </div>
            </section>
        </aside>
    </section>
@endsection
