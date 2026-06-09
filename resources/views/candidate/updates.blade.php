<x-shell-layout :title="__('ui.nav.candidate_updates').' | '.config('app.name')">
    <div class="space-y-6">
        <x-glass-card :title="__('candidate_portal.notifications.title')" :subtitle="__('candidate_portal.notifications.subtitle')">
            @if(($portalNotifications ?? collect())->isEmpty())
                <x-empty-state :title="__('candidate_portal.notifications.empty_title')" :message="__('candidate_portal.notifications.empty_message')" />
            @else
                <div class="space-y-3">
                    @foreach($portalNotifications as $notification)
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

        <div class="grid gap-6 xl:grid-cols-2">
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
                <a href="{{ route('candidate.social-hub.index', ['company' => $company->slug]) }}" class="mt-4 inline-flex rounded-xl bg-danger-600 px-4 py-2 text-sm font-semibold text-white transition-weightless hover:bg-danger-700">{{ __('candidate_portal.social_hub.open') }}</a>
            </x-glass-card>

            <x-glass-card :title="__('candidate_portal.faq.title')" :subtitle="__('candidate_portal.faq.subtitle')">
                <div class="space-y-3">
                    @forelse(($faqs ?? collect()) as $faq)
                        <article class="rounded-xl border border-slate-200/80 bg-white/75 p-4">
                            <p class="text-xs uppercase tracking-[0.18em] text-aura-700/85">{{ $faq->category }}</p>
                            <h3 class="mt-1 text-sm font-semibold text-slate-900">{{ $faq->question }}</h3>
                            <p class="mt-2 text-sm text-slate-700">{{ \Illuminate\Support\Str::limit((string) $faq->answer, 140) }}</p>
                        </article>
                    @empty
                        <x-empty-state :title="__('candidate_portal.faq.empty_title')" :message="__('candidate_portal.faq.empty_message')" />
                    @endforelse
                </div>
                <div class="mt-4 flex flex-wrap gap-3">
                    <a href="{{ route('candidate.faq', ['company' => $company->slug]) }}" class="inline-flex rounded-xl border border-aura-300/50 bg-white px-4 py-2 text-sm font-semibold text-slate-800 transition-weightless hover:bg-white">{{ __('candidate_portal.faq.open_faq_page') }}</a>
                    <a href="{{ route('candidate.applications', ['company' => $company->slug]) }}" class="inline-flex rounded-xl border border-success-300/50 bg-success-50 px-4 py-2 text-sm font-semibold text-success-800 transition-weightless hover:bg-success-100/80">{{ __('ui.nav.candidate_applications') }}</a>
                </div>
            </x-glass-card>
        </div>

        @include('candidate.partials.guide-bot', ['company' => $company])
    </div>
</x-shell-layout>
