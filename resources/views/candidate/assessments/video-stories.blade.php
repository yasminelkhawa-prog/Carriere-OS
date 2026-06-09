<x-shell-layout :title="__('video_assessment.stories.title').' | '.config('app.name')">
    <div class="space-y-6" data-guide-bot-disabled="true">
        @if(session('status'))
            <x-toast-alert type="success">{{ session('status') }}</x-toast-alert>
        @endif
        @if(session('error'))
            <x-toast-alert type="warning">{{ session('error') }}</x-toast-alert>
        @endif
        @if($errors->any())
            <x-toast-alert type="warning">{{ $errors->first() }}</x-toast-alert>
        @endif

        <x-glass-card :title="__('video_assessment.stories.title')" :subtitle="__('video_assessment.stories.subtitle')">
            @if(! $config)
                <x-empty-state
                    :title="__('video_assessment.stories.messages.no_config_title')"
                    :message="__('video_assessment.stories.messages.no_config_message')" />
            @elseif(! $currentQuestion)
                <x-empty-state
                    :title="__('video_assessment.stories.messages.completed_title')"
                    :message="__('video_assessment.stories.messages.completed_message')" />

                @if($latestUnifiedRequest && $latestUnifiedRequest->status === \App\Models\AiRequest::STATUS_FAILED)
                    <p class="mt-3 text-sm text-danger-700">{{ __('video_assessment.stories.labels.processing_failed') }}</p>
                @elseif($latestUnifiedRequest && in_array($latestUnifiedRequest->status, [\App\Models\AiRequest::STATUS_QUEUED, \App\Models\AiRequest::STATUS_RUNNING], true))
                    <p class="mt-3 text-sm text-primary-700">{{ __('video_assessment.stories.labels.processing') }}</p>
                @endif
            @else
                @php
                    $total = (int) $progress['total'];
                    $answered = (int) $progress['answered'];
                    $attemptsUsed = $currentAttempts->count();
                    $maxAttempts = max(1, (int) $config->retries_allowed + 1);
                    $retriesLeft = max(0, $maxAttempts - $attemptsUsed);
                @endphp

                <div class="rounded-2xl border border-white/80 bg-white/65 p-4 shadow-aura backdrop-blur-2xl">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <p class="text-sm font-semibold text-slate-900">{{ __('video_assessment.stories.progress', ['current' => $answered, 'total' => $total]) }}</p>
                        <p class="text-xs uppercase tracking-wide text-aura-700/90">{{ $progress['percent'] }}%</p>
                    </div>
                    <div class="mt-2 h-2.5 rounded-full bg-aura-100/80">
                        <div class="h-full rounded-full bg-success-600 transition-all duration-500" style="width: {{ $progress['percent'] }}%"></div>
                    </div>
                </div>

                <div class="grid gap-4 lg:grid-cols-[20rem_minmax(0,1fr)]">
                    <div class="space-y-2 rounded-2xl border border-white/80 bg-white/65 p-3 shadow-aura backdrop-blur-2xl">
                        @foreach($questions as $question)
                            @php
                                $isCurrent = (string) $currentQuestion->id === (string) $question->id;
                                $hasAnswer = $latestResponsesByQuestionId->has((string) $question->id);
                            @endphp
                            <a href="{{ route('candidate.video-stories', ['company' => $company->slug, 'application' => $application->id, 'question_id' => $question->id]) }}"
                               @class([
                                   'block rounded-xl border px-3 py-2.5 text-sm transition-weightless',
                                   $isCurrent
                                       ? 'border-aura-300 bg-aura-100/70 text-aura-900'
                                       : 'border-slate-200 bg-white/85 text-slate-700 hover:bg-white',
                               ])>
                                <p class="font-medium">{{ $question->display_order }}. {{ \Illuminate\Support\Str::limit($question->question_text, 80) }}</p>
                                <p class="mt-1 text-[11px] uppercase tracking-wide {{ $hasAnswer ? 'text-success-700' : 'text-slate-500' }}">
                                    {{ $hasAnswer ? __('sjt.states.scored') : __('sjt.states.not_started') }}
                                </p>
                            </a>
                        @endforeach
                    </div>

                    <div class="space-y-4">
                        <section class="rounded-2xl border border-white/80 bg-white/65 p-5 shadow-aura backdrop-blur-2xl">
                            <h3 class="text-lg font-semibold text-slate-900">{{ $currentQuestion->display_order }}. {{ $currentQuestion->question_text }}</h3>
                            <div class="mt-3 grid gap-3 md:grid-cols-3">
                                <div class="rounded-xl border border-slate-200 bg-white/80 p-3">
                                    <p class="text-xs uppercase tracking-wide text-slate-600">{{ __('video_assessment.stories.labels.read_timer') }}</p>
                                    <p id="read-timer-display" class="mt-1 text-base font-semibold text-slate-900">{{ $config->read_time_seconds }}s</p>
                                </div>
                                <div class="rounded-xl border border-slate-200 bg-white/80 p-3">
                                    <p class="text-xs uppercase tracking-wide text-slate-600">{{ __('video_assessment.stories.labels.answer_timer') }}</p>
                                    <p id="answer-timer-display" class="mt-1 text-base font-semibold text-slate-900">{{ $config->answer_time_seconds }}s</p>
                                </div>
                                <div class="rounded-xl border border-slate-200 bg-white/80 p-3">
                                    <p class="text-xs uppercase tracking-wide text-slate-600">{{ __('video_assessment.stories.labels.retries_left') }}</p>
                                    <p class="mt-1 text-base font-semibold text-slate-900">{{ $retriesLeft }}</p>
                                </div>
                            </div>

                            <p class="mt-3 rounded-xl border border-aura-200/50 bg-aura-50/80 px-3 py-2 text-xs text-aura-900">
                                {{ __('video_assessment.stories.labels.guide_blocked') }}
                            </p>
                        </section>

                        <section class="rounded-2xl border border-white/80 bg-white/65 p-5 shadow-aura backdrop-blur-2xl">
                            <form method="POST"
                                  action="{{ route('candidate.video-stories.submit', ['company' => $company->slug, 'application' => $application->id, 'videoQuestion' => $currentQuestion->id]) }}"
                                  enctype="multipart/form-data"
                                  class="space-y-3"
                                  id="video-story-form">
                                @csrf
                                <input type="hidden" name="read_time_completed" id="read-time-completed" value="0">

                                <div class="grid gap-3 md:grid-cols-2">
                                    <x-form-field :label="__('video_assessment.stories.labels.duration_seconds')" name="duration_seconds">
                                        <input type="number" min="1" max="{{ $config->answer_time_seconds }}" name="duration_seconds" value="{{ old('duration_seconds', $config->answer_time_seconds) }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2 text-sm" required>
                                    </x-form-field>
                                    <x-form-field :label="__('video_assessment.stories.labels.recording_file')" name="video_file">
                                        <input type="file" name="video_file" accept=".mp4,.webm,.ogg,.mov" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2 text-sm file:mr-4 file:rounded-lg file:border-0 file:bg-aura-100 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-aura-700" required>
                                    </x-form-field>
                                </div>

                                <div class="grid gap-3 md:grid-cols-3">
                                    <x-form-field :label="__('video_assessment.stories.labels.pauses_count')" name="pauses_count">
                                        <input type="number" min="0" max="500" name="pauses_count" value="{{ old('pauses_count') }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2 text-sm">
                                    </x-form-field>
                                    <x-form-field :label="__('video_assessment.stories.labels.speech_rate')" name="speech_rate_estimate">
                                        <input type="number" min="0" max="800" step="0.01" name="speech_rate_estimate" value="{{ old('speech_rate_estimate') }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2 text-sm">
                                    </x-form-field>
                                    <x-form-field :label="__('video_assessment.stories.labels.filler_ratio')" name="filler_ratio_estimate">
                                        <input type="number" min="0" max="1" step="0.0001" name="filler_ratio_estimate" value="{{ old('filler_ratio_estimate') }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2 text-sm">
                                    </x-form-field>
                                </div>

                                <x-form-field :label="__('video_assessment.stories.labels.transcript')" name="transcript_text">
                                    <textarea name="transcript_text" rows="5" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2 text-sm">{{ old('transcript_text') }}</textarea>
                                </x-form-field>

                                <div class="flex flex-wrap gap-2">
                                    <button type="button" id="start-read-timer" class="rounded-xl border border-aura-300/60 bg-white/90 px-4 py-2 text-sm font-medium text-slate-900 transition-weightless hover:bg-white">
                                        {{ __('video_assessment.stories.actions.start_reading') }}
                                    </button>
                                    <button type="submit" name="action" value="next" id="submit-next" disabled class="rounded-xl bg-success-600 px-4 py-2 text-sm font-semibold text-white transition-weightless hover:bg-success-700 disabled:cursor-not-allowed disabled:opacity-50">
                                        {{ __('video_assessment.stories.actions.submit_and_next') }}
                                    </button>
                                    <button type="submit"
                                            name="action"
                                            value="retry"
                                            id="submit-retry"
                                            data-retry-allowed="{{ $retriesLeft > 1 ? '1' : '0' }}"
                                            @disabled(true)
                                            class="rounded-xl border border-primary-300/60 bg-primary-50 px-4 py-2 text-sm font-medium text-primary-800 transition-weightless hover:bg-primary-100/80 disabled:cursor-not-allowed disabled:opacity-50">
                                        {{ __('video_assessment.stories.actions.submit_and_retry') }}
                                    </button>
                                </div>
                            </form>
                        </section>

                        @if($currentAttempts->isNotEmpty())
                            <section class="rounded-2xl border border-white/80 bg-white/65 p-5 shadow-aura backdrop-blur-2xl">
                                <p class="text-sm font-semibold text-slate-900">{{ __('video_assessment.stories.labels.retries_left') }}: {{ $retriesLeft }}</p>
                                <div class="mt-3 space-y-2">
                                    @foreach($currentAttempts as $attempt)
                                        <div class="rounded-xl border border-slate-200 bg-white/80 p-3">
                                            <p class="text-xs font-semibold text-slate-800">{{ __('video_assessment.stories.labels.attempt', ['attempt' => $attempt->attempt_number]) }}</p>
                                            <p class="mt-1 text-xs text-slate-600">{{ $attempt->duration_seconds }}s | pauses: {{ $attempt->pauses_count ?? '-' }} | filler: {{ $attempt->filler_ratio_estimate ?? '-' }}</p>
                                        </div>
                                    @endforeach
                                </div>
                            </section>
                        @endif
                    </div>
                </div>
            @endif

            <div class="mt-5">
                <a href="{{ route('candidate.portal', ['company' => $company->slug]) }}" class="rounded-xl border border-aura-300/50 bg-white/85 px-4 py-2 text-sm font-medium text-slate-900 transition-weightless hover:bg-white">
                    {{ __('video_assessment.stories.actions.open_candidate_portal') }}
                </a>
            </div>
        </x-glass-card>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const readTotal = {{ (int) ($config?->read_time_seconds ?? 0) }};
            const answerTotal = {{ (int) ($config?->answer_time_seconds ?? 0) }};
            const startReadButton = document.getElementById('start-read-timer');
            const readDisplay = document.getElementById('read-timer-display');
            const answerDisplay = document.getElementById('answer-timer-display');
            const readCompletedInput = document.getElementById('read-time-completed');
            const submitNext = document.getElementById('submit-next');
            const submitRetry = document.getElementById('submit-retry');

            if (startReadButton && readDisplay && answerDisplay && readCompletedInput && submitNext) {
                let readRemaining = readTotal;
                let answerRemaining = answerTotal;
                let readInterval = null;
                let answerInterval = null;

                const enableSubmit = function () {
                    submitNext.disabled = false;
                    if (submitRetry) {
                        submitRetry.disabled = submitRetry.dataset.retryAllowed !== '1';
                    }
                };

                const startAnswerTimer = function () {
                    if (answerInterval) {
                        clearInterval(answerInterval);
                    }
                    answerDisplay.textContent = `${answerRemaining}s`;
                    answerInterval = setInterval(function () {
                        answerRemaining--;
                        answerDisplay.textContent = `${Math.max(0, answerRemaining)}s`;

                        if (answerRemaining <= 0) {
                            clearInterval(answerInterval);
                            submitNext.disabled = true;
                            if (submitRetry) {
                                submitRetry.disabled = true;
                            }
                        }
                    }, 1000);
                };

                startReadButton.addEventListener('click', function () {
                    startReadButton.disabled = true;
                    if (readInterval) {
                        clearInterval(readInterval);
                    }

                    readDisplay.textContent = `${readRemaining}s`;
                    readInterval = setInterval(function () {
                        readRemaining--;
                        readDisplay.textContent = `${Math.max(0, readRemaining)}s`;

                        if (readRemaining <= 0) {
                            clearInterval(readInterval);
                            readCompletedInput.value = '1';
                            readDisplay.textContent = '{{ __('video_assessment.stories.labels.read_complete') }}';
                            enableSubmit();
                            startAnswerTimer();
                        }
                    }, 1000);
                });
            }

            document.dispatchEvent(new CustomEvent('candidate-guide:disable', {
                detail: { page: 'video-stories-assessment' }
            }));
        });
    </script>
</x-shell-layout>
