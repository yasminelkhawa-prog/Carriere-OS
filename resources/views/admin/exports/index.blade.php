<x-shell-layout :title="__('ui.exports.history.title').' | '.config('app.name')">
    <section class="space-y-4">
        @if(session('status'))
            <x-toast-alert type="success">{{ session('status') }}</x-toast-alert>
        @endif
        @if(session('error'))
            <x-toast-alert type="warning">{{ session('error') }}</x-toast-alert>
        @endif

        <div>
            <h1 class="panel-title text-3xl font-semibold tracking-tight text-slate-900">{{ __('ui.exports.history.heading') }}</h1>
            <p class="mt-1 text-sm text-slate-600">{{ __('ui.exports.history.subheading') }}</p>
        </div>

        @if($requiresCompanySelection)
            <x-glass-card>
                <x-empty-state :title="__('ui.exports.history.select_company_title')" :message="__('ui.exports.history.select_company_message')" />
            </x-glass-card>
        @else
            <x-glass-card class="p-4">
                <form method="GET" action="{{ route('admin.exports.index') }}" class="grid gap-3 md:grid-cols-4">
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

                    <x-form-field :label="__('ui.exports.fields.type')" name="export_type">
                        <select name="export_type" data-placeholder="{{ __('ui.exports.placeholders.type') }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2 text-sm">
                            <option value="">{{ __('ui.exports.placeholders.type') }}</option>
                            @foreach($exportTypes as $exportType)
                                <option value="{{ $exportType }}" @selected((string) ($filters['export_type'] ?? '') === (string) $exportType)>
                                    {{ __('ui.exports.types.'.$exportType) }}
                                </option>
                            @endforeach
                        </select>
                    </x-form-field>

                    <x-form-field :label="__('ui.exports.fields.status')" name="status">
                        <select name="status" data-placeholder="{{ __('ui.exports.placeholders.status') }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2 text-sm">
                            <option value="">{{ __('ui.exports.placeholders.status') }}</option>
                            @foreach($statuses as $status)
                                <option value="{{ $status }}" @selected((string) ($filters['status'] ?? '') === (string) $status)>
                                    {{ __('ui.exports.statuses.'.$status) }}
                                </option>
                            @endforeach
                        </select>
                    </x-form-field>

                    <x-form-field :label="__('ui.exports.fields.format')" name="format">
                        <select name="format" data-placeholder="{{ __('ui.exports.placeholders.format') }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2 text-sm">
                            <option value="">{{ __('ui.exports.placeholders.format') }}</option>
                            @foreach($formats as $format)
                                <option value="{{ $format }}" @selected((string) ($filters['format'] ?? '') === (string) $format)>
                                    {{ strtoupper($format) }}
                                </option>
                            @endforeach
                        </select>
                    </x-form-field>

                    <div class="md:col-span-4 flex items-end gap-2">
                        <button type="submit" class="rounded-xl bg-success-600 px-4 py-2.5 text-sm font-semibold text-white transition-weightless hover:bg-success-700">
                            {{ __('ui.exports.history.apply') }}
                        </button>
                        <a href="{{ route('admin.exports.index', array_filter(['company_id' => request('company_id', $selectedCompanyId)])) }}" class="rounded-xl border border-aura-300/50 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition-weightless hover:bg-slate-50">
                            {{ __('ui.exports.history.reset') }}
                        </a>
                    </div>
                </form>
            </x-glass-card>

            <x-glass-card class="p-0">
                @if($exports->isEmpty())
                    <div class="p-6">
                        <x-empty-state :title="__('ui.exports.history.empty_title')" :message="__('ui.exports.history.empty_message')" />
                    </div>
                @else
                    <x-table>
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-5 py-3 text-left text-xs uppercase tracking-wider text-slate-500">{{ __('ui.exports.fields.requested_by') }}</th>
                                <th class="px-5 py-3 text-left text-xs uppercase tracking-wider text-slate-500">{{ __('ui.exports.fields.type') }}</th>
                                <th class="px-5 py-3 text-left text-xs uppercase tracking-wider text-slate-500">{{ __('ui.exports.fields.format') }}</th>
                                <th class="px-5 py-3 text-left text-xs uppercase tracking-wider text-slate-500">{{ __('ui.exports.fields.status') }}</th>
                                <th class="px-5 py-3 text-left text-xs uppercase tracking-wider text-slate-500">{{ __('ui.exports.fields.created_at') }}</th>
                                <th class="px-5 py-3 text-left text-xs uppercase tracking-wider text-slate-500">{{ __('ui.exports.fields.file') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @foreach($exports as $export)
                                @php
                                    $badgeClass = match ((string) $export->status) {
                                        'completed' => 'bg-success-100 text-success-800',
                                        'failed' => 'bg-danger-100 text-danger-800',
                                        'processing' => 'bg-primary-100 text-primary-800',
                                        default => 'bg-slate-100 text-slate-700',
                                    };
                                @endphp
                                <tr>
                                    <td class="px-5 py-3 text-sm text-slate-800">
                                        {{ $export->requestedBy?->profile?->full_name ?? $export->requestedBy?->email ?? __('ui.dashboard.unknown') }}
                                    </td>
                                    <td class="px-5 py-3 text-sm text-slate-800">{{ __('ui.exports.types.'.$export->export_type) }}</td>
                                    <td class="px-5 py-3 text-sm text-slate-700">{{ strtoupper((string) $export->format) }}</td>
                                    <td class="px-5 py-3 text-sm">
                                        <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $badgeClass }}">
                                            {{ __('ui.exports.statuses.'.$export->status) }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-3 text-sm text-slate-700">{{ optional($export->created_at)->format('Y-m-d H:i:s') }}</td>
                                    <td class="px-5 py-3 text-sm">
                                        @if((string) $export->status === \App\Models\Export::STATUS_COMPLETED && $export->file_url)
                                            <a href="{{ \App\Http\Controllers\ReportingExportController::signedDownloadUrl($export) }}" class="rounded-lg border border-aura-300/50 bg-aura-50 px-3 py-1.5 text-xs font-semibold text-aura-800 transition-weightless hover:bg-aura-100">
                                                {{ __('ui.exports.history.download') }}
                                            </a>
                                        @else
                                            <span class="text-xs text-slate-500">{{ __('ui.exports.history.not_ready') }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-table>
                @endif
            </x-glass-card>

            @if($exports instanceof \Illuminate\Contracts\Pagination\Paginator)
                <div>{{ $exports->links() }}</div>
            @endif
        @endif
    </section>
</x-shell-layout>
