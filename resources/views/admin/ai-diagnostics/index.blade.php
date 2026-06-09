<x-shell-layout :title="__('ui.ai_diagnostics.title').' | '.config('app.name')">
    <x-glass-card :title="__('ui.ai_diagnostics.title')" :subtitle="__('ui.ai_diagnostics.subtitle')">
        @if (session('status'))
            <x-toast-alert type="success">{{ session('status') }}</x-toast-alert>
        @endif

        @if($requiresCompanyFilter)
            <x-toast-alert type="warning">{{ __('ui.ai_diagnostics.superadmin_company_filter_required') }}</x-toast-alert>
        @endif

        <form method="GET" action="{{ route('admin.ai-diagnostics.index') }}" class="mt-4 grid gap-4 lg:grid-cols-4">
            @if(auth()->user()?->isSuperadmin())
                <x-form-field :label="__('ui.ai_diagnostics.filters.company')" name="company_id">
                    <select name="company_id" data-placeholder="{{ __('ui.ai_diagnostics.filters.company_placeholder') }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-slate-900 shadow-sm">
                        <option value="">{{ __('ui.ai_diagnostics.filters.company_placeholder') }}</option>
                        @foreach($companies as $company)
                            <option value="{{ $company->id }}" @selected((string) $selectedCompanyId === (string) $company->id)>{{ $company->name }}</option>
                        @endforeach
                    </select>
                </x-form-field>
            @endif

            <x-form-field :label="__('ui.ai_diagnostics.filters.status')" name="status">
                <select name="status" data-placeholder="{{ __('ui.ai_diagnostics.filters.status_placeholder') }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-slate-900 shadow-sm">
                    <option value="">{{ __('ui.ai_diagnostics.filters.status_placeholder') }}</option>
                    <option value="queued" @selected($selectedStatus === 'queued')>{{ __('ui.ai_diagnostics.statuses.queued') }}</option>
                    <option value="running" @selected($selectedStatus === 'running')>{{ __('ui.ai_diagnostics.statuses.running') }}</option>
                    <option value="succeeded" @selected($selectedStatus === 'succeeded')>{{ __('ui.ai_diagnostics.statuses.succeeded') }}</option>
                    <option value="failed" @selected($selectedStatus === 'failed')>{{ __('ui.ai_diagnostics.statuses.failed') }}</option>
                </select>
            </x-form-field>

            <x-form-field :label="__('ui.ai_diagnostics.filters.request_type')" name="request_type">
                <select name="request_type" data-placeholder="{{ __('ui.ai_diagnostics.filters.request_type_placeholder') }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-slate-900 shadow-sm">
                    <option value="">{{ __('ui.ai_diagnostics.filters.request_type_placeholder') }}</option>
                    @foreach($requestTypes as $type)
                        <option value="{{ $type }}" @selected($selectedRequestType === $type)>{{ $type }}</option>
                    @endforeach
                </select>
            </x-form-field>

            <div class="flex items-end">
                <button type="submit" class="rounded-xl border border-aura-300/50 bg-white px-4 py-2 text-sm font-medium text-slate-900 transition-weightless hover:bg-white">
                    {{ __('ui.ai_diagnostics.filters.apply') }}
                </button>
            </div>
        </form>

        <div class="mt-6 overflow-x-auto">
            <x-table>
                <thead class="bg-white/75">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold">{{ __('ui.ai_diagnostics.table.created_at') }}</th>
                        <th class="px-4 py-3 text-left font-semibold">{{ __('ui.ai_diagnostics.table.request_type') }}</th>
                        <th class="px-4 py-3 text-left font-semibold">{{ __('ui.ai_diagnostics.table.status') }}</th>
                        <th class="px-4 py-3 text-left font-semibold">{{ __('ui.ai_diagnostics.table.model') }}</th>
                        <th class="px-4 py-3 text-left font-semibold">{{ __('ui.ai_diagnostics.table.request_preview') }}</th>
                        <th class="px-4 py-3 text-left font-semibold">{{ __('ui.ai_diagnostics.table.response_preview') }}</th>
                        <th class="px-4 py-3 text-left font-semibold">{{ __('ui.ai_diagnostics.table.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/70">
                    @forelse($aiRequests as $aiRequest)
                        <tr>
                            <td class="px-4 py-3 text-xs text-slate-700">{{ $aiRequest->created_at?->toDateTimeString() }}</td>
                            <td class="px-4 py-3 text-xs text-slate-800">{{ $aiRequest->request_type }}</td>
                            <td class="px-4 py-3">
                                @php($statusVariant = $aiRequest->status === 'failed' ? 'danger' : ($aiRequest->status === 'succeeded' ? 'success' : 'default'))
                                <x-badge :variant="$statusVariant">{{ __('ui.ai_diagnostics.statuses.'.$aiRequest->status) }}</x-badge>
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-700">{{ $aiRequest->model_name }}</td>
                            <td class="px-4 py-3 text-xs text-slate-700">{{ \Illuminate\Support\Str::limit((string) $aiRequest->request_preview, 120) }}</td>
                            <td class="px-4 py-3 text-xs text-slate-700">{{ \Illuminate\Support\Str::limit((string) $aiRequest->response_preview, 120) }}</td>
                            <td class="px-4 py-3">
                                @if($aiRequest->status === 'failed')
                                    <p class="mb-2 text-xs text-danger-700">{{ __('ui.ai_diagnostics.processing_failed') }}</p>
                                    <form method="POST" action="{{ route('admin.ai-diagnostics.retry', $aiRequest) }}">
                                        @csrf
                                        @if(auth()->user()?->isSuperadmin())
                                            <input type="hidden" name="company_id" value="{{ $selectedCompanyId }}">
                                        @endif
                                        <button type="submit" class="rounded-lg border border-danger-300/60 bg-white px-3 py-1.5 text-xs font-medium text-danger-800 transition-weightless hover:bg-danger-50">
                                            {{ __('ui.ai_diagnostics.retry_action') }}
                                        </button>
                                    </form>
                                @else
                                    <span class="text-xs text-slate-500">{{ __('ui.ai_diagnostics.no_action') }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-6">
                                <x-empty-state :title="__('ui.ai_diagnostics.empty_title')" :message="__('ui.ai_diagnostics.empty_message')" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </x-table>
        </div>

        <div class="mt-6">{{ $aiRequests->links() }}</div>
    </x-glass-card>
</x-shell-layout>
