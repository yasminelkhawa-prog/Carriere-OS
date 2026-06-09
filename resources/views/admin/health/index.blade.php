<x-shell-layout :title="__('ui.health.title').' | '.config('app.name')">
    <section class="space-y-4">
        @if(session('status'))
            <x-toast-alert type="success">{{ session('status') }}</x-toast-alert>
        @endif
        @if(session('error'))
            <x-toast-alert type="warning">{{ session('error') }}</x-toast-alert>
        @endif
        @if($errors->any())
            <x-toast-alert type="error">{{ $errors->first() }}</x-toast-alert>
        @endif

        <div>
            <h1 class="panel-title text-3xl font-semibold tracking-tight text-slate-900">{{ __('ui.health.heading') }}</h1>
            <p class="mt-1 text-sm text-slate-600">{{ __('ui.health.subheading') }}</p>
        </div>

        @if($requiresCompanySelection)
            <x-glass-card>
                <x-empty-state :title="__('ui.health.select_company_title')" :message="__('ui.health.select_company_message')" />
            </x-glass-card>
        @else
            <x-glass-card class="p-4">
                <form method="GET" action="{{ route('admin.health.index') }}" class="grid gap-3 md:grid-cols-4">
                    @if(auth()->user()?->isSuperadmin())
                        <x-form-field :label="__('jobs.company')" name="company_id">
                            <select name="company_id" data-placeholder="{{ __('jobs.company_placeholder') }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2 text-sm">
                                <option value="">{{ __('jobs.company_placeholder') }}</option>
                                @foreach($companies as $company)
                                    <option value="{{ $company->id }}" @selected((string) request('company_id', $selectedCompanyId) === (string) $company->id)>{{ $company->name }}</option>
                                @endforeach
                            </select>
                        </x-form-field>
                    @endif

                    <div class="flex items-end gap-2 @if(auth()->user()?->isSuperadmin()) md:col-span-3 @else md:col-span-4 @endif">
                        <button type="submit" class="rounded-xl bg-success-600 px-4 py-2.5 text-sm font-semibold text-white transition-weightless hover:bg-success-700">
                            {{ __('ui.health.actions.apply_company') }}
                        </button>
                    </div>
                </form>
            </x-glass-card>

            <div class="grid gap-3 sm:grid-cols-3">
                <x-glass-card class="p-4">
                    <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('ui.health.summary.pass') }}</p>
                    <p class="mt-2 text-3xl font-semibold text-success-700">{{ $statusSummary['pass'] ?? 0 }}</p>
                </x-glass-card>
                <x-glass-card class="p-4">
                    <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('ui.health.summary.warning') }}</p>
                    <p class="mt-2 text-3xl font-semibold text-danger-700">{{ $statusSummary['warning'] ?? 0 }}</p>
                </x-glass-card>
                <x-glass-card class="p-4">
                    <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('ui.health.summary.fail') }}</p>
                    <p class="mt-2 text-3xl font-semibold text-danger-700">{{ $statusSummary['fail'] ?? 0 }}</p>
                </x-glass-card>
            </div>

            <x-glass-card class="p-0">
                <x-table>
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-5 py-3 text-left text-xs uppercase tracking-wider text-slate-500">{{ __('ui.health.table.check') }}</th>
                            <th class="px-5 py-3 text-left text-xs uppercase tracking-wider text-slate-500">{{ __('ui.health.table.status') }}</th>
                            <th class="px-5 py-3 text-left text-xs uppercase tracking-wider text-slate-500">{{ __('ui.health.table.detail') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @forelse($checks as $check)
                            @php
                                $badgeClass = match ((string) $check['status']) {
                                    'pass' => 'bg-success-100 text-success-800',
                                    'fail' => 'bg-danger-100 text-danger-800',
                                    default => 'bg-danger-100 text-danger-800',
                                };
                            @endphp
                            <tr>
                                <td class="px-5 py-3 text-sm font-semibold text-slate-900">{{ $check['title'] }}</td>
                                <td class="px-5 py-3 text-sm">
                                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $badgeClass }}">
                                        {{ __('ui.health.statuses.'.$check['status']) }}
                                    </span>
                                </td>
                                <td class="px-5 py-3 text-sm text-slate-700">{{ $check['detail'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-5 py-8 text-center text-sm text-slate-600">{{ __('ui.health.empty_checks') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </x-table>
            </x-glass-card>

            <x-glass-card class="p-4">
                <h2 class="text-lg font-semibold text-slate-900">{{ __('ui.health.retention.title') }}</h2>
                <p class="mt-1 text-sm text-slate-600">{{ __('ui.health.retention.subtitle') }}</p>

                <form method="POST" action="{{ route('admin.health.retention.update') }}" class="mt-4 grid gap-3 md:grid-cols-2">
                    @csrf
                    @if(auth()->user()?->isSuperadmin() && $selectedCompanyId)
                        <input type="hidden" name="company_id" value="{{ $selectedCompanyId }}">
                    @endif

                    <x-form-field :label="__('ui.health.retention.video_days')" name="video_retention_days" required>
                        <input
                            type="number"
                            name="video_retention_days"
                            min="{{ $retentionMinDays }}"
                            max="{{ $retentionMaxDays }}"
                            required
                            value="{{ (string) old('video_retention_days', (int) ($retentionSetting?->video_retention_days ?? 365)) }}"
                            class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2 text-sm"
                        >
                    </x-form-field>

                    <x-form-field :label="__('ui.health.retention.ai_days')" name="ai_artifact_retention_days" required>
                        <input
                            type="number"
                            name="ai_artifact_retention_days"
                            min="{{ $retentionMinDays }}"
                            max="{{ $retentionMaxDays }}"
                            required
                            value="{{ (string) old('ai_artifact_retention_days', (int) ($retentionSetting?->ai_artifact_retention_days ?? 180)) }}"
                            class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2 text-sm"
                        >
                    </x-form-field>

                    <div class="md:col-span-2 flex flex-wrap items-center gap-2">
                        <button type="submit" class="rounded-xl bg-success-600 px-4 py-2 text-sm font-semibold text-white transition-weightless hover:bg-success-700">
                            {{ __('ui.health.retention.save') }}
                        </button>
                        <span class="text-xs text-slate-500">
                            {{ __('ui.health.retention.last_pruned', ['time' => optional($retentionSetting?->last_pruned_at)->format('Y-m-d H:i:s') ?? __('ui.dashboard.not_available')]) }}
                        </span>
                    </div>
                </form>

                <form method="POST" action="{{ route('admin.health.retention.prune') }}" class="mt-2">
                    @csrf
                    @if(auth()->user()?->isSuperadmin() && $selectedCompanyId)
                        <input type="hidden" name="company_id" value="{{ $selectedCompanyId }}">
                    @endif
                    <button type="submit" class="rounded-xl border border-aura-300/50 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition-weightless hover:bg-slate-50">
                        {{ __('ui.health.retention.run_now') }}
                    </button>
                </form>
            </x-glass-card>
        @endif
    </section>
</x-shell-layout>
