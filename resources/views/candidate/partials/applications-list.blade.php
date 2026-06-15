@if(($applications ?? collect())->isEmpty())
    <x-empty-state :title="__('candidate_portal.applications.empty_title')" :message="__('candidate_portal.applications.empty_message')" />
@else
    <div class="space-y-4">
        @foreach($applications as $application)
            @php
                $stageLabel = (string) ($application->currentStage?->stage_label ?? __('candidate_portal.applications.unknown_stage'));
                $isHired = (bool) (($hiredFlowApplications ?? collect())->get((string) $application->id, (string) $application->status === \App\Models\Application::STATUS_HIRED));
                $feedbackEligible = (bool) (($reverseFeedbackEligibility ?? collect())->get((string) $application->id, false));
                $reverseFeedback = $application->reverseFeedback;
                $contract = $application->contract;
                $onboardingDocuments = $application->onboardingDocuments ?? collect();
                $uploadedDocTypes = $onboardingDocuments->pluck('doc_type')->map(static fn ($value) => (string) $value)->filter()->unique()->values();
                $docTypeOptions = collect(\App\Models\OnboardingDocument::types());
                $selectedDocType = (string) old('doc_type', (string) ($docTypeOptions->first() ?? ''));
                $allDocTypesUploaded = $docTypeOptions->isNotEmpty() && $docTypeOptions->every(static fn (string $docType): bool => $uploadedDocTypes->contains($docType));
                $selectedDocTypeUploaded = $selectedDocType !== '' && $uploadedDocTypes->contains($selectedDocType);
                $uploadDisabled = $allDocTypesUploaded || $selectedDocTypeUploaded;
                $onboardingTasks = $application->onboardingTasks ?? collect();
                $nextStep = (string) (($nextSteps ?? collect())->get((string) $application->id, __('candidate_portal.applications.next_step_default')));
                $statusTracker = (array) (($statusTrackers ?? collect())->get((string) $application->id, []));
            @endphp
            <x-glass-card>
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-xs uppercase tracking-[0.24em] text-aura-700/85">{{ $application->job?->department?->name ?? __('career.list.no_department') }}</p>
                        <h3 class="mt-1 text-lg font-semibold text-slate-900">{{ $application->job?->title ?? __('sjt.messages.unknown_job') }}</h3>
                        <p class="mt-1 text-sm text-slate-600">{{ __('candidate_portal.applications.stage_label') }}: <span class="font-medium text-slate-800">{{ $stageLabel }}</span></p>
                        <p class="mt-1 text-sm text-slate-600">{{ __('candidate_portal.applications.next_step_label') }}: <span class="font-medium text-slate-800">{{ $nextStep }}</span></p>
                    </div>
                    <x-badge>{{ __('candidates.list.status.'.$application->status) }}</x-badge>
                </div>

                @include('candidate.partials.transparency-insights', ['application' => $application, 'statusTracker' => $statusTracker])

                @if($isHired)
                    <div class="mt-4 grid gap-3 xl:grid-cols-2">
                        <div class="rounded-xl border border-slate-200 bg-white/85 p-3">
                            <p class="text-xs uppercase tracking-wide text-slate-600">{{ __('candidate_portal.onboarding.contract.title') }}</p>
                            @if($contract)
                                <div class="mt-2 flex flex-wrap items-center gap-2 text-xs text-slate-700">
                                    <x-badge>{{ __('candidate_portal.onboarding.contract.statuses.'.$contract->contract_status) }}</x-badge>
                                    <a href="{{ \App\Http\Controllers\CandidatePortalController::signedContractUrl($contract) }}" class="rounded-md border border-aura-200 px-2 py-1 text-aura-700">{{ __('candidate_portal.onboarding.contract.download') }}</a>
                                </div>
                                @if($contract->contract_status !== \App\Models\Contract::STATUS_SIGNED && ! $contract->signed_at)
                                    <form method="POST" action="{{ route('candidate.contract.sign', ['company' => $company->slug, 'application' => $application->id]) }}" class="mt-3 space-y-2">
                                        @csrf
                                        <input type="text" name="typed_signature" value="{{ old('typed_signature') }}" placeholder="{{ __('candidate_portal.onboarding.contract.typed_signature_placeholder') }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2 text-sm text-slate-900 shadow-sm" required>
                                        <label class="inline-flex items-start gap-2 text-xs text-slate-700">
                                            <input type="checkbox" name="acknowledgement" value="1" class="mt-0.5 rounded border-aura-300 text-aura-600 focus:ring-aura-400" required>
                                            <span>{{ __('candidate_portal.onboarding.contract.acknowledgement') }}</span>
                                        </label>
                                        <button type="submit" class="rounded-xl bg-success-600 px-4 py-2 text-sm font-semibold text-white transition-weightless hover:bg-success-700">{{ __('candidate_portal.onboarding.contract.sign_action') }}</button>
                                    </form>
                                @endif
                            @else
                                <p class="mt-2 text-sm text-slate-600">{{ __('candidate_portal.onboarding.contract.empty') }}</p>
                            @endif
                        </div>

                        <div class="rounded-xl border border-slate-200 bg-white/85 p-3">
                            <p class="text-xs uppercase tracking-wide text-slate-600">{{ __('candidate_portal.onboarding.documents.title') }}</p>
                            <form method="POST" action="{{ route('candidate.onboarding-documents.store', ['company' => $company->slug, 'application' => $application->id]) }}" enctype="multipart/form-data" class="mt-3 space-y-2">
                                @csrf
                                <select name="doc_type" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2 text-sm text-slate-900 shadow-sm" @disabled($allDocTypesUploaded) required>
                                    @foreach(\App\Models\OnboardingDocument::types() as $docTypeOption)
                                        <option value="{{ $docTypeOption }}" @selected($selectedDocType === $docTypeOption)>{{ __('candidate_portal.onboarding.documents.types.'.$docTypeOption) }}</option>
                                    @endforeach
                                </select>
                                <input type="file" name="file" accept=".pdf,.doc,.docx,.png,.jpg,.jpeg" class="w-full rounded-xl border border-aura-200/40 bg-white/85 px-3 py-2 text-sm text-slate-900 shadow-sm" @disabled($uploadDisabled) required>
                                <button type="submit" class="rounded-xl bg-success-600 px-4 py-2 text-sm font-semibold text-white transition-weightless hover:bg-success-700 disabled:cursor-not-allowed disabled:bg-slate-400" @disabled($uploadDisabled)>{{ $uploadDisabled ? __('candidate_portal.onboarding.documents.uploaded_action') : __('candidate_portal.onboarding.documents.upload_action') }}</button>
                            </form>
                        </div>
                    </div>

                    <div class="mt-3 rounded-xl border border-slate-200 bg-white/85 p-3">
                        <p class="text-xs uppercase tracking-wide text-slate-600">{{ __('candidate_portal.onboarding.tasks.title') }}</p>
                        <div class="mt-2 space-y-2">
                            @forelse($onboardingTasks as $task)
                                <div class="flex items-center justify-between rounded-lg border border-slate-200 bg-white p-2 text-xs">
                                    <div>
                                        <p class="font-semibold text-slate-800">{{ $task->task_name }}</p>
                                        <p class="text-slate-600">{{ __('candidate_portal.onboarding.tasks.due_at') }}: {{ $task->due_at ? $task->due_at->format('Y-m-d H:i').' UTC' : __('candidates.detail.not_available') }}</p>
                                    </div>
                                    <form method="POST" action="{{ route('candidate.onboarding-tasks.toggle', ['company' => $company->slug, 'application' => $application->id, 'onboardingTask' => $task->id]) }}">
                                        @csrf
                                        <button type="submit" class="rounded-md border px-2 py-1 text-xs {{ $task->is_completed ? 'border-success-200 bg-success-50 text-success-800' : 'border-aura-200 bg-aura-50 text-aura-800' }}">{{ $task->is_completed ? __('candidate_portal.onboarding.tasks.mark_open') : __('candidate_portal.onboarding.tasks.mark_done') }}</button>
                                    </form>
                                </div>
                            @empty
                                <p class="text-xs text-slate-600">{{ __('candidate_portal.onboarding.tasks.empty') }}</p>
                            @endforelse
                        </div>
                    </div>
                @endif

                @if($feedbackEligible || $reverseFeedback)
                    @if($reverseFeedback)
                        <div class="mt-4 rounded-xl border border-success-200 bg-success-50/70 px-3 py-2 text-sm text-success-900">{{ __('candidate_portal.feedback.already_submitted') }}</div>
                    @else
                        <form method="POST" action="{{ route('candidate.reverse-feedback.store', ['company' => $company->slug, 'application' => $application->id]) }}" class="mt-4 space-y-3 rounded-2xl border border-slate-200 bg-white/85 p-4">
                            @csrf
                            <input type="hidden" name="is_anonymous" value="1">
                            <p class="text-sm font-semibold text-slate-900">{{ __('candidate_portal.feedback.form_title') }}</p>
                            <div class="grid gap-3 md:grid-cols-3">
                                @foreach(['rating_clarity', 'rating_speed', 'rating_kindness'] as $ratingField)
                                    <x-form-field :label="__('candidate_portal.feedback.'.$ratingField)" :name="$ratingField">
                                        <select name="{{ $ratingField }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2 text-sm text-slate-900 shadow-sm">
                                            <option value="">{{ __('candidate_portal.feedback.rating_placeholder') }}</option>
                                            @for($i = 1; $i <= 5; $i++)
                                                <option value="{{ $i }}" @selected((string) old($ratingField) === (string) $i)>{{ $i }}</option>
                                            @endfor
                                        </select>
                                    </x-form-field>
                                @endforeach
                            </div>
                            <textarea name="comment" rows="3" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2 text-sm text-slate-900 shadow-sm" placeholder="{{ __('candidate_portal.feedback.comment_placeholder') }}">{{ old('comment') }}</textarea>
                            <button type="submit" class="rounded-xl bg-success-600 px-4 py-2 text-sm font-semibold text-white transition-weightless hover:bg-success-700">{{ __('candidate_portal.feedback.submit_action') }}</button>
                        </form>
                    @endif
                @endif
            </x-glass-card>
        @endforeach
    </div>
@endif
