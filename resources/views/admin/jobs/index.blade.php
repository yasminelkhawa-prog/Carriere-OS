<x-shell-layout :title="__('jobs.title').' | '.config('app.name')">
    <link rel="stylesheet" type="text/css" href="https://unpkg.com/trix@2.0.8/dist/trix.css">
    <script type="text/javascript" src="https://unpkg.com/trix@2.0.8/dist/trix.umd.min.js"></script>
    <style>
        trix-toolbar [data-trix-button-group="file-tools"] { display: none; }
        trix-editor { min-height: 100px !important; }
        /* Prevent scrollbars on toolbar and make buttons smaller to fit on one line */
        trix-toolbar { margin-bottom: 4px !important; }
        trix-toolbar .trix-button-row { 
            display: flex !important; 
            flex-wrap: nowrap !important; 
            overflow-x: auto !important; 
            -ms-overflow-style: none; 
            scrollbar-width: none; 
            width: 100%; 
        }
        trix-toolbar .trix-button-row::-webkit-scrollbar { display: none; }
        trix-toolbar .trix-button-group { 
            margin-bottom: 0 !important; 
            margin-top: 0 !important; 
            margin-right: 2px !important; 
            display: flex !important; 
            flex-wrap: nowrap !important; 
            flex-shrink: 0 !important; 
        }
        trix-toolbar .trix-button { 
            padding: 0 2px !important; 
            height: 20px !important; 
            min-width: 20px !important; 
            font-size: 10px !important; 
        }
        trix-toolbar .trix-button::before { 
            width: 12px !important; 
            height: 12px !important; 
        }
    </style>
    @if($requiresCompanySelection)
        <div class="p-6">
            <x-empty-state :title="__('jobs.company')" :message="__('master.common.company_scope_required')" />
        </div>
    @else
        <div class="flex h-[calc(100vh-theme(spacing.16)-theme(spacing.8))] overflow-hidden rounded-2xl border border-white/80 bg-white/60 shadow-[0_22px_44px_-30px_rgba(30,41,59,0.45)] backdrop-blur-xl" x-data="{
            selectedJobId: '{{ $jobs->first()?->id }}',
            jobs: {{ Js::from($jobs->items()) }},
            get selectedJob() {
                return this.jobs.find(j => j.id === this.selectedJobId) || this.jobs[0];
            },
            statusColor(status) {
                switch(status) {
                    case 'published': return 'bg-success-100 text-success-800 border-success-200';
                    case 'draft': return 'bg-primary-100 text-primary-800 border-primary-200';
                    default: return 'bg-slate-100 text-slate-700 border-slate-200';
                }
            },
            statusLabel(status) {
                switch(status) {
                    case 'published': return '{{ __('jobs.statuses.published') }}';
                    case 'draft': return '{{ __('jobs.statuses.draft') }}';
                    case 'closed': return '{{ __('jobs.statuses.closed') }}';
                    case 'archived': return '{{ __('jobs.statuses.archived') }}';
                    default: return status;
                }
            }
        }">

            <!-- SIDEBAR -->
            <div class="hidden w-56 flex-col overflow-y-auto border-r border-slate-200/60 bg-white/80 lg:flex">
                <div class="p-4">
                    <a href="{{ route('jobs.index') }}" class="flex items-center justify-between rounded-lg bg-aura-50 px-3 py-2 text-sm font-semibold text-aura-800 transition-weightless">
                        <span class="flex items-center gap-2">📋 Tous les postes</span>
                        <span class="rounded-full bg-aura-200 px-2 py-0.5 text-[10px] font-bold text-aura-900">{{ $totalJobsCount }}</span>
                    </a>
                    
                    <div class="mt-4 px-3 text-[10px] font-bold uppercase tracking-wider text-slate-400">Vues rapides</div>
                    <div class="mt-2 space-y-1">
                        <a href="{{ route('jobs.index', ['status' => 'published']) }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium text-slate-600 transition-weightless hover:bg-slate-50 hover:text-slate-900 @if($selectedStatus === 'published') bg-slate-50 text-slate-900 @endif">
                            🟢 En cours
                            <span class="ml-auto text-xs font-semibold text-slate-400">{{ $activeJobsCount }}</span>
                        </a>
                        <a href="{{ route('jobs.index', ['status' => 'archived']) }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium text-slate-600 transition-weightless hover:bg-slate-50 hover:text-slate-900 @if($selectedStatus === 'archived') bg-slate-50 text-slate-900 @endif">
                            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            Clôturés
                            <span class="ml-auto text-xs font-semibold text-slate-400">{{ $closedJobsCount }}</span>
                        </a>
                    </div>

                    <div class="mt-6 px-3 text-[10px] font-bold uppercase tracking-wider text-slate-400">Par département</div>
                    <div class="mt-2 space-y-1">
                        @foreach($departments as $dept)
                            <a href="{{ route('jobs.index', ['department_id' => $dept->id]) }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium text-slate-600 transition-weightless hover:bg-slate-50 hover:text-slate-900 @if((string)$selectedDepartmentId === (string)$dept->id) bg-slate-50 text-slate-900 @endif">
                                <span class="truncate flex-1">{{ $dept->name }}</span>
                                <span class="flex-shrink-0 ml-auto rounded-md bg-slate-100 px-1.5 py-0.5 text-[10px] font-bold text-slate-500">{{ $dept->jobs_count }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- MAIN -->
            <div class="flex flex-1 flex-col overflow-hidden bg-slate-50/30">
                <div class="border-b border-slate-200/60 p-4">
                    <div class="flex items-center justify-between">
                        <h1 class="text-xl font-bold text-slate-800">{{ __('jobs.title') }}</h1>
                        <x-modal id="create-job-modal" title="Nouveau poste" maxWidth="3xl">
                            <x-slot:trigger>
                                <button type="button" class="rounded-xl bg-success-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition-weightless hover:bg-success-700">
                                    ＋ Nouveau poste
                                </button>
                            </x-slot:trigger>
                            
                            <form method="POST" action="{{ route('jobs.store') }}" class="mt-4 grid gap-3 lg:grid-cols-6">
                                @csrf
                                @if(auth()->user()?->isSuperadmin())
                                    <input type="hidden" name="company_id" value="{{ $selectedCompanyId }}">
                                @endif
                                <x-form-field :label="__('jobs.fields.title')" name="title" class="lg:col-span-3">
                                    <select name="title" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-slate-900 shadow-sm" required>
                                        <option value="">Sélectionnez un besoin de recrutement...</option>
                                        @if(isset($availableTitles))
                                            @foreach($availableTitles as $titleOption)
                                                <option value="{{ $titleOption }}" @selected(old('title') === $titleOption)>
                                                    {{ $titleOption }}
                                                </option>
                                            @endforeach
                                        @endif
                                    </select>
                                    <div class="mt-2 text-[11px]">
                                        <span class="text-slate-500">Ne figure pas dans la liste ?</span>
                                        <a href="{{ route('home') }}" class="font-semibold text-aura-600 hover:text-aura-700 transition-colors">
                                            Ajouter un nouveau poste dans TB recrutement
                                        </a>
                                    </div>
                                </x-form-field>
                                <x-form-field :label="__('jobs.department')" name="department_id" class="lg:col-span-3">
                                    <select name="department_id" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-slate-900 shadow-sm">
                                        <option value="">{{ __('jobs.department_placeholder') }}</option>
                                        @foreach($departments as $department)
                                            <option value="{{ $department->id }}">{{ $department->name }}</option>
                                        @endforeach
                                    </select>
                                </x-form-field>
                                <x-form-field label="Famille de poste" name="job_family" class="lg:col-span-3">
                                    <select name="job_family" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-slate-900 shadow-sm" required>
                                        <option value="">Sélectionnez une famille...</option>
                                        @foreach(\App\Models\PsyTest::PROFILES as $profile)
                                            <option value="{{ $profile }}">{{ ucfirst($profile) }}</option>
                                        @endforeach
                                    </select>
                                    <div class="mt-2 text-[11px] text-slate-500">
                                        Utilisé pour déterminer le profil du test psycho (ex: ingenieur, management).
                                    </div>
                                </x-form-field>

                                <x-form-field label="Fiche de poste (Aperçu)" name="blocks[overview]" class="lg:col-span-3">
                                    <input id="blocks_overview" type="hidden" name="blocks[overview]">
                                    <trix-editor input="blocks_overview" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2 text-sm text-slate-900 shadow-sm" placeholder="Décrivez le rôle..."></trix-editor>
                                </x-form-field>

                                <x-form-field label="Missions" name="blocks[responsibilities]" class="lg:col-span-3">
                                    <input id="blocks_responsibilities" type="hidden" name="blocks[responsibilities]">
                                    <trix-editor input="blocks_responsibilities" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2 text-sm text-slate-900 shadow-sm" placeholder="Missions principales..."></trix-editor>
                                </x-form-field>

                                <x-form-field label="Compétences" name="blocks[requirements]" class="lg:col-span-3">
                                    <input id="blocks_requirements" type="hidden" name="blocks[requirements]">
                                    <trix-editor input="blocks_requirements" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2 text-sm text-slate-900 shadow-sm" placeholder="Compétences requises..."></trix-editor>
                                </x-form-field>

                                <x-form-field label="Rattachement hiérarchique" name="blocks[reporting_line]" class="lg:col-span-3">
                                    <input id="blocks_reporting_line" type="hidden" name="blocks[reporting_line]">
                                    <trix-editor input="blocks_reporting_line" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2 text-sm text-slate-900 shadow-sm" placeholder="Manager, Équipe..."></trix-editor>
                                </x-form-field>

                                <x-form-field :label="__('jobs.fields.location')" name="location" class="lg:col-span-3">
                                    <input type="text" name="location" value="{{ old('location') }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-slate-900 shadow-sm" placeholder="Où le travail va prendre place ? (ex: Paris, Remote)">
                                </x-form-field>
                                <x-form-field :label="__('jobs.status')" name="status" class="lg:col-span-3">
                                    <select name="status" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-slate-900 shadow-sm">
                                        <option value="published" selected>En cours</option>
                                        <option value="archived">Clôturé</option>
                                    </select>
                                </x-form-field>
                                <div class="lg:col-span-6 mt-4 flex justify-end">
                                    <button type="submit" class="rounded-xl bg-success-600 px-4 py-2 text-sm font-medium text-white transition-weightless hover:bg-success-700 shadow-sm">
                                        Créer le poste
                                    </button>
                                </div>
                            </form>
                        </x-modal>
                    </div>

                    <div class="mt-4 grid grid-cols-2 gap-4 xl:grid-cols-4">
                        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm border-t-2 border-t-slate-900">
                            <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Postes totaux</div>
                            <div class="mt-1 text-2xl font-bold text-slate-800">{{ $totalJobsCount }}</div>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm border-t-2 border-t-danger-500">
                            <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Pas encore lancés</div>
                            <div class="mt-1 text-2xl font-bold text-slate-800">{{ $notYetLaunchedCount }}</div>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm border-t-2 border-t-amber-500">
                            <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">En cours</div>
                            <div class="mt-1 text-2xl font-bold text-slate-800">{{ $activeJobsCount }}</div>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm border-t-2 border-t-aura-500">
                            <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Clôturés</div>
                            <div class="mt-1 text-2xl font-bold text-slate-800">{{ $closedJobsCount }}</div>
                        </div>
                    </div>

                    <form method="GET" action="{{ route('jobs.index') }}" class="mt-4 flex flex-wrap items-center gap-3">
                        <div class="flex flex-1 items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 shadow-sm min-w-[200px]" x-data>
                            <svg class="size-4 text-slate-400" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z" clip-rule="evenodd" /></svg>
                            <input type="text" name="q" value="{{ $searchTerm }}" placeholder="{{ __('jobs.search_placeholder') }}" class="w-full border-none bg-transparent p-0 text-sm focus:ring-0" x-on:input.debounce.500ms="$event.target.form.submit()">
                        </div>
                        @if($selectedStatus)
                            <input type="hidden" name="status" value="{{ $selectedStatus }}">
                        @endif
                        @if($selectedDepartmentId)
                            <input type="hidden" name="department_id" value="{{ $selectedDepartmentId }}">
                        @endif
                        <button type="submit" class="hidden"></button>
                        <a href="{{ route('jobs.index') }}" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-600 transition-weightless hover:bg-slate-50">Réinitialiser</a>
                    </form>
                </div>

                <div class="flex-1 overflow-y-auto p-4">
                    <div class="flex flex-col gap-3">
                        @forelse($jobs as $job)
                            <div 
                                @click="selectedJobId = '{{ $job->id }}'"
                                class="flex cursor-pointer items-center gap-4 rounded-xl border border-slate-200 bg-white p-4 transition-weightless hover:border-aura-300 hover:shadow-md"
                                :class="selectedJobId === '{{ $job->id }}' ? 'border-aura-400 ring-2 ring-aura-100 shadow-sm' : 'border-slate-200'"
                            >
                                <div class="h-10 w-1.5 rounded-full" :class="{
                                    'bg-success-500': '{{ $job->status }}' === 'published',
                                    'bg-slate-300': '{{ $job->status }}' === 'draft',
                                    'bg-amber-500': '{{ $job->status }}' === 'closed'
                                }"></div>
                                <div class="flex-1">
                                    <div class="font-bold text-slate-800">{{ $job->title }}</div>
                                    <div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                                        @if($job->location) <span>{{ $job->location }}</span> @endif
                                        @if($job->department) <span>{{ $job->department->name }}</span> @endif
                                        <span>{{ $job->created_at->format('d M Y') }}</span>
                                    </div>
                                    <div class="mt-2 flex gap-2">
                                        <span class="inline-flex items-center rounded-md border border-slate-200 bg-slate-50 px-2 py-0.5 text-[10px] font-semibold text-slate-600" :class="statusColor('{{ $job->status }}')">
                                            <span x-text="statusLabel('{{ $job->status }}')"></span>
                                        </span>
                                        @if($job->blind_mode_active)
                                            <span class="inline-flex items-center rounded-md border border-primary-200 bg-primary-50 px-2 py-0.5 text-[10px] font-semibold text-primary-700">Blind Mode</span>
                                        @endif
                                    </div>
                                </div>
                                <div class="hidden items-center gap-6 sm:flex">
                                    <div class="text-center">
                                        <div class="text-lg font-bold text-slate-800">{{ $job->applications_count ?? 0 }}</div>
                                        <div class="text-[10px] font-medium text-slate-400">Candidatures</div>
                                    </div>
                                </div>
                                <div class="ml-4 flex items-center gap-2">
                                    <a href="{{ route('jobs.show', ['job' => $job, 'company_id' => $selectedCompanyId]) }}" class="flex size-8 items-center justify-center rounded-lg border border-slate-200 text-slate-500 transition-weightless hover:bg-slate-50">
                                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                        </svg>
                                    </a>
                                </div>
                            </div>
                        @empty
                            <div class="py-12 text-center">
                                <x-empty-state :title="__('jobs.empty_title')" :message="__('jobs.empty_message')" />
                            </div>
                        @endforelse
                    </div>

                    @if($jobs instanceof \Illuminate\Contracts\Pagination\Paginator)
                        <div class="mt-6">{{ $jobs->links() }}</div>
                    @endif
                </div>
            </div>

            <!-- RIGHT PANEL -->
            <div class="hidden w-[380px] flex-col border-l border-slate-200/60 bg-white xl:flex" x-show="selectedJob">
                <template x-if="selectedJob">
                    <div class="flex h-full flex-col">
                        <div class="border-b border-slate-200 p-5">
                            <div class="text-lg font-bold text-slate-800" x-text="selectedJob.title"></div>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <span class="rounded bg-aura-50 px-2 py-1 text-xs font-semibold text-aura-800" x-show="selectedJob.location" x-text="selectedJob.location"></span>
                                <span class="rounded bg-slate-100 px-2 py-1 text-xs font-medium text-slate-700" x-show="selectedJob.employment_type" x-text="selectedJob.employment_type"></span>
                                <span class="rounded bg-slate-100 px-2 py-1 text-xs font-medium text-slate-700" x-show="selectedJob.department" x-text="selectedJob.department.name"></span>
                            </div>
                        </div>

                        <div class="flex-1 overflow-y-auto p-5">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Date de création</div>
                                    <div class="mt-1 text-sm font-medium text-slate-800" x-text="new Date(selectedJob.created_at).toLocaleDateString()"></div>
                                </div>
                                <div>
                                    <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Statut</div>
                                    <div class="mt-1">
                                        <span class="inline-flex items-center rounded-full border px-2 py-0.5 text-[11px] font-semibold" :class="statusColor(selectedJob.status)">
                                            <span x-text="statusLabel(selectedJob.status)"></span>
                                        </span>
                                    </div>
                                </div>
                                <div class="col-span-2" x-show="selectedJob.salary_min || selectedJob.salary_max">
                                    <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Budget Salaire</div>
                                    <div class="mt-1 text-sm font-medium text-slate-800">
                                        <span x-text="selectedJob.salary_min || 0"></span> - <span x-text="selectedJob.salary_max || 0"></span> <span x-text="selectedJob.salary_currency || ''"></span>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-8" x-show="selectedJob.pipeline_stages && selectedJob.pipeline_stages.length > 0">
                                <div class="text-xs font-bold text-slate-800">Candidatures (Pipeline)</div>
                                <div class="mt-3 flex overflow-hidden rounded-lg border border-slate-200">
                                    <template x-for="(stage, index) in selectedJob.pipeline_stages" :key="stage.id">
                                        <div class="flex-1 p-2 text-center" :class="{'border-r border-slate-200': index !== selectedJob.pipeline_stages.length - 1}">
                                            <div class="text-base font-bold text-slate-800" x-text="stage.applications_count || 0"></div>
                                            <div class="text-[10px] text-slate-500 truncate px-1" x-text="stage.stage_label"></div>
                                        </div>
                                    </template>
                                </div>
                            </div>

                            <div class="mt-6 rounded-xl border border-slate-200 bg-slate-50 p-4" x-data="{
                                get progress() {
                                    if (!selectedJob.pipeline_stages || selectedJob.pipeline_stages.length === 0) return 0;
                                    const total = selectedJob.applications_count || 0;
                                    if (total === 0) return 0;
                                    const lastStage = selectedJob.pipeline_stages[selectedJob.pipeline_stages.length - 1];
                                    return Math.min(100, Math.round(((lastStage.applications_count || 0) / total) * 100));
                                }
                            }">
                                <div class="flex items-center justify-between text-xs">
                                    <span class="text-slate-600">Avancement recrutement</span>
                                    <span class="font-bold text-success-600" x-text="progress + '%'"></span>
                                </div>
                                <div class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-slate-200">
                                    <div class="h-full bg-success-500 transition-all duration-500" :style="`width: ${progress}%`"></div>
                                </div>
                            </div>

                            <div class="mt-4" x-show="selectedJob.hired_candidates && selectedJob.hired_candidates.length > 0">
                                <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-2">Candidat(s) sélectionné(s) et embauché(s)</div>
                                <div class="flex flex-col gap-2">
                                    <template x-for="candidate in selectedJob.hired_candidates" :key="candidate.id">
                                        <div class="flex flex-col gap-2 rounded-lg border border-success-200 bg-success-50 p-3">
                                            <div class="flex items-center gap-3">
                                                <div class="flex size-8 items-center justify-center rounded-full bg-success-100 text-success-600 font-bold text-xs uppercase" x-text="candidate.initials"></div>
                                                <div class="flex-1 min-w-0">
                                                    <div class="text-sm font-bold text-slate-800 truncate" x-text="candidate.full_name"></div>
                                                    <div class="text-[10px] text-slate-500 truncate" x-text="candidate.email"></div>
                                                </div>
                                                <a :href="`/candidates?application_id=${candidate.application_id}`" class="flex-shrink-0 text-[10px] font-bold text-success-600 hover:text-success-700 underline">Voir profil</a>
                                            </div>
                                            <div class="mt-1 border-t border-success-200/50 pt-2">
                                                <ul class="list-disc list-inside space-y-1 text-xs text-slate-700">
                                                    <li><strong>Rattachement hiérarchique :</strong> <span x-text="selectedJob.department ? selectedJob.department.name : 'Non défini'"></span></li>
                                                    <li><strong>Embauche et Onboarding :</strong> Processus finalisé avec succès</li>
                                                </ul>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>

                        </div>

                        <div class="border-t border-slate-200 p-4">
                            <a :href="`/admin/jobs/${selectedJob.id}?company_id={{ $selectedCompanyId }}`" class="block w-full rounded-xl bg-aura-600 py-2.5 text-center text-sm font-semibold text-white transition-weightless hover:bg-aura-700">
                                Gérer le poste →
                            </a>
                        </div>
                    </div>
                </template>
                <div x-show="!selectedJob" class="flex h-full items-center justify-center p-6 text-center text-slate-500">
                    Sélectionnez un poste pour voir les détails
                </div>
            </div>

        </div>
    @endif
</x-shell-layout>
