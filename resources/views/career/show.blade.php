<x-career-layout :title="$job->title.' | '.$company->name" :company="$company">
    @if(! empty($jobPostingSchema ?? null))
        <script type="application/ld+json">{!! json_encode($jobPostingSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
    @endif

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_24rem]">
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
                        <h3 class="text-sm font-semibold uppercase tracking-wider text-aura-700/85">{{ __('career.detail.block_label', ['type' => $typeLabel]) }}</h3>
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
                    class="mt-4 rounded-2xl border border-aura-200/60 bg-aura-50/55 p-4 shadow-sm"
                    data-career-apply-assistant
                    data-prompts-base64="{{ base64_encode(json_encode($assistantPrompts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) }}"
                    data-complete-label="{{ __('career.apply.assistant.complete_label') }}"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-aura-700/85">{{ __('career.apply.assistant.eyebrow') }}</p>
                            <h3 class="mt-1 text-base font-semibold text-slate-900">{{ __('career.apply.assistant.title') }}</h3>
                            <p class="mt-1 text-sm text-slate-600">{{ __('career.apply.assistant.subtitle') }}</p>
                        </div>
                        <button type="button" data-career-assistant-start class="rounded-xl border border-aura-300/60 bg-white px-3 py-2 text-xs font-semibold text-aura-900 transition-weightless hover:bg-aura-100">
                            {{ __('career.apply.assistant.start_action') }}
                        </button>
                    </div>

                    <div data-career-assistant-messages class="mt-4 max-h-64 space-y-2 overflow-y-auto rounded-xl border border-white/80 bg-white/80 p-3">
                        <p class="rounded-lg border border-aura-200/60 bg-aura-50 px-3 py-2 text-xs text-slate-700">
                            {{ __('career.apply.assistant.welcome') }}
                        </p>
                    </div>

                    <div class="mt-3 flex gap-2">
                        <input
                            type="text"
                            data-career-assistant-input
                            class="w-full rounded-xl border border-aura-200/50 bg-white/90 px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-aura-400 focus:ring-aura-300"
                            placeholder="{{ __('career.apply.assistant.input_placeholder') }}"
                        >
                        <button type="button" data-career-assistant-send class="rounded-xl bg-aura-600 px-4 py-2 text-sm font-semibold text-white transition-weightless hover:bg-aura-700">
                            {{ __('career.apply.assistant.send_action') }}
                        </button>
                    </div>

                    <div class="mt-2 flex items-center justify-between gap-2">
                        <p class="text-xs text-slate-500">{{ __('career.apply.assistant.footer_note') }}</p>
                        <button type="button" data-career-assistant-skip class="text-xs font-semibold text-slate-600 transition-weightless hover:text-aura-700">
                            {{ __('career.apply.assistant.skip_action') }}
                        </button>
                    </div>
                </div>

                <form method="POST" action="{{ route('career.apply', ['company' => $company, 'job' => $job]) }}" enctype="multipart/form-data" class="mt-4 space-y-4">
                    @csrf

                    {{-- Capture UTM params from query string --}}
                    <input type="hidden" name="utm_source" value="{{ request()->query('utm_source') }}">
                    <input type="hidden" name="utm_medium" value="{{ request()->query('utm_medium') }}">
                    <input type="hidden" name="utm_campaign" value="{{ request()->query('utm_campaign') }}">
                    <input type="hidden" name="assistant_answers_json" value="{{ old('assistant_answers_json') }}">

                    <x-form-field :label="__('career.apply.fields.full_name')" name="full_name" required>
                        <input type="text" name="full_name" value="{{ old('full_name') }}" required class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-slate-900 shadow-sm focus:border-aura-400 focus:ring-aura-300">
                    </x-form-field>

                    <x-form-field :label="__('career.apply.fields.email')" name="email" required>
                        <input type="email" name="email" value="{{ old('email') }}" required class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-slate-900 shadow-sm focus:border-aura-400 focus:ring-aura-300">
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
                        <input type="text" name="phone" value="{{ old('phone') }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-slate-900 shadow-sm focus:border-aura-400 focus:ring-aura-300">
                    </x-form-field>

                    <x-form-field :label="__('career.apply.fields.location')" name="location">
                        <input type="text" name="location" value="{{ old('location') }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-slate-900 shadow-sm focus:border-aura-400 focus:ring-aura-300">
                    </x-form-field>

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
