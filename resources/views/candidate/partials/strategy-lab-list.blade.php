<x-glass-card :title="__('strategy_lab.candidate.title')" :subtitle="__('strategy_lab.candidate.subtitle')">
    <div class="space-y-4">
        @foreach($strategyLabBriefs as $brief)
            @php
                $application = $brief->application;
                $submission = $brief->submission;
                $summary = $brief->aiSummary;
                $isPastDeadline = $brief->deadline_at && $brief->deadline_at->isPast();
                $briefReady = is_string($brief->brief_pdf_url) && trim($brief->brief_pdf_url) !== '';
                $submissionDisabled = $isPastDeadline || ! $briefReady;
            @endphp
            <div class="rounded-2xl border border-white/70 bg-white/70 p-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-xs uppercase tracking-[0.24em] text-aura-700/85">{{ __('strategy_lab.labels.strategy_lab') }}</p>
                        <h3 class="mt-1 text-lg font-semibold text-slate-900">{{ $brief->brief_title }}</h3>
                        <p class="mt-1 text-sm text-slate-600">{{ $application?->job?->title ?? __('strategy_lab.labels.unknown_job') }}</p>
                    </div>
                    <x-badge>{{ __('strategy_lab.status.'.$brief->status) }}</x-badge>
                </div>
                <div class="mt-3 grid gap-3 md:grid-cols-2">
                    <div class="rounded-xl border border-slate-200 bg-white/80 p-3">
                        <p class="text-xs uppercase tracking-wide text-slate-600">{{ __('strategy_lab.labels.deadline') }}</p>
                        <p class="mt-1 text-sm font-medium text-slate-800">{{ optional($brief->deadline_at)->format('Y-m-d H:i') }} UTC</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-white/80 p-3">
                        <p class="text-xs uppercase tracking-wide text-slate-600">{{ __('strategy_lab.labels.brief') }}</p>
                        @if($briefReady)
                            <a href="{{ \App\Http\Controllers\StrategyLabController::signedBriefUrl($brief) }}" class="mt-2 inline-flex rounded-lg border border-aura-300/50 bg-white px-3 py-1.5 text-sm font-medium text-slate-800 transition-weightless hover:bg-white">{{ __('strategy_lab.actions.download_brief') }}</a>
                        @else
                            <p class="mt-2 text-sm text-primary-700">{{ __('strategy_lab.messages.brief_processing') }}</p>
                        @endif
                    </div>
                </div>
                @if($submission)
                    <div class="mt-4 rounded-xl border border-success-200 bg-success-50/70 p-3">
                        <p class="text-sm font-semibold text-success-900">{{ __('strategy_lab.messages.submission_received') }}</p>
                        <p class="mt-1 text-xs text-slate-700">{{ __('strategy_lab.labels.submitted_at') }}: {{ optional($submission->submitted_at)->format('Y-m-d H:i') }} UTC</p>
                        @if($summary)
                            <p class="mt-2 text-sm text-slate-700">{{ $summary->executive_summary_text }}</p>
                        @endif
                    </div>
                @else
                    <form method="POST" action="{{ route('candidate.strategy-lab.submit', ['company' => $company, 'application' => $application->id]) }}" enctype="multipart/form-data" class="mt-4 space-y-3">
                        @csrf
                        <x-form-field :label="__('strategy_lab.fields.submission_type')" name="submission_type">
                            <select name="submission_type" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-sm text-slate-900 shadow-sm" @disabled($submissionDisabled)>
                                <option value="{{ \App\Models\StrategyLabSubmission::TYPE_DOCUMENT }}">{{ __('strategy_lab.submission.types.document') }}</option>
                                <option value="{{ \App\Models\StrategyLabSubmission::TYPE_PRESENTATION }}">{{ __('strategy_lab.submission.types.presentation') }}</option>
                            </select>
                        </x-form-field>
                        <input type="file" name="submission_file" accept=".pdf,.doc,.docx,.ppt,.pptx,.odp,.key,.txt" class="w-full rounded-xl border border-aura-200/40 bg-white/85 px-3 py-2 text-sm text-slate-900 shadow-sm" @disabled($submissionDisabled) required>
                        <button type="submit" class="rounded-xl bg-success-600 px-4 py-2 text-sm font-semibold text-white transition-weightless hover:bg-success-700 disabled:cursor-not-allowed disabled:opacity-50" @disabled($submissionDisabled)>{{ __('strategy_lab.actions.submit_solution') }}</button>
                    </form>
                @endif
            </div>
        @endforeach
    </div>
</x-glass-card>
