<div
    class="fixed bottom-4 right-4 z-50 w-[24rem] max-w-[calc(100vw-2rem)]"
    data-recruiter-assistant-bot
    data-endpoint="{{ route('candidates.assistant.ask', array_filter(['company_id' => $selectedCompanyId])) }}"
    data-application-id="{{ (string) ($selectedApplication?->id ?? '') }}"
    data-processing-error="{{ __('candidates.assistant.fallbacks.processing_error') }}"
    data-network-error="{{ __('candidates.assistant.fallbacks.network_error') }}"
    data-no-answer="{{ __('candidates.assistant.fallbacks.no_answer') }}"
>
    @php
        $assistantApplications = $applications instanceof \Illuminate\Contracts\Pagination\Paginator
            ? collect($applications->items())
            : collect($applications ?? []);
    @endphp

    <button
        type="button"
        data-assistant-toggle
        class="ml-auto inline-flex rounded-xl border border-success-300/60 bg-success-50 px-4 py-2 text-sm font-semibold text-success-900 shadow-[0_16px_40px_-24px_rgba(22,163,74,0.45)] transition-weightless hover:bg-success-100/80"
    >
        {{ __('candidates.assistant.title') }}
    </button>

    <div data-assistant-panel class="mt-2 hidden rounded-2xl border border-white/80 bg-white/95 p-3 shadow-[0_22px_55px_-35px_rgba(15,23,42,0.35)] backdrop-blur-2xl">
        <p class="text-xs text-slate-600">{{ __('candidates.assistant.hint') }}</p>
        <div class="mt-3 space-y-1">
            <label for="recruiter-assistant-application" class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                {{ __('candidates.assistant.application_label') }}
            </label>
            <select
                id="recruiter-assistant-application"
                name="application_id"
                data-assistant-application-select
                data-native-select
                data-placeholder="{{ __('candidates.assistant.application_placeholder') }}"
                class="w-full rounded-xl border border-aura-200/50 bg-white/90 px-3 py-2 text-sm text-slate-900 shadow-sm"
            >
                <option value="">{{ __('candidates.assistant.application_placeholder') }}</option>
                @foreach($assistantApplications as $application)
                    @php
                        $applicationCandidateName = trim((string) optional($application->candidate)->full_name);
                        $applicationJobTitle = trim((string) optional($application->job)->title);
                        $assistantOptionLabel = $applicationCandidateName !== ''
                            ? $applicationCandidateName
                            : __('candidates.detail.masked_identifier_value', [
                                'identifier' => \App\Http\Controllers\CandidateWorkspaceController::maskedCandidateIdentifier((string) $application->id),
                            ]);

                        if ($applicationJobTitle !== '') {
                            $assistantOptionLabel .= ' - '.$applicationJobTitle;
                        }
                    @endphp
                    <option value="{{ $application->id }}" @selected((string) ($selectedApplication?->id ?? '') === (string) $application->id)>
                        {{ $assistantOptionLabel }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="mt-2 flex flex-wrap gap-2">
            <button type="button" data-assistant-prompt class="cursor-pointer rounded-full border border-slate-200 bg-white px-2.5 py-1 text-[11px] text-slate-700 transition-weightless hover:border-success-300 hover:bg-success-50">{{ __('candidates.assistant.prompts.pipeline_overview') }}</button>
            <button type="button" data-assistant-prompt class="cursor-pointer rounded-full border border-slate-200 bg-white px-2.5 py-1 text-[11px] text-slate-700 transition-weightless hover:border-success-300 hover:bg-success-50">{{ __('candidates.assistant.prompts.interviews_today') }}</button>
            <button type="button" data-assistant-prompt class="cursor-pointer rounded-full border border-slate-200 bg-white px-2.5 py-1 text-[11px] text-slate-700 transition-weightless hover:border-success-300 hover:bg-success-50">{{ __('candidates.assistant.prompts.under_analysis') }}</button>
            <button type="button" data-assistant-prompt class="cursor-pointer rounded-full border border-slate-200 bg-white px-2.5 py-1 text-[11px] text-slate-700 transition-weightless hover:border-success-300 hover:bg-success-50">{{ __('candidates.assistant.prompts.pending_feedback') }}</button>
            <button type="button" data-assistant-prompt class="cursor-pointer rounded-full border border-slate-200 bg-white px-2.5 py-1 text-[11px] text-slate-700 transition-weightless hover:border-success-300 hover:bg-success-50">{{ __('candidates.assistant.prompts.offer_blockers') }}</button>
            <button type="button" data-assistant-prompt class="cursor-pointer rounded-full border border-slate-200 bg-white px-2.5 py-1 text-[11px] text-slate-700 transition-weightless hover:border-success-300 hover:bg-success-50">{{ __('candidates.assistant.prompts.stalled_candidatures') }}</button>
        </div>

        <div data-assistant-messages class="mt-3 max-h-56 space-y-2 overflow-y-auto rounded-xl border border-slate-200/80 bg-slate-50/70 p-2">
            <p class="rounded-lg border border-success-200/60 bg-success-50/70 px-2 py-1.5 text-xs text-slate-700">
                {{ __('candidates.assistant.hint') }}
            </p>
        </div>

        <form data-assistant-form class="mt-3 space-y-2">
            <textarea
                name="message"
                rows="3"
                maxlength="600"
                class="w-full rounded-xl border border-aura-200/50 bg-white/90 px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-aura-400 focus:ring-aura-300"
                placeholder="{{ __('candidates.assistant.placeholder') }}"
                required
            ></textarea>
            <button
                type="submit"
                class="w-full rounded-xl bg-success-600 px-3 py-2 text-sm font-semibold text-white transition-weightless hover:bg-success-700"
            >
                {{ __('candidates.assistant.send_action') }}
            </button>
        </form>
    </div>
</div>
