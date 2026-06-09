        @else
            <!-- NEW LAYOUT: JOB CARDS & RANKED LIST -->
            <x-glass-card class="mb-4 p-0 overflow-hidden">
                <div class="p-4 border-b border-white/60 bg-white/55">
                    <form method="GET" action="{{ route('candidates.index') }}" class="flex flex-wrap items-end gap-4">
                        @if(auth()->user()->isSuperadmin())
                            <x-form-field :label="__('jobs.company')" name="company_id" class="w-48">
                                <select name="company_id" data-placeholder="{{ __('jobs.company_placeholder') }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2 text-sm">
                                    <option value="">{{ __('jobs.company_placeholder') }}</option>
                                    @foreach($companies as $company)
                                        <option value="{{ $company->id }}" @selected((string) $selectedCompanyId === (string) $company->id)>{{ $company->name }}</option>
                                    @endforeach
                                </select>
                            </x-form-field>
                        @endif
                        <x-form-field :label="__('candidates.filters.search')" name="q" class="w-48">
                            <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2 text-sm" autocomplete="off">
                        </x-form-field>
                        <x-form-field :label="__('candidates.filters.job')" name="job_id" class="w-48">
                            <select name="job_id" data-placeholder="{{ __('candidates.filters.job_placeholder') }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2 text-sm">
                                <option value="">{{ __('candidates.filters.job_placeholder') }}</option>
                                @foreach($jobs as $job)
                                    <option value="{{ $job->id }}" @selected(($filters['job_id'] ?? null) === $job->id)>{{ $job->title }}</option>
                                @endforeach
                            </select>
                        </x-form-field>
                        <x-form-field :label="__('candidates.filters.status')" name="status" class="w-48">
                            <select name="status" data-placeholder="{{ __('candidates.filters.status_placeholder') }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2 text-sm">
                                <option value="">{{ __('candidates.filters.status_placeholder') }}</option>
                                @foreach($statuses as $status)
                                    <option value="{{ $status }}" @selected(($filters['status'] ?? null) === $status)>{{ __('candidates.list.status.'.$status) }}</option>
                                @endforeach
                            </select>
                        </x-form-field>
                        <div class="flex items-center gap-2 pb-1">
                            <button type="submit" class="rounded-xl bg-success-600 px-4 py-2 text-sm font-semibold text-white transition-weightless hover:bg-success-700">
                                {{ __('candidates.filters.apply') }}
                            </button>
                            <a href="{{ route('candidates.index', array_filter(['company_id' => $selectedCompanyId])) }}" class="rounded-xl border border-aura-300/40 bg-white/80 px-4 py-2 text-center text-sm font-semibold text-slate-700 transition-weightless hover:bg-white">
                                {{ __('candidates.filters.reset') }}
                            </a>
                        </div>
                    </form>
                </div>
            </x-glass-card>

            @if(!request()->filled('job_id') && !request()->filled('q'))
                <!-- JOB CARDS GRID -->
                <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    @forelse($jobs as $job)
                        <a href="{{ route('candidates.index', array_merge(request()->query(), ['job_id' => $job->id])) }}" class="group block rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition hover:border-aura-400 hover:shadow-md">
                            <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-aura-50 text-aura-600 group-hover:bg-aura-100">
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 0 0 .75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 0 0-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0 1 12 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 0 1-.673-.38m0 0A2.18 2.18 0 0 1 3 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 0 1 3.413-.387m7.5 0V5.25A2.25 2.25 0 0 0 13.5 3h-3a2.25 2.25 0 0 0-2.25 2.25v.894m7.5 0a48.667 48.667 0 0 0-7.5 0M12 12.75h.008v.008H12v-.008Z" />
                                </svg>
                            </div>
                            <h3 class="truncate text-lg font-bold text-slate-800">{{ $job->title }}</h3>
                            <p class="mt-1 text-sm font-medium text-slate-500">
                                {{ $job->applications_count }} candidat{{ $job->applications_count !== 1 ? 's' : '' }}
                            </p>
                        </a>
                    @empty
                        <div class="col-span-full">
                            <x-empty-state :title="__('candidates.list.empty_title')" :message="__('candidates.list.empty_message')" />
                        </div>
                    @endforelse
                </div>
            @else
                <!-- RANKED CANDIDATES LIST -->
                <x-glass-card class="p-0 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm text-slate-600">
                            <thead class="border-b border-slate-200 bg-slate-50/50 text-xs uppercase text-slate-500">
                                <tr>
                                    <th scope="col" class="px-6 py-4 font-semibold">Nom complet</th>
                                    <th scope="col" class="px-6 py-4 font-semibold">Score</th>
                                    <th scope="col" class="px-6 py-4 font-semibold">Formation / Ã‰cole</th>
                                    <th scope="col" class="px-6 py-4 font-semibold">ExpÃ©rience</th>
                                    <th scope="col" class="px-6 py-4 font-semibold">DerniÃ¨re entreprise</th>
                                    <th scope="col" class="px-6 py-4 font-semibold text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 bg-white">
                                @forelse($applications as $app)
                                    @php
                                        $cv = $app->cvParsingResults->first();
                                        $school = $cv && !empty($cv->parsed_education) ? ($cv->parsed_education[0]['institution_name'] ?? $cv->parsed_education[0]['degree_name'] ?? '-') : '-';
                                        $experience = $cv && $cv->total_years_experience !== null ? number_format((float) $cv->total_years_experience, 1) . ' ans' : '-';
                                        $lastCompany = $cv && !empty($cv->parsed_experience) ? ($cv->parsed_experience[0]['company_name'] ?? $cv->parsed_experience[0]['job_title'] ?? '-') : '-';
                                        $score = $app->global_match_score !== null ? number_format((float) $app->global_match_score, 1) : '-';
                                    @endphp
                                    <tr class="transition hover:bg-slate-50">
                                        <td class="whitespace-nowrap px-6 py-4 font-medium text-slate-900">
                                            {{ $app->candidate?->full_name ?? 'Inconnu' }}
                                        </td>
                                        <td class="whitespace-nowrap px-6 py-4">
                                            @if($score !== '-')
                                                <span class="inline-flex items-center rounded-full bg-primary-50 px-2.5 py-1 text-xs font-bold text-primary-700 border border-primary-100">
                                                    {{ $score }}%
                                                </span>
                                            @else
                                                <span class="text-slate-400">-</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 truncate max-w-[200px]" title="{{ $school }}">
                                            {{ $school }}
                                        </td>
                                        <td class="whitespace-nowrap px-6 py-4">
                                            {{ $experience }}
                                        </td>
                                        <td class="px-6 py-4 truncate max-w-[200px]" title="{{ $lastCompany }}">
                                            {{ $lastCompany }}
                                        </td>
                                        <td class="whitespace-nowrap px-6 py-4 text-right">
                                            <a href="{{ route('candidates.index', array_merge(request()->query(), ['application_id' => $app->id])) }}" class="inline-flex items-center rounded-lg bg-white px-3 py-1.5 text-xs font-semibold text-aura-700 border border-aura-200 shadow-sm transition hover:bg-aura-50">
                                                Voir le profil
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-6 py-8 text-center text-slate-500">
                                            Aucun candidat trouvÃ© pour ce filtre.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if($applications instanceof \Illuminate\Pagination\LengthAwarePaginator && $applications->hasPages())
                        <div class="border-t border-slate-200 bg-white px-6 py-4">
                            {{ $applications->links() }}
                        </div>
                    @endif
                </x-glass-card>
            @endif
