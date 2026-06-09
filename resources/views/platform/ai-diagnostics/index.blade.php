<x-shell-layout :title="__('platform.ai_diagnostics.title').' | '.config('app.name')">
    <x-glass-card :title="__('platform.ai_diagnostics.title')" :subtitle="__('platform.ai_diagnostics.subtitle')">

        {{-- Toast --}}
        @if (session('status'))
            <x-toast-alert type="success" class="mb-4">{{ session('status') }}</x-toast-alert>
        @endif

        {{-- Top action bar --}}
        <div class="flex flex-wrap items-start justify-between gap-4">

            {{-- Filters --}}
            <form method="GET" action="{{ route('platform.ai-diagnostics') }}" class="flex flex-wrap items-end gap-3">
                {{-- Company filter --}}
                <div class="min-w-[180px]">
                    <label class="mb-1 block text-xs font-medium text-slate-700">{{ __('platform.ai_diagnostics.filters.company') }}</label>
                    <select name="company_id"
                            data-placeholder="{{ __('platform.ai_diagnostics.filters.company_placeholder') }}"
                            class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2 text-sm text-slate-900 shadow-sm">
                        <option value="">{{ __('platform.ai_diagnostics.filters.company_placeholder') }}</option>
                        @foreach($companies as $company)
                            <option value="{{ $company->id }}" @selected((string) $selectedCompanyId === (string) $company->id)>
                                {{ $company->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Status filter --}}
                <div class="min-w-[150px]">
                    <label class="mb-1 block text-xs font-medium text-slate-700">{{ __('platform.ai_diagnostics.filters.status') }}</label>
                    <select name="status"
                            data-placeholder="{{ __('platform.ai_diagnostics.filters.status_placeholder') }}"
                            class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2 text-sm text-slate-900 shadow-sm">
                        <option value="">{{ __('platform.ai_diagnostics.filters.status_placeholder') }}</option>
                        @foreach(['queued','running','succeeded','failed'] as $s)
                            <option value="{{ $s }}" @selected($selectedStatus === $s)>{{ __('platform.ai_diagnostics.statuses.'.$s) }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Request Type filter --}}
                <div class="min-w-[180px]">
                    <label class="mb-1 block text-xs font-medium text-slate-700">{{ __('platform.ai_diagnostics.filters.request_type') }}</label>
                    <select name="request_type"
                            data-placeholder="{{ __('platform.ai_diagnostics.filters.request_type_placeholder') }}"
                            class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2 text-sm text-slate-900 shadow-sm">
                        <option value="">{{ __('platform.ai_diagnostics.filters.request_type_placeholder') }}</option>
                        @foreach($requestTypes as $type)
                            <option value="{{ $type }}" @selected($selectedRequestType === $type)>{{ $type }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Search --}}
                <div class="min-w-[200px]">
                    <label class="mb-1 block text-xs font-medium text-slate-700">{{ __('platform.ai_diagnostics.filters.search') }}</label>
                    <input type="text" name="search" value="{{ $search }}"
                           placeholder="{{ __('platform.ai_diagnostics.filters.search_placeholder') }}"
                           class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2 text-sm text-slate-900 shadow-sm focus:outline-none focus:ring-2 focus:ring-aura-400/40">
                </div>

                <button type="submit"
                        class="rounded-xl border border-aura-300/50 bg-white px-4 py-2 text-sm font-medium text-slate-900 transition-weightless hover:bg-aura-50">
                    {{ __('platform.ai_diagnostics.filters.apply') }}
                </button>
                @if($selectedCompanyId || $selectedStatus || $selectedRequestType || $search)
                    <a href="{{ route('platform.ai-diagnostics') }}"
                       class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-600 transition-weightless hover:bg-slate-50">
                        {{ __('platform.ai_diagnostics.filters.clear') }}
                    </a>
                @endif
            </form>

            {{-- Create Test Request Button (dev-only) --}}
            @if($isDevMode)
                <x-modal :title="__('platform.ai_diagnostics.create_test_title')">
                    <x-slot:trigger>
                        <button type="button"
                                class="flex items-center gap-2 rounded-xl border border-success-400/60 bg-success-600 px-4 py-2 text-sm font-semibold text-white shadow-lg shadow-success-200/40 transition-weightless hover:bg-success-700">
                            <svg class="size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                            </svg>
                            {{ __('platform.ai_diagnostics.create_test_button') }}
                            <span class="rounded-full bg-danger-400/90 px-2 py-0.5 text-xs font-bold text-danger-900">{{ __('platform.ai_diagnostics.create_test_dev_badge') }}</span>
                        </button>
                    </x-slot:trigger>

                    {{-- Modal body using Alpine for conditional field --}}
                    <div x-data="{ selectedType: '' }">
                        <form method="POST" action="{{ route('platform.ai-diagnostics.store') }}" class="space-y-4">
                            @csrf

                            {{-- Company --}}
                            <x-form-field :label="__('platform.ai_diagnostics.form.company')" name="company_id" :required="true">
                                <select name="company_id"
                                        data-placeholder="{{ __('platform.ai_diagnostics.form.company_placeholder') }}"
                                        class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-slate-900 shadow-sm"
                                        required>
                                    <option value="">{{ __('platform.ai_diagnostics.form.company_placeholder') }}</option>
                                    @foreach($companies as $company)
                                        <option value="{{ $company->id }}">{{ $company->name }}</option>
                                    @endforeach
                                </select>
                            </x-form-field>

                            {{-- Request Type --}}
                            <x-form-field :label="__('platform.ai_diagnostics.form.request_type')" name="request_type" :required="true">
                                <select name="request_type"
                                        data-placeholder="{{ __('platform.ai_diagnostics.form.request_type_placeholder') }}"
                                        class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-slate-900 shadow-sm"
                                        x-model="selectedType"
                                        required>
                                    <option value="">{{ __('platform.ai_diagnostics.form.request_type_placeholder') }}</option>
                                    @foreach($testRequestTypes as $type)
                                        <option value="{{ $type }}">{{ $type }}</option>
                                    @endforeach
                                </select>
                            </x-form-field>

                            {{-- Input Text --}}
                            <x-form-field :label="__('platform.ai_diagnostics.form.input_text')" name="input_text" :required="true">
                                <textarea name="input_text" rows="4" required
                                          placeholder="{{ __('platform.ai_diagnostics.form.input_text_placeholder') }}"
                                          class="w-full resize-none rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:outline-none focus:ring-2 focus:ring-aura-400/40"></textarea>
                            </x-form-field>

                            {{-- Force Invalid JSON (only for candidate_analysis_json) --}}
                            <div x-show="selectedType === 'candidate_analysis_json'" x-cloak
                                 class="rounded-xl border border-danger-200/70 bg-danger-50/80 p-4">
                                <label class="flex cursor-pointer items-start gap-3">
                                    <input type="checkbox" name="force_invalid_json" value="1"
                                           class="mt-0.5 rounded border-danger-400 text-danger-600 focus:ring-danger-400">
                                    <span class="text-sm font-medium text-danger-900">
                                        {{ __('platform.ai_diagnostics.form.force_invalid_json') }}
                                    </span>
                                </label>
                                <p class="ml-7 mt-1 text-xs text-danger-700">{{ __('platform.ai_diagnostics.form.force_invalid_json_help') }}</p>
                            </div>

                            {{-- Actions --}}
                            <div class="flex justify-end gap-3 pt-2">
                                <button type="button" @click="open = false"
                                        class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition-weightless hover:bg-slate-50">
                                    {{ __('platform.ai_diagnostics.create_cancel') }}
                                </button>
                                <button type="submit"
                                        class="rounded-xl bg-success-600 px-5 py-2 text-sm font-semibold text-white shadow-sm transition-weightless hover:bg-success-700">
                                    {{ __('platform.ai_diagnostics.create_submit') }}
                                </button>
                            </div>
                        </form>
                    </div>
                </x-modal>
            @endif
        </div>

        {{-- Requests Table --}}
        <div class="mt-6 overflow-x-auto">
            <x-table>
                <thead class="bg-white/75">
                    <tr>
                        <th class="px-4 py-3 text-left text-sm font-semibold text-slate-800">{{ __('platform.ai_diagnostics.table.created_at') }}</th>
                        <th class="px-4 py-3 text-left text-sm font-semibold text-slate-800">{{ __('platform.ai_diagnostics.table.company') }}</th>
                        <th class="px-4 py-3 text-left text-sm font-semibold text-slate-800">{{ __('platform.ai_diagnostics.table.request_type') }}</th>
                        <th class="px-4 py-3 text-left text-sm font-semibold text-slate-800">{{ __('platform.ai_diagnostics.table.status') }}</th>
                        <th class="px-4 py-3 text-left text-sm font-semibold text-slate-800">{{ __('platform.ai_diagnostics.table.duration') }}</th>
                        <th class="px-4 py-3 text-left text-sm font-semibold text-slate-800">{{ __('platform.ai_diagnostics.table.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/70">
                    @forelse($aiRequests as $aiRequest)
                        @php
                            $statusVariant = match($aiRequest->status) {
                                'succeeded' => 'success',
                                'failed'    => 'danger',
                                'running'   => 'pending',
                                default     => 'default',
                            };
                            $durationMs = null;
                            if ($aiRequest->started_at && $aiRequest->finished_at) {
                                $durationMs = $aiRequest->started_at->diffInMilliseconds($aiRequest->finished_at);
                            }
                        @endphp
                        <tr class="transition-colors hover:bg-aura-50/30">
                            <td class="px-4 py-3 text-xs text-slate-600">{{ $aiRequest->created_at?->toDateTimeString() }}</td>
                            <td class="px-4 py-3 text-sm font-medium text-slate-800">{{ $aiRequest->company?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-xs font-mono text-slate-700">{{ $aiRequest->request_type }}</td>
                            <td class="px-4 py-3">
                                <x-badge :variant="$statusVariant">
                                    {{ __('platform.ai_diagnostics.statuses.'.$aiRequest->status) }}
                                </x-badge>
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-600">
                                @if($durationMs !== null)
                                    {{ number_format($durationMs) }} {{ __('platform.ai_diagnostics.ms') }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <a href="{{ route('platform.ai-diagnostics.show', $aiRequest->id) }}"
                                   class="rounded-lg border border-aura-300/50 bg-white px-3 py-1.5 text-xs font-medium text-aura-800 transition-weightless hover:bg-aura-50">
                                    {{ __('platform.ai_diagnostics.view_action') }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-10">
                                <x-empty-state
                                    :title="__('platform.ai_diagnostics.empty_title')"
                                    :message="__('platform.ai_diagnostics.empty_message')" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </x-table>
        </div>

        <div class="mt-6">{{ $aiRequests->links() }}</div>

    </x-glass-card>
</x-shell-layout>
