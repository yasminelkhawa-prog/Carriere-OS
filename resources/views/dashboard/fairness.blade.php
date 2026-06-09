<x-shell-layout :title="__('ui.fairness.title').' | '.config('app.name')">
    <section class="space-y-4">
        <div>
            <h1 class="panel-title text-3xl font-semibold tracking-tight text-slate-900">{{ __('ui.fairness.heading') }}</h1>
            <p class="mt-1 text-sm text-slate-600">{{ __('ui.fairness.subheading') }}</p>
        </div>

        @if(session('status'))
            <x-toast-alert type="success">{{ session('status') }}</x-toast-alert>
        @endif
        @if(session('error'))
            <x-toast-alert type="warning">{{ session('error') }}</x-toast-alert>
        @endif

        @if($requiresCompanySelection)
            <x-glass-card>
                <x-empty-state :title="__('ui.fairness.select_company_title')" :message="__('ui.fairness.select_company_message')" />
            </x-glass-card>
        @else
            <x-glass-card class="p-4">
                <form method="GET" action="{{ route('analytics.fairness') }}" class="grid gap-3 lg:grid-cols-5">
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

                    <x-form-field :label="__('ui.fairness.filters.job')" name="job_id">
                        <select name="job_id" data-placeholder="{{ __('ui.fairness.filters.job_placeholder') }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2 text-sm">
                            <option value="">{{ __('ui.fairness.filters.job_placeholder') }}</option>
                            @foreach($jobs as $job)
                                <option value="{{ $job->id }}" @selected((string) ($filters['job_id'] ?? '') === (string) $job->id)>{{ $job->title }}</option>
                            @endforeach
                        </select>
                    </x-form-field>

                    <x-form-field :label="__('ui.fairness.filters.dimension')" name="dimension_key">
                        <select name="dimension_key" data-placeholder="{{ __('ui.fairness.filters.dimension_placeholder') }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2 text-sm">
                            @foreach($dimensions as $dimension)
                                <option value="{{ $dimension['key'] }}" @selected((string) $selectedDimensionKey === (string) $dimension['key'])>{{ $dimension['label'] }}</option>
                            @endforeach
                        </select>
                    </x-form-field>

                    <x-form-field :label="__('ui.fairness.filters.period')" name="period">
                        <select name="period" data-placeholder="{{ __('ui.fairness.filters.period') }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2 text-sm">
                            @foreach($periodOptions as $value => $label)
                                <option value="{{ $value }}" @selected((string) ($filters['period'] ?? '30d') === (string) $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </x-form-field>

                    <div class="flex items-end gap-2">
                        <button type="submit" class="rounded-xl bg-success-600 px-4 py-2.5 text-sm font-semibold text-white transition-weightless hover:bg-success-700">
                            {{ __('ui.fairness.filters.apply') }}
                        </button>
                        <a href="{{ route('analytics.fairness', array_filter(['company_id' => request('company_id')])) }}" class="rounded-xl border border-aura-300/50 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition-weightless hover:bg-slate-50">
                            {{ __('ui.fairness.filters.reset') }}
                        </a>
                    </div>
                </form>

                <p class="mt-3 rounded-xl border border-aura-200/40 bg-aura-50/70 px-3 py-2 text-xs text-aura-900">
                    {{ $selectedDimensionExplanation }}
                </p>
            </x-glass-card>

            @if($showAlertsProminently && $alerts->isNotEmpty())
                <x-glass-card class="p-4">
                    <div class="rounded-xl border border-danger-300/50 bg-danger-50/80 p-3">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-danger-900">{{ __('ui.fairness.alerts.prominent_title') }}</p>
                                <p class="mt-0.5 text-xs text-danger-800">{{ __('ui.fairness.alerts.prominent_subtitle') }}</p>
                            </div>
                            <x-badge variant="danger">{{ $alerts->count() }}</x-badge>
                        </div>

                        <div class="mt-3 space-y-2">
                            @foreach($alerts as $alert)
                                <div class="rounded-lg border border-danger-200 bg-white/85 px-3 py-2">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <p class="text-sm font-semibold text-slate-900">{{ $alert->job?->title ?? __('ui.fairness.not_available') }}</p>
                                        <span class="rounded-full px-2 py-0.5 text-xs font-semibold
                                            @if($alert->severity === 'critical') bg-danger-100 text-danger-700
                                            @elseif($alert->severity === 'high') bg-danger-100 text-danger-700
                                            @elseif($alert->severity === 'medium') bg-primary-100 text-primary-700
                                            @else bg-success-100 text-success-700
                                            @endif">
                                            {{ ucfirst((string) $alert->severity) }}
                                        </span>
                                    </div>
                                    <p class="mt-1 text-xs text-slate-700">{{ $alert->message }}</p>
                                    <div class="mt-2 flex flex-wrap items-center justify-between gap-2">
                                        <p class="text-[11px] text-slate-600">{{ optional($alert->created_at)->diffForHumans() }}</p>
                                        @if($canResolveAlerts)
                                            <form method="POST" action="{{ route('analytics.fairness.alerts.resolve', ['biasAlert' => $alert->id, 'company_id' => request('company_id')]) }}">
                                                @csrf
                                                <button type="submit" class="rounded-lg border border-success-300/60 bg-success-50 px-2 py-1 text-xs font-semibold text-success-800 hover:bg-success-100">
                                                    {{ __('ui.fairness.alerts.resolve') }}
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </x-glass-card>
            @elseif($alerts->isNotEmpty())
                <x-glass-card class="p-3">
                    <p class="text-xs text-slate-700">
                        {{ __('ui.fairness.alerts.restricted_hint', ['count' => $alerts->count()]) }}
                    </p>
                </x-glass-card>
            @endif

            <div class="grid gap-4 xl:grid-cols-2">
                <x-glass-card class="p-5">
                    <h2 class="text-xl font-semibold text-slate-900">{{ __('ui.fairness.equality_pulse.title') }}</h2>
                    <p class="mt-1 text-xs text-slate-600">{{ __('ui.fairness.equality_pulse.subtitle') }}</p>

                    @if(! $hasSufficientData || ! is_array($equalityPulse))
                        <div class="mt-4">
                            <x-empty-state :title="__('ui.fairness.insufficient_data_title')" :message="__('ui.fairness.insufficient_data_message')" />
                        </div>
                    @else
                        @php
                            $gaugePercent = max(0, min(100, (float) ($equalityPulse['fairness_index'] ?? 0)));
                            $statusKey = (string) ($equalityPulse['status_key'] ?? 'watch');
                            $statusClass = match ($statusKey) {
                                'critical' => 'text-danger-700',
                                'healthy' => 'text-success-700',
                                default => 'text-primary-700',
                            };
                            $gaugeColor = match ($statusKey) {
                                'critical' => '#dc2626',
                                'healthy' => '#059669',
                                default => '#6d28d9',
                            };
                        @endphp
                        <div class="mt-4 flex flex-wrap items-center gap-4">
                            <div class="grid size-32 place-items-center rounded-full" style="background: conic-gradient({{ $gaugeColor }} {{ $gaugePercent }}%, #e2e8f0 {{ $gaugePercent }}%);">
                                <div class="grid size-24 place-items-center rounded-full bg-white">
                                    <span class="text-xl font-semibold text-slate-900">{{ number_format($gaugePercent, 1) }}</span>
                                </div>
                            </div>
                            <div class="space-y-1">
                                <p class="text-sm text-slate-700">
                                    {{ __('ui.fairness.equality_pulse.impact_ratio') }}:
                                    <span class="font-semibold">{{ number_format((float) ($equalityPulse['impact_ratio'] ?? 0), 4) }}</span>
                                </p>
                                <p class="text-sm text-slate-700">
                                    {{ __('ui.fairness.equality_pulse.threshold') }}:
                                    <span class="font-semibold">{{ number_format(\App\Services\Fairness\FairnessAuditService::IMPACT_RATIO_ALERT_THRESHOLD, 2) }}</span>
                                </p>
                                <p class="text-sm font-semibold {{ $statusClass }}">
                                    {{ __('ui.fairness.equality_pulse.status.'.$statusKey) }}
                                </p>
                            </div>
                        </div>
                    @endif
                </x-glass-card>

                <x-glass-card class="p-5">
                    <h2 class="text-xl font-semibold text-slate-900">{{ __('ui.fairness.diversity_funnel.title') }}</h2>
                    <p class="mt-1 text-xs text-slate-600">{{ __('ui.fairness.diversity_funnel.subtitle') }}</p>

                    @if(! $hasSufficientData || $diversityFunnel->isEmpty())
                        <div class="mt-4">
                            <x-empty-state :title="__('ui.fairness.insufficient_data_title')" :message="__('ui.fairness.insufficient_data_message')" />
                        </div>
                    @else
                        <div class="mt-4 space-y-3">
                            @foreach($diversityFunnel as $stage)
                                <div class="rounded-xl border border-slate-200 bg-white/85 p-3">
                                    <div class="flex items-center justify-between text-xs text-slate-700">
                                        <span class="font-semibold text-slate-900">{{ $stage['stage_label'] }}</span>
                                        <span>{{ __('ui.fairness.diversity_funnel.impact_ratio') }} {{ number_format((float) $stage['impact_ratio'], 4) }}</span>
                                    </div>
                                    <div class="mt-2 space-y-2">
                                        <div>
                                            <div class="flex items-center justify-between text-[11px] text-slate-600">
                                                <span>{{ $selectedDimensionGroups['group_a'] }}</span>
                                                <span>{{ $stage['group_a_count'] }}</span>
                                            </div>
                                            <div class="mt-1 h-2 rounded-full bg-slate-100">
                                                <div class="h-2 rounded-full bg-primary-500" style="width: {{ max(3, (float) $stage['group_a_percent']) }}%;"></div>
                                            </div>
                                        </div>
                                        <div>
                                            <div class="flex items-center justify-between text-[11px] text-slate-600">
                                                <span>{{ $selectedDimensionGroups['group_b'] }}</span>
                                                <span>{{ $stage['group_b_count'] }}</span>
                                            </div>
                                            <div class="mt-1 h-2 rounded-full bg-slate-100">
                                                <div class="h-2 rounded-full bg-success-500" style="width: {{ max(3, (float) $stage['group_b_percent']) }}%;"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </x-glass-card>
            </div>
        @endif
    </section>
</x-shell-layout>
