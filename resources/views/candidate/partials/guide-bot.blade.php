<div
    id="candidate-guide-bot"
    class="fixed bottom-4 right-4 z-40 w-[22rem] max-w-[calc(100vw-2rem)]"
    data-candidate-guide-bot
    data-endpoint="{{ route('candidate.guide.ask', ['company' => $company->slug]) }}"
    data-disabled="false"
>
    <button
        type="button"
        data-guide-toggle
        class="ml-auto inline-flex rounded-xl border border-aura-300/50 bg-white/90 px-4 py-2 text-sm font-semibold text-aura-800 shadow-[0_16px_40px_-24px_rgba(100,103,242,0.7)] transition-weightless hover:bg-white"
    >
        {{ __('candidate_portal.guider.title') }}
    </button>

    <div data-guide-panel class="mt-2 hidden rounded-2xl border border-white/80 bg-white/95 p-3 shadow-[0_22px_55px_-35px_rgba(15,23,42,0.35)] backdrop-blur-2xl">
        <p class="text-xs text-slate-600">{{ __('candidate_portal.guider.hint') }}</p>
        <p class="mt-1 text-[11px] text-slate-500">{{ __('candidate_portal.guider.privacy_note') }}</p>

        <div data-guide-messages class="mt-3 max-h-56 space-y-2 overflow-y-auto rounded-xl border border-slate-200/80 bg-slate-50/70 p-2">
            <p class="rounded-lg border border-aura-200/60 bg-aura-50/80 px-2 py-1.5 text-xs text-slate-700">
                {{ __('candidate_portal.guider.welcome') }}
            </p>
        </div>

        <form data-guide-form class="mt-3 space-y-2">
            <textarea
                name="message"
                rows="3"
                maxlength="600"
                class="w-full rounded-xl border border-aura-200/50 bg-white/90 px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-aura-400 focus:ring-aura-300"
                placeholder="{{ __('candidate_portal.guider.placeholder') }}"
                required
            ></textarea>
            <button
                type="submit"
                class="w-full rounded-xl bg-success-600 px-3 py-2 text-sm font-semibold text-white transition-weightless hover:bg-success-700"
            >
                {{ __('candidate_portal.guider.send_action') }}
            </button>
        </form>
    </div>
</div>
