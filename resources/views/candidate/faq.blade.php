<x-shell-layout :title="__('candidate_portal.faq.title').' | '.config('app.name')">
    <div class="space-y-6">
        @if(session('status'))
            <x-toast-alert type="success">{{ session('status') }}</x-toast-alert>
        @endif
        @if(session('error'))
            <x-toast-alert type="warning">{{ session('error') }}</x-toast-alert>
        @endif

        <x-glass-card :title="__('candidate_portal.faq.title')" :subtitle="__('candidate_portal.faq.subtitle')">
            <form method="GET" action="{{ route('candidate.faq', ['company' => $company->slug]) }}" class="grid gap-3 md:grid-cols-3">
                <x-form-field :label="__('candidate_portal.faq.category_label')" name="category">
                    <select name="category" data-placeholder="{{ __('candidate_portal.faq.category_placeholder') }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-sm text-slate-900 shadow-sm">
                        <option value="">{{ __('candidate_portal.faq.category_placeholder') }}</option>
                        @foreach($categories as $category)
                            <option value="{{ $category }}" @selected($selectedCategory === (string) $category)>{{ $category }}</option>
                        @endforeach
                    </select>
                </x-form-field>

                <x-form-field :label="__('candidate_portal.faq.search_label')" name="q">
                    <input type="text" name="q" value="{{ $searchTerm }}" placeholder="{{ __('candidate_portal.faq.search_placeholder') }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-sm text-slate-900 shadow-sm">
                </x-form-field>

                <div class="flex items-end gap-2">
                    <button type="submit" class="rounded-xl bg-success-600 px-4 py-2.5 text-sm font-semibold text-white transition-weightless hover:bg-success-700">
                        {{ __('candidate_portal.faq.apply_filters') }}
                    </button>
                    <a href="{{ route('candidate.faq', ['company' => $company->slug]) }}" class="rounded-xl border border-aura-300/50 bg-white px-4 py-2.5 text-sm font-medium text-slate-800 transition-weightless hover:bg-white">
                        {{ __('candidate_portal.faq.reset_filters') }}
                    </a>
                </div>
            </form>
        </x-glass-card>

        <x-glass-card>
            <div class="flex items-center justify-between gap-3">
                <p class="text-sm text-slate-700">{{ __('candidate_portal.faq.published_only_hint') }}</p>
                <a href="{{ route('candidate.portal', ['company' => $company->slug]) }}" class="rounded-lg border border-aura-300/50 bg-white px-3 py-1.5 text-xs font-medium text-slate-800 transition-weightless hover:bg-white">
                    {{ __('candidate_portal.faq.back_to_portal') }}
                </a>
            </div>

            <div class="mt-4 space-y-3">
                @forelse($faqs as $faq)
                    <article class="rounded-2xl border border-white/80 bg-white/70 p-4">
                        <p class="text-xs uppercase tracking-[0.2em] text-aura-700/85">{{ $faq->category }}</p>
                        <h3 class="mt-1 text-sm font-semibold text-slate-900">{{ $faq->question }}</h3>
                        <p class="mt-2 text-sm text-slate-700">{{ $faq->answer }}</p>
                    </article>
                @empty
                    <x-empty-state :title="__('candidate_portal.faq.empty_title')" :message="__('candidate_portal.faq.empty_message')" />
                @endforelse
            </div>

            @if($faqs instanceof \Illuminate\Contracts\Pagination\Paginator)
                <div class="mt-5">{{ $faqs->links() }}</div>
            @endif
        </x-glass-card>
    </div>

    @include('candidate.partials.guide-bot', ['company' => $company])
</x-shell-layout>
