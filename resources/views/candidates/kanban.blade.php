<x-shell-layout :title="__('kanban.title').' | '.config('app.name')">
    <div
        x-data="kanbanBoard()"
        x-init="init()"
        class="space-y-4"
    >
        <!-- Floating Alerts (Fixed top right to avoid layout shift) -->
        <div class="fixed right-6 top-6 z-50 flex flex-col gap-3">
            @if(session('status'))
                <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)" x-transition.opacity.duration.500ms class="flex items-center gap-3 rounded-2xl border border-emerald-200 bg-emerald-50/95 px-5 py-3.5 shadow-xl backdrop-blur-md">
                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-emerald-200/50">
                        <svg class="h-5 w-5 text-emerald-700" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" /></svg>
                    </div>
                    <span class="text-sm font-bold text-emerald-800">{{ session('status') }}</span>
                </div>
            @endif
            @if(session('error'))
                <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 6000)" x-transition.opacity.duration.500ms class="flex items-center gap-3 rounded-2xl border border-rose-200 bg-rose-50/95 px-5 py-3.5 shadow-xl backdrop-blur-md">
                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-rose-200/50">
                        <svg class="h-5 w-5 text-rose-700" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    </div>
                    <span class="text-sm font-bold text-rose-800">{{ session('error') }}</span>
                </div>
            @endif
        </div>

        <!-- Header Section -->
        <div class="mb-6 flex flex-col justify-end gap-5 md:flex-row md:items-end">
            
            <div class="flex w-full shrink-0 flex-col gap-3 sm:w-auto sm:flex-row sm:items-center ml-auto">
                @if($requiresCompanySelection)
                    <div class="text-sm text-amber-600 font-medium bg-amber-50 px-3 py-1.5 rounded-lg border border-amber-200">
                        {{ __('kanban.select_company_message') }}
                    </div>
                @else
                    <form method="GET" action="{{ route('candidates.kanban') }}" class="flex w-full flex-col gap-3 sm:flex-row sm:items-center">
                        @if(auth()->user()->isSuperadmin())
                            <select name="company_id" data-placeholder="{{ __('kanban.filters.company_placeholder') }}" class="w-full sm:w-48 rounded-xl border border-slate-200/60 bg-white/60 px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm backdrop-blur-xl transition-all hover:bg-white focus:border-aura-500 focus:ring focus:ring-aura-500/20" onchange="this.form.submit()">
                                <option value="">{{ __('kanban.filters.company_placeholder') }}</option>
                                @foreach($companies as $company)
                                    <option value="{{ $company->id }}" @selected((string) $selectedCompanyId === (string) $company->id)>{{ $company->name }}</option>
                                @endforeach
                            </select>
                        @endif
                        <select name="job_id" data-placeholder="{{ __('kanban.filters.job_placeholder') }}" class="w-full sm:w-64 rounded-xl border border-slate-200/60 bg-white/60 px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm backdrop-blur-xl transition-all hover:bg-white focus:border-aura-500 focus:ring focus:ring-aura-500/20" onchange="this.form.submit()">
                            <option value="">{{ __('kanban.filters.job_placeholder') }}</option>
                            @foreach($jobs as $job)
                                <option value="{{ $job->id }}" @selected((string) optional($selectedJob)->id === (string) $job->id)>{{ $job->title }}</option>
                            @endforeach
                        </select>
                    </form>
                @endif
            </div>
        </div>

        @if(! $requiresCompanySelection && $pipelineBlocked)
            <x-glass-card :title="__('kanban.pipeline.misconfigured_title')" :subtitle="__('kanban.pipeline.misconfigured_subtitle')">
                <div class="space-y-3 rounded-xl border border-danger-200/80 bg-danger-50/70 p-4 text-sm text-danger-900">
                    <p>{{ $pipelineIssue }}</p>
                    <p>{{ __('kanban.pipeline.fix_instructions') }}</p>
                    @can('access-admin-pages')
                        @if($pipelineFixUrl)
                            <a href="{{ $pipelineFixUrl }}" class="inline-flex items-center rounded-lg bg-danger-600 px-3 py-1.5 text-xs font-semibold text-white transition-weightless hover:bg-danger-700">
                                {{ __('kanban.pipeline.fix_cta') }}
                            </a>
                        @endif
                    @endcan
                </div>
            </x-glass-card>
        @endif

        @if(! $requiresCompanySelection && ! $pipelineBlocked && $boardStages->isNotEmpty())
            <!-- Kanban Container (Columns flex to fit screen, items-stretch to equal height) -->
            <div class="flex w-full items-stretch gap-4 pb-4 px-1" style="min-height: calc(100vh - 180px);">
                @foreach($boardStages as $stage)
                    @php 
                        $cards = $cardsByStage[(string) $stage->id] ?? collect(); 
                        $stageColors = ['bg-slate-50/50', 'bg-blue-50/50', 'bg-indigo-50/50', 'bg-purple-50/50', 'bg-emerald-50/50', 'bg-rose-50/50'];
                        $stageColor = $stageColors[$loop->index % count($stageColors)];
                        
                        $iconColors = ['text-slate-500', 'text-blue-500', 'text-indigo-500', 'text-purple-500', 'text-emerald-500', 'text-rose-500'];
                        $iconColor = $iconColors[$loop->index % count($iconColors)];

                        $stageKeyLower = strtolower($stage->stage_key ?? $stage->stage_label);
                        $iconSvg = match(true) {
                            str_contains($stageKeyLower, 'appl') || str_contains($stageKeyLower, 'nouveau') => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />',
                            str_contains($stageKeyLower, 'screen') || str_contains($stageKeyLower, 'qualif') => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />',
                            str_contains($stageKeyLower, 'interv') || str_contains($stageKeyLower, 'entretien') => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />',
                            str_contains($stageKeyLower, 'offer') || str_contains($stageKeyLower, 'offre') => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />',
                            str_contains($stageKeyLower, 'hire') || str_contains($stageKeyLower, 'embauch') => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" />',
                            str_contains($stageKeyLower, 'reject') || str_contains($stageKeyLower, 'refus') => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />',
                            default => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />'
                        };
                    @endphp
                    
                    <section
                        class="flex flex-1 min-w-0 flex-col rounded-3xl border border-slate-200/60 {{ $stageColor }} p-3 shadow-[0_2px_10px_-4px_rgba(0,0,0,0.05)] backdrop-blur-xl transition-all duration-300"
                        data-stage-id="{{ $stage->target_stage_id ?? $stage->id }}"
                        data-stage-key="{{ $stage->stage_key }}"
                        data-stage-terminal="{{ $stage->is_terminal ? '1' : '0' }}"
                        @dragover.prevent="$el.classList.add('ring-2','ring-aura-400','shadow-lg','scale-[1.01]')"
                        @dragleave="$el.classList.remove('ring-2','ring-aura-400','shadow-lg','scale-[1.01]')"
                        @drop.prevent="dropOnStage($event, $el)"
                    >
                        <header class="mb-4 mt-1 px-1 flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <svg class="h-5 w-5 {{ $iconColor }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">{!! $iconSvg !!}</svg>
                                <h3 class="truncate text-[15px] font-bold text-slate-800">{{ $stage->stage_label }}</h3>
                            </div>
                            <div class="flex h-6 min-w-[24px] items-center justify-center rounded-full bg-white px-2 text-[11px] font-black text-slate-600 shadow-sm">
                                {{ $cards->count() }}
                            </div>
                        </header>
                        
                        <style>
                            .hide-scroll::-webkit-scrollbar { display: none; }
                            .hide-scroll { -ms-overflow-style: none; scrollbar-width: none; }
                        </style>
                        <div class="flex flex-1 flex-col gap-4 overflow-y-auto hide-scroll pb-2 px-0.5" x-data="{ expanded: false }">
                            @forelse($cards as $card)
                                @php
                                    $blind = \App\Http\Controllers\CandidateWorkspaceController::shouldMaskIdentity(
                                        $card->job,
                                        (string) ($stage->stage_key ?? ''),
                                        (string) ($stage->stage_label ?? '')
                                    );
                                    $maskedIdentifier = \App\Http\Controllers\CandidateWorkspaceController::maskedCandidateIdentifier((string) $card->id);
                                    $name = $blind
                                        ? __('candidates.detail.masked_identifier_value', ['identifier' => $maskedIdentifier])
                                        : (string) optional($card->candidate)->full_name;

                                    $lastEvent = $card->activityEvents->first();
                                    $xaiReason = trim((string) ($card->rejectionDraft?->xai_reason_text ?? $card->scoring?->xai_summary ?? ''));
                                    $fallbackReason = $xaiReason !== '' ? $xaiReason : __('candidates.detail.not_available');
                                    $rejectionSubject = trim((string) ($card->rejectionDraft?->draft_subject
                                        ?? __('kanban.mail.rejection_subject', ['job' => (string) ($card->job?->title ?? __('candidates.detail.not_available'))])));
                                    $rejectionBody = trim((string) ($card->rejectionDraft?->draft_body
                                        ?? __('kanban.rejection.default_draft', [
                                            'job' => (string) ($card->job?->title ?? __('candidates.detail.not_available')),
                                            'reason' => $fallbackReason,
                                            'name' => $name,
                                            'company' => (string) ($card->company?->name ?? 'notre entreprise'),
                                        ])));
                                    
                                    $initials = collect(explode(' ', $name))->map(fn($w) => strtoupper(substr($w, 0, 1)))->take(2)->join('');
                                @endphp
                                <article
                                    @if($loop->index >= 10)
                                        x-show="expanded"
                                        x-cloak
                                    @endif
                                    draggable="true"
                                    class="group relative flex cursor-grab flex-col rounded-2xl border border-slate-200/80 bg-white p-4 shadow-[0_2px_8px_-4px_rgba(0,0,0,0.05)] transition-all duration-300 hover:-translate-y-1 hover:border-aura-300 hover:shadow-[0_8px_20px_-4px_rgba(0,0,0,0.1)] active:cursor-grabbing"
                                    data-application-id="{{ $card->id }}"
                                    data-from-stage-id="{{ $card->current_stage_id }}"
                                    data-candidate-name="{{ $name }}"
                                    data-job-title="{{ (string) ($card->job?->title ?? '') }}"
                                    data-xai-reason="{{ $xaiReason }}"
                                    data-rejection-subject="{{ $rejectionSubject }}"
                                    data-rejection-body="{{ $rejectionBody }}"
                                    @dragstart="startDrag($event, $el)"
                                >
                                    <!-- Header Strip (Like Notion icon + title) -->
                                    <div class="mb-3 flex items-center gap-2 border-b border-slate-100 pb-3">
                                        <div class="flex h-5 w-5 shrink-0 items-center justify-center rounded-[5px] bg-slate-800 text-[9px] font-bold text-white shadow-sm">
                                            {{ $initials }}
                                        </div>
                                        <span class="text-xs font-semibold text-slate-700">Candidate</span>
                                        
                                        @if($blind)
                                            <div class="ml-auto flex items-center gap-1 rounded bg-slate-100 px-1.5 py-0.5 text-[9px] font-bold text-slate-500">
                                                <svg class="h-2.5 w-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" /></svg>
                                                BLIND
                                            </div>
                                        @endif
                                    </div>
                                    
                                    <!-- Body (Main task info) -->
                                    <div class="mb-4 flex flex-col">
                                        <span class="mb-1 text-[11px] font-medium text-slate-500">Application for:</span>
                                        <h4 class="text-[15px] font-bold leading-snug text-slate-800 transition-colors group-hover:text-aura-600">{{ $name }}</h4>
                                        <span class="mt-0.5 line-clamp-2 text-xs font-medium text-slate-500">{{ $card->job?->title }}</span>
                                    </div>

                                    <!-- Divider & Footer -->
                                    <div class="mt-auto border-t border-slate-100 pt-3">
                                        <p class="text-[13px] font-bold text-slate-800">{{ $card->created_at->format('l, d M') }}</p>
                                        <p class="mt-0.5 text-[11px] font-medium text-slate-500">
                                            {{ $card->created_at->format('H:i') }} | {{ $card->created_at->diffForHumans(null, true, true) }}
                                        </p>
                                    </div>
                                </article>
                            @empty
                                <div class="flex h-full min-h-[8rem] flex-col items-center justify-center rounded-2xl border-2 border-dashed border-slate-300/60 bg-white/30 p-4 text-center">
                                    <span class="text-xs font-bold text-slate-400">{{ __('kanban.board.empty_stage') }}</span>
                                </div>
                            @endforelse

                            @if($cards->count() > 10)
                                <button 
                                    type="button" 
                                    @click="expanded = !expanded" 
                                    class="mt-2 w-full rounded-xl bg-white/80 px-3 py-2.5 text-xs font-bold text-slate-600 transition-all hover:bg-white hover:text-aura-600 hover:shadow-sm"
                                    x-text="expanded ? 'Voir moins' : 'Voir plus (' + ({{ $cards->count() }} - 10) + ' autres)'"
                                ></button>
                            @endif
                        </div>
                    </section>
                @endforeach
            </div>
        @endif

        <form x-ref="standardForm" method="POST" x-bind:action="transitionUrl">
            @csrf
            <input type="hidden" name="to_stage_id" :value="pending.toStageId">
            <input type="hidden" name="transition_type" value="standard">
            <input type="hidden" name="confirm_terminal" :value="pending.confirmTerminal ? 1 : 0">
            <input type="hidden" name="job_id" value="{{ optional($selectedJob)->id }}">
            <input type="hidden" name="company_id" value="{{ $selectedCompanyId }}">
        </form>

        <div
            x-cloak
            x-show="modal.type === 'interview'"
            x-on:keydown.escape.window="closeModal()"
            @click.self="closeModal()"
            class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/20 p-4 backdrop-blur-sm"
        >
            <div class="mx-auto my-6 w-full max-w-lg rounded-2xl border border-white/70 bg-white/90 p-6 shadow-xl max-h-[calc(100vh-3rem)] overflow-y-auto">
                <div class="flex items-center justify-between gap-3">
                    <h3 class="text-lg font-semibold text-slate-900">{{ __('kanban.modal.interview_title') }}</h3>
                    <button type="button" @click="closeModal()" class="rounded-lg border border-slate-200 bg-white px-2 py-1 text-sm text-slate-700">
                        {{ __('kanban.modal.cancel') }}
                    </button>
                </div>
                <form x-ref="interviewForm" method="POST" x-bind:action="transitionUrl" @submit.prevent="submitTransition($event)" class="mt-4 space-y-3" data-interview-schedule-form>
                    @csrf
                    <input type="hidden" name="to_stage_id" :value="pending.toStageId">
                    <input type="hidden" name="transition_type" value="interview">
                    <input type="hidden" name="job_id" value="{{ optional($selectedJob)->id }}">
                    <input type="hidden" name="company_id" value="{{ $selectedCompanyId }}">

                    <label class="block text-sm text-slate-700">{{ __('kanban.modal.scheduled_for') }}
                        <input type="datetime-local" name="scheduled_for" required class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                    </label>
                    <label class="block text-sm text-slate-700">{{ __('interviews.fields.duration_minutes') }}
                        <input type="number" name="duration_minutes" min="15" step="15" value="60" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                    </label>
                    <input type="hidden" name="timezone" value="{{ config('app.timezone', 'UTC') }}">
                    <label class="block text-sm text-slate-700">{{ __('interviews.fields.interviewers') }}
                        <select name="interviewer_user_ids[]" multiple required data-placeholder="{{ __('interviews.fields.interviewers') }}" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                            @foreach($interviewers as $interviewer)
                                <option value="{{ $interviewer->id }}">{{ $interviewer->profile?->full_name ?? $interviewer->email }}</option>
                            @endforeach
                        </select>
                    </label>
                    <div data-interview-meeting-link-group>
                        <label class="block text-sm text-slate-700">{{ __('interviews.fields.meeting_link') }}
                            <input type="url" name="meeting_link" value="{{ old('meeting_link', $actorZoomLink) }}" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                        </label>
                    </div>
                    <div class="hidden" data-interview-location-address-group>
                        <label class="block text-sm text-slate-700">{{ __('interviews.fields.location_address') }}
                            <input type="text" name="location_address" value="{{ old('location_address') }}" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                        </label>
                    </div>
                    <label class="block text-sm text-slate-700">{{ __('interviews.fields.location_type') }}
                        <select name="location_type" data-placeholder="{{ __('interviews.fields.location_type') }}" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" data-interview-location-select>
                            <option value="{{ \App\Models\Interview::LOCATION_ZOOM }}" @selected(old('location_type', \App\Models\Interview::LOCATION_ZOOM) === \App\Models\Interview::LOCATION_ZOOM)>{{ __('interviews.location_types.zoom') }}</option>
                            <option value="{{ \App\Models\Interview::LOCATION_IN_PERSON }}" @selected(old('location_type') === \App\Models\Interview::LOCATION_IN_PERSON)>{{ __('interviews.location_types.in_person') }}</option>
                            <option value="{{ \App\Models\Interview::LOCATION_OTHER }}" @selected(old('location_type') === \App\Models\Interview::LOCATION_OTHER)>{{ __('interviews.location_types.other') }}</option>
                        </select>
                    </label>
                    <label class="block text-sm text-slate-700">{{ __('kanban.modal.channel') }}
                        <input type="text" name="channel" value="{{ old('channel') }}" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                    </label>
                    <label class="block text-sm text-slate-700">{{ __('interviews.fields.notes') }}
                        <textarea name="notes" rows="2" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm"></textarea>
                    </label>

                    <template x-if="pending.isTerminal">
                        <label class="flex items-center gap-2 text-sm text-slate-700">
                            <input type="checkbox" name="confirm_terminal" value="1" required>
                            <span>{{ __('kanban.modal.terminal_confirm') }}</span>
                        </label>
                    </template>
                    <div class="sticky bottom-0 flex justify-end gap-2 bg-white/95 pt-2">
                        <button type="button" @click="closeModal(true)" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">{{ __('kanban.modal.cancel') }}</button>
                        <button type="submit" class="rounded-xl bg-success-600 px-3 py-2 text-sm font-semibold text-white">{{ __('kanban.modal.save') }}</button>
                    </div>
                </form>
            </div>
        </div>

        <div
            x-cloak
            x-show="modal.type === 'rejected'"
            x-on:keydown.escape.window="closeModal()"
            @click.self="closeModal()"
            class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/20 p-4 backdrop-blur-sm"
        >
            <div class="mx-auto my-6 w-full max-w-xl rounded-2xl border border-white/70 bg-white/90 p-6 shadow-xl max-h-[calc(100vh-3rem)] overflow-y-auto">
                <div class="flex items-center justify-between gap-3">
                    <h3 class="text-lg font-semibold text-slate-900">{{ __('kanban.modal.rejected_title') }}</h3>
                    <button type="button" @click="closeModal()" class="rounded-lg border border-slate-200 bg-white px-2 py-1 text-sm text-slate-700">
                        {{ __('kanban.modal.cancel') }}
                    </button>
                </div>
                <form x-ref="rejectedForm" method="POST" x-bind:action="transitionUrl" @submit.prevent="submitTransition($event)" class="mt-4 space-y-3">
                    @csrf
                    <input type="hidden" name="to_stage_id" :value="pending.toStageId">
                    <input type="hidden" name="transition_type" value="rejected">
                    <input type="hidden" name="job_id" value="{{ optional($selectedJob)->id }}">
                    <input type="hidden" name="company_id" value="{{ $selectedCompanyId }}">

                    <label class="block text-sm text-slate-700">{{ __('kanban.modal.reason') }}
                        <input type="text" name="reason" x-model="rejectionDraft.reason" required class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                    </label>
                    <label class="block text-sm text-slate-700">{{ __('kanban.modal.draft_subject') }}
                        <input type="text" name="draft_subject" x-model="rejectionDraft.draftSubject" required class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                    </label>
                    <label class="block text-sm text-slate-700">{{ __('kanban.modal.draft_body') }}
                        <textarea name="draft_body" x-model="rejectionDraft.draftBody" rows="5" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm"></textarea>
                    </label>
                    <template x-if="pending.isTerminal">
                        <label class="flex items-center gap-2 text-sm text-slate-700">
                            <input type="checkbox" name="confirm_terminal" value="1" required>
                            <span>{{ __('kanban.modal.terminal_confirm') }}</span>
                        </label>
                    </template>
                    <div class="sticky bottom-0 flex justify-end gap-2 bg-white/95 pt-2">
                        <button type="button" @click="closeModal(true)" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">{{ __('kanban.modal.cancel') }}</button>
                        <button type="submit" class="rounded-xl bg-success-600 px-3 py-2 text-sm font-semibold text-white">{{ __('kanban.modal.save_draft') }}</button>
                        <button type="submit" name="send_rejection_now" value="1" class="rounded-xl bg-danger-600 px-3 py-2 text-sm font-semibold text-white">{{ __('kanban.modal.send') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function kanbanBoard() {
            return {
                dragging: null,
                pending: {
                    applicationId: null,
                    toStageId: null,
                    isTerminal: false,
                    confirmTerminal: false,
                },
                rejectionDraft: {
                    reason: '',
                    xaiReason: '',
                    draftSubject: '',
                    draftBody: '',
                },
                modal: { type: null },
                transitionUrl: '',
                rejectionSubjectTemplate: @json(__('kanban.mail.rejection_subject', ['job' => ':job'])),
                rejectionBodyTemplate: @json(__('kanban.rejection.default_draft', ['job' => ':job', 'reason' => ':reason'])),
                rejectionNoReason: @json(__('candidates.detail.not_available')),
                init() {
                    const error = @json(session('error'));
                    if (error) {
                        document.dispatchEvent(new CustomEvent('app:toast', { detail: { type: 'warning', message: error } }));
                    }
                },
                startDrag(event, el) {
                    this.dragging = {
                        applicationId: el.dataset.applicationId,
                        fromStageId: el.dataset.fromStageId,
                    };
                    event.dataTransfer.effectAllowed = 'move';
                },
                dropOnStage(event, stageEl) {
                    stageEl.classList.remove('ring-2', 'ring-aura-300');
                    if (!this.dragging) {
                        return;
                    }
                    const toStageId = stageEl.dataset.stageId;
                    const stageKey = (stageEl.dataset.stageKey || '').toLowerCase();
                    const isTerminal = stageEl.dataset.stageTerminal === '1';

                    if (this.dragging.fromStageId === toStageId) {
                        document.dispatchEvent(new CustomEvent('app:toast', { detail: { type: 'warning', message: @json(__('kanban.errors.same_stage')) } }));
                        return;
                    }

                    this.pending.applicationId = this.dragging.applicationId;
                    this.pending.toStageId = toStageId;
                    this.pending.isTerminal = isTerminal;
                    this.pending.confirmTerminal = false;
                    this.transitionUrl = `{{ url('/candidates') }}/${this.pending.applicationId}/kanban-transition`;
                    this.syncModalActions();

                    if (stageKey.includes('interview')) {
                        this.modal.type = 'interview';
                        return;
                    }

                    if (stageKey.includes('reject')) {
                        const cardEl = document.querySelector(`[data-application-id="${this.dragging.applicationId}"]`);
                        const jobTitle = cardEl?.dataset.jobTitle || '';
                        const xaiReason = (cardEl?.dataset.xaiReason || '').trim();
                        const existingSubject = (cardEl?.dataset.rejectionSubject || '').trim();
                        const existingBody = (cardEl?.dataset.rejectionBody || '').trim();
                        const reasonForTemplate = xaiReason !== '' ? xaiReason : this.rejectionNoReason;

                        this.rejectionDraft = {
                            reason: '',
                            xaiReason: xaiReason,
                            draftSubject: existingSubject !== '' ? existingSubject : this.rejectionSubjectTemplate.replace(':job', jobTitle || this.rejectionNoReason),
                            draftBody: existingBody !== ''
                                ? existingBody
                                : this.rejectionBodyTemplate
                                    .replace(':job', jobTitle || this.rejectionNoReason)
                                    .replace(':reason', reasonForTemplate),
                        };

                        this.modal.type = 'rejected';
                        return;
                    }

                    if (isTerminal) {
                        const confirmed = window.confirm(@json(__('kanban.modal.terminal_confirm')));
                        if (!confirmed) {
                            this.closeModal(true);
                            return;
                        }
                        this.pending.confirmTerminal = true;
                    }

                    this.submitStandardTransition();
                },
                closeModal(showCancelledToast = false) {
                    this.modal.type = null;
                    this.pending = { applicationId: null, toStageId: null, isTerminal: false, confirmTerminal: false };
                    this.rejectionDraft = { reason: '', xaiReason: '', draftSubject: '', draftBody: '' };
                    this.transitionUrl = '';
                    if (showCancelledToast) {
                        document.dispatchEvent(new CustomEvent('app:toast', { detail: { type: 'warning', message: @json(__('kanban.errors.cancelled_move')) } }));
                    }
                },
                syncModalActions() {
                    if (!this.transitionUrl) {
                        return;
                    }

                    if (this.$refs.interviewForm) {
                        this.$refs.interviewForm.setAttribute('action', this.transitionUrl);
                    }

                    if (this.$refs.rejectedForm) {
                        this.$refs.rejectedForm.setAttribute('action', this.transitionUrl);
                    }
                },
                submitTransition(event) {
                    if (!this.transitionUrl) {
                        document.dispatchEvent(new CustomEvent('app:toast', { detail: { type: 'warning', message: @json(__('kanban.errors.cancelled_move')) } }));
                        return;
                    }

                    const form = event.target;
                    form.setAttribute('action', this.transitionUrl);
                    form.submit();
                },
                submitStandardTransition() {
                    if (!this.transitionUrl || !this.$refs.standardForm) {
                        document.dispatchEvent(new CustomEvent('app:toast', { detail: { type: 'warning', message: @json(__('kanban.errors.cancelled_move')) } }));
                        return;
                    }

                    const form = this.$refs.standardForm;
                    form.setAttribute('action', this.transitionUrl);

                    const toStageInput = form.querySelector('input[name="to_stage_id"]');
                    if (toStageInput) {
                        toStageInput.value = this.pending.toStageId || '';
                    }

                    const terminalConfirmInput = form.querySelector('input[name="confirm_terminal"]');
                    if (terminalConfirmInput) {
                        terminalConfirmInput.value = this.pending.confirmTerminal ? '1' : '0';
                    }

                    form.submit();
                },
            };
        }
    </script>
    <script>
        (() => {
            const initInterviewLocationForms = () => {
                document.querySelectorAll('[data-interview-schedule-form]').forEach((form) => {
                    const locationSelect = form.querySelector('[data-interview-location-select]');
                    const meetingLinkGroup = form.querySelector('[data-interview-meeting-link-group]');
                    const addressGroup = form.querySelector('[data-interview-location-address-group]');
                    const addressInput = addressGroup?.querySelector('input[name="location_address"]');

                    if (!locationSelect) {
                        return;
                    }

                    const syncInterviewLocationFields = () => {
                        const isInPerson = locationSelect.value === @json(\App\Models\Interview::LOCATION_IN_PERSON);

                        if (meetingLinkGroup) {
                            meetingLinkGroup.classList.toggle('hidden', isInPerson);
                        }

                        if (addressGroup) {
                            addressGroup.classList.toggle('hidden', !isInPerson);
                        }

                        if (addressInput) {
                            addressInput.required = isInPerson;
                        }
                    };

                    locationSelect.addEventListener('change', syncInterviewLocationFields);
                    syncInterviewLocationFields();
                });
            };

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initInterviewLocationForms, { once: true });
                return;
            }

            initInterviewLocationForms();
        })();
    </script>
</x-shell-layout>
