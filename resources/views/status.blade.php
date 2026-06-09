<x-shell-layout :title="__('ui.status.title').' | '.config('app.name')">
    <div class="space-y-6">
        <x-glass-card :title="__('ui.status.title')" :subtitle="__('ui.status.subtitle')">
            <x-toast-alert type="info">{{ __('ui.status.alert_message') }}</x-toast-alert>

            <dl class="mt-6 grid gap-4 sm:grid-cols-2">
                <div class="rounded-xl border border-white/80 bg-white/70 p-4">
                    <dt class="text-xs uppercase tracking-wider text-aura-700/80">{{ __('ui.status.environment') }}</dt>
                    <dd class="mt-2 text-lg font-medium text-slate-900">{{ app()->environment() }}</dd>
                </div>
                <div class="rounded-xl border border-white/80 bg-white/70 p-4">
                    <dt class="text-xs uppercase tracking-wider text-aura-700/80">{{ __('ui.status.queue') }}</dt>
                    <dd class="mt-2 text-lg font-medium text-slate-900">{{ config('queue.default') }}</dd>
                </div>
                <div class="rounded-xl border border-white/80 bg-white/70 p-4">
                    <dt class="text-xs uppercase tracking-wider text-aura-700/80">{{ __('ui.status.storage') }}</dt>
                    <dd class="mt-2 text-lg font-medium text-slate-900">{{ config('filesystems.default') }}</dd>
                </div>
                <div class="rounded-xl border border-white/80 bg-white/70 p-4">
                    <dt class="text-xs uppercase tracking-wider text-aura-700/80">{{ __('ui.status.database') }}</dt>
                    <dd class="mt-2 text-lg font-medium text-slate-900">{{ config('database.default') }}</dd>
                </div>
            </dl>
        </x-glass-card>

        <x-glass-card>
            <div class="grid gap-5 md:grid-cols-2">
                <x-form-field :label="__('ui.status.sample_filter')" name="status_filter">
                    <select name="status_filter" data-placeholder="{{ __('ui.status.sample_filter_placeholder') }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-slate-900 shadow-sm">
                        <option value="">{{ __('ui.status.sample_filter_placeholder') }}</option>
                        <option value="open">{{ __('ui.status.sample_filter_open') }}</option>
                        <option value="in_progress">{{ __('ui.status.sample_filter_in_progress') }}</option>
                        <option value="closed">{{ __('ui.status.sample_filter_closed') }}</option>
                    </select>
                </x-form-field>

                <x-form-field :label="__('ui.status.sample_multi')" name="sample_skills">
                    <select name="sample_skills[]" multiple data-placeholder="{{ __('ui.status.sample_multi_placeholder') }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-slate-900 shadow-sm">
                        <option value="communication">{{ __('ui.status.sample_multi_a') }}</option>
                        <option value="leadership">{{ __('ui.status.sample_multi_b') }}</option>
                        <option value="analysis">{{ __('ui.status.sample_multi_c') }}</option>
                    </select>
                </x-form-field>
            </div>
        </x-glass-card>

        <x-glass-card :title="__('ui.status.table_title')">
            <x-table>
                <thead class="bg-white/75">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold">{{ __('ui.status.table_col_task') }}</th>
                        <th class="px-4 py-3 text-left font-semibold">{{ __('ui.status.table_col_state') }}</th>
                        <th class="px-4 py-3 text-left font-semibold">{{ __('ui.status.table_col_owner') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/70">
                    <tr>
                        <td class="px-4 py-3">{{ __('ui.status.table_row_ai') }}</td>
                        <td class="px-4 py-3"><x-badge variant="success">{{ __('ui.status.table_state_queued') }}</x-badge></td>
                        <td class="px-4 py-3">{{ __('ui.status.table_owner_system') }}</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3">{{ __('ui.status.table_row_media') }}</td>
                        <td class="px-4 py-3"><x-badge>{{ __('ui.status.table_state_pending') }}</x-badge></td>
                        <td class="px-4 py-3">{{ __('ui.status.table_owner_worker') }}</td>
                    </tr>
                </tbody>
            </x-table>
        </x-glass-card>
    </div>
</x-shell-layout>
