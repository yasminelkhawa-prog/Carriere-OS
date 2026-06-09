<x-shell-layout :title="__('communications.title').' | '.config('app.name')">
    <x-glass-card :title="__('communications.title')" :subtitle="__('communications.subtitle')">
        @if(session('status'))
            <x-toast-alert type="success">{{ session('status') }}</x-toast-alert>
        @endif

        @if($errors->any())
            <x-toast-alert type="warning">{{ $errors->first() }}</x-toast-alert>
        @endif

        @if($requiresCompanySelection)
            <div class="mt-4">
                <x-empty-state :title="__('master.company_filter.label')" :message="__('master.common.company_scope_required')" />
            </div>
        @else
            <div class="space-y-6">
                <form method="GET" action="{{ route('admin.email-templates.index') }}" class="mt-4 grid gap-3 md:grid-cols-4">
                    @if(auth()->user()?->isSuperadmin())
                        <x-form-field :label="__('master.company_filter.label')" name="company_id">
                            <select name="company_id" data-placeholder="{{ __('master.company_filter.placeholder') }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2 text-sm">
                                <option value="">{{ __('master.company_filter.placeholder') }}</option>
                                @foreach($companies as $company)
                                    <option value="{{ $company->id }}" @selected((string) $selectedCompanyId === (string) $company->id)>{{ $company->name }}</option>
                                @endforeach
                            </select>
                        </x-form-field>
                    @endif

                    <x-form-field :label="__('communications.manager.template_key')" name="template_key">
                        <select name="template_key" data-placeholder="{{ __('communications.manager.template_key') }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2 text-sm">
                            @foreach($templateKeys as $templateKey)
                                <option value="{{ $templateKey }}" @selected($selectedTemplateKey === $templateKey)>
                                    {{ __('communications.templates.'.$templateKey) }}
                                </option>
                            @endforeach
                        </select>
                    </x-form-field>

                    <x-form-field :label="__('communications.manager.language')" name="language">
                        <select name="language" data-placeholder="{{ __('communications.manager.language') }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2 text-sm">
                            <option value="en" @selected($selectedLanguage === 'en')>{{ __('ui.locale.en') }}</option>
                            <option value="fr" @selected($selectedLanguage === 'fr')>{{ __('ui.locale.fr') }}</option>
                        </select>
                    </x-form-field>

                    <x-form-field :label="__('communications.logs.status')" name="status">
                        <select name="status" data-placeholder="{{ __('communications.logs.all_statuses') }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2 text-sm">
                            <option value="">{{ __('communications.logs.all_statuses') }}</option>
                            @foreach($availableStatuses as $statusOption)
                                <option value="{{ $statusOption }}" @selected($selectedStatus === $statusOption)>
                                    {{ __('communications.status.'.$statusOption) }}
                                </option>
                            @endforeach
                        </select>
                    </x-form-field>

                    <div class="md:col-span-4 flex justify-end">
                        <button type="submit" class="rounded-xl border border-aura-300/50 bg-white px-4 py-2 text-sm font-medium text-slate-900 transition-weightless hover:bg-white">
                            {{ __('communications.logs.filter') }}
                        </button>
                    </div>
                </form>

                <form method="POST" action="{{ route('admin.email-templates.upsert') }}" class="space-y-4 rounded-2xl border border-white/70 bg-white/70 p-4">
                    @csrf
                    @if(auth()->user()?->isSuperadmin())
                        <input type="hidden" name="company_id" value="{{ $selectedCompanyId }}">
                    @endif
                    <input type="hidden" name="template_key" value="{{ $selectedTemplateKey }}">
                    <input type="hidden" name="language" value="{{ $selectedLanguage }}">

                    <div class="grid gap-4 lg:grid-cols-2">
                        <x-form-field :label="__('communications.manager.subject')" name="subject_template">
                            <textarea id="subject-template-input" name="subject_template" rows="3" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2 text-sm">{{ old('subject_template', (string) ($selectedTemplate?->subject_template ?? '')) }}</textarea>
                        </x-form-field>

                        <x-form-field :label="__('communications.manager.sample_variables')" name="preview_variables">
                            <textarea id="preview-variables-input" rows="3" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2 text-sm">{{ $sampleVariablesJson }}</textarea>
                        </x-form-field>
                    </div>

                    <x-form-field :label="__('communications.manager.body')" name="body_template">
                        <textarea id="body-template-input" name="body_template" rows="8" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2 text-sm">{{ old('body_template', (string) ($selectedTemplate?->body_template ?? '')) }}</textarea>
                    </x-form-field>

                    <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" name="is_active" value="1" class="rounded border-aura-300 text-aura-600 focus:ring-aura-400" @checked((bool) old('is_active', $selectedTemplate?->is_active ?? true))>
                        <span>{{ __('communications.manager.active') }}</span>
                    </label>

                    <div class="flex justify-end">
                        <button type="submit" class="rounded-xl bg-success-600 px-4 py-2 text-sm font-semibold text-white transition-weightless hover:bg-success-700">
                            {{ __('communications.manager.save') }}
                        </button>
                    </div>
                </form>

                <div class="grid gap-4 lg:grid-cols-2">
                    <div class="rounded-2xl border border-slate-200 bg-white/80 p-4">
                        <p class="text-xs uppercase tracking-wide text-slate-600">{{ __('communications.manager.preview_subject') }}</p>
                        <p id="preview-subject-output" class="mt-2 text-sm text-slate-800"></p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white/80 p-4">
                        <p class="text-xs uppercase tracking-wide text-slate-600">{{ __('communications.manager.preview_body') }}</p>
                        <pre id="preview-body-output" class="mt-2 whitespace-pre-wrap text-sm text-slate-800"></pre>
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white/75 p-4">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="text-sm font-semibold text-slate-900">{{ __('communications.logs.title') }}</p>
                        <p class="text-xs text-slate-600">{{ __('communications.logs.subtitle') }}</p>
                    </div>
                    <div class="mt-3 overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-xs">
                            <thead class="bg-white/80">
                                <tr>
                                    <th class="px-2 py-2 text-left font-semibold text-slate-700">{{ __('communications.logs.status') }}</th>
                                    <th class="px-2 py-2 text-left font-semibold text-slate-700">{{ __('communications.logs.to') }}</th>
                                    <th class="px-2 py-2 text-left font-semibold text-slate-700">{{ __('communications.logs.subject') }}</th>
                                    <th class="px-2 py-2 text-left font-semibold text-slate-700">{{ __('communications.logs.template') }}</th>
                                    <th class="px-2 py-2 text-left font-semibold text-slate-700">{{ __('communications.logs.related') }}</th>
                                    <th class="px-2 py-2 text-left font-semibold text-slate-700">{{ __('communications.logs.created') }}</th>
                                    <th class="px-2 py-2 text-left font-semibold text-slate-700">{{ __('communications.logs.sent') }}</th>
                                    <th class="px-2 py-2 text-left font-semibold text-slate-700">{{ __('communications.logs.error') }}</th>
                                    <th class="px-2 py-2 text-left font-semibold text-slate-700">{{ __('communications.logs.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse($logs as $log)
                                    <tr>
                                        <td class="px-2 py-2 text-slate-700">{{ __('communications.status.'.$log->status) }}</td>
                                        <td class="px-2 py-2 text-slate-700">{{ $log->to_email }}</td>
                                        <td class="px-2 py-2 text-slate-700">{{ \Illuminate\Support\Str::limit((string) $log->subject, 70) }}</td>
                                        <td class="px-2 py-2 text-slate-700">{{ $log->template_key ? __('communications.templates.'.$log->template_key) : '-' }}</td>
                                        <td class="px-2 py-2 text-slate-700">{{ $log->related_entity_type && $log->related_entity_id ? $log->related_entity_type.'#'.$log->related_entity_id : '-' }}</td>
                                        <td class="px-2 py-2 text-slate-700">{{ optional($log->created_at)->format('Y-m-d H:i') }}</td>
                                        <td class="px-2 py-2 text-slate-700">{{ optional($log->sent_at)->format('Y-m-d H:i') ?: '-' }}</td>
                                        <td class="px-2 py-2 text-danger-700">{{ \Illuminate\Support\Str::limit((string) ($log->error_message ?? ''), 80) ?: '-' }}</td>
                                        <td class="px-2 py-2">
                                            @if($log->status === \App\Models\EmailOutboxLog::STATUS_FAILED)
                                                <form method="POST" action="{{ route('admin.email-templates.retry-outbox', $log) }}" class="inline">
                                                    @csrf
                                                    @if(auth()->user()?->isSuperadmin())
                                                        <input type="hidden" name="company_id" value="{{ $selectedCompanyId }}">
                                                    @endif
                                                    <input type="hidden" name="template_key" value="{{ $selectedTemplateKey }}">
                                                    <input type="hidden" name="language" value="{{ $selectedLanguage }}">
                                                    <input type="hidden" name="status" value="{{ (string) ($selectedStatus ?? '') }}">
                                                    <button type="submit" class="inline-flex size-8 items-center justify-center rounded-lg border border-success-300/60 bg-success-50 text-success-800 transition-weightless hover:bg-success-100/80" title="{{ __('communications.logs.retry') }}" aria-label="{{ __('communications.logs.retry') }}">
                                                        <svg class="size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-4.992m0 0-2.446 2.446a8.25 8.25 0 1 0 2.056 5.519" />
                                                        </svg>
                                                        <span class="sr-only">{{ __('communications.logs.retry') }}</span>
                                                    </button>
                                                </form>
                                            @else
                                                <span class="text-slate-400">-</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="px-2 py-6">
                                            <x-empty-state :title="__('communications.logs.empty_title')" :message="__('communications.logs.empty_message')" />
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if($logs instanceof \Illuminate\Contracts\Pagination\Paginator)
                        <div class="mt-4">{{ $logs->links() }}</div>
                    @endif
                </div>
            </div>
        @endif
    </x-glass-card>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const subjectInput = document.getElementById('subject-template-input');
            const bodyInput = document.getElementById('body-template-input');
            const variablesInput = document.getElementById('preview-variables-input');
            const subjectOutput = document.getElementById('preview-subject-output');
            const bodyOutput = document.getElementById('preview-body-output');

            if (!subjectInput || !bodyInput || !variablesInput || !subjectOutput || !bodyOutput) {
                return;
            }

            const renderWithVariables = function (template, variables) {
                return template.replace(/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/g, function (_, key) {
                    return Object.prototype.hasOwnProperty.call(variables, key) ? String(variables[key] ?? '') : '';
                });
            };

            const refreshPreview = function () {
                let parsed = {};
                try {
                    parsed = JSON.parse(variablesInput.value || '{}');
                } catch (_error) {
                    parsed = {};
                }

                subjectOutput.textContent = renderWithVariables(subjectInput.value || '', parsed);
                bodyOutput.textContent = renderWithVariables(bodyInput.value || '', parsed);
            };

            ['input', 'change'].forEach(function (eventName) {
                subjectInput.addEventListener(eventName, refreshPreview);
                bodyInput.addEventListener(eventName, refreshPreview);
                variablesInput.addEventListener(eventName, refreshPreview);
            });

            refreshPreview();
        });
    </script>
</x-shell-layout>
