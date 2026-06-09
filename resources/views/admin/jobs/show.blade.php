<x-shell-layout :title="$job->title.' | '.config('app.name')">
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-xl font-bold text-slate-800">{{ $job->title }}</h1>
                <p class="text-sm text-slate-500">{{ __('jobs.subtitle') }}</p>
            </div>
        </div>

        @if (session('status'))
            <x-toast-alert type="success">{{ session('status') }}</x-toast-alert>
        @endif
        @if (session('error'))
            <x-toast-alert type="error" class="mt-2">{{ session('error') }}</x-toast-alert>
        @endif

        <form method="POST" action="{{ route('jobs.update', ['job' => $job, 'company_id' => $selectedCompanyId]) }}" class="mt-4 grid gap-4 lg:grid-cols-6">
            @csrf
            @method('PATCH')
            @if(auth()->user()?->isSuperadmin())
                <input type="hidden" name="company_id" value="{{ $selectedCompanyId }}">
            @endif
            <x-form-field :label="__('jobs.fields.title')" name="title" class="lg:col-span-2">
                <input type="text" name="title" value="{{ old('title', $job->title) }}" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-aura-500 focus:ring-aura-500">
            </x-form-field>
            <x-form-field :label="__('jobs.department')" name="department_id" class="lg:col-span-1">
                <select name="department_id" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-aura-500 focus:ring-aura-500">
                    <option value="">{{ __('jobs.department_placeholder') }}</option>
                    @foreach($departments as $department)
                        <option value="{{ $department->id }}" @selected((string) old('department_id', $job->department_id) === (string) $department->id)>{{ $department->name }}</option>
                    @endforeach
                </select>
            </x-form-field>
            <x-form-field :label="__('jobs.fields.location')" name="location" class="lg:col-span-1">
                <input type="text" name="location" value="{{ old('location', $job->location) }}" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-aura-500 focus:ring-aura-500">
            </x-form-field>
            <x-form-field :label="__('jobs.status')" name="status" class="lg:col-span-1">
                <select name="status" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-aura-500 focus:ring-aura-500">
                    @foreach(\App\Models\Job::statuses() as $status)
                        <option value="{{ $status }}" @selected(old('status', $job->status) === $status)>{{ __('jobs.statuses.'.$status) }}</option>
                    @endforeach
                </select>
            </x-form-field>
            <x-form-field :label="__('jobs.fields.salary_budget_max')" name="salary_budget_max" class="lg:col-span-1">
                <input type="number" min="0" name="salary_budget_max" value="{{ old('salary_budget_max', $job->salary_budget_max) }}" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-aura-500 focus:ring-aura-500">
            </x-form-field>
            
            <div class="flex items-center justify-between lg:col-span-6 pt-2">
                <button type="submit" class="rounded-xl bg-success-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition-weightless hover:bg-success-700">{{ __('jobs.updated') }}</button>
                <div class="flex items-center gap-2">
                    <input type="checkbox" name="blind_mode_active" value="1" class="rounded border-slate-300 text-aura-600 focus:ring-aura-500" @checked(old('blind_mode_active', $job->blind_mode_active))>
                    <span class="text-sm font-medium text-slate-700">{{ __('jobs.fields.blind_mode') }}</span>
                </div>
            </div>
        </form>

        <div class="mt-8" x-data="{ tab: @js(request('tab', 'description')) }">
            <div class="flex flex-wrap gap-2 border-b border-slate-100 pb-4">
                <button type="button" class="rounded-full px-4 py-2 text-sm font-medium transition-weightless" :class="tab === 'description' ? 'bg-aura-100 text-aura-800' : 'bg-slate-50 text-slate-600 hover:bg-slate-100'" @click="tab = 'description'">{{ __('jobs.tabs.description') }}</button>
                <button type="button" class="rounded-full px-4 py-2 text-sm font-medium transition-weightless" :class="tab === 'pipeline' ? 'bg-aura-100 text-aura-800' : 'bg-slate-50 text-slate-600 hover:bg-slate-100'" @click="tab = 'pipeline'">{{ __('jobs.tabs.pipeline') }}</button>
                <button type="button" class="rounded-full px-4 py-2 text-sm font-medium transition-weightless" :class="tab === 'weighting' ? 'bg-aura-100 text-aura-800' : 'bg-slate-50 text-slate-600 hover:bg-slate-100'" @click="tab = 'weighting'">{{ __('jobs.tabs.weighting') }}</button>
                <button type="button" class="rounded-full px-4 py-2 text-sm font-medium transition-weightless" :class="tab === 'analysis' ? 'bg-aura-100 text-aura-800' : 'bg-slate-50 text-slate-600 hover:bg-slate-100'" @click="tab = 'analysis'">{{ __('jobs.tabs.analysis') }}</button>
                <button type="button" class="rounded-full px-4 py-2 text-sm font-medium transition-weightless" :class="tab === 'persona' ? 'bg-aura-100 text-aura-800' : 'bg-slate-50 text-slate-600 hover:bg-slate-100'" @click="tab = 'persona'">{{ __('jobs.tabs.persona') }}</button>
            </div>

            <div class="mt-6" x-show="tab === 'description'" x-cloak>
                @php
                    $blocksByType = $job->descriptionBlocks->keyBy('block_type');
                    $getContent = function($type) use ($blocksByType) {
                        $block = $blocksByType->get($type);
                        if (!$block) return '';
                        $json = $block->block_content_json;
                        if (is_array($json) && isset($json['text'])) return $json['text'];
                        if (is_array($json)) return json_encode($json, JSON_UNESCAPED_UNICODE);
                        return '';
                    };
                @endphp
                <form method="POST" action="{{ route('jobs.blocks.save', ['job' => $job, 'company_id' => $selectedCompanyId]) }}" class="space-y-6">
                    @csrf
                    @if(auth()->user()?->isSuperadmin())
                        <input type="hidden" name="company_id" value="{{ $selectedCompanyId }}">
                    @endif

                    <div>
                        <input type="hidden" name="block_type[]" value="overview">
                        <input type="hidden" name="display_order[]" value="1">
                        <x-form-field label="Aperçu du poste">
                            <textarea name="block_content[]" rows="3" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-aura-500 focus:ring-aura-500" placeholder="Décrivez le rôle en quelques mots...">{{ old('block_content.0', $getContent('overview')) }}</textarea>
                        </x-form-field>
                    </div>

                    <div>
                        <input type="hidden" name="block_type[]" value="responsibilities">
                        <input type="hidden" name="display_order[]" value="2">
                        <x-form-field label="Missions & Responsabilités">
                            <textarea name="block_content[]" rows="5" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-aura-500 focus:ring-aura-500" placeholder="Quelles seront les missions principales ?">{{ old('block_content.1', $getContent('responsibilities')) }}</textarea>
                        </x-form-field>
                    </div>

                    <div>
                        <input type="hidden" name="block_type[]" value="requirements">
                        <input type="hidden" name="display_order[]" value="3">
                        <x-form-field label="Profil & Compétences recherchés">
                            <textarea name="block_content[]" rows="5" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-aura-500 focus:ring-aura-500" placeholder="Compétences, expérience, diplômes...">{{ old('block_content.2', $getContent('requirements')) }}</textarea>
                        </x-form-field>
                    </div>

                    <div>
                        <input type="hidden" name="block_type[]" value="benefits">
                        <input type="hidden" name="display_order[]" value="4">
                        <x-form-field label="Avantages">
                            <textarea name="block_content[]" rows="3" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-aura-500 focus:ring-aura-500" placeholder="Tickets restaurant, mutuelle, télétravail...">{{ old('block_content.3', $getContent('benefits')) }}</textarea>
                        </x-form-field>
                    </div>

                    <div>
                        <input type="hidden" name="block_type[]" value="company_intro">
                        <input type="hidden" name="display_order[]" value="5">
                        <x-form-field label="À propos de l'entreprise">
                            <textarea name="block_content[]" rows="4" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-aura-500 focus:ring-aura-500" placeholder="Présentation de votre entreprise...">{{ old('block_content.4', $getContent('company_intro')) }}</textarea>
                        </x-form-field>
                    </div>

                    <div class="flex justify-end pt-2">
                        <button type="submit" class="rounded-xl bg-success-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition-weightless hover:bg-success-700">
                            Enregistrer la fiche de poste
                        </button>
                    </div>
                </form>
            </div>

            <div class="mt-4" x-show="tab === 'pipeline'" x-cloak>
                @php
                    $pipelineStagesData = $job->pipelineStages->map(fn($stage, $i) => [
                        'id' => (string) $stage->id,
                        'key' => $stage->stage_key,
                        'label' => $stage->stage_label,
                        'order' => $stage->display_order,
                        'terminal' => $stage->is_terminal,
                        'idx' => $i,
                    ])->values()->all();
                @endphp
                <form method="POST" action="{{ route('jobs.pipeline.save', ['job' => $job, 'company_id' => $selectedCompanyId]) }}" x-data='{
                    rows: @json($pipelineStagesData),
                    addRow() { this.rows.push({ id: "", key: "", label: "", order: this.rows.length + 1, terminal: false, idx: this.rows.length }); }
                }' class="space-y-3">
                    @csrf
                    @if(auth()->user()?->isSuperadmin())
                        <input type="hidden" name="company_id" value="{{ $selectedCompanyId }}">
                    @endif

                    <template x-for="(row, index) in rows" :key="index">
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 grid gap-4 lg:grid-cols-5">
                            <input type="hidden" x-bind:name="'stage_id[' + index + ']'" x-bind:value="row.id ?? ''">
                            <input type="text" x-bind:name="'stage_key[' + index + ']'" x-model="row.key" placeholder="{{ __('jobs.pipeline.stage_key') }}" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-aura-500 focus:ring-aura-500">
                            <input type="text" x-bind:name="'stage_label[' + index + ']'" x-model="row.label" placeholder="{{ __('jobs.pipeline.stage_label') }}" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-aura-500 focus:ring-aura-500">
                            <input type="number" min="1" x-bind:name="'display_order[' + index + ']'" x-model="row.order" placeholder="{{ __('jobs.pipeline.display_order') }}" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-aura-500 focus:ring-aura-500">
                            <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                                <input type="checkbox" x-bind:name="'is_terminal[]'" x-bind:value="index" x-model="row.terminal" class="rounded border-slate-300 text-aura-600 focus:ring-aura-500">
                                <span>{{ __('jobs.pipeline.terminal') }}</span>
                            </label>
                            <button type="button" class="rounded-xl border border-danger-200 bg-white px-3 py-2 text-sm text-danger-700 shadow-sm transition-weightless hover:bg-danger-50" @click="rows.splice(index, 1)">Supprimer l'étape</button>
                        </div>
                    </template>

                    <div class="flex gap-2 pt-2">
                        <button type="button" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition-weightless hover:bg-slate-50" @click="addRow()">{{ __('jobs.pipeline.add_row') }}</button>
                        <button type="submit" class="rounded-xl bg-success-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition-weightless hover:bg-success-700">{{ __('jobs.pipeline.save') }}</button>
                    </div>
                    @error('pipeline')
                        <p class="text-xs text-danger-700">{{ $message }}</p>
                    @enderror
                </form>
            </div>

            <div class="mt-4" x-show="tab === 'weighting'" x-cloak>
                @php
                    $storedWeighting = is_array($job->weightingConfig?->weighting_json)
                        ? $job->weightingConfig?->weighting_json
                        : [];
                    $hasNewWeightingKeys = collect([
                        'skills_match',
                        'experience_match',
                        'education_match',
                        'certifications',
                        'language_match',
                        'assessment_performance',
                        'interview_performance',
                        'strategy_lab',
                        'culture_fit',
                    ])->contains(fn (string $key): bool => array_key_exists($key, $storedWeighting));

                    if (! $hasNewWeightingKeys && (
                        array_key_exists('skill', $storedWeighting)
                        || array_key_exists('experience', $storedWeighting)
                        || array_key_exists('culture', $storedWeighting)
                        || array_key_exists('potential', $storedWeighting)
                    )) {
                        $weights = [
                            'skills_match' => (int) ($storedWeighting['skill'] ?? 40),
                            'experience_match' => (int) ($storedWeighting['experience'] ?? 30),
                            'education_match' => 0,
                            'certifications' => 0,
                            'language_match' => 0,
                            'assessment_performance' => (int) ($storedWeighting['potential'] ?? 10),
                            'interview_performance' => 0,
                            'strategy_lab' => 0,
                            'culture_fit' => (int) ($storedWeighting['culture'] ?? 20),
                        ];
                    } else {
                        $weights = [
                            'skills_match' => (int) ($storedWeighting['skills_match'] ?? 20),
                            'experience_match' => (int) ($storedWeighting['experience_match'] ?? 15),
                            'education_match' => (int) ($storedWeighting['education_match'] ?? 10),
                            'certifications' => (int) ($storedWeighting['certifications'] ?? 8),
                            'language_match' => (int) ($storedWeighting['language_match'] ?? 8),
                            'assessment_performance' => (int) ($storedWeighting['assessment_performance'] ?? 10),
                            'interview_performance' => (int) ($storedWeighting['interview_performance'] ?? 10),
                            'strategy_lab' => (int) ($storedWeighting['strategy_lab'] ?? 10),
                            'culture_fit' => (int) ($storedWeighting['culture_fit'] ?? 9),
                        ];
                    }
                @endphp
                <form method="POST" action="{{ route('jobs.weighting.save', ['job' => $job, 'company_id' => $selectedCompanyId]) }}" x-data='{
                    skills_match: {{ (int)($weights["skills_match"] ?? 20) }},
                    experience_match: {{ (int)($weights["experience_match"] ?? 15) }},
                    education_match: {{ (int)($weights["education_match"] ?? 10) }},
                    certifications: {{ (int)($weights["certifications"] ?? 8) }},
                    language_match: {{ (int)($weights["language_match"] ?? 8) }},
                    assessment_performance: {{ (int)($weights["assessment_performance"] ?? 10) }},
                    interview_performance: {{ (int)($weights["interview_performance"] ?? 10) }},
                    strategy_lab: {{ (int)($weights["strategy_lab"] ?? 10) }},
                    culture_fit: {{ (int)($weights["culture_fit"] ?? 9) }},
                    get total() {
                        return this.skills_match
                            + this.experience_match
                            + this.education_match
                            + this.certifications
                            + this.language_match
                            + this.assessment_performance
                            + this.interview_performance
                            + this.strategy_lab
                            + this.culture_fit;
                    }
                }' class="space-y-4">
                    @csrf
                    @if(auth()->user()?->isSuperadmin())
                        <input type="hidden" name="company_id" value="{{ $selectedCompanyId }}">
                    @endif

                    <div class="grid gap-4 lg:grid-cols-3">
                        <x-form-field :label="__('jobs.weighting.skills_match')" name="weight_skills_match">
                            <input type="range" min="0" max="100" name="weight_skills_match" x-model.number="skills_match" class="w-full">
                            <p class="text-sm text-slate-700" x-text="skills_match"></p>
                        </x-form-field>
                        <x-form-field :label="__('jobs.weighting.experience_match')" name="weight_experience_match">
                            <input type="range" min="0" max="100" name="weight_experience_match" x-model.number="experience_match" class="w-full">
                            <p class="text-sm text-slate-700" x-text="experience_match"></p>
                        </x-form-field>
                        <x-form-field :label="__('jobs.weighting.education_match')" name="weight_education_match">
                            <input type="range" min="0" max="100" name="weight_education_match" x-model.number="education_match" class="w-full">
                            <p class="text-sm text-slate-700" x-text="education_match"></p>
                        </x-form-field>
                        <x-form-field :label="__('jobs.weighting.certifications')" name="weight_certifications">
                            <input type="range" min="0" max="100" name="weight_certifications" x-model.number="certifications" class="w-full">
                            <p class="text-sm text-slate-700" x-text="certifications"></p>
                        </x-form-field>
                        <x-form-field :label="__('jobs.weighting.language_match')" name="weight_language_match">
                            <input type="range" min="0" max="100" name="weight_language_match" x-model.number="language_match" class="w-full">
                            <p class="text-sm text-slate-700" x-text="language_match"></p>
                        </x-form-field>
                        <x-form-field :label="__('jobs.weighting.assessment_performance')" name="weight_assessment_performance">
                            <input type="range" min="0" max="100" name="weight_assessment_performance" x-model.number="assessment_performance" class="w-full">
                            <p class="text-sm text-slate-700" x-text="assessment_performance"></p>
                        </x-form-field>
                        <x-form-field :label="__('jobs.weighting.interview_performance')" name="weight_interview_performance">
                            <input type="range" min="0" max="100" name="weight_interview_performance" x-model.number="interview_performance" class="w-full">
                            <p class="text-sm text-slate-700" x-text="interview_performance"></p>
                        </x-form-field>
                        <x-form-field :label="__('jobs.weighting.strategy_lab')" name="weight_strategy_lab">
                            <input type="range" min="0" max="100" name="weight_strategy_lab" x-model.number="strategy_lab" class="w-full">
                            <p class="text-sm text-slate-700" x-text="strategy_lab"></p>
                        </x-form-field>
                        <x-form-field :label="__('jobs.weighting.culture_fit')" name="weight_culture_fit">
                            <input type="range" min="0" max="100" name="weight_culture_fit" x-model.number="culture_fit" class="w-full">
                            <p class="text-sm text-slate-700" x-text="culture_fit"></p>
                        </x-form-field>
                    </div>

                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <p class="text-sm font-semibold text-slate-900">{{ __('jobs.weighting.total') }}: <span x-text="total"></span></p>
                        <p class="text-xs text-danger-700" x-show="total !== 100">{{ __('jobs.weighting_sum_invalid') }}</p>
                        @error('weight_total')
                            <p class="text-xs text-danger-700">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="pt-2">
                        <button type="submit" class="rounded-xl bg-success-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition-weightless hover:bg-success-700">{{ __('jobs.weighting.save') }}</button>
                    </div>
                </form>
            </div>

            <div class="mt-4 space-y-4" x-show="tab === 'analysis'" x-cloak>
                @php
                    $snapshotRows = collect((array) ($analysisSnapshot['rows'] ?? []));
                    $snapshotTopThree = collect((array) ($analysisSnapshot['top_three'] ?? []));
                    $snapshotDistribution = collect((array) ($analysisSnapshot['score_distribution'] ?? []));
                    $snapshotFairness = is_array($analysisSnapshot['fairness'] ?? null)
                        ? $analysisSnapshot['fairness']
                        : null;
                    $maxDistributionCount = max(1, (int) $snapshotDistribution->max('count'));
                @endphp

                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <p class="text-xs uppercase tracking-wide text-slate-600">{{ __('jobs.analysis.title') }}</p>
                            <p class="mt-1 text-sm text-slate-600">{{ __('jobs.analysis.subtitle') }}</p>
                        </div>
                        <a href="{{ route('candidates.index', array_filter(['company_id' => $selectedCompanyId, 'job_id' => $job->id])) }}"
                           class="rounded-lg border border-aura-200 bg-white px-3 py-1.5 text-xs font-semibold text-aura-800 shadow-sm transition-weightless hover:bg-aura-50">
                            {{ __('jobs.analysis.open_workspace') }}
                        </a>
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-xs uppercase tracking-wide text-slate-600">{{ __('jobs.analysis.top_three') }}</p>
                    @if($snapshotTopThree->isEmpty())
                        <p class="mt-2 text-sm text-slate-600">{{ __('jobs.analysis.empty') }}</p>
                    @else
                        <div class="mt-3 grid gap-3 md:grid-cols-3">
                            @foreach($snapshotTopThree as $topRow)
                                <div class="rounded-xl border border-success-200/70 bg-success-50/60 p-3">
                                    <p class="text-[11px] font-semibold uppercase tracking-wide text-success-700">#{{ (int) ($topRow['ranking_position'] ?? 0) }}</p>
                                    <p class="mt-1 text-sm font-semibold text-slate-900">{{ (string) ($topRow['candidate_name'] ?? __('candidates.detail.not_available')) }}</p>
                                    <p class="mt-1 text-xs text-slate-600">{{ __('jobs.analysis.total_score') }}: {{ is_numeric($topRow['total_score'] ?? null) ? number_format((float) $topRow['total_score'], 1).'/100' : __('candidates.detail.not_available') }}</p>
                                    <a href="{{ route('candidates.index', array_filter(['company_id' => $selectedCompanyId, 'application_id' => (string) ($topRow['application_id'] ?? '')])) }}"
                                       class="mt-2 inline-flex rounded-md border border-slate-200 bg-white px-2.5 py-1 text-xs font-medium text-slate-700 transition-weightless hover:bg-slate-50">
                                        {{ __('jobs.analysis.open_review') }}
                                    </a>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-xs uppercase tracking-wide text-slate-600">{{ __('jobs.analysis.ranking_grid') }}</p>
                    @if($snapshotRows->isEmpty())
                        <p class="mt-2 text-sm text-slate-600">{{ __('jobs.analysis.empty') }}</p>
                    @else
                        <div class="mt-3 overflow-x-auto">
                            <table class="min-w-full divide-y divide-slate-200 text-sm">
                                <thead class="bg-slate-50/80">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs uppercase tracking-wide text-slate-600">{{ __('jobs.analysis.rank') }}</th>
                                        <th class="px-3 py-2 text-left text-xs uppercase tracking-wide text-slate-600">{{ __('jobs.analysis.candidate') }}</th>
                                        <th class="px-3 py-2 text-left text-xs uppercase tracking-wide text-slate-600">{{ __('jobs.analysis.total_score') }}</th>
                                        <th class="px-3 py-2 text-left text-xs uppercase tracking-wide text-slate-600">{{ __('jobs.analysis.indicators') }}</th>
                                        <th class="px-3 py-2 text-left text-xs uppercase tracking-wide text-slate-600">{{ __('jobs.analysis.status') }}</th>
                                        <th class="px-3 py-2 text-left text-xs uppercase tracking-wide text-slate-600">{{ __('jobs.analysis.review') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 bg-white">
                                    @foreach($snapshotRows as $row)
                                        @php
                                            $analysisStatus = (string) ($row['analysis_status'] ?? '');
                                            $statusClasses = match ($analysisStatus) {
                                                'ready' => 'border-success-200 bg-success-50 text-success-800',
                                                \App\Services\Analysis\CandidateAnalysisService::ANALYSIS_INVALID_CV => 'border-danger-200 bg-danger-50 text-danger-800',
                                                'insufficient_data' => 'border-primary-200 bg-primary-50 text-primary-800',
                                                default => 'border-slate-200 bg-slate-100 text-slate-700',
                                            };
                                        @endphp
                                        <tr>
                                            <td class="px-3 py-2 text-slate-700">#{{ (int) ($row['ranking_position'] ?? 0) }}</td>
                                            <td class="px-3 py-2 font-medium text-slate-900">{{ (string) ($row['candidate_name'] ?? __('candidates.detail.not_available')) }}</td>
                                            <td class="px-3 py-2 text-slate-700">{{ is_numeric($row['total_score'] ?? null) ? number_format((float) $row['total_score'], 1).'/100' : __('candidates.detail.not_available') }}</td>
                                            <td class="px-3 py-2 text-xs text-slate-600">
                                                S {{ is_numeric(data_get($row, 'indicators.skills')) ? number_format((float) data_get($row, 'indicators.skills'), 0) : '-' }}
                                                |
                                                E {{ is_numeric(data_get($row, 'indicators.experience')) ? number_format((float) data_get($row, 'indicators.experience'), 0) : '-' }}
                                                |
                                                Ed {{ is_numeric(data_get($row, 'indicators.education')) ? number_format((float) data_get($row, 'indicators.education'), 0) : '-' }}
                                            </td>
                                            <td class="px-3 py-2">
                                                <span class="inline-flex rounded-full border px-2 py-0.5 text-[11px] font-semibold {{ $statusClasses }}">
                                                    {{ \App\Services\Analysis\CandidateAnalysisService::analysisStatusLabel($analysisStatus) }}
                                                </span>
                                            </td>
                                            <td class="px-3 py-2">
                                                <a href="{{ route('candidates.index', array_filter(['company_id' => $selectedCompanyId, 'application_id' => (string) ($row['application_id'] ?? '')])) }}"
                                                   class="rounded-md border border-slate-200 bg-white px-2.5 py-1 text-xs font-medium text-slate-700 transition-weightless hover:bg-slate-50">
                                                    {{ __('jobs.analysis.open_review') }}
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>

                <div class="grid gap-4 lg:grid-cols-2">
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <p class="text-xs uppercase tracking-wide text-slate-600">{{ __('jobs.analysis.score_distribution') }}</p>
                        @if($snapshotDistribution->isEmpty())
                            <p class="mt-2 text-sm text-slate-600">{{ __('jobs.analysis.empty') }}</p>
                        @else
                            <div class="mt-3 space-y-2">
                                @foreach($snapshotDistribution as $bucket)
                                    @php
                                        $count = (int) ($bucket['count'] ?? 0);
                                        $width = (int) round(($count / $maxDistributionCount) * 100);
                                    @endphp
                                    <div>
                                        <div class="flex items-center justify-between text-xs text-slate-700">
                                            <span>{{ (string) ($bucket['label'] ?? '') }}</span>
                                            <span>{{ $count }}</span>
                                        </div>
                                        <div class="mt-1 h-2 overflow-hidden rounded-full bg-slate-200">
                                            <div class="h-2 rounded-full bg-primary-500" style="width: {{ max(4, $width) }}%"></div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <p class="text-xs uppercase tracking-wide text-slate-600">{{ __('jobs.analysis.fairness') }}</p>
                        @if(! is_array($snapshotFairness))
                            <p class="mt-2 text-sm text-slate-600">{{ __('jobs.analysis.empty') }}</p>
                        @else
                            @php
                                $gender = (array) ($snapshotFairness['gender'] ?? []);
                                $school = (array) ($snapshotFairness['school'] ?? []);
                            @endphp
                            <div class="mt-3 space-y-3 text-xs text-slate-700">
                                <div class="rounded-xl border border-slate-200 bg-white p-3">
                                    <p class="font-semibold text-slate-800">{{ __('jobs.analysis.fairness_gender') }}</p>
                                    <p class="mt-1">{{ __('ui.fairness.groups.men') }}: {{ (int) ($gender['group_a'] ?? 0) }} | {{ __('ui.fairness.groups.women') }}: {{ (int) ($gender['group_b'] ?? 0) }}</p>
                                    <p class="mt-1">{{ __('ui.fairness.equality_pulse.impact_ratio') }}: {{ is_numeric($gender['impact_ratio'] ?? null) ? number_format((float) $gender['impact_ratio'], 2) : __('candidates.detail.not_available') }}</p>
                                </div>
                                <div class="rounded-xl border border-slate-200 bg-white p-3">
                                    <p class="font-semibold text-slate-800">{{ __('jobs.analysis.fairness_school') }}</p>
                                    <p class="mt-1">{{ __('ui.fairness.groups.top_grande') }}: {{ (int) ($school['group_a'] ?? 0) }} | {{ __('ui.fairness.groups.regular_faculty') }}: {{ (int) ($school['group_b'] ?? 0) }}</p>
                                    <p class="mt-1">{{ __('ui.fairness.equality_pulse.impact_ratio') }}: {{ is_numeric($school['impact_ratio'] ?? null) ? number_format((float) $school['impact_ratio'], 2) : __('candidates.detail.not_available') }}</p>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="mt-4 space-y-4" x-show="tab === 'multiposting'" x-cloak>
                @php
                    $channelGroups = $multipostingChannelGroups ?? [];
                @endphp
                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <h3 class="text-sm font-semibold uppercase tracking-wider text-aura-700/85">{{ __('jobs.multiposting.title') }}</h3>
                            <p class="mt-1 text-sm text-slate-700">{{ __('jobs.multiposting.description') }}</p>
                        </div>

                        <x-ui.modal
                            id="multiposting-workflow-modal"
                            :title="__('jobs.multiposting.bulk.modal_title')"
                            :initially-open="request()->boolean('open_multiposting_workflow')"
                        >
                            <x-slot:trigger>
                                <button type="button" class="rounded-xl bg-primary-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-primary-700">
                                    {{ __('jobs.multiposting.bulk.launch') }}
                                </button>
                            </x-slot:trigger>

                            <form method="POST"
                                  action="{{ route('jobs.multiposting.bulk', ['job' => $job, 'company_id' => $selectedCompanyId]) }}"
                                  x-data="{ selected: [] }"
                                  class="space-y-4">
                                @csrf
                                @if(auth()->user()?->isSuperadmin())
                                    <input type="hidden" name="company_id" value="{{ $selectedCompanyId }}">
                                @endif

                                <div class="rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700">
                                    {{ __('jobs.multiposting.bulk.description') }}
                                </div>

                                <div class="space-y-4">
                                    @foreach($channelGroups as $deliveryType => $channels)
                                        <div class="space-y-2">
                                            <p class="text-xs font-semibold uppercase tracking-wide text-aura-700/85">
                                                {{ __('jobs.multiposting.delivery_types.'.$deliveryType) }}
                                            </p>

                                            <div class="space-y-2">
                                                @foreach($channels as $channel)
                                                    @php
                                                        $platform = (string) ($channel['key'] ?? '');
                                                        $readiness = (array) (($multipostingReadinessByPlatform ?? [])[$platform] ?? []);
                                                        $isReady = (bool) ($readiness['ready'] ?? true);
                                                        $posting = $jobPostingsByPlatform->get($platform);
                                                        $status = (string) ($posting?->status ?? \App\Models\JobPosting::STATUS_DISABLED);
                                                        $requiresOauth = (bool) ($readiness['requires_oauth'] ?? false);
                                                    @endphp

                                                    <label class="flex items-start gap-3 rounded-xl border p-3 {{ $isReady ? 'border-slate-200 bg-white' : 'border-danger-200 bg-danger-50/50' }}">
                                                        <input type="checkbox"
                                                               name="platforms[]"
                                                               value="{{ $platform }}"
                                                               x-model="selected"
                                                               @disabled(! $isReady)
                                                               class="mt-1 rounded border-slate-300 text-primary-600 focus:ring-primary-500 disabled:cursor-not-allowed">
                                                        <span class="flex-1">
                                                            <span class="flex flex-wrap items-center gap-2">
                                                                <span class="text-sm font-semibold text-slate-900">{{ $channel['label'] ?? __('jobs.multiposting.platforms.'.$platform) }}</span>
                                                                <span class="rounded-full border px-2 py-0.5 text-[11px] text-slate-700">
                                                                    {{ __('jobs.multiposting.statuses.'.$status) }}
                                                                </span>
                                                            </span>
                                                            <span class="mt-1 block text-xs text-slate-600">
                                                                {{ __('jobs.multiposting.publish_methods.'.((string) ($channel['publish_method'] ?? 'unknown'))) }}
                                                                •
                                                                {{ __('jobs.multiposting.auth_methods.'.((string) ($channel['auth_method'] ?? 'unknown'))) }}
                                                                •
                                                                {{ __('jobs.multiposting.execution_modes.'.((string) ($channel['execution_mode'] ?? 'unknown'))) }}
                                                            </span>
                                                            @if(! $isReady)
                                                                <span class="mt-1 block text-xs text-danger-800">
                                                                    {{ $requiresOauth ? __('jobs.multiposting.readiness.oauth_required') : __('jobs.multiposting.readiness.not_ready') }}
                                                                </span>
                                                                @if($requiresOauth)
                                                                    <a href="{{ route('configuration.index', ['company_id' => $selectedCompanyId]) }}" class="mt-2 inline-flex text-xs font-semibold text-danger-900 underline underline-offset-2">
                                                                        {{ __('jobs.multiposting.readiness.open_configuration') }}
                                                                    </a>
                                                                @endif
                                                            @endif
                                                        </span>
                                                    </label>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endforeach
                                </div>

                                <div class="rounded-xl border border-slate-200 bg-white p-3">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('jobs.multiposting.bulk.selected_label') }}</p>
                                    <p class="mt-1 text-sm text-slate-800" x-text="selected.length ? selected.join(', ') : '{{ __('jobs.multiposting.bulk.none_selected') }}'"></p>
                                </div>

                                <div class="flex flex-wrap gap-3">
                                    <button type="submit"
                                            name="action"
                                            value="enable"
                                            :disabled="selected.length === 0"
                                            class="rounded-xl border border-success-300/60 bg-success-50 px-4 py-2 text-sm font-medium text-success-900 transition hover:bg-success-100 disabled:cursor-not-allowed disabled:border-slate-200 disabled:bg-slate-100 disabled:text-slate-500">
                                        {{ __('jobs.multiposting.bulk.enable_selected') }}
                                    </button>
                                    <button type="submit"
                                            name="action"
                                            value="generate"
                                            :disabled="selected.length === 0"
                                            class="rounded-xl border border-primary-300/60 bg-primary-50 px-4 py-2 text-sm font-medium text-primary-900 transition hover:bg-primary-100 disabled:cursor-not-allowed disabled:border-slate-200 disabled:bg-slate-100 disabled:text-slate-500">
                                        {{ __('jobs.multiposting.bulk.generate_selected') }}
                                    </button>
                                    <button type="submit"
                                            name="action"
                                            value="publish"
                                            :disabled="selected.length === 0"
                                            class="rounded-xl bg-success-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-success-700 disabled:cursor-not-allowed disabled:bg-slate-300">
                                        {{ __('jobs.multiposting.bulk.publish_selected') }}
                                    </button>
                                </div>
                            </form>
                        </x-ui.modal>
                    </div>
                </div>
                @foreach($channelGroups as $deliveryType => $channels)
                    <section class="space-y-3">
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wider text-aura-700/85">
                                {{ __('jobs.multiposting.delivery_types.'.$deliveryType) }}
                            </p>
                            <p class="mt-1 text-sm text-slate-700">
                                {{ __('jobs.multiposting.delivery_type_help.'.$deliveryType) }}
                            </p>
                        </div>

                        <div class="grid gap-4 lg:grid-cols-2">
                    @foreach($channels as $channel)
                        @php
                            $platform = (string) ($channel['key'] ?? '');
                            $posting = $jobPostingsByPlatform->get($platform);
                            $status = (string) ($posting?->status ?? \App\Models\JobPosting::STATUS_DISABLED);
                            $isEnabled = $status !== \App\Models\JobPosting::STATUS_DISABLED;
                            $statusClasses = [
                                \App\Models\JobPosting::STATUS_DISABLED => 'border-slate-200 bg-slate-100 text-slate-700',
                                \App\Models\JobPosting::STATUS_DRAFT => 'border-primary-200 bg-primary-100 text-primary-900',
                                \App\Models\JobPosting::STATUS_GENERATING => 'border-primary-200 bg-primary-50 text-primary-900',
                                \App\Models\JobPosting::STATUS_READY => 'border-success-200 bg-success-100 text-success-900',
                                \App\Models\JobPosting::STATUS_PUBLISHING => 'border-primary-200 bg-primary-50 text-primary-900',
                                \App\Models\JobPosting::STATUS_PUBLISHED => 'border-success-200 bg-success-100 text-success-900',
                                \App\Models\JobPosting::STATUS_FAILED => 'border-danger-200 bg-danger-100 text-danger-900',
                            ];
                            $statusClass = $statusClasses[$status] ?? $statusClasses[\App\Models\JobPosting::STATUS_DISABLED];
                            $trackingUrl = (string) ($posting?->tracking_url ?? '');
                            $hasContent = trim((string) ($posting?->ai_generated_content ?? '')) !== '';
                            $postedAt = $posting?->posted_at;
                            $executionMode = (string) ($channel['execution_mode'] ?? 'unknown');
                            $lastAttemptedAt = $posting?->last_publish_attempted_at;
                            $lastSucceededAt = $posting?->last_publish_succeeded_at;
                            $lastPublishStatus = (string) ($posting?->last_publish_status ?? '');
                            $lastExecutionMode = (string) ($posting?->last_execution_mode ?? $executionMode);
                            $lastPublishError = trim((string) ($posting?->last_publish_error ?? ''));
                            $recentAttempts = $posting?->publishAttempts
                                ? $posting->publishAttempts->take(3)
                                : collect();
                            $publishMethod = (string) ($channel['publish_method'] ?? 'unknown');
                            $authMethod = (string) ($channel['auth_method'] ?? 'unknown');
                            $capabilities = (array) ($channel['capabilities'] ?? []);
                            $readiness = (array) (($multipostingReadinessByPlatform ?? [])[$platform] ?? []);
                            $automation = (array) (($multipostingAutomationByPlatform ?? [])[$platform] ?? []);
                            $isReady = (bool) ($readiness['ready'] ?? true);
                            $requiresOauth = (bool) ($readiness['requires_oauth'] ?? false);
                            $integrationStatus = (string) ($readiness['integration_status'] ?? '');
                            $configurationUrl = route('configuration.index', ['company_id' => $selectedCompanyId]);
                            $usesAutomationFallback = (bool) ($automation['enabled'] ?? false);
                            $automationRole = (string) ($automation['role'] ?? 'fallback');
                            $automationScriptExists = (bool) ($automation['script_exists'] ?? false);
                            $capabilityLabels = collect($capabilities)
                                ->filter(fn ($enabled) => (bool) $enabled)
                                ->keys()
                                ->map(fn ($capability) => __('jobs.multiposting.capabilities.'.$capability))
                                ->filter(fn ($label) => ! str_contains((string) $label, 'jobs.multiposting.capabilities.'))
                                ->values();
                        @endphp

                        <article class="rounded-2xl border border-slate-200 bg-slate-50 p-4 space-y-3">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h4 class="text-base font-semibold text-slate-900">{{ $channel['label'] ?? __('jobs.multiposting.platforms.'.$platform) }}</h4>
                                    <p class="text-xs text-slate-600">{{ __('jobs.multiposting.clicks', ['count' => (int) ($posting?->clicks_count ?? 0)]) }}</p>
                                </div>
                                <span class="rounded-full border px-2.5 py-1 text-xs font-semibold {{ $statusClass }}">
                                    {{ __('jobs.multiposting.statuses.'.$status) }}
                                </span>
                            </div>

                            <div class="flex flex-wrap gap-2 text-[11px]">
                                <span class="rounded-full border border-aura-200/60 bg-aura-50 px-2.5 py-1 text-slate-700">
                                    {{ __('jobs.multiposting.publish_methods.'.$publishMethod) }}
                                </span>
                                <span class="rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-slate-700">
                                    {{ __('jobs.multiposting.auth_methods.'.$authMethod) }}
                                </span>
                                <span class="rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-slate-700">
                                    {{ __('jobs.multiposting.execution_modes.'.$executionMode) }}
                                </span>
                                @if($usesAutomationFallback)
                                    <span class="rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-amber-900">
                                        {{ __('jobs.multiposting.automation.role_badge', ['role' => __('jobs.multiposting.automation.roles.'.$automationRole)]) }}
                                    </span>
                                @endif
                            </div>

                            @if($capabilityLabels->isNotEmpty())
                                <div class="flex flex-wrap gap-2">
                                    @foreach($capabilityLabels as $capabilityLabel)
                                        <span class="rounded-full border border-primary-200 bg-primary-50 px-2.5 py-1 text-[11px] text-primary-900">
                                            {{ $capabilityLabel }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif

                            @if(! $isReady)
                                <div class="rounded-xl border border-danger-200 bg-danger-50 p-3 text-sm text-danger-900">
                                    <p class="font-semibold">{{ __('jobs.multiposting.readiness.not_ready') }}</p>
                                    <p class="mt-1">{{ __('jobs.multiposting.readiness.oauth_required') }}</p>
                                    @if($requiresOauth)
                                        <p class="mt-1 text-xs uppercase tracking-wide text-danger-700">
                                            {{ __('jobs.multiposting.readiness.integration_status') }}:
                                            {{ __('ui.integrations.statuses.'.$integrationStatus) }}
                                        </p>
                                    @endif
                                    <a href="{{ $configurationUrl }}" class="mt-3 inline-flex rounded-xl border border-danger-300/60 bg-white px-3 py-2 text-xs font-medium text-danger-900 transition hover:bg-danger-100/40">
                                        {{ __('jobs.multiposting.readiness.open_configuration') }}
                                    </a>
                                </div>
                            @endif

                            @if($usesAutomationFallback)
                                <div class="rounded-xl border border-amber-200 bg-amber-50/80 p-3 text-sm text-amber-900">
                                    <p class="font-semibold">{{ __('jobs.multiposting.automation.title') }}</p>
                                    <p class="mt-1">{{ __('jobs.multiposting.automation.fallback_notice') }}</p>
                                    <p class="mt-2 text-xs uppercase tracking-wide text-amber-800">
                                        {{ __('jobs.multiposting.automation.role_label', ['role' => __('jobs.multiposting.automation.roles.'.$automationRole)]) }}
                                    </p>
                                    <p class="mt-1 text-xs text-amber-900">
                                        {{ __('jobs.multiposting.automation.script_status', ['status' => $automationScriptExists ? __('jobs.multiposting.automation.script_ready') : __('jobs.multiposting.automation.script_missing')]) }}
                                    </p>
                                </div>
                            @endif

                            <div class="flex flex-wrap gap-2">
                                <form method="POST" action="{{ route('jobs.multiposting.toggle', ['job' => $job, 'platform' => $platform, 'company_id' => $selectedCompanyId]) }}">
                                    @csrf
                                    @if(auth()->user()?->isSuperadmin())
                                        <input type="hidden" name="company_id" value="{{ $selectedCompanyId }}">
                                    @endif
                                    <button type="submit" @disabled(! $isReady) class="{{ $isEnabled ? 'rounded-xl border border-danger-300/60 bg-danger-50 px-3 py-2 text-xs font-medium text-danger-800 transition-weightless hover:bg-danger-100/80' : 'rounded-xl border border-success-300/60 bg-success-50 px-3 py-2 text-xs font-medium text-success-800 transition-weightless hover:bg-success-100/80' }} disabled:cursor-not-allowed disabled:border-slate-200 disabled:bg-slate-100 disabled:text-slate-500">
                                        {{ $isEnabled ? __('jobs.multiposting.disable') : __('jobs.multiposting.enable') }}
                                    </button>
                                </form>

                                @if($isEnabled)
                                    <form method="POST" action="{{ route('jobs.multiposting.generate', ['job' => $job, 'platform' => $platform, 'company_id' => $selectedCompanyId]) }}">
                                        @csrf
                                        @if(auth()->user()?->isSuperadmin())
                                            <input type="hidden" name="company_id" value="{{ $selectedCompanyId }}">
                                        @endif
                                        <button type="submit" @disabled(! $isReady) class="rounded-xl border border-primary-300/60 bg-primary-50 px-3 py-2 text-xs font-medium text-primary-800 transition-weightless hover:bg-primary-100/80 disabled:cursor-not-allowed disabled:border-slate-200 disabled:bg-slate-100 disabled:text-slate-500">
                                            {{ __('jobs.multiposting.generate') }}
                                        </button>
                                    </form>

                                    @if($status === \App\Models\JobPosting::STATUS_FAILED)
                                        <form method="POST" action="{{ route('jobs.multiposting.retry', ['job' => $job, 'platform' => $platform, 'company_id' => $selectedCompanyId]) }}">
                                            @csrf
                                            @if(auth()->user()?->isSuperadmin())
                                                <input type="hidden" name="company_id" value="{{ $selectedCompanyId }}">
                                            @endif
                                            <button type="submit" @disabled(! $isReady) class="rounded-xl border border-danger-300/60 bg-white/80 px-3 py-2 text-xs font-medium text-danger-800 transition-weightless hover:bg-danger-50 disabled:cursor-not-allowed disabled:border-slate-200 disabled:bg-slate-100 disabled:text-slate-500">
                                                {{ __('jobs.multiposting.retry') }}
                                            </button>
                                        </form>
                                    @endif

                                    <x-ui.modal :id="'multiposting-edit-'.$platform" :title="__('jobs.multiposting.edit_modal_title', ['platform' => __('jobs.multiposting.platforms.'.$platform)])">
                                        <x-slot:trigger>
                                            <button type="button" @disabled(! $isReady) class="rounded-xl border border-aura-300/50 bg-white/80 px-3 py-2 text-xs font-medium text-slate-900 transition-weightless hover:bg-white disabled:cursor-not-allowed disabled:border-slate-200 disabled:bg-slate-100 disabled:text-slate-500">
                                                {{ __('jobs.multiposting.edit') }}
                                            </button>
                                        </x-slot:trigger>

                                        <form method="POST" action="{{ route('jobs.multiposting.save-content', ['job' => $job, 'platform' => $platform, 'company_id' => $selectedCompanyId]) }}" class="space-y-3">
                                            @csrf
                                            @if(auth()->user()?->isSuperadmin())
                                                <input type="hidden" name="company_id" value="{{ $selectedCompanyId }}">
                                            @endif
                                            <x-form-field :label="__('jobs.multiposting.content_label')">
                                                <textarea name="ai_generated_content" rows="10" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-sm text-slate-900 shadow-sm">{{ old('ai_generated_content', (string) ($posting?->ai_generated_content ?? '')) }}</textarea>
                                            </x-form-field>
                                            <p class="text-xs text-slate-600">{{ __('jobs.multiposting.content_help') }}</p>
                                            <button type="submit" class="rounded-xl bg-success-600 px-3 py-2 text-sm text-white transition-weightless hover:bg-success-700">
                                                {{ __('jobs.multiposting.save_content') }}
                                            </button>
                                        </form>
                                    </x-ui.modal>

                                    <form method="POST" action="{{ route('jobs.multiposting.publish', ['job' => $job, 'platform' => $platform, 'company_id' => $selectedCompanyId]) }}">
                                        @csrf
                                        @if(auth()->user()?->isSuperadmin())
                                            <input type="hidden" name="company_id" value="{{ $selectedCompanyId }}">
                                        @endif
                                        <button type="submit" @disabled(! $hasContent || ! $isReady) class="rounded-xl bg-success-600 px-3 py-2 text-xs font-medium text-white transition-weightless hover:bg-success-700 disabled:cursor-not-allowed disabled:bg-slate-200 disabled:text-slate-600">
                                            {{ __('jobs.multiposting.publish') }}
                                        </button>
                                    </form>
                                @endif
                            </div>

                            <div class="space-y-2">
                                <p class="text-xs font-semibold uppercase tracking-wider text-aura-700/80">{{ __('jobs.multiposting.tracking_link') }}</p>
                                @if($trackingUrl !== '')
                                    <input type="text" readonly value="{{ $trackingUrl }}" class="w-full rounded-xl border border-aura-200/40 bg-white/90 px-3 py-2 text-xs text-slate-800">
                                    <a href="{{ $trackingUrl }}" target="_blank" rel="noopener noreferrer" class="inline-flex rounded-xl border border-aura-300/50 bg-white/80 px-3 py-2 text-xs font-medium text-slate-900 transition-weightless hover:bg-white">
                                        {{ __('jobs.multiposting.open_tracking_link') }}
                                    </a>
                                @else
                                    <p class="text-xs text-slate-600">{{ __('jobs.multiposting.tracking_disabled') }}</p>
                                @endif

                                @if($postedAt)
                                    <p class="text-xs text-slate-600">{{ __('jobs.multiposting.posted_at', ['date' => $postedAt->diffForHumans()]) }}</p>
                                @endif
                            </div>

                            <div class="space-y-2 rounded-xl border border-slate-200 bg-slate-50/70 p-3">
                                <p class="text-xs font-semibold uppercase tracking-wider text-aura-700/80">{{ __('jobs.multiposting.diagnostics.title') }}</p>
                                @if($lastAttemptedAt)
                                    <p class="text-xs text-slate-700">{{ __('jobs.multiposting.diagnostics.last_attempted_at', ['date' => $lastAttemptedAt->diffForHumans()]) }}</p>
                                @endif
                                @if($lastSucceededAt)
                                    <p class="text-xs text-slate-700">{{ __('jobs.multiposting.diagnostics.last_succeeded_at', ['date' => $lastSucceededAt->diffForHumans()]) }}</p>
                                @endif
                                @if($lastPublishStatus !== '')
                                    <p class="text-xs text-slate-700">{{ __('jobs.multiposting.diagnostics.last_status', ['status' => __('jobs.multiposting.attempt_statuses.'.$lastPublishStatus)]) }}</p>
                                @endif
                                @if($lastExecutionMode !== '')
                                    <p class="text-xs text-slate-700">{{ __('jobs.multiposting.diagnostics.execution_mode', ['mode' => __('jobs.multiposting.execution_modes.'.$lastExecutionMode)]) }}</p>
                                @endif
                                @if($lastPublishError !== '')
                                    <p class="text-xs text-danger-700">{{ __('jobs.multiposting.diagnostics.last_error', ['error' => $lastPublishError]) }}</p>
                                @endif
                                @if($usesAutomationFallback)
                                    <p class="text-xs text-slate-700">{{ __('jobs.multiposting.automation.evidence_note') }}</p>
                                @endif

                                @if($recentAttempts->isNotEmpty())
                                    <div class="space-y-2 border-t border-slate-200 pt-2">
                                        <p class="text-xs font-semibold text-slate-700">{{ __('jobs.multiposting.diagnostics.recent_attempts') }}</p>
                                        @foreach($recentAttempts as $attempt)
                                            <div class="rounded-lg border border-white/80 bg-white/80 px-3 py-2 text-xs text-slate-700">
                                                <p class="font-semibold text-slate-900">
                                                    {{ __('jobs.multiposting.diagnostics.attempt_number', ['number' => $attempt->attempt_number]) }}
                                                    ·
                                                    {{ __('jobs.multiposting.attempt_statuses.'.$attempt->status) }}
                                                </p>
                                                <p class="mt-1">
                                                    {{ __('jobs.multiposting.diagnostics.execution_mode', ['mode' => __('jobs.multiposting.execution_modes.'.((string) ($attempt->execution_mode ?? 'unknown')))]) }}
                                                </p>
                                                @if($attempt->queued_at)
                                                    <p class="mt-1">{{ __('jobs.multiposting.diagnostics.queued_at', ['date' => $attempt->queued_at->diffForHumans()]) }}</p>
                                                @endif
                                                @if($attempt->finished_at)
                                                    <p class="mt-1">{{ __('jobs.multiposting.diagnostics.finished_at', ['date' => $attempt->finished_at->diffForHumans()]) }}</p>
                                                @endif
                                                @if($attempt->error_message)
                                                    <p class="mt-1 text-danger-700">{{ __('jobs.multiposting.diagnostics.last_error', ['error' => $attempt->error_message]) }}</p>
                                                @endif
                                                @if(data_get($attempt->diagnostics_json, 'failure_code'))
                                                    <p class="mt-1 text-xs text-slate-600">
                                                        {{ __('jobs.multiposting.automation.failure_code', ['code' => data_get($attempt->diagnostics_json, 'failure_code')]) }}
                                                    </p>
                                                @endif
                                                @if(data_get($attempt->diagnostics_json, 'screenshot_path'))
                                                    <p class="mt-1 text-xs text-slate-600">
                                                        {{ __('jobs.multiposting.automation.screenshot_path', ['path' => data_get($attempt->diagnostics_json, 'screenshot_path')]) }}
                                                    </p>
                                                @endif
                                                @if($attempt->external_url)
                                                    <a href="{{ $attempt->external_url }}" target="_blank" rel="noopener noreferrer" class="mt-2 inline-flex rounded-lg border border-aura-300/50 bg-white px-2.5 py-1 text-xs font-medium text-slate-900 transition-weightless hover:bg-aura-50">
                                                        {{ __('jobs.multiposting.diagnostics.open_external_url') }}
                                                    </a>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    <p class="text-xs text-slate-600">{{ __('jobs.multiposting.diagnostics.empty') }}</p>
                                @endif
                            </div>
                        </article>
                    @endforeach
                        </div>
                    </section>
                @endforeach
            </div>

            <div class="mt-4 space-y-3" x-show="tab === 'persona'" x-cloak>
                <div class="flex flex-wrap gap-2">
                    <form method="POST" action="{{ route('jobs.persona.generate', ['job' => $job, 'company_id' => $selectedCompanyId]) }}">
                        @csrf
                        @if(auth()->user()?->isSuperadmin())
                            <input type="hidden" name="company_id" value="{{ $selectedCompanyId }}">
                        @endif
                        <button type="submit" class="rounded-xl bg-primary-600 px-3 py-2 text-sm text-white transition-weightless hover:bg-primary-700">{{ __('jobs.persona.generate') }}</button>
                    </form>
                    <form method="POST" action="{{ route('jobs.persona.refresh', ['job' => $job, 'company_id' => $selectedCompanyId]) }}">
                        @csrf
                        @if(auth()->user()?->isSuperadmin())
                            <input type="hidden" name="company_id" value="{{ $selectedCompanyId }}">
                        @endif
                        <button type="submit" class="rounded-xl bg-primary-600 px-3 py-2 text-sm text-white transition-weightless hover:bg-primary-700">{{ __('jobs.persona.refresh') }}</button>
                    </form>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-xs uppercase tracking-wider text-aura-700/80">{{ __('jobs.persona.last_request') }}</p>
                    <p class="text-sm text-slate-800">
                        {{ $latestPersonaRequest ? __('ui.ai_diagnostics.statuses.'.$latestPersonaRequest->status) : __('jobs.persona.none') }}
                    </p>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    @if($job->persona?->persona_json)
                        <pre class="overflow-x-auto rounded-lg bg-slate-100/80 p-3 text-xs text-slate-800">{{ json_encode($job->persona->persona_json, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) }}</pre>
                    @else
                        <x-empty-state :title="__('jobs.persona.title')" :message="__('jobs.persona.none')" />
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-shell-layout>
