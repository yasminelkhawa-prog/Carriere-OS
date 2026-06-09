<x-shell-layout :title="__('interviews.title').' | '.config('app.name')">
    <div class="space-y-4">
        <x-glass-card :title="__('interviews.title')" :subtitle="$interview->application?->candidate?->full_name.' - '.$interview->application?->job?->title">
            @if(session('status'))
                <x-toast-alert type="success">{{ session('status') }}</x-toast-alert>
            @endif
            @if($errors->any())
                <x-toast-alert type="warning">{{ $errors->first() }}</x-toast-alert>
            @endif

            @php
                $requiredFeedbackCount = $interview->participants
                    ->where('participant_role', 'interviewer')
                    ->count();
                $submittedFeedbackCount = $interview->feedback
                    ->pluck('author_user_id')
                    ->filter()
                    ->unique()
                    ->count();
                $missingFeedbackCount = max(0, $requiredFeedbackCount - $submittedFeedbackCount);
            @endphp

            <div class="grid gap-3 md:grid-cols-2 lg:grid-cols-5">
                <div class="rounded-xl border border-slate-200 bg-white/70 p-3 text-sm text-slate-700">
                    <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('interviews.detail.schedule') }}</p>
                    {{ $interview->scheduled_start_at?->timezone($interview->timezone)->format('Y-m-d H:i') }}
                    -
                    {{ $interview->scheduled_end_at?->timezone($interview->timezone)->format('H:i') }}
                    ({{ $interview->timezone }})
                </div>
                <div class="rounded-xl border border-slate-200 bg-white/70 p-3 text-sm text-slate-700">
                    <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('interviews.fields.meeting_link') }}</p>
                    {{ $interview->meeting_link ?: __('candidates.detail.not_available') }}
                </div>
                <div class="rounded-xl border border-slate-200 bg-white/70 p-3 text-sm text-slate-700">
                    <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('interviews.fields.location_address') }}</p>
                    {{ $interview->location_address ?: __('candidates.detail.not_available') }}
                </div>
                <div class="rounded-xl border border-slate-200 bg-white/70 p-3 text-sm text-slate-700">
                    <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('interviews.fields.location_type') }}</p>
                    {{ __('interviews.location_types.'.$interview->location_type) }}
                </div>
                <div class="rounded-xl border border-slate-200 bg-white/70 p-3 text-sm text-slate-700">
                    <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('interviews.detail.feedback_status') }}</p>
                    {{ __('interviews.detail.feedback_status_value', ['submitted' => $submittedFeedbackCount, 'required' => $requiredFeedbackCount, 'missing' => $missingFeedbackCount]) }}
                </div>
            </div>

            <div class="mt-4 flex flex-wrap gap-2">
                @if($canGenerateInvite)
                    <a href="{{ $inviteUrl }}" target="_blank" rel="noopener" class="rounded-xl bg-primary-600 px-3 py-2 text-xs font-semibold text-white">
                        {{ __('interviews.detail.invite') }}
                    </a>
                @endif
            </div>
        </x-glass-card>

        <x-glass-card :title="__('interviews.detail.participants')">
            <div class="space-y-2 text-sm text-slate-700">
                @foreach($interview->participants as $participant)
                    <div class="rounded-lg border border-slate-200 bg-white/70 px-3 py-2">
                        {{ $participant->user?->profile?->full_name ?? $participant->user?->email }}
                        ({{ $participant->participant_role }})
                    </div>
                @endforeach
            </div>
        </x-glass-card>

        <x-glass-card :title="__('interviews.detail.feedback')">
            @if($canSubmitFeedback)
                <form method="POST" action="{{ route('interviews.feedback.store', ['interview' => $interview->id, 'company_id' => request('company_id')]) }}" class="space-y-3">
                    @csrf
                    <x-form-field :label="__('interviews.fields.recommendation')" name="recommendation" required>
                        <select name="recommendation" required class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                            <option value="hire">{{ __('interviews.recommendations.hire') }}</option>
                            <option value="hold">{{ __('interviews.recommendations.hold') }}</option>
                            <option value="no">{{ __('interviews.recommendations.no') }}</option>
                        </select>
                    </x-form-field>

                    <div class="grid gap-3 md:grid-cols-3">
                        <x-form-field :label="__('interviews.fields.rating_technical')" name="rating_technical">
                            <input type="number" min="1" max="5" name="rating_technical" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                        </x-form-field>
                        <x-form-field :label="__('interviews.fields.rating_communication')" name="rating_communication">
                            <input type="number" min="1" max="5" name="rating_communication" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                        </x-form-field>
                        <x-form-field :label="__('interviews.fields.rating_problem_solving')" name="rating_problem_solving">
                            <input type="number" min="1" max="5" name="rating_problem_solving" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                        </x-form-field>
                    </div>

                    <x-form-field :label="__('interviews.fields.feedback_notes')" name="notes">
                        <textarea name="notes" rows="3" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm"></textarea>
                    </x-form-field>

                    <button type="submit" class="rounded-xl bg-success-600 px-3 py-2 text-xs font-semibold text-white">{{ __('interviews.detail.add_feedback') }}</button>
                </form>
            @else
                <div class="rounded-xl border border-slate-200 bg-white/70 p-3 text-xs text-slate-600">
                    {{ __('interviews.permissions.feedback_interviewer_only') }}
                </div>
            @endif

            <div class="mt-4 space-y-2">
                @foreach($interview->feedback as $feedback)
                    <div class="rounded-xl border border-slate-200 bg-white/70 p-3">
                        <p class="text-xs font-semibold text-slate-800">{{ $feedback->author?->profile?->full_name ?? $feedback->author?->email }}</p>
                        <p class="mt-1 text-xs text-slate-600">{{ __('interviews.recommendations.'.$feedback->recommendation) }}</p>
                        <p class="mt-1 text-xs text-slate-600">
                            {{ __('interviews.fields.rating_technical') }}: {{ data_get($feedback->ratings_json, 'technical') ?? '-' }},
                            {{ __('interviews.fields.rating_communication') }}: {{ data_get($feedback->ratings_json, 'communication') ?? '-' }},
                            {{ __('interviews.fields.rating_problem_solving') }}: {{ data_get($feedback->ratings_json, 'problem_solving') ?? '-' }}
                        </p>
                        <p class="mt-1 text-xs text-slate-600">{{ $feedback->notes ?: __('candidates.detail.not_available') }}</p>
                        <p class="mt-1 text-[11px] text-slate-500">{{ $feedback->created_at?->diffForHumans() }}</p>
                    </div>
                @endforeach
            </div>
        </x-glass-card>
    </div>
</x-shell-layout>
