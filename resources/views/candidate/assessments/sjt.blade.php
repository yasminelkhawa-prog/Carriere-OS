<x-shell-layout :title="__('sjt.title')">
    <div class="space-y-6" data-guide-bot-disabled="true">
        <x-glass-card :title="__('sjt.title')" :subtitle="__('sjt.subtitle')">
            @if (session('status'))
                <x-toast-alert type="success">{{ session('status') }}</x-toast-alert>
            @endif

            @if ($errors->has('assessment'))
                <x-ui.alert variant="danger" class="mt-3">
                    {{ $errors->first('assessment') }}
                </x-ui.alert>
            @endif

            @if ($applications->isEmpty())
                <div class="mt-4">
                    <x-empty-state :title="__('sjt.empty_applications_title')" :message="__('sjt.empty_applications_message')" />
                </div>
            @else
                <form method="GET" action="{{ route('candidate.assessments.sjt') }}" class="mt-4 grid gap-3 md:grid-cols-2">
                    <x-form-field :label="__('sjt.fields.application')" name="application_id">
                        <select name="application_id" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-sm text-slate-900 shadow-sm">
                            @foreach($applications as $application)
                                <option value="{{ $application->id }}" @selected((string) $selectedApplication?->id === (string) $application->id)>
                                    {{ $application->job?->title ?? __('sjt.messages.unknown_job') }} ({{ strtoupper($application->status) }})
                                </option>
                            @endforeach
                        </select>
                    </x-form-field>

                    <x-form-field :label="__('sjt.fields.scenario')" name="scenario_id">
                        <select name="scenario_id" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-sm text-slate-900 shadow-sm">
                            @foreach($scenarios as $scenario)
                                <option value="{{ $scenario->id }}" @selected((string) $selectedScenario?->id === (string) $scenario->id)>
                                    {{ $scenario->title }}
                                </option>
                            @endforeach
                        </select>
                    </x-form-field>

                    <div class="md:col-span-2">
                        <button type="submit" class="rounded-xl border border-aura-300/50 bg-white/85 px-4 py-2 text-sm font-medium text-slate-900 transition-weightless hover:bg-white">
                            {{ __('sjt.actions.load') }}
                        </button>
                    </div>
                </form>

                <div class="mt-5 rounded-2xl border border-white/80 bg-white/65 p-4 shadow-aura backdrop-blur-2xl">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <p class="text-sm font-semibold text-slate-900">{{ __('sjt.progress.title') }}</p>
                        <p class="text-xs uppercase tracking-wide text-aura-700/90">
                            {{ __('sjt.progress.answered', ['answered' => $progress['answered'], 'total' => $progress['total']]) }}
                        </p>
                    </div>
                    <div class="mt-3 h-2.5 rounded-full bg-aura-100/80">
                        <div class="h-full rounded-full bg-success-600 transition-all duration-500" style="width: {{ $progress['percent'] }}%"></div>
                    </div>
                    <div class="mt-3 grid gap-2 text-xs text-slate-700 sm:grid-cols-3">
                        <p>{{ __('sjt.progress.submitted', ['submitted' => $progress['submitted'], 'total' => $progress['total']]) }}</p>
                        <p>{{ __('sjt.progress.scored', ['scored' => $progress['scored'], 'total' => $progress['total']]) }}</p>
                        <p>{{ __('sjt.progress.percent', ['percent' => $progress['percent']]) }}</p>
                    </div>
                </div>

                @if($scenarios->isEmpty())
                    <div class="mt-5">
                        <x-empty-state :title="__('sjt.empty_scenarios_title')" :message="__('sjt.empty_scenarios_message')" />
                    </div>
                @elseif($selectedScenario)
                    <div class="mt-5 grid gap-4 lg:grid-cols-[18rem_minmax(0,1fr)]">
                        <div class="space-y-2 rounded-2xl border border-white/80 bg-white/65 p-3 shadow-aura backdrop-blur-2xl">
                            @foreach($scenarios as $scenario)
                                @php
                                    $state = data_get($scenarioStatuses, (string) $scenario->id.'.state', 'not_started');
                                @endphp
                                <a href="{{ route('candidate.assessments.sjt', ['application_id' => $selectedApplication->id, 'scenario_id' => $scenario->id]) }}"
                                   @class([
                                       'block rounded-xl border px-3 py-2.5 text-sm transition-weightless',
                                       (string) $selectedScenario->id === (string) $scenario->id
                                           ? 'border-aura-300 bg-aura-100/70 text-aura-900'
                                           : 'border-slate-200 bg-white/85 text-slate-700 hover:bg-white',
                                   ])>
                                    <p class="font-medium">{{ $scenario->title }}</p>
                                    <p class="mt-1 text-[11px] uppercase tracking-wide text-aura-700/85">{{ __('sjt.states.'.$state) }}</p>
                                </a>
                            @endforeach
                        </div>

                        <div class="space-y-4">
                            <section class="rounded-2xl border border-white/80 bg-white/65 p-5 shadow-aura backdrop-blur-2xl">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <h3 class="text-lg font-semibold text-slate-900">{{ $selectedScenario->title }}</h3>
                                    <p class="text-xs uppercase tracking-[0.2em] text-aura-700/90">{{ __('sjt.scenario_badge') }}</p>
                                </div>

                                @if($selectedScenario->scenario_media_url)
                                    @php
                                        $mediaUrl = (string) $selectedScenario->scenario_media_url;
                                        $isVideo = \Illuminate\Support\Str::of($mediaUrl)->lower()->endsWith(['.mp4', '.webm', '.ogg']);
                                    @endphp
                                    <div class="mt-4 overflow-hidden rounded-2xl border border-aura-200/40 bg-slate-950/5">
                                        @if($isVideo)
                                            <video class="h-72 w-full object-cover" controls autoplay muted loop playsinline>
                                                <source src="{{ $mediaUrl }}">
                                            </video>
                                        @else
                                            <img src="{{ $mediaUrl }}" alt="{{ $selectedScenario->title }}" class="h-72 w-full object-cover">
                                        @endif
                                    </div>
                                @endif

                                <div class="mt-4 text-sm leading-relaxed text-slate-700">{!! $selectedScenario->scenario_text !!}</div>
                            </section>

                            @php
                                $state = data_get($scenarioStatuses, (string) $selectedScenario->id.'.state', 'not_started');
                                $isFinalSubmission = (bool) data_get($scenarioStatuses, (string) $selectedScenario->id.'.has_submission', false);
                            @endphp
                            <section
                                class="rounded-2xl border border-white/80 bg-white/65 p-5 shadow-aura backdrop-blur-2xl"
                                @unless($isFinalSubmission)
                                    x-data="{
                                        length: {{ strlen(old('response_text', (string) ($selectedResponse?->response_text ?? ''))) }}
                                    }"
                                @endunless
                            >
                                <header class="flex flex-wrap items-start justify-between gap-2">
                                    <div>
                                        <h4 class="text-base font-semibold text-slate-900">{{ __('sjt.response_title') }}</h4>
                                        @if($isFinalSubmission)
                                            <p class="text-xs text-slate-600">{{ __('sjt.readonly_notice') }}</p>
                                        @endif
                                    </div>
                                    <span class="rounded-full border border-aura-300/60 bg-aura-100/70 px-3 py-1 text-xs font-medium uppercase tracking-wide text-aura-800">
                                        {{ __('sjt.states.'.$state) }}
                                    </span>
                                </header>

                                @if(! $isFinalSubmission)
                                    <form method="POST" class="mt-4 space-y-3">
                                        @csrf
                                        <p class="text-sm text-slate-700">{{ __('sjt.response_instruction') }}</p>
                                        <textarea
                                            id="sjt-response-text"
                                            name="response_text"
                                            rows="10"
                                            class="w-full rounded-xl border border-aura-200/50 bg-white/90 px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-aura-400 focus:ring-aura-300 @error('response_text') border-danger-400 @enderror"
                                            x-on:input="length = $event.target.value.length"
                                        >{{ old('response_text', (string) ($selectedResponse?->response_text ?? '')) }}</textarea>
                                        @error('response_text')
                                            <p class="text-xs text-danger-700">{{ $message }}</p>
                                        @enderror


                                        <div class="flex flex-wrap items-center justify-between gap-2 text-xs text-slate-600">
                                            <p>{{ __('sjt.length_hint', ['min' => $responseMin, 'max' => $responseMax]) }}</p>
                                            <p><span x-text="length"></span> / {{ $responseMax }}</p>
                                        </div>

                                        <p class="rounded-xl border border-aura-200/50 bg-aura-50/80 px-3 py-2 text-xs text-aura-900">
                                            {{ __('sjt.guide_bot_blocked') }}
                                        </p>

                                        <div class="flex flex-wrap gap-2">
                                            <button
                                                id="sjt-save-draft"
                                                type="submit"
                                                formaction="{{ route('candidate.assessments.sjt.draft', ['application' => $selectedApplication->id, 'scenario' => $selectedScenario->id]) }}"
                                                class="rounded-xl border border-primary-300/60 bg-primary-50 px-4 py-2 text-sm font-medium text-primary-800 transition-weightless hover:bg-primary-100/80">
                                                {{ __('sjt.actions.save_draft') }}
                                            </button>
                                            <button
                                                id="sjt-submit-final"
                                                type="submit"
                                                formaction="{{ route('candidate.assessments.sjt.submit', ['application' => $selectedApplication->id, 'scenario' => $selectedScenario->id]) }}"
                                                class="rounded-xl bg-success-600 px-4 py-2 text-sm font-semibold text-white transition-weightless hover:bg-success-700">
                                                {{ __('sjt.actions.submit_final') }}
                                            </button>
                                        </div>
                                    </form>
                                @endif

                                @if($selectedScoringRequest && in_array($selectedScoringRequest->status, [\App\Models\AiRequest::STATUS_QUEUED, \App\Models\AiRequest::STATUS_RUNNING, \App\Models\AiRequest::STATUS_FAILED], true))
                                    <div class="mt-4 rounded-xl border border-aura-200/50 bg-aura-50/80 p-3 text-sm text-slate-700">
                                        <p class="font-medium text-aura-900">{{ __('sjt.processing_state') }}</p>
                                        <p class="mt-1 text-xs text-primary-700">{{ __('sjt.processing_hint') }}</p>

                                        @if($selectedScoringRequest->status === \App\Models\AiRequest::STATUS_FAILED && $selectedResponse)
                                            <form method="POST" action="{{ route('candidate.assessments.sjt.retry', ['sjtResponse' => $selectedResponse->id]) }}" class="mt-3">
                                                @csrf
                                                <button type="submit" class="rounded-lg border border-primary-300/60 bg-primary-50 px-3 py-1.5 text-xs font-medium text-primary-800 transition-weightless hover:bg-primary-100/80">
                                                    {{ __('sjt.actions.retry_scoring') }}
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                @endif

                                @if($selectedResponse?->ai_score !== null)
                                    <div class="mt-4 rounded-xl border border-success-200/60 bg-success-50/70 p-3">
                                        <p class="text-sm font-semibold text-success-900">{{ __('sjt.score_label', ['score' => number_format((float) $selectedResponse->ai_score, 2)]) }}</p>

                                        @if(is_array($selectedResponse->ai_feedback_json))
                                            <div class="mt-2 space-y-2 text-sm text-slate-700">
                                                <p>{{ data_get($selectedResponse->ai_feedback_json, 'summary', '') }}</p>
                                                @if(is_array(data_get($selectedResponse->ai_feedback_json, 'strengths')))
                                                    <p><span class="font-semibold text-slate-900">{{ __('sjt.feedback.strengths') }}:</span> {{ implode(', ', data_get($selectedResponse->ai_feedback_json, 'strengths', [])) }}</p>
                                                @endif
                                                @if(is_array(data_get($selectedResponse->ai_feedback_json, 'concerns')))
                                                    <p><span class="font-semibold text-slate-900">{{ __('sjt.feedback.concerns') }}:</span> {{ implode(', ', data_get($selectedResponse->ai_feedback_json, 'concerns', [])) }}</p>
                                                @endif
                                                @if(is_string(data_get($selectedResponse->ai_feedback_json, 'recommendation')))
                                                    <p><span class="font-semibold text-slate-900">{{ __('sjt.feedback.recommendation') }}:</span> {{ data_get($selectedResponse->ai_feedback_json, 'recommendation') }}</p>
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                @endif
                            </section>
                        </div>
                    </div>
                @endif
            @endif
        </x-glass-card>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const responseField = document.getElementById('sjt-response-text');


            document.dispatchEvent(new CustomEvent('candidate-guide:disable', {
                detail: { page: 'sjt-assessment' }
            }));
        });
    </script>
</x-shell-layout>
