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
            <div class="space-y-2">
                <!-- Hidden navigation form for state changes -->
                <form id="navigation-form" method="GET" action="{{ route('admin.email-templates.index') }}" class="hidden">
                    <input type="hidden" name="template_key" id="selected-template-key-input" value="{{ $selectedTemplateKey }}">
                    <input type="hidden" name="language" id="selected-language-input" value="{{ $selectedLanguage }}">
                    <input type="hidden" name="status" value="{{ (string) ($selectedStatus ?? '') }}">
                    @if(auth()->user()?->isSuperadmin())
                        <input type="hidden" name="company_id" value="{{ $selectedCompanyId }}">
                    @endif
                </form>

                <!-- Template Key (Trigger) Selection Cards -->
                <div class="space-y-3">
                    <div class="space-y-1">
                        <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wider">
                            {{ $selectedLanguage === 'fr' ? 'Événements déclencheurs' : 'Triggering Events' }}
                        </h3>
                        <p class="text-xs text-slate-500">
                            {{ $selectedLanguage === 'fr' ? "Sélectionnez l'un des événements ci-dessous pour modifier son modèle d'e-mail automatique." : 'Select one of the triggering events below to configure its automated email template.' }}
                        </p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                        @foreach($templateKeys as $templateKey)
                            @php
                                $isActive = $selectedTemplateKey === $templateKey;
                                $icon = match($templateKey) {
                                    'application_acknowledgement' => 'inbox',
                                    'application_portal_verification' => 'key',
                                    'interview_confirmation' => 'calendar',
                                    'onboarding_welcome_after_signing' => 'document-check',
                                    'rejection_decision' => 'x-circle',
                                    default => 'inbox',
                                };
                            @endphp
                            <div onclick="selectTemplateKey('{{ $templateKey }}')" 
                                 class="cursor-pointer rounded-2xl border p-4 transition-all duration-200 hover:shadow-md hover:scale-[1.02] flex flex-col justify-between h-full group {{ $isActive ? 'border-[#004d3d] bg-[#004d3d]/5 ring-2 ring-[#004d3d]/10 shadow-sm' : 'border-slate-200 bg-white hover:border-slate-300' }}">
                                <div>
                                    <div class="flex items-center justify-between">
                                        <div class="rounded-xl p-2 {{ $isActive ? 'bg-[#004d3d]/10' : 'bg-slate-55 group-hover:bg-slate-100' }}">
                                            @if($icon === 'inbox')
                                                <svg class="size-5 {{ $isActive ? 'text-[#004d3d]' : 'text-slate-500' }}" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 3.75H6.912a2.25 2.25 0 0 0-2.15 1.588L2.35 13.177a2.25 2.25 0 0 0-.1.661V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18v-4.162c0-.224-.034-.447-.1-.661L19.24 5.338a2.25 2.25 0 0 0-2.15-1.588H15M2.25 13.5h3.86a2.25 2.25 0 0 1 2.008 1.24l.885 1.77a2.25 2.25 0 0 0 2.007 1.24h1.98a2.25 2.25 0 0 0 2.007-1.24l.885-1.77a2.25 2.25 0 0 1 2.007-1.24h3.86m-18 0h18" />
                                                </svg>
                                            @elseif($icon === 'key')
                                                <svg class="size-5 {{ $isActive ? 'text-[#004d3d]' : 'text-slate-500' }}" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 0 1 3 3m3 0a6 6 0 0 1-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1 1 21.75 8.25Z" />
                                                </svg>
                                            @elseif($icon === 'calendar')
                                                <svg class="size-5 {{ $isActive ? 'text-[#004d3d]' : 'text-slate-500' }}" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                                                </svg>
                                            @elseif($icon === 'document-check')
                                                <svg class="size-5 {{ $isActive ? 'text-[#004d3d]' : 'text-slate-500' }}" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                                </svg>
                                            @elseif($icon === 'x-circle')
                                                <svg class="size-5 {{ $isActive ? 'text-red-700' : 'text-slate-500' }}" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                                </svg>
                                            @endif
                                        </div>
                                        @if($isActive)
                                            <span class="inline-flex items-center justify-center rounded-full bg-[#004d3d] text-white p-0.5 size-5 shadow-sm">
                                                <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                                </svg>
                                            </span>
                                        @endif
                                    </div>
                                    <h4 class="mt-3 text-xs font-bold leading-tight {{ $isActive ? 'text-[#004d3d]' : 'text-slate-800' }}">
                                        {{ __('communications.templates.'.$templateKey) }}
                                    </h4>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- Template Form -->
                <div>
                    <form method="POST" action="{{ route('admin.email-templates.upsert') }}" class="space-y-3 rounded-2xl border border-slate-100 bg-white p-4 shadow-sm">
                            @csrf
                            @if(auth()->user()?->isSuperadmin())
                                <input type="hidden" name="company_id" value="{{ $selectedCompanyId }}">
                            @endif
                            <input type="hidden" name="template_key" value="{{ $selectedTemplateKey }}">
                            <input type="hidden" name="language" value="{{ $selectedLanguage }}">

                            <!-- Header: Name, Language tabs, Active state toggle -->
                            <div class="flex flex-wrap items-center justify-between gap-4 pb-4 border-b border-slate-100">
                                <div>
                                    <h3 class="text-base font-bold text-slate-900">
                                        {{ __('communications.templates.'.$selectedTemplateKey) }}
                                    </h3>
                                </div>
                                
                                <div class="flex items-center gap-4">
                                    <!-- Language toggle switch tabs -->
                                    <div class="flex items-center gap-0.5 bg-slate-100 p-0.5 rounded-xl border border-slate-200/50">
                                        <button type="button" onclick="selectLanguage('en')" 
                                                class="px-3 py-1 text-[10px] font-bold rounded-lg transition-all {{ $selectedLanguage === 'en' ? 'bg-[#004d3d] text-white shadow-sm' : 'text-slate-600 hover:text-slate-800' }}">
                                            {{ __('ui.locale.en') }}
                                        </button>
                                        <button type="button" onclick="selectLanguage('fr')" 
                                                class="px-3 py-1 text-[10px] font-bold rounded-lg transition-all {{ $selectedLanguage === 'fr' ? 'bg-[#004d3d] text-white shadow-sm' : 'text-slate-600 hover:text-slate-800' }}">
                                            {{ __('ui.locale.fr') }}
                                        </button>
                                    </div>

                                    <!-- Active Template Toggle Switch -->
                                    <div class="flex items-center gap-2 border-l border-slate-200 pl-4">
                                        <span class="text-[9px] font-bold text-slate-500 uppercase tracking-wider">{{ __('communications.manager.active') }}</span>
                                        <button type="button" onclick="toggleActiveTemplate()" id="is-active-toggle-btn"
                                                class="relative inline-flex h-5 w-10 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none {{ ($selectedTemplate?->is_active ?? true) ? 'bg-[#004d3d]' : 'bg-slate-200' }}">
                                            <span class="pointer-events-none inline-block size-4 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out {{ ($selectedTemplate?->is_active ?? true) ? 'translate-x-5' : 'translate-x-0' }}"></span>
                                        </button>
                                        <input type="hidden" name="is_active" id="is-active-hidden" value="{{ ($selectedTemplate?->is_active ?? true) ? '1' : '0' }}">
                                    </div>
                                </div>
                            </div>

                            <!-- Subject Input -->
                            <div>
                                <label for="subject-template-input" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">{{ __('communications.manager.subject') }}</label>
                                <input type="text" id="subject-template-input" name="subject_template" 
                                       value="{{ old('subject_template', (string) ($selectedTemplate?->subject_template ?? '')) }}"
                                       class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-4 py-2.5 text-sm focus:border-[#004d3d] focus:bg-white focus:outline-none focus:ring-1 focus:ring-[#004d3d]/30 transition-all" />
                            </div>

                            <!-- Body Input -->
                            <div>
                                <label for="body-template-input" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">{{ __('communications.manager.body') }}</label>
                                <textarea id="body-template-input" name="body_template" rows="6" 
                                          class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-4 py-3 text-sm focus:border-[#004d3d] focus:bg-white focus:outline-none focus:ring-1 focus:ring-[#004d3d]/30 transition-all font-mono leading-relaxed">{{ old('body_template', (string) ($selectedTemplate?->body_template ?? '')) }}</textarea>
                            </div>
                            <!-- Form Actions -->
                            <div class="flex justify-end pt-2">
                                <button type="submit" class="rounded-xl bg-[#004d3d] hover:bg-[#00382c] px-6 py-2.5 text-xs font-bold text-white shadow-sm transition-all flex items-center gap-2">
                                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                    </svg>
                                    {{ __('communications.manager.save') }}
                                </button>
                            </div>
                    </form>
                </div>

                <!-- Outbox Logs Section -->
                <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-150 pb-4">
                        <div>
                            <p class="text-sm font-bold text-slate-900">{{ __('communications.logs.title') }}</p>
                            <p class="text-xs text-slate-500">{{ __('communications.logs.subtitle') }}</p>
                        </div>

                        <!-- Localized Table Filters Form directly in Logs Header -->
                        <form method="GET" action="{{ route('admin.email-templates.index') }}" class="flex flex-wrap items-center gap-3">
                            <input type="hidden" name="template_key" value="{{ $selectedTemplateKey }}">
                            <input type="hidden" name="language" value="{{ $selectedLanguage }}">

                            @if(auth()->user()?->isSuperadmin())
                                <div class="w-full sm:w-48">
                                    <select name="company_id" onchange="this.form.submit()" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-[#004d3d]">
                                        <option value="">{{ __('master.company_filter.placeholder') }}</option>
                                        @foreach($companies as $company)
                                            <option value="{{ $company->id }}" @selected((string) $selectedCompanyId === (string) $company->id)>{{ $company->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif

                            <div class="w-full sm:w-48">
                                <select name="status" onchange="this.form.submit()" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-[#004d3d]">
                                    <option value="">{{ __('communications.logs.all_statuses') }}</option>
                                    @foreach($availableStatuses as $statusOption)
                                        <option value="{{ $statusOption }}" @selected($selectedStatus === $statusOption)>
                                            {{ __('communications.status.'.$statusOption) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            
                            <button type="submit" class="rounded-xl bg-white border border-slate-200 hover:border-slate-300 text-slate-700 hover:text-slate-900 px-4 py-1.5 text-xs font-semibold shadow-sm transition-all">
                                {{ __('communications.logs.filter') }}
                            </button>
                        </form>
                    </div>
                    
                    <div class="mt-3 overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-150 text-xs">
                            <thead>
                                <tr class="text-slate-400 font-semibold uppercase tracking-wider text-[10px] bg-slate-50/50">
                                    <th class="px-3 py-3 text-left">{{ __('communications.logs.status') }}</th>
                                    <th class="px-3 py-3 text-left">{{ __('communications.logs.to') }}</th>
                                    <th class="px-3 py-3 text-left">{{ __('communications.logs.subject') }}</th>
                                    <th class="px-3 py-3 text-left">{{ __('communications.logs.template') }}</th>
                                    <th class="px-3 py-3 text-left">{{ __('communications.logs.related') }}</th>
                                    <th class="px-3 py-3 text-left">{{ __('communications.logs.created') }}</th>
                                    <th class="px-3 py-3 text-left">{{ __('communications.logs.sent') }}</th>
                                    <th class="px-3 py-3 text-left">{{ __('communications.logs.error') }}</th>
                                    <th class="px-3 py-3 text-right">{{ __('communications.logs.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse($logs as $log)
                                    <tr class="hover:bg-slate-50/55 transition-colors">
                                        <td class="px-3 py-3.5">
                                            @if($log->status === 'sent')
                                                <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 border border-emerald-200/50 px-2.5 py-0.5 text-[10px] font-semibold text-emerald-800">
                                                    {{ __('communications.status.sent') }}
                                                </span>
                                            @elseif($log->status === 'queued')
                                                <span class="inline-flex items-center gap-1 rounded-full bg-blue-50 border border-blue-200/50 px-2.5 py-0.5 text-[10px] font-semibold text-blue-800">
                                                    {{ __('communications.status.queued') }}
                                                </span>
                                            @else
                                                <span class="inline-flex items-center gap-1 rounded-full bg-rose-50 border border-rose-200/50 px-2.5 py-0.5 text-[10px] font-semibold text-rose-800">
                                                    {{ __('communications.status.failed') }}
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-3.5 text-slate-800 font-medium">{{ $log->to_email }}</td>
                                        <td class="px-3 py-3.5 text-slate-600">{{ \Illuminate\Support\Str::limit((string) $log->subject, 50) }}</td>
                                        <td class="px-3 py-3.5 text-slate-500 font-mono text-[10px]">{{ $log->template_key ? __('communications.templates.'.$log->template_key) : '-' }}</td>
                                        <td class="px-3 py-3.5 text-slate-400 font-mono text-[10px]">{{ $log->related_entity_type && $log->related_entity_id ? $log->related_entity_type.'#'.substr($log->related_entity_id, 0, 8) : '-' }}</td>
                                        <td class="px-3 py-3.5 text-slate-500">{{ optional($log->created_at)->format('Y-m-d H:i') }}</td>
                                        <td class="px-3 py-3.5 text-slate-500">{{ optional($log->sent_at)->format('Y-m-d H:i') ?: '-' }}</td>
                                        <td class="px-3 py-3.5 text-rose-600 max-w-xs truncate" title="{{ $log->error_message }}">{{ $log->error_message ?: '-' }}</td>
                                        <td class="px-3 py-3.5 text-right">
                                            @if($log->status === \App\Models\EmailOutboxLog::STATUS_FAILED)
                                                <form method="POST" action="{{ route('admin.email-templates.retry-outbox', $log) }}" class="inline">
                                                    @csrf
                                                    @if(auth()->user()?->isSuperadmin())
                                                        <input type="hidden" name="company_id" value="{{ $selectedCompanyId }}">
                                                    @endif
                                                    <input type="hidden" name="template_key" value="{{ $selectedTemplateKey }}">
                                                    <input type="hidden" name="language" value="{{ $selectedLanguage }}">
                                                    <input type="hidden" name="status" value="{{ (string) ($selectedStatus ?? '') }}">
                                                    <button type="submit" class="inline-flex size-7 items-center justify-center rounded-xl border border-success-300/60 bg-success-50 text-success-800 transition-all hover:bg-success-100" title="{{ __('communications.logs.retry') }}" aria-label="{{ __('communications.logs.retry') }}">
                                                        <svg class="size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor" aria-hidden="true">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-4.992m0 0-2.446 2.446a8.25 8.25 0 1 0 2.056 5.519" />
                                                        </svg>
                                                    </button>
                                                </form>
                                            @else
                                                <span class="text-slate-300 font-mono">-</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="px-2 py-8">
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
        // Global variables for selections
        function selectTemplateKey(key) {
            document.getElementById('selected-template-key-input').value = key;
            document.getElementById('navigation-form').submit();
        }

        function selectLanguage(lang) {
            document.getElementById('selected-language-input').value = lang;
            document.getElementById('navigation-form').submit();
        }

        function toggleActiveTemplate() {
            const hiddenInput = document.getElementById('is-active-hidden');
            const btn = document.getElementById('is-active-toggle-btn');
            const span = btn.querySelector('span');
            
            if (hiddenInput.value === '1') {
                hiddenInput.value = '0';
                btn.classList.remove('bg-[#004d3d]');
                btn.classList.add('bg-slate-200');
                span.classList.remove('translate-x-5');
                span.classList.add('translate-x-0');
            } else {
                hiddenInput.value = '1';
                btn.classList.remove('bg-slate-200');
                btn.classList.add('bg-[#004d3d]');
                span.classList.remove('translate-x-0');
                span.classList.add('translate-x-5');
            }
        }

        document.addEventListener('DOMContentLoaded', function () {
            const subjectInput = document.getElementById('subject-template-input');
            const bodyInput = document.getElementById('body-template-input');
            const subjectOutput = document.getElementById('preview-subject-output');
            const bodyOutput = document.getElementById('preview-body-output');
            const availableVarsContainer = document.getElementById('available-variables-container');
            const dynamicFieldsContainer = document.getElementById('dynamic-variables-form-fields');

            if (!subjectInput || !bodyInput || !subjectOutput || !bodyOutput || !availableVarsContainer || !dynamicFieldsContainer) {
                return;
            }

            // Standard variable definitions with localized friendly names and mock defaults
            const standardVariables = {
                candidate_name: { en: 'Candidate Name', fr: 'Nom du candidat', default: 'Alex Morgan' },
                job_title: { en: 'Job Title', fr: 'Intitulé du poste', default: 'Senior Product Manager' },
                company_name: { en: 'Company Name', fr: 'Nom de l\'entreprise', default: 'Malik and Co' },
                application_reference: { en: 'Application Reference', fr: 'Référence de la candidature', default: 'APP-123456' },
                verification_url: { en: 'Verification Link', fr: 'Lien de vérification', default: 'https://example.test/candidate/email-verify/...' },
                scheduled_for: { en: 'Scheduled For', fr: 'Planifié pour', default: '2026-03-15 14:30 UTC' },
                channel: { en: 'Channel', fr: 'Canal', default: 'In person' },
                meeting_link: { en: 'Meeting Link', fr: 'Lien de réunion', default: 'https://zoom.us/j/1234567890' },
                location_label: { en: 'Location Label', fr: 'Label de localisation', default: 'Address' },
                location_value: { en: 'Location Value', fr: 'Valeur de localisation', default: '221B Baker Street, London' },
                draft_body: { en: 'Draft Body', fr: 'Corps du brouillon', default: 'Thank you for your time and interest. We are moving forward with another profile.' },
                xai_reason: { en: 'AI Matching Signal', fr: 'Signal de correspondance IA', default: 'Current role requires deeper ownership in cross-functional launch metrics.' },
            };

            const selectedLanguage = @json($selectedLanguage);
            const templateDefaultVars = Object.keys(JSON.parse(@json($sampleVariablesJson)));

            // State management
            let lastFocusedField = bodyInput;
            let mockValues = {};

            // Initialize mockValues from standardVariables
            Object.keys(standardVariables).forEach(key => {
                mockValues[key] = standardVariables[key].default;
            });

            // Keep track of last focused field
            subjectInput.addEventListener('focus', function() { lastFocusedField = this; });
            bodyInput.addEventListener('focus', function() { lastFocusedField = this; });

            // Helper to insert placeholders at cursor
            const insertAtCursor = function(myField, myValue) {
                if (myField.selectionStart || myField.selectionStart === 0) {
                    const startPos = myField.selectionStart;
                    const endPos = myField.selectionEnd;
                    myField.value = myField.value.substring(0, startPos)
                        + myValue
                        + myField.value.substring(endPos, myField.value.length);
                    myField.selectionStart = startPos + myValue.length;
                    myField.selectionEnd = startPos + myValue.length;
                } else {
                    myField.value += myValue;
                }
            };

            // Parse variables currently in inputs
            const extractVariables = function () {
                const text = (subjectInput.value || '') + ' ' + (bodyInput.value || '');
                const regex = /\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/g;
                const found = [];
                let match;
                while ((match = regex.exec(text)) !== null) {
                    if (!found.includes(match[1])) {
                        found.push(match[1]);
                    }
                }
                return found;
            };

            // Render clickable variable tokens in editor
            const renderAvailableVariables = function() {
                availableVarsContainer.innerHTML = '';
                templateDefaultVars.forEach(v => {
                    const labelText = standardVariables[v]
                        ? (selectedLanguage === 'fr' ? standardVariables[v].fr : standardVariables[v].en)
                        : v;
                    
                    const badge = document.createElement('button');
                    badge.type = 'button';
                    badge.className = 'inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-slate-50 hover:bg-slate-100 hover:border-slate-300 px-2.5 py-1 text-xs text-slate-700 transition-all font-medium';
                    badge.innerHTML = `
                        <span class="font-mono text-[#004d3d] font-semibold">\{\{${v}\}\}</span>
                        <span class="text-[10px] text-slate-400">(${labelText})</span>
                    `;
                    
                    badge.addEventListener('click', function() {
                        if (lastFocusedField) {
                            insertAtCursor(lastFocusedField, `\{\{${v}\}\}`);
                            lastFocusedField.dispatchEvent(new Event('input'));
                        }
                    });
                    availableVarsContainer.appendChild(badge);
                });
            };

            // Render placeholders with test variables in mock email
            const renderWithVariables = function (template, variables) {
                return template.replace(/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/g, function (_, key) {
                    return Object.prototype.hasOwnProperty.call(variables, key) ? String(variables[key] ?? '') : '';
                });
            };

            const refreshPreview = function () {
                const vars = {};
                dynamicFieldsContainer.querySelectorAll('input[data-var]').forEach(input => {
                    const key = input.getAttribute('data-var');
                    vars[key] = input.value;
                });

                subjectOutput.textContent = renderWithVariables(subjectInput.value || '', vars);
                bodyOutput.innerHTML = renderWithVariables(bodyInput.value || '', vars)
                    .replace(/&/g, "&amp;")
                    .replace(/</g, "&lt;")
                    .replace(/>/g, "&gt;")
                    .replace(/"/g, "&quot;")
                    .replace(/'/g, "&#039;")
                    .replace(/\n/g, "<br>");
            };

            // Rebuild dynamic variables editor inputs
            const rebuildDynamicFields = function () {
                const vars = extractVariables();
                
                const currentInputs = Array.from(dynamicFieldsContainer.querySelectorAll('input[data-var]'));
                const currentVarKeys = currentInputs.map(input => input.getAttribute('data-var'));
                
                // Remove deleted variables
                currentInputs.forEach(input => {
                    const key = input.getAttribute('data-var');
                    if (!vars.includes(key)) {
                        input.closest('.var-field-group').remove();
                    }
                });

                // Add newly created variables
                vars.forEach(v => {
                    if (!currentVarKeys.includes(v)) {
                        const val = mockValues[v] !== undefined ? mockValues[v] : (standardVariables[v] ? standardVariables[v].default : '');
                        mockValues[v] = val;

                        const group = document.createElement('div');
                        group.className = 'var-field-group space-y-1';
                        
                        const labelText = standardVariables[v] 
                            ? (selectedLanguage === 'fr' ? standardVariables[v].fr : standardVariables[v].en)
                            : v;
                        
                        group.innerHTML = `
                            <label class="block text-xs font-semibold text-slate-700 mb-1">${labelText}</label>
                            <input type="text" data-var="${v}" value="${val}" 
                                   class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-3 py-1.5 text-xs focus:border-[#004d3d] focus:bg-white focus:outline-none focus:ring-1 focus:ring-[#004d3d]/30 transition-all" />
                        `;
                        
                        const inputEl = group.querySelector('input');
                        inputEl.addEventListener('input', function() {
                            mockValues[v] = this.value;
                            refreshPreview();
                        });

                        dynamicFieldsContainer.appendChild(group);
                    }
                });
            };

            // Setup Event Listeners
            ['input', 'change'].forEach(function (eventName) {
                subjectInput.addEventListener(eventName, function() {
                    rebuildDynamicFields();
                    refreshPreview();
                });
                bodyInput.addEventListener(eventName, function() {
                    rebuildDynamicFields();
                    refreshPreview();
                });
            });

            // Initialize UI
            renderAvailableVariables();
            rebuildDynamicFields();
            refreshPreview();
        });
    </script>
</x-shell-layout>
