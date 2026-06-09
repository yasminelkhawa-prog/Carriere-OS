<x-shell-layout :title="__('ui.employer_brand.title').' | '.config('app.name')">
    <section class="space-y-4">
        <div>
            <h1 class="panel-title text-3xl font-semibold tracking-tight text-slate-900">{{ __('ui.employer_brand.heading') }}</h1>
            <p class="mt-1 text-sm text-slate-600">{{ __('ui.employer_brand.subheading') }}</p>
        </div>

        @if($requiresCompanySelection)
            <x-glass-card>
                <x-empty-state :title="__('ui.employer_brand.select_company_title')" :message="__('ui.employer_brand.select_company_message')" />
            </x-glass-card>
        @else
            <x-glass-card class="p-4">
                <form method="GET" action="{{ route('analytics.index') }}" class="grid gap-3 lg:grid-cols-5">
                    @if(auth()->user()?->isSuperadmin())
                        <x-form-field :label="__('jobs.company')" name="company_id">
                            <select name="company_id" data-placeholder="{{ __('jobs.company_placeholder') }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2 text-sm">
                                <option value="">{{ __('jobs.company_placeholder') }}</option>
                                @foreach($companies as $company)
                                    <option value="{{ $company->id }}" @selected((string) request('company_id') === (string) $company->id)>{{ $company->name }}</option>
                                @endforeach
                            </select>
                        </x-form-field>
                    @endif

                    <x-form-field :label="__('ui.employer_brand.filters.recruiter')" name="recruiter_id">
                        <select name="recruiter_id" data-placeholder="{{ __('ui.employer_brand.filters.recruiter_placeholder') }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2 text-sm">
                            <option value="">{{ __('ui.employer_brand.filters.recruiter_placeholder') }}</option>
                            @foreach($recruiters as $recruiter)
                                <option value="{{ $recruiter['id'] }}" @selected((string) ($filters['recruiter_id'] ?? '') === (string) $recruiter['id'])>{{ $recruiter['name'] }}</option>
                            @endforeach
                        </select>
                    </x-form-field>

                    <x-form-field :label="__('ui.employer_brand.filters.job')" name="job_id">
                        <select name="job_id" data-placeholder="{{ __('ui.employer_brand.filters.job_placeholder') }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2 text-sm">
                            <option value="">{{ __('ui.employer_brand.filters.job_placeholder') }}</option>
                            @foreach($jobs as $job)
                                <option value="{{ $job->id }}" @selected((string) ($filters['job_id'] ?? '') === (string) $job->id)>{{ $job->title }}</option>
                            @endforeach
                        </select>
                    </x-form-field>

                    <x-form-field :label="__('ui.employer_brand.filters.period')" name="period">
                        <select name="period" data-placeholder="{{ __('ui.employer_brand.filters.period') }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2 text-sm">
                            @foreach($periodOptions as $value => $label)
                                <option value="{{ $value }}" @selected((string) ($filters['period'] ?? '30d') === (string) $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </x-form-field>

                    <div class="flex items-end gap-2">
                        <button type="submit" class="rounded-xl bg-success-600 px-4 py-2.5 text-sm font-semibold text-white transition-weightless hover:bg-success-700">
                            {{ __('ui.employer_brand.filters.apply') }}
                        </button>
                        <a href="{{ route('analytics.index', array_filter(['company_id' => request('company_id')])) }}" class="rounded-xl border border-aura-300/50 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition-weightless hover:bg-slate-50">
                            {{ __('ui.employer_brand.filters.reset') }}
                        </a>
                    </div>
                </form>
            </x-glass-card>

            @if(! ($canViewReverseFeedbackInsights ?? false))
                <div class="rounded-xl border border-primary-200/70 bg-primary-50/70 px-3 py-2 text-xs text-primary-900">
                    {{ __('ui.employer_brand.reverse_feedback_restricted') }}
                </div>
            @endif

            <div class="grid gap-2" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));">
                <x-glass-card class="p-2.5">
                    <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-500">{{ __('ui.employer_brand.kpis.responses') }}</p>
                    <p class="mt-1 text-xl font-semibold text-slate-900">{{ number_format((int) ($ratingSummary['responses'] ?? 0)) }}</p>
                </x-glass-card>
                <x-glass-card class="p-2.5">
                    <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-500">{{ __('ui.employer_brand.kpis.overall') }}</p>
                    <p class="mt-1 text-xl font-semibold text-slate-900">{{ $ratingSummary['avg_overall'] !== null ? number_format((float) $ratingSummary['avg_overall'], 2).' / 5' : __('ui.employer_brand.not_available') }}</p>
                </x-glass-card>
                <x-glass-card class="p-2.5">
                    <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-500">{{ __('ui.employer_brand.kpis.pending') }}</p>
                    <p class="mt-1 text-xl font-semibold text-primary-700">{{ number_format((int) $pendingSentimentCount) }}</p>
                </x-glass-card>
                <x-glass-card class="p-2.5">
                    <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-500">{{ __('ui.employer_brand.kpis.active_alerts') }}</p>
                    <p class="mt-1 text-xl font-semibold text-danger-700">{{ number_format($activeAlerts->count()) }}</p>
                </x-glass-card>
            </div>

            <div class="grid gap-4 xl:grid-cols-[2fr_1fr]">
                <x-glass-card class="p-5">
                    <h2 class="text-xl font-semibold text-slate-900">{{ __('ui.employer_brand.trend_title') }}</h2>
                    @if($trendPoints->isEmpty())
                        <div class="mt-4">
                            <x-empty-state :title="__('ui.employer_brand.trend_empty_title')" :message="__('ui.employer_brand.trend_empty_message')" />
                        </div>
                    @else
                        <div class="mt-4 space-y-2">
                            @foreach($trendPoints as $point)
                                <div class="rounded-xl border border-slate-200 bg-white/85 p-2">
                                    <div class="flex items-center justify-between text-xs text-slate-700">
                                        <span>{{ $point['date'] }}</span>
                                        <span>{{ number_format((float) $point['avg_score'], 2) }}</span>
                                    </div>
                                    <div class="mt-1 h-2 rounded-full bg-slate-100">
                                        <div class="h-2 rounded-full {{ ($point['avg_score'] ?? 0) < 0 ? 'bg-danger-500' : 'bg-success-500' }}" style="width: {{ max(4, (float) $point['bar_percent']) }}%;"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </x-glass-card>

                <x-glass-card class="p-5">
                    <h2 class="text-xl font-semibold text-slate-900">{{ __('ui.employer_brand.themes_title') }}</h2>
                    @if($topThemes->isEmpty())
                        <div class="mt-4">
                            <x-empty-state :title="__('ui.employer_brand.themes_empty_title')" :message="__('ui.employer_brand.themes_empty_message')" />
                        </div>
                    @else
                        <div class="mt-4 space-y-2">
                            @foreach($topThemes as $theme)
                                <div class="flex items-center justify-between rounded-xl border border-slate-200 bg-white/90 px-3 py-2 text-sm">
                                    <span class="font-medium text-slate-800">{{ $theme['theme'] }}</span>
                                    <span class="rounded-full bg-aura-100 px-2 py-0.5 text-xs font-semibold text-aura-700">{{ $theme['count'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </x-glass-card>
            </div>

            <x-glass-card class="p-0">
                <div class="border-b border-slate-200 px-5 py-4">
                    <h2 class="text-xl font-semibold text-slate-900">{{ __('ui.employer_brand.sentiment_table_title') }}</h2>
                </div>
                @if($sentimentEntries->isEmpty())
                    <div class="px-5 py-5">
                        <x-empty-state :title="__('ui.employer_brand.sentiment_empty_title')" :message="__('ui.employer_brand.sentiment_empty_message')" />
                    </div>
                @else
                    <x-table>
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs uppercase tracking-wider text-slate-500">{{ __('ui.employer_brand.columns.source') }}</th>
                                <th class="px-4 py-3 text-left text-xs uppercase tracking-wider text-slate-500">{{ __('ui.employer_brand.columns.score') }}</th>
                                <th class="px-4 py-3 text-left text-xs uppercase tracking-wider text-slate-500">{{ __('ui.employer_brand.columns.themes') }}</th>
                                <th class="px-4 py-3 text-left text-xs uppercase tracking-wider text-slate-500">{{ __('ui.employer_brand.columns.risk') }}</th>
                                <th class="px-4 py-3 text-left text-xs uppercase tracking-wider text-slate-500">{{ __('ui.employer_brand.columns.action') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @foreach($sentimentEntries as $entry)
                                <tr>
                                    <td class="px-4 py-3 text-sm text-slate-700">
                                        <p class="font-semibold text-slate-900">{{ $entry['source_label'] }}</p>
                                        @if(!empty($entry['feedback_excerpt']))
                                            <p class="mt-1 text-xs text-slate-600">{{ $entry['feedback_excerpt'] }}</p>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-sm text-slate-700">
                                        {{ is_numeric($entry['sentiment_score']) ? number_format((float) $entry['sentiment_score'], 2) : __('ui.employer_brand.pending') }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-slate-700">
                                        @if(!empty($entry['themes']))
                                            {{ implode(', ', array_map(static fn ($theme) => \Illuminate\Support\Str::headline((string) $theme), $entry['themes'])) }}
                                        @else
                                            {{ __('ui.employer_brand.not_available') }}
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-sm">
                                        <span class="rounded-full px-2 py-0.5 text-xs font-semibold
                                            @if($entry['risk_level'] === 'critical') bg-danger-100 text-danger-700
                                            @elseif($entry['risk_level'] === 'high') bg-danger-100 text-danger-700
                                            @elseif($entry['risk_level'] === 'medium') bg-primary-100 text-primary-700
                                            @elseif($entry['risk_level'] === 'pending') bg-primary-100 text-primary-700
                                            @else bg-success-100 text-success-700
                                            @endif">
                                            {{ ucfirst((string) $entry['risk_level']) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-slate-700">
                                        @if($entry['drilldown_url'])
                                            <a href="{{ $entry['drilldown_url'] }}" class="text-aura-700 hover:underline">{{ __('ui.employer_brand.investigate') }}</a>
                                        @else
                                            {{ __('ui.employer_brand.not_available') }}
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-table>
                @endif
            </x-glass-card>

            <x-glass-card class="p-0">
                <div class="border-b border-slate-200 px-5 py-4">
                    <h2 class="text-xl font-semibold text-slate-900">{{ __('ui.employer_brand.alerts_title') }}</h2>
                </div>
                @if($activeAlerts->isEmpty())
                    <div class="px-5 py-5">
                        <x-empty-state :title="__('ui.employer_brand.alerts_empty_title')" :message="__('ui.employer_brand.alerts_empty_message')" />
                    </div>
                @else
                    <x-table>
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs uppercase tracking-wider text-slate-500">{{ __('ui.employer_brand.columns.severity') }}</th>
                                <th class="px-4 py-3 text-left text-xs uppercase tracking-wider text-slate-500">{{ __('ui.employer_brand.columns.message') }}</th>
                                <th class="px-4 py-3 text-left text-xs uppercase tracking-wider text-slate-500">{{ __('ui.employer_brand.columns.created') }}</th>
                                <th class="px-4 py-3 text-left text-xs uppercase tracking-wider text-slate-500">{{ __('ui.employer_brand.columns.action') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @foreach($activeAlerts as $alert)
                                <tr>
                                    <td class="px-4 py-3 text-sm">
                                        <span class="rounded-full px-2 py-0.5 text-xs font-semibold
                                            @if($alert['severity'] === 'critical') bg-danger-100 text-danger-700
                                            @elseif($alert['severity'] === 'high') bg-danger-100 text-danger-700
                                            @elseif($alert['severity'] === 'medium') bg-primary-100 text-primary-700
                                            @else bg-success-100 text-success-700
                                            @endif">
                                            {{ ucfirst((string) $alert['severity']) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-slate-700">{{ $alert['message'] }}</td>
                                    <td class="px-4 py-3 text-sm text-slate-700">{{ optional($alert['created_at'])->diffForHumans() }}</td>
                                    <td class="px-4 py-3 text-sm">
                                        <div class="flex items-center gap-2">
                                            @if($alert['drilldown_url'])
                                                <a href="{{ $alert['drilldown_url'] }}" class="text-aura-700 hover:underline">{{ __('ui.employer_brand.investigate') }}</a>
                                            @endif
                                            @if($alert['can_resolve'])
                                                <form method="POST" action="{{ route('analytics.alerts.resolve', ['brandAlert' => $alert['id'], 'company_id' => request('company_id')]) }}">
                                                    @csrf
                                                    <button type="submit" class="rounded-lg border border-success-300/60 bg-success-50 px-2 py-1 text-xs font-semibold text-success-800 hover:bg-success-100">
                                                        {{ __('ui.employer_brand.resolve') }}
                                                    </button>
                                                </form>
                                            @else
                                                <span class="text-xs text-slate-500">{{ __('ui.employer_brand.resolve_critical_forbidden') }}</span>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-table>
                @endif
            </x-glass-card>
        @endif
    </section>
</x-shell-layout>
