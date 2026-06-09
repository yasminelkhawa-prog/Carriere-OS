<x-shell-layout :title="__('platform.ai_diagnostics.detail_title').' | '.config('app.name')">
    <x-glass-card :title="__('platform.ai_diagnostics.detail_title')">

        {{-- Toast --}}
        @if (session('status'))
            <x-toast-alert type="success" class="mb-4">{{ session('status') }}</x-toast-alert>
        @endif

        {{-- Back link --}}
        <div class="mb-6">
            <a href="{{ route('platform.ai-diagnostics') }}"
               class="inline-flex items-center gap-2 text-sm font-medium text-aura-700 transition-weightless hover:text-aura-900">
                <svg class="size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                </svg>
                {{ __('platform.ai_diagnostics.back_to_list') }}
            </a>
        </div>

        @php
            $statusVariant = match($aiRequest->status) {
                'succeeded' => 'success',
                'failed'    => 'danger',
                'running'   => 'pending',
                default     => 'default',
            };
        @endphp

        {{-- Header: Status + company --}}
        <div class="mb-6 flex flex-wrap items-center gap-4">
            <x-badge :variant="$statusVariant" class="text-base">
                {{ __('platform.ai_diagnostics.statuses.'.$aiRequest->status) }}
            </x-badge>
            <span class="text-sm font-semibold text-slate-800">{{ $aiRequest->company?->name }}</span>
            <span class="font-mono text-xs text-slate-500">{{ $aiRequest->id }}</span>
        </div>

        {{-- Meta grid --}}
        <div class="mb-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div class="rounded-xl border border-slate-100 bg-white/70 px-4 py-3">
                <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('platform.ai_diagnostics.field_type') }}</p>
                <p class="mt-1 font-mono text-sm font-semibold text-slate-900">{{ $aiRequest->request_type }}</p>
            </div>
            <div class="rounded-xl border border-slate-100 bg-white/70 px-4 py-3">
                <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('platform.ai_diagnostics.field_model') }}</p>
                <p class="mt-1 text-sm font-semibold text-slate-900">{{ $aiRequest->model_name ?? '-' }}</p>
            </div>
            <div class="rounded-xl border border-slate-100 bg-white/70 px-4 py-3">
                <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('platform.ai_diagnostics.field_prompt_version') }}</p>
                <p class="mt-1 text-sm font-semibold text-slate-900">{{ $aiRequest->prompt_version ?? '-' }}</p>
            </div>
            <div class="rounded-xl border border-slate-100 bg-white/70 px-4 py-3">
                <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('platform.ai_diagnostics.field_created') }}</p>
                <p class="mt-1 text-sm text-slate-900">{{ $aiRequest->created_at?->toDateTimeString() ?? '-' }}</p>
            </div>
            <div class="rounded-xl border border-slate-100 bg-white/70 px-4 py-3">
                <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('platform.ai_diagnostics.field_started') }}</p>
                <p class="mt-1 text-sm text-slate-900">{{ $aiRequest->started_at?->toDateTimeString() ?? '-' }}</p>
            </div>
            <div class="rounded-xl border border-slate-100 bg-white/70 px-4 py-3">
                <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('platform.ai_diagnostics.field_finished') }}</p>
                <p class="mt-1 text-sm text-slate-900">{{ $aiRequest->finished_at?->toDateTimeString() ?? '-' }}</p>
            </div>
            @if($duration !== null)
                <div class="rounded-xl border border-slate-100 bg-white/70 px-4 py-3">
                    <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('platform.ai_diagnostics.field_duration') }}</p>
                    <p class="mt-1 text-sm font-semibold text-slate-900">{{ number_format($duration) }} {{ __('platform.ai_diagnostics.ms') }}</p>
                </div>
            @endif
            <div class="rounded-xl border border-slate-100 bg-white/70 px-4 py-3">
                <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('platform.ai_diagnostics.field_artifacts') }}</p>
                <p class="mt-1 text-sm font-semibold text-slate-900">{{ $aiRequest->artifacts->count() }}</p>
            </div>
        </div>

        {{-- Error message (failed only) --}}
        @if($aiRequest->status === 'failed' && $aiRequest->error_message)
            <div class="mb-6 rounded-xl border border-danger-200/70 bg-danger-50/80 px-5 py-4">
                <p class="mb-1 text-sm font-semibold text-danger-800">{{ __('platform.ai_diagnostics.processing_failed') }}</p>
                <p class="text-xs text-danger-700">{{ $aiRequest->error_message }}</p>
            </div>
        @endif

        {{-- Attempt log --}}
        @if(count($attempts) > 0)
            <div class="mb-6">
                <h3 class="mb-3 text-sm font-semibold text-slate-800">{{ __('platform.ai_diagnostics.field_attempts') }}</h3>
                <div class="space-y-2">
                    @foreach($attempts as $attempt)
                        @php
                            $attemptStatus = data_get($attempt, 'status', 'unknown');
                            $attemptVariant = $attemptStatus === 'succeeded' ? 'success' : 'danger';
                        @endphp
                        <div class="flex flex-wrap items-start gap-3 rounded-xl border border-slate-100 bg-white/60 px-4 py-3 text-xs">
                            <span class="font-semibold text-slate-700">#{{ data_get($attempt, 'attempt') }}</span>
                            <x-badge :variant="$attemptVariant">{{ $attemptStatus }}</x-badge>
                            <span class="text-slate-500">{{ data_get($attempt, 'at') }}</span>
                            @if(data_get($attempt, 'error'))
                                <span class="text-danger-700">{{ \Illuminate\Support\Str::limit(data_get($attempt, 'error'), 200) }}</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Redacted request payload --}}
        <div class="mb-6">
            <h3 class="mb-2 text-sm font-semibold text-slate-800">{{ __('platform.ai_diagnostics.request_preview_title') }}</h3>
            <pre class="max-h-64 overflow-auto rounded-xl border border-aura-100 bg-slate-900/95 p-4 text-xs leading-relaxed text-success-300">{{ json_encode($redactedRequest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
        </div>

        {{-- Redacted response payload --}}
        <div class="mb-8">
            <h3 class="mb-2 text-sm font-semibold text-slate-800">{{ __('platform.ai_diagnostics.response_preview_title') }}</h3>
            <pre class="max-h-64 overflow-auto rounded-xl border border-aura-100 bg-slate-900/95 p-4 text-xs leading-relaxed text-primary-300">{{ json_encode($redactedResponse, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
        </div>

        {{-- Retry button (failed only) --}}
        @if($aiRequest->status === 'failed')
            <div class="flex items-center gap-4 border-t border-slate-100 pt-6">
                <p class="text-sm text-danger-700 font-medium">{{ __('platform.ai_diagnostics.processing_failed') }}</p>
                <form method="POST" action="{{ route('platform.ai-diagnostics.retry', $aiRequest->id) }}">
                    @csrf
                    <button type="submit"
                            class="rounded-xl border border-danger-300/60 bg-white px-4 py-2 text-sm font-semibold text-danger-800 transition-weightless hover:bg-danger-50">
                        {{ __('platform.ai_diagnostics.retry_action') }}
                    </button>
                </form>
            </div>
        @endif

    </x-glass-card>
</x-shell-layout>

