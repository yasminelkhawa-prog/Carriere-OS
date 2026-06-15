<x-career-layout :title="$job->title.' | '.$company->name" :company="$company" :wide="true">
    @if(! empty($jobPostingSchema ?? null))
        <script type="application/ld+json">{!! json_encode($jobPostingSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
    @endif

    <div class="grid gap-6 lg:grid-cols-2">
        <x-glass-card>
            <p>
                <span class="inline-flex rounded-full border border-success-200/70 bg-success-100/70 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.14em] text-success-800">{{ $job->department?->name ?? __('career.list.no_department') }}</span>
            </p>
            <h2 class="mt-2 text-3xl font-semibold tracking-tight text-slate-900">{{ $job->title }}</h2>
            <p class="mt-2 text-sm text-slate-700">{{ $job->location ?: __('career.list.location_tbd') }}</p>

            @php
                $fallbackOrderedTypes = ['overview', 'company_intro', 'responsibilities', 'requirements', 'benefits', 'custom'];
                $allBlocksMarkedOverview = $job->descriptionBlocks->isNotEmpty()
                    && $job->descriptionBlocks->every(fn ($descriptionBlock) => $descriptionBlock->block_type === 'overview');
            @endphp
            <div class="mt-6 space-y-4">
                @forelse($job->descriptionBlocks as $blockIndex => $block)
                    @php
                        $resolvedType = $block->block_type;
                        if ($allBlocksMarkedOverview) {
                            $resolvedType = $fallbackOrderedTypes[$blockIndex] ?? 'custom';
                        }

                        $typeLabelKey = "career.detail.block_types.{$resolvedType}";
                        $typeLabel = trans()->has($typeLabelKey)
                            ? __($typeLabelKey)
                            : \Illuminate\Support\Str::headline(str_replace('_', ' ', $resolvedType));
                    @endphp
                    <section class="rounded-2xl border border-white/70 bg-white/60 p-4 backdrop-blur-2xl">
                        <h3 class="text-sm font-semibold uppercase tracking-wider text-aura-700/85">{{ $typeLabel }}</h3>
                        <p class="mt-2 whitespace-pre-line text-sm leading-relaxed text-slate-700">{{ data_get($block->block_content_json, 'text', json_encode($block->block_content_json, JSON_UNESCAPED_UNICODE)) }}</p>
                    </section>
                @empty
                    <x-empty-state :title="__('career.detail.empty_description_title')" :message="__('career.detail.empty_description_message')" />
                @endforelse
            </div>
        </x-glass-card>

        <x-glass-card :title="__('career.apply.title')" :subtitle="__('career.apply.subtitle')">
            @if ($errors->any())
                <x-ui.alert variant="danger">
                    {{ __('career.apply.errors.summary') }}
                </x-ui.alert>
            @endif

            @if($hasAppliedForJob ?? false)
                <div class="mt-4 rounded-xl border border-success-200 bg-success-50/70 px-4 py-3 text-sm text-success-900">
                    {{ __('career.apply.already_applied_notice') }}
                </div>
                <button type="button" disabled class="mt-4 w-full cursor-not-allowed rounded-xl border border-slate-300 bg-slate-100 px-4 py-2.5 text-sm font-semibold text-slate-500 opacity-80">
                    {{ __('career.list.already_applied') }}
                </button>
            @else
                @php
                    $assistantPrompts = [
                        [
                            'id' => 'full_name',
                            'question' => __('career.apply.assistant.questions.full_name'),
                            'field' => 'full_name',
                            'prefill' => old('full_name'),
                        ],
                        [
                            'id' => 'email',
                            'question' => __('career.apply.assistant.questions.email'),
                            'field' => 'email',
                            'prefill' => old('email'),
                        ],
                        [
                            'id' => 'phone',
                            'question' => __('career.apply.assistant.questions.phone'),
                            'field' => 'phone',
                            'prefill' => old('phone'),
                            'allow_skip' => true,
                        ],
                        [
                            'id' => 'location',
                            'question' => __('career.apply.assistant.questions.location'),
                            'field' => 'location',
                            'prefill' => old('location'),
                            'allow_skip' => true,
                        ],
                        [
                            'id' => 'years_experience',
                            'question' => __('career.apply.assistant.questions.years_experience'),
                            'field' => 'years_experience',
                            'prefill' => old('years_experience'),
                            'allow_skip' => true,
                        ],
                        [
                            'id' => 'last_company',
                            'question' => __('career.apply.assistant.questions.last_company'),
                            'field' => 'last_company',
                            'prefill' => old('last_company'),
                            'allow_skip' => true,
                        ],
                        [
                            'id' => 'main_skills',
                            'question' => __('career.apply.assistant.questions.main_skills'),
                            'field' => 'main_skills',
                            'prefill' => old('main_skills'),
                            'allow_skip' => true,
                        ],
                        [
                            'id' => 'diploma',
                            'question' => __('career.apply.assistant.questions.diploma'),
                            'allow_skip' => true,
                        ],
                        [
                            'id' => 'school',
                            'question' => __('career.apply.assistant.questions.school'),
                            'allow_skip' => true,
                        ],
                        [
                            'id' => 'referral_code',
                            'question' => __('career.apply.assistant.questions.referral_code'),
                            'field' => 'referral_code',
                            'prefill' => old('referral_code'),
                            'allow_skip' => true,
                        ],
                        [
                            'id' => 'motivation',
                            'question' => __('career.apply.assistant.questions.motivation', ['job' => $job->title]),
                            'allow_skip' => true,
                        ],
                    ];
                @endphp
                <div
                    class="fixed bottom-5 right-5 z-50 flex flex-col items-end gap-3"
                    x-data="{ open: false }"
                    data-career-apply-assistant
                    data-prompts-base64="{{ base64_encode(json_encode($assistantPrompts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) }}"
                    data-complete-label="{{ __('career.apply.assistant.complete_label') }}"
                >
                    <!-- Chatbot Panel -->
                    <div
                        x-show="open"
                        x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0 translate-y-4 scale-95"
                        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                        x-transition:leave="transition ease-in duration-150"
                        x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                        x-transition:leave-end="opacity-0 translate-y-4 scale-95"
                        class="flex w-[380px] max-w-[calc(100vw-2.5rem)] flex-col overflow-hidden rounded-3xl border border-white/60 bg-white shadow-aura"
                        style="display: none; max-height: min(34rem, calc(100vh - 8rem));"
                    >
                        <!-- Header -->
                        <div class="flex items-center gap-3 bg-gradient-to-br from-aura-600 via-aura-500 to-sky-400 px-4 py-3.5">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 28" class="h-9 w-9" aria-hidden="true">
                                    <path d="M5 1h22a5 5 0 0 1 5 5v11a5 5 0 0 1-5 5H13l-6 6v-6H5a5 5 0 0 1-5-5V6a5 5 0 0 1 5-5z" fill="#f3f0ff" />
                                    <rect x="6" y="6" width="20" height="11" rx="4" fill="#312e81" />
                                    <circle cx="13" cy="11.5" r="2.3" fill="#7dd3fc" />
                                    <circle cx="19" cy="11.5" r="2.3" fill="#7dd3fc" />
                                </svg>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-white/70">{{ __('career.apply.assistant.eyebrow') }}</p>
                                <h3 class="truncate text-sm font-semibold text-white">{{ __('career.apply.assistant.title') }}</h3>
                            </div>
                            <button type="button" @click="open = false" class="shrink-0 rounded-full p-1.5 text-white/80 transition-weightless hover:bg-white/15 hover:text-white" aria-label="{{ __('ui.close') }}">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <!-- Message Container -->
                        <div data-career-assistant-messages class="flex-1 space-y-2.5 overflow-y-auto bg-slate-50 p-3.5">
                            <div class="flex items-start gap-2">
                                <span class="flex h-7 w-7 shrink-0 items-center justify-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 28" class="h-7 w-7" aria-hidden="true">
                                        <path d="M5 1h22a5 5 0 0 1 5 5v11a5 5 0 0 1-5 5H13l-6 6v-6H5a5 5 0 0 1-5-5V6a5 5 0 0 1 5-5z" fill="#f3f0ff" />
                                        <rect x="6" y="6" width="20" height="11" rx="4" fill="#312e81" />
                                        <circle cx="13" cy="11.5" r="2.3" fill="#7dd3fc" />
                                        <circle cx="19" cy="11.5" r="2.3" fill="#7dd3fc" />
                                    </svg>
                                </span>
                                <p class="max-w-[80%] rounded-2xl rounded-bl-sm bg-gradient-to-br from-aura-200 to-sky-200 px-3 py-2 text-xs leading-relaxed text-slate-800 shadow-sm">
                                    {{ __('career.apply.assistant.welcome') }}
                                </p>
                            </div>
                        </div>

                        <!-- Controls -->
                        <div class="space-y-2.5 border-t border-slate-100 bg-white p-3">
                            <button type="button" data-career-assistant-start class="flex w-full items-center justify-center gap-2 rounded-full bg-gradient-to-br from-aura-600 to-sky-400 px-4 py-2.5 text-xs font-semibold text-white shadow-sm transition-weightless hover:from-aura-700 hover:to-sky-500 disabled:hidden">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                </svg>
                                {{ __('career.apply.assistant.start_action') }}
                            </button>

                            <div class="flex items-center gap-2">
                                <input
                                    type="text"
                                    data-career-assistant-input
                                    class="w-full rounded-full border border-slate-200 bg-slate-50 px-4 py-2.5 text-xs text-slate-900 shadow-sm focus:border-aura-400 focus:bg-white focus:ring-aura-300"
                                    placeholder="{{ __('career.apply.assistant.input_placeholder') }}"
                                >
                                <button type="button" data-career-assistant-send aria-label="{{ __('career.apply.assistant.send_action') }}" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-aura-600 to-sky-400 text-white shadow-sm transition-weightless hover:from-aura-700 hover:to-sky-500">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M3.105 2.289a.75.75 0 0 0-.826.95l1.414 4.95a.75.75 0 0 0 .54.519l6.09 1.732a.25.25 0 0 1 0 .48l-6.09 1.732a.75.75 0 0 0-.54.52l-1.414 4.95a.75.75 0 0 0 .826.95 28.896 28.896 0 0 0 15.293-7.155.75.75 0 0 0 0-1.115A28.897 28.897 0 0 0 3.105 2.289Z" />
                                    </svg>
                                </button>
                            </div>

                            <div class="flex items-center justify-between gap-3">
                                <p class="text-[10px] leading-tight text-slate-400">{{ __('career.apply.assistant.footer_note') }}</p>
                                <button type="button" data-career-assistant-skip class="shrink-0 text-[11px] font-semibold text-slate-500 transition-weightless hover:text-aura-700">
                                    {{ __('career.apply.assistant.skip_action') }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Chatbot Toggle Button -->
                    <button
                        type="button"
                        @click="open = !open"
                        class="flex h-14 w-14 items-center justify-center rounded-full bg-gradient-to-br from-aura-600 to-sky-400 text-white shadow-aura transition-transform hover:scale-105 hover:from-aura-700 hover:to-sky-500 focus:outline-none"
                        aria-label="{{ __('career.apply.assistant.eyebrow') }}"
                    >
                        <!-- Chat Icon -->
                        <svg x-show="!open" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 28" class="h-8 w-8" aria-hidden="true">
                            <path d="M5 1h22a5 5 0 0 1 5 5v11a5 5 0 0 1-5 5H13l-6 6v-6H5a5 5 0 0 1-5-5V6a5 5 0 0 1 5-5z" fill="#f3f0ff" />
                            <rect x="6" y="6" width="20" height="11" rx="4" fill="#312e81" />
                            <circle cx="13" cy="11.5" r="2.3" fill="#7dd3fc" />
                            <circle cx="19" cy="11.5" r="2.3" fill="#7dd3fc" />
                        </svg>
                        <!-- Close Icon -->
                        <svg x-show="open" xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="display: none;">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>


                @php
                    $saved = session('career_apply_saved', []);
                    $moroccanSchools = __('career.apply.moroccan_schools');
                    $diplomaTypes   = __('career.apply.diploma_types');
                @endphp

                <form method="POST" action="{{ route('career.apply', ['company' => $company, 'job' => $job]) }}" enctype="multipart/form-data" class="mt-4 space-y-4">
                    @csrf

                    {{-- Capture UTM params from query string --}}
                    <input type="hidden" name="utm_source" value="{{ request()->query('utm_source') }}">
                    <input type="hidden" name="utm_medium" value="{{ request()->query('utm_medium') }}">
                    <input type="hidden" name="utm_campaign" value="{{ request()->query('utm_campaign') }}">
                    <input type="hidden" name="assistant_answers_json" value="{{ old('assistant_answers_json') }}">

                    {{-- ── Identité ── --}}
                    <x-form-field :label="__('career.apply.fields.full_name')" name="full_name" required>
                        <input type="text" name="full_name" value="{{ old('full_name', $saved['full_name'] ?? '') }}" required class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-slate-900 shadow-sm focus:border-aura-400 focus:ring-aura-300">
                    </x-form-field>

                    <x-form-field :label="__('career.apply.fields.email')" name="email" required>
                        <input type="email" name="email" value="{{ old('email', $saved['email'] ?? '') }}" required class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-slate-900 shadow-sm focus:border-aura-400 focus:ring-aura-300">
                    </x-form-field>

                    <x-form-field :label="__('career.apply.fields.password')" name="password" required>
                        <div class="relative">
                            <input id="career-apply-password" type="password" name="password" required autocomplete="new-password" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 pr-12 text-slate-900 shadow-sm focus:border-aura-400 focus:ring-aura-300">
                            <button
                                type="button"
                                data-password-toggle
                                data-password-target="career-apply-password"
                                data-show-label="{{ __('career.apply.toggle_show') }}"
                                data-hide-label="{{ __('career.apply.toggle_hide') }}"
                                aria-label="{{ __('career.apply.toggle_show') }}"
                                class="absolute inset-y-0 right-0 my-1 mr-1 inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 transition-weightless hover:bg-slate-50 hover:text-slate-900"
                            >
                                <svg data-eye-open xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z" />
                                    <circle cx="12" cy="12" r="3" />
                                </svg>
                                <svg data-eye-closed xmlns="http://www.w3.org/2000/svg" class="hidden h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M17.94 17.94A10.94 10.94 0 0 1 12 20C5 20 1 12 1 12a21.66 21.66 0 0 1 5.06-6.94" />
                                    <path d="M9.9 4.24A10.96 10.96 0 0 1 12 4c7 0 11 8 11 8a21.58 21.58 0 0 1-2.16 3.19" />
                                    <path d="M14.12 14.12a3 3 0 1 1-4.24-4.24" />
                                    <path d="m1 1 22 22" />
                                </svg>
                                <span class="sr-only" data-password-toggle-label>{{ __('career.apply.toggle_show') }}</span>
                            </button>
                        </div>
                    </x-form-field>

                    <x-form-field :label="__('career.apply.fields.password_confirmation')" name="password_confirmation" required>
                        <div class="relative">
                            <input id="career-apply-password-confirmation" type="password" name="password_confirmation" required autocomplete="new-password" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 pr-12 text-slate-900 shadow-sm focus:border-aura-400 focus:ring-aura-300">
                            <button
                                type="button"
                                data-password-toggle
                                data-password-target="career-apply-password-confirmation"
                                data-show-label="{{ __('career.apply.toggle_show') }}"
                                data-hide-label="{{ __('career.apply.toggle_hide') }}"
                                aria-label="{{ __('career.apply.toggle_show') }}"
                                class="absolute inset-y-0 right-0 my-1 mr-1 inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 transition-weightless hover:bg-slate-50 hover:text-slate-900"
                            >
                                <svg data-eye-open xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z" />
                                    <circle cx="12" cy="12" r="3" />
                                </svg>
                                <svg data-eye-closed xmlns="http://www.w3.org/2000/svg" class="hidden h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M17.94 17.94A10.94 10.94 0 0 1 12 20C5 20 1 12 1 12a21.66 21.66 0 0 1 5.06-6.94" />
                                    <path d="M9.9 4.24A10.96 10.96 0 0 1 12 4c7 0 11 8 11 8a21.58 21.58 0 0 1-2.16 3.19" />
                                    <path d="M14.12 14.12a3 3 0 1 1-4.24-4.24" />
                                    <path d="m1 1 22 22" />
                                </svg>
                                <span class="sr-only" data-password-toggle-label>{{ __('career.apply.toggle_show') }}</span>
                            </button>
                        </div>
                    </x-form-field>

                    <x-form-field :label="__('career.apply.fields.phone')" name="phone">
                        <input type="text" name="phone" value="{{ old('phone', $saved['phone'] ?? '') }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-slate-900 shadow-sm focus:border-aura-400 focus:ring-aura-300">
                    </x-form-field>

                    <x-form-field :label="__('career.apply.fields.location')" name="location">
                        <input type="text" name="location" value="{{ old('location', $saved['location'] ?? '') }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-slate-900 shadow-sm focus:border-aura-400 focus:ring-aura-300">
                    </x-form-field>

                    {{-- ── Parcours professionnel ── --}}
                    <div class="rounded-xl border border-aura-100/60 bg-aura-50/40 px-4 py-3">
                        <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-aura-700">Parcours professionnel</p>
                        <div class="space-y-4">

                            <div class="grid grid-cols-2 gap-3">
                                <x-form-field :label="__('career.apply.fields.years_experience')" name="years_experience">
                                    <input type="number" name="years_experience" min="0" max="60" value="{{ old('years_experience', $saved['years_experience'] ?? '') }}" placeholder="ex: 5" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-slate-900 shadow-sm focus:border-aura-400 focus:ring-aura-300">
                                </x-form-field>

                                <x-form-field :label="__('career.apply.fields.last_company')" name="last_company">
                                    <input type="text" name="last_company" value="{{ old('last_company', $saved['last_company'] ?? '') }}" placeholder="ex: Google, OCP..." class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-slate-900 shadow-sm focus:border-aura-400 focus:ring-aura-300">
                                </x-form-field>
                            </div>

                            <x-form-field :label="__('career.apply.fields.main_skills')" name="main_skills">
                                <input type="text" name="main_skills" value="{{ old('main_skills', $saved['main_skills'] ?? '') }}" placeholder="ex: Python, Management de projet, Data Analysis..." class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-slate-900 shadow-sm focus:border-aura-400 focus:ring-aura-300">
                            </x-form-field>

                        </div>
                    </div>

                    {{-- ── Formation ── --}}
                    <div class="rounded-xl border border-aura-100/60 bg-aura-50/40 px-4 py-3" x-data="{
                        schoolType: '{{ old('school_type', $saved['school_type'] ?? '') }}',
                        moroccanSchool: '{{ old('school_name', ($saved['school_type'] ?? '') === 'moroccan' ? ($saved['school_name'] ?? '') : '') }}',
                    }">
                        <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-aura-700">Formation</p>
                        <div class="space-y-4">

                            <x-form-field :label="__('career.apply.fields.diploma_type')" name="diploma_type">
                                <select name="diploma_type" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-slate-900 shadow-sm focus:border-aura-400 focus:ring-aura-300">
                                    <option value="">— Choisir —</option>
                                    @foreach($diplomaTypes as $key => $label)
                                        <option value="{{ $key }}" {{ old('diploma_type', $saved['diploma_type'] ?? '') === $key ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </x-form-field>

                            <x-form-field :label="__('career.apply.fields.school_type')" name="school_type">
                                <div class="flex gap-3">
                                    <label class="flex flex-1 cursor-pointer items-center gap-2.5 rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-sm text-slate-700 transition-weightless has-[:checked]:border-aura-400 has-[:checked]:bg-aura-50">
                                        <input type="radio" name="school_type" value="moroccan" x-model="schoolType" class="text-aura-600 focus:ring-aura-400">
                                        Diplôme marocain
                                    </label>
                                    <label class="flex flex-1 cursor-pointer items-center gap-2.5 rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-sm text-slate-700 transition-weightless has-[:checked]:border-aura-400 has-[:checked]:bg-aura-50">
                                        <input type="radio" name="school_type" value="foreign" x-model="schoolType" class="text-aura-600 focus:ring-aura-400">
                                        Diplôme étranger
                                    </label>
                                </div>
                            </x-form-field>

                            {{-- Moroccan school selector --}}
                            <div x-show="schoolType === 'moroccan'" x-cloak class="space-y-3">
                                <x-form-field :label="__('career.apply.fields.school_name_moroccan')" name="school_name">
                                    <select
                                        name="school_name"
                                        x-model="moroccanSchool"
                                        @change="if($event.target.value !== 'other') $el.closest('form').querySelector('[name=school_name_manual]').value = ''"
                                        class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-slate-900 shadow-sm focus:border-aura-400 focus:ring-aura-300"
                                    >
                                        <option value="">— Choisir votre école —</option>
                                        @foreach($moroccanSchools as $key => $label)
                                            <option value="{{ $key }}" {{ old('school_name', ($saved['school_type'] ?? '') === 'moroccan' ? ($saved['school_name'] ?? '') : '') === $key ? 'selected' : '' }}>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </x-form-field>
                                <div x-show="moroccanSchool === 'other'" x-cloak>
                                    <x-form-field :label="__('career.apply.fields.school_name_other')" name="school_name_manual">
                                        <input type="text" name="school_name_manual" value="{{ old('school_name_manual') }}" placeholder="Précisez le nom de votre école..." class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-slate-900 shadow-sm focus:border-aura-400 focus:ring-aura-300">
                                    </x-form-field>
                                </div>
                            </div>

                            {{-- Foreign school fields --}}
                            <div x-show="schoolType === 'foreign'" x-cloak class="space-y-3">
                                <x-form-field :label="__('career.apply.fields.school_country')" name="school_country">
                                    <select name="school_country" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-slate-900 shadow-sm focus:border-aura-400 focus:ring-aura-300">
                                        <option value="">— Choisir le pays —</option>
                                        @foreach([
                                            'FR' => '🇫🇷 France',
                                            'BE' => '🇧🇪 Belgique',
                                            'CH' => '🇨🇭 Suisse',
                                            'CA' => '🇨🇦 Canada',
                                            'DE' => '🇩🇪 Allemagne',
                                            'GB' => '🇬🇧 Royaume-Uni',
                                            'ES' => '🇪🇸 Espagne',
                                            'US' => '🇺🇸 États-Unis',
                                            'AE' => '🇦🇪 Émirats Arabes Unis',
                                            'SA' => '🇸🇦 Arabie Saoudite',
                                            'EG' => '🇪🇬 Égypte',
                                            'TN' => '🇹🇳 Tunisie',
                                            'DZ' => '🇩🇿 Algérie',
                                            'SN' => '🇸🇳 Sénégal',
                                            'CI' => '🇨🇮 Côte d\'Ivoire',
                                            'OTHER' => '🌍 Autre pays',
                                        ] as $code => $name)
                                            <option value="{{ $code }}" {{ old('school_country', ($saved['school_type'] ?? '') === 'foreign' ? ($saved['school_country'] ?? '') : '') === $code ? 'selected' : '' }}>{{ $name }}</option>
                                        @endforeach
                                    </select>
                                </x-form-field>
                                <x-form-field :label="__('career.apply.fields.school_name')" name="school_name">
                                    <input type="text" name="school_name" value="{{ old('school_name', ($saved['school_type'] ?? '') === 'foreign' ? ($saved['school_name'] ?? '') : '') }}" placeholder="ex: Sciences Po Paris, MIT, HEC Montréal..." class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-slate-900 shadow-sm focus:border-aura-400 focus:ring-aura-300">
                                </x-form-field>
                            </div>

                        </div>
                    </div>

                    {{-- ── Documents ── --}}
                    <x-form-field :label="__('career.apply.fields.resume')" name="resume" required>
                        <div class="space-y-2" data-file-upload>
                            <input id="career-resume-upload" type="file" name="resume" required accept=".pdf,application/pdf" class="sr-only" data-file-upload-input>
                            <label for="career-resume-upload" class="flex cursor-pointer items-center justify-between gap-3 rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-sm text-slate-900 shadow-sm transition-weightless hover:border-aura-300 hover:bg-white">
                                <span class="truncate" data-file-upload-label data-empty-label="{{ __('career.apply.file_upload.choose') }}">{{ __('career.apply.file_upload.choose') }}</span>
                                <span class="shrink-0 rounded-lg bg-aura-100 px-3 py-1.5 text-xs font-semibold text-aura-700">{{ __('career.apply.file_upload.browse') }}</span>
                            </label>
                            <p class="text-xs text-slate-500">{{ __('career.apply.file_upload.resume_hint') }}</p>
                        </div>
                    </x-form-field>

                    <x-form-field :label="__('career.apply.fields.portfolio')" name="portfolio">
                        <div class="space-y-2" data-file-upload>
                            <input id="career-portfolio-upload" type="file" name="portfolio" accept=".pdf,.doc,.docx,.rtf,.txt,.zip" class="sr-only" data-file-upload-input>
                            <label for="career-portfolio-upload" class="flex cursor-pointer items-center justify-between gap-3 rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-sm text-slate-900 shadow-sm transition-weightless hover:border-aura-300 hover:bg-white">
                                <span class="truncate" data-file-upload-label data-empty-label="{{ __('career.apply.file_upload.choose') }}">{{ __('career.apply.file_upload.choose') }}</span>
                                <span class="shrink-0 rounded-lg bg-aura-100 px-3 py-1.5 text-xs font-semibold text-aura-700">{{ __('career.apply.file_upload.browse') }}</span>
                            </label>
                            <p class="text-xs text-slate-500">{{ __('career.apply.file_upload.portfolio_hint') }}</p>
                        </div>
                    </x-form-field>

                    <x-form-field :label="__('career.apply.fields.referral_code')" name="referral_code">
                        <input type="text" name="referral_code" value="{{ old('referral_code') }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-slate-900 shadow-sm focus:border-aura-400 focus:ring-aura-300">
                    </x-form-field>

                    <div class="rounded-xl border border-aura-200/40 bg-white/60 px-4 py-3">
                        <label class="flex items-start gap-3 cursor-pointer">
                            <input type="checkbox" name="consent" value="1" class="mt-0.5 rounded border-aura-300 text-aura-600 focus:ring-aura-400 @error('consent') border-danger-400 @enderror">
                            <span class="text-sm leading-relaxed text-slate-700">
                                {!! __('career.apply.consent.label') !!}
                            </span>
                        </label>
                        @error('consent')
                            <p class="mt-1.5 text-xs text-danger-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <button type="submit" class="w-full rounded-xl bg-success-600 px-4 py-2.5 text-sm font-semibold text-white transition-weightless hover:bg-success-700">
                        {{ __('career.apply.submit') }}
                    </button>
                </form>
            @endif
        </x-glass-card>
    </div>

    <script>
        document.querySelectorAll('[data-file-upload]').forEach((wrapper) => {
            const input = wrapper.querySelector('[data-file-upload-input]');
            const label = wrapper.querySelector('[data-file-upload-label]');

            if (!input || !label) {
                return;
            }

            input.addEventListener('change', () => {
                const fileName = input.files && input.files.length > 0 ? input.files[0].name : '';
                label.textContent = fileName || label.dataset.emptyLabel || 'Choose a file';
                label.classList.toggle('font-semibold', fileName !== '');
                label.classList.toggle('text-success-800', fileName !== '');
            });
        });

        document.querySelectorAll('[data-password-toggle]').forEach((button) => {
            const targetId = button.getAttribute('data-password-target') || '';
            if (targetId === '') {
                return;
            }

            const input = document.getElementById(targetId);
            if (!(input instanceof HTMLInputElement)) {
                return;
            }

            const showLabel = button.getAttribute('data-show-label') || 'Show';
            const hideLabel = button.getAttribute('data-hide-label') || 'Hide';
            const eyeOpen = button.querySelector('[data-eye-open]');
            const eyeClosed = button.querySelector('[data-eye-closed]');
            const srLabel = button.querySelector('[data-password-toggle-label]');

            button.addEventListener('click', () => {
                const shouldShow = input.type === 'password';
                input.type = shouldShow ? 'text' : 'password';

                if (eyeOpen instanceof SVGElement && eyeClosed instanceof SVGElement) {
                    eyeOpen.classList.toggle('hidden', shouldShow);
                    eyeClosed.classList.toggle('hidden', !shouldShow);
                }

                const nextLabel = shouldShow ? hideLabel : showLabel;
                button.setAttribute('aria-label', nextLabel);
                if (srLabel instanceof HTMLElement) {
                    srLabel.textContent = nextLabel;
                }
            });
        });
    </script>
</x-career-layout>
