<x-shell-layout :title="__('video_assessment.config.title').' | '.config('app.name')">
    <x-glass-card :title="__('video_assessment.config.title')" :subtitle="__('video_assessment.config.subtitle')">
        @if(session('status'))
            <x-toast-alert type="success">{{ session('status') }}</x-toast-alert>
        @endif

        @if($errors->any())
            <div class="mt-3 rounded-xl border border-danger-300/50 bg-danger-50/70 px-3 py-2 text-xs text-danger-800">
                {{ $errors->first() }}
            </div>
        @endif

        @if($requiresCompanySelection)
            <div class="mt-4">
                <x-empty-state :title="__('master.common.company_scope_required')" :message="__('master.common.company_scope_required')" />
            </div>
        @else
            <form method="POST" action="{{ route('admin.video-configs.store') }}" class="mt-4 space-y-3 rounded-2xl border border-white/70 bg-white/70 p-4">
                @csrf
                @if(auth()->user()->isSuperadmin())
                    <input type="hidden" name="company_id" value="{{ $selectedCompanyId }}">
                @endif
                <div class="grid gap-3 md:grid-cols-2">
                    <x-form-field :label="__('video_assessment.config.fields.job')" name="job_id">
                        <select name="job_id" required data-placeholder="{{ __('video_assessment.config.fields.job') }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2 text-sm">
                            <option value=""></option>
                            @foreach($jobs as $job)
                                <option value="{{ $job->id }}" @selected(old('job_id') === (string) $job->id)>{{ $job->title }}</option>
                            @endforeach
                        </select>
                    </x-form-field>

                    <x-form-field :label="__('video_assessment.config.fields.name')" name="name">
                        <input type="text" name="name" value="{{ old('name') }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2 text-sm" required>
                    </x-form-field>
                </div>

                <div class="grid gap-3 md:grid-cols-3">
                    <x-form-field :label="__('video_assessment.config.fields.read_time_seconds')" name="read_time_seconds">
                        <input type="number" min="5" max="300" name="read_time_seconds" value="{{ old('read_time_seconds', 20) }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2 text-sm" required>
                    </x-form-field>
                    <x-form-field :label="__('video_assessment.config.fields.answer_time_seconds')" name="answer_time_seconds">
                        <input type="number" min="10" max="900" name="answer_time_seconds" value="{{ old('answer_time_seconds', 120) }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2 text-sm" required>
                    </x-form-field>
                    <x-form-field :label="__('video_assessment.config.fields.retries_allowed')" name="retries_allowed">
                        <input type="number" min="0" max="10" name="retries_allowed" value="{{ old('retries_allowed', 1) }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2 text-sm" required>
                    </x-form-field>
                </div>

                <div class="space-y-2">
                    <p class="text-xs uppercase tracking-wide text-slate-600">{{ __('video_assessment.config.fields.questions') }}</p>
                    @for($i = 0; $i < 5; $i++)
                        <input type="text"
                               name="questions[]"
                               value="{{ old('questions.'.$i) }}"
                               placeholder="{{ __('video_assessment.config.fields.question_placeholder', ['number' => $i + 1]) }}"
                               class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2 text-sm">
                    @endfor
                </div>

                <button type="submit" class="rounded-xl bg-success-600 px-4 py-2 text-sm font-semibold text-white transition-weightless hover:bg-success-700">
                    {{ __('video_assessment.config.actions.create') }}
                </button>
            </form>

            <div class="mt-6 space-y-4">
                @forelse($configs as $config)
                    <form method="POST" action="{{ route('admin.video-configs.update', ['videoConfig' => $config->id]) }}" class="rounded-2xl border border-slate-200 bg-white/70 p-4">
                        @csrf
                        @method('PATCH')
                        @if(auth()->user()->isSuperadmin())
                            <input type="hidden" name="company_id" value="{{ $selectedCompanyId }}">
                        @endif

                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-slate-900">{{ $config->name }}</p>
                                <p class="text-xs text-slate-600">{{ $config->job?->title }}</p>
                            </div>
                            <button type="submit" class="rounded-lg border border-success-300/60 bg-success-50 px-3 py-1.5 text-xs font-medium text-success-800">
                                {{ __('video_assessment.config.actions.update') }}
                            </button>
                        </div>

                        <div class="mt-3 grid gap-3 md:grid-cols-2">
                            <x-form-field :label="__('video_assessment.config.fields.job')" name="job_id">
                                <select name="job_id" required data-placeholder="{{ __('video_assessment.config.fields.job') }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2 text-sm">
                                    @foreach($jobs as $job)
                                        <option value="{{ $job->id }}" @selected((string) $config->job_id === (string) $job->id)>{{ $job->title }}</option>
                                    @endforeach
                                </select>
                            </x-form-field>

                            <x-form-field :label="__('video_assessment.config.fields.name')" name="name">
                                <input type="text" name="name" value="{{ $config->name }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2 text-sm" required>
                            </x-form-field>
                        </div>

                        <div class="mt-3 grid gap-3 md:grid-cols-3">
                            <x-form-field :label="__('video_assessment.config.fields.read_time_seconds')" name="read_time_seconds">
                                <input type="number" min="5" max="300" name="read_time_seconds" value="{{ $config->read_time_seconds }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2 text-sm" required>
                            </x-form-field>
                            <x-form-field :label="__('video_assessment.config.fields.answer_time_seconds')" name="answer_time_seconds">
                                <input type="number" min="10" max="900" name="answer_time_seconds" value="{{ $config->answer_time_seconds }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2 text-sm" required>
                            </x-form-field>
                            <x-form-field :label="__('video_assessment.config.fields.retries_allowed')" name="retries_allowed">
                                <input type="number" min="0" max="10" name="retries_allowed" value="{{ $config->retries_allowed }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2 text-sm" required>
                            </x-form-field>
                        </div>

                        <div class="mt-3 space-y-2">
                            <p class="text-xs uppercase tracking-wide text-slate-600">{{ __('video_assessment.config.fields.questions') }}</p>
                            @php($questions = $config->questions->sortBy('display_order')->values())
                            @for($i = 0; $i < max(5, $questions->count()); $i++)
                                <input type="text"
                                       name="questions[]"
                                       value="{{ (string) ($questions->get($i)?->question_text ?? '') }}"
                                       placeholder="{{ __('video_assessment.config.fields.question_placeholder', ['number' => $i + 1]) }}"
                                       class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2 text-sm">
                            @endfor
                        </div>
                    </form>
                @empty
                    <x-empty-state :title="__('video_assessment.config.empty_title')" :message="__('video_assessment.config.empty_message')" />
                @endforelse
            </div>
        @endif
    </x-glass-card>
</x-shell-layout>
