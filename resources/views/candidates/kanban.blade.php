<x-shell-layout :title="__('kanban.title').' | '.config('app.name')">
    <div
        x-data="kanbanBoard()"
        x-init="init()"
        class="space-y-4"
    >
        <x-glass-card :title="__('kanban.title')" :subtitle="__('kanban.subtitle')">
            @if(session('status'))
                <x-toast-alert type="success">{{ session('status') }}</x-toast-alert>
            @endif
            @if(session('error'))
                <x-toast-alert type="warning">{{ session('error') }}</x-toast-alert>
            @endif

            @if($requiresCompanySelection)
                <x-empty-state :title="__('kanban.select_company_title')" :message="__('kanban.select_company_message')" />
            @else
                <form method="GET" action="{{ route('candidates.kanban') }}" class="grid gap-3 md:grid-cols-4">
                    @if(auth()->user()->isSuperadmin())
                        <x-form-field :label="__('kanban.filters.company')" name="company_id">
                            <select name="company_id" data-placeholder="{{ __('kanban.filters.company_placeholder') }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2 text-sm" onchange="this.form.submit()">
                                <option value="">{{ __('kanban.filters.company_placeholder') }}</option>
                                @foreach($companies as $company)
                                    <option value="{{ $company->id }}" @selected((string) $selectedCompanyId === (string) $company->id)>{{ $company->name }}</option>
                                @endforeach
                            </select>
                        </x-form-field>
                    @endif
                    <x-form-field :label="__('kanban.filters.job')" name="job_id">
                        <select name="job_id" data-placeholder="{{ __('kanban.filters.job_placeholder') }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2 text-sm" onchange="this.form.submit()">
                            <option value="">{{ __('kanban.filters.job_placeholder') }}</option>
                            @foreach($jobs as $job)
                                <option value="{{ $job->id }}" @selected((string) optional($selectedJob)->id === (string) $job->id)>{{ $job->title }}</option>
                            @endforeach
                        </select>
                    </x-form-field>
                </form>
            @endif
        </x-glass-card>

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
            <div class="pb-2">
                <div class="grid grid-cols-[repeat(auto-fit,minmax(18rem,1fr))] gap-4">
                    @foreach($boardStages as $stage)
                        <section
                            class="min-w-0 rounded-2xl border border-white/70 bg-white/60 p-3 shadow-[0_20px_40px_-30px_rgba(100,103,242,0.35)] backdrop-blur-2xl"
                            data-stage-id="{{ $stage->target_stage_id ?? $stage->id }}"
                            data-stage-key="{{ $stage->stage_key }}"
                            data-stage-terminal="{{ $stage->is_terminal ? '1' : '0' }}"
                            @dragover.prevent="$el.classList.add('ring-2','ring-aura-300')"
                            @dragleave="$el.classList.remove('ring-2','ring-aura-300')"
                            @drop.prevent="dropOnStage($event, $el)"
                        >
                            <header class="mb-3 flex items-center justify-between">
                                <h3 class="text-sm font-semibold text-slate-900">{{ $stage->stage_label }}</h3>
                                @if($stage->is_terminal)
                                    <x-badge variant="warning">{{ __('kanban.board.terminal_short') }}</x-badge>
                                @endif
                            </header>
                            <div class="space-y-2" x-data="{ expanded: false }">
                                @php $cards = $cardsByStage[(string) $stage->id] ?? collect(); @endphp
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
                                    @endphp
                                    <article
                                        @if($loop->index >= 3)
                                            x-show="expanded"
                                            x-cloak
                                        @endif
                                        draggable="true"
                                        class="cursor-grab rounded-xl border border-slate-200 bg-white/90 p-3 transition-weightless hover:-translate-y-0.5 hover:shadow-md active:cursor-grabbing"
                                        data-application-id="{{ $card->id }}"
                                        data-from-stage-id="{{ $card->current_stage_id }}"
                                        data-candidate-name="{{ $name }}"
                                        data-job-title="{{ (string) ($card->job?->title ?? '') }}"
                                        data-xai-reason="{{ $xaiReason }}"
                                        data-rejection-subject="{{ $rejectionSubject }}"
                                        data-rejection-body="{{ $rejectionBody }}"
                                        @dragstart="startDrag($event, $el)"
                                    >
                                        <div class="flex items-start justify-between gap-2">
                                            <p class="text-sm font-semibold text-slate-900">{{ $name }}</p>
                                            @php
                                                $cardStatusVariant = match ((string) $card->status) {
                                                    'hired' => 'success',
                                                    'rejected', 'withdrawn' => 'danger',
                                                    default => 'pending',
                                                };
                                            @endphp
                                            <x-badge :variant="$cardStatusVariant">{{ __('candidates.list.status.'.$card->status) }}</x-badge>
                                        </div>
                                        @if($blind)
                                            <p class="mt-1 text-[11px] text-slate-500">{{ __('candidates.detail.blind_mode') }}</p>
                                        @endif
                                        <p class="mt-1 text-xs text-slate-600">{{ $card->job?->title }}</p>


                                    </article>
                                @empty
                                    <div class="rounded-xl border border-dashed border-slate-200 bg-white/50 p-3 text-xs text-slate-600">
                                        {{ __('kanban.board.empty_stage') }}
                                    </div>
                                @endforelse

                                @if($cards->count() > 3)
                                    <button 
                                        type="button" 
                                        @click="expanded = !expanded" 
                                        class="mt-2 w-full rounded-lg bg-white/50 px-3 py-1.5 text-xs font-semibold text-slate-600 transition-weightless hover:bg-white/80"
                                        x-text="expanded ? 'Voir moins' : 'Voir plus (' + ({{ $cards->count() }} - 3) + ' autres)'"
                                    >Voir plus</button>
                                @endif
                            </div>
                        </section>
                    @endforeach
                </div>
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
