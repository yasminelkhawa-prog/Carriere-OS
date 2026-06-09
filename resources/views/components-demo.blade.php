<x-shell-layout :title="__('ui.demo.title').' | '.config('app.name')">
    <div class="space-y-6">
        <x-glass-card :title="__('ui.demo.title')" :subtitle="__('ui.demo.subtitle')">
            <div class="grid gap-4 md:grid-cols-2">
                <div class="space-y-2 rounded-xl border border-white/80 bg-white/70 p-4">
                    <h3 class="text-sm font-semibold text-slate-900">{{ __('ui.demo.states.normal') }}</h3>
                    <button type="button" class="rounded-xl border border-aura-300/40 bg-white/80 px-4 py-2 text-sm text-slate-900 transition-weightless hover:bg-white">
                        {{ __('ui.demo.actions.primary') }}
                    </button>
                </div>

                <div class="space-y-2 rounded-xl border border-white/80 bg-white/70 p-4">
                    <h3 class="text-sm font-semibold text-slate-900">{{ __('ui.demo.states.hover') }}</h3>
                    <button type="button" class="rounded-xl border border-aura-300/40 bg-white/80 px-4 py-2 text-sm text-slate-900 transition-weightless hover:bg-aura-100">
                        {{ __('ui.demo.actions.hover_me') }}
                    </button>
                </div>

                <div class="space-y-2 rounded-xl border border-white/80 bg-white/70 p-4">
                    <h3 class="text-sm font-semibold text-slate-900">{{ __('ui.demo.states.focus') }}</h3>
                    <x-form-field :label="__('ui.demo.fields.focus_input')">
                        <input autofocus type="text" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-slate-900 shadow-sm transition-weightless focus:border-aura-400 focus:ring-aura-300" placeholder="{{ __('ui.demo.fields.focus_placeholder') }}">
                    </x-form-field>
                </div>

                <div class="space-y-2 rounded-xl border border-white/80 bg-white/70 p-4">
                    <h3 class="text-sm font-semibold text-slate-900">{{ __('ui.demo.states.disabled') }}</h3>
                    <button type="button" disabled class="rounded-xl border border-slate-200 bg-slate-100 px-4 py-2 text-sm text-slate-500">
                        {{ __('ui.demo.actions.disabled') }}
                    </button>
                </div>

                <div class="space-y-2 rounded-xl border border-white/80 bg-white/70 p-4">
                    <h3 class="text-sm font-semibold text-slate-900">{{ __('ui.demo.states.loading') }}</h3>
                    <button type="button" class="inline-flex items-center gap-2 rounded-xl border border-aura-300/40 bg-white/80 px-4 py-2 text-sm text-slate-900">
                        <span class="size-3 animate-spin rounded-full border-2 border-aura-500 border-t-transparent"></span>
                        {{ __('ui.demo.actions.loading') }}
                    </button>
                </div>

                <div class="space-y-2 rounded-xl border border-white/80 bg-white/70 p-4">
                    <h3 class="text-sm font-semibold text-slate-900">{{ __('ui.demo.states.error') }}</h3>
                    <x-form-field :label="__('ui.demo.fields.error_input')" name="demo_error" :error="__('ui.demo.fields.error_message')">
                        <input type="text" class="w-full rounded-xl border border-danger-300 bg-white/80 px-3 py-2.5 text-slate-900 shadow-sm focus:border-danger-500 focus:ring-danger-300" placeholder="{{ __('ui.demo.fields.error_placeholder') }}">
                    </x-form-field>
                </div>
            </div>
        </x-glass-card>

        <x-glass-card :title="__('ui.demo.select2.title')" :subtitle="__('ui.demo.select2.subtitle')">
            <div class="grid gap-5 md:grid-cols-2">
                <x-form-field :label="__('ui.demo.select2.normal')">
                    <select class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-slate-900" data-placeholder="{{ __('ui.demo.select2.placeholder') }}">
                        <option value="">{{ __('ui.demo.select2.placeholder') }}</option>
                        <option value="1">{{ __('ui.demo.select2.option_1') }}</option>
                        <option value="2">{{ __('ui.demo.select2.option_2') }}</option>
                    </select>
                </x-form-field>

                <x-form-field :label="__('ui.demo.select2.filters')">
                    <select class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-slate-900" multiple data-placeholder="{{ __('ui.demo.select2.filter_placeholder') }}">
                        <option value="open">{{ __('ui.status.sample_filter_open') }}</option>
                        <option value="in_progress">{{ __('ui.status.sample_filter_in_progress') }}</option>
                        <option value="closed">{{ __('ui.status.sample_filter_closed') }}</option>
                    </select>
                </x-form-field>
            </div>

            <div class="mt-4">
                <x-modal id="select2-demo-modal" :title="__('ui.demo.select2.modal_title')">
                    <x-slot:trigger>
                        <button type="button" class="rounded-xl border border-aura-300/40 bg-white/80 px-4 py-2 text-sm text-slate-900 transition-weightless hover:bg-white">
                            {{ __('ui.demo.select2.modal_open') }}
                        </button>
                    </x-slot:trigger>

                    <x-form-field :label="__('ui.demo.select2.modal_label')">
                        <select class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-slate-900" data-placeholder="{{ __('ui.demo.select2.placeholder') }}">
                            <option value="">{{ __('ui.demo.select2.placeholder') }}</option>
                            <option value="a">{{ __('ui.demo.select2.option_1') }}</option>
                            <option value="b">{{ __('ui.demo.select2.option_2') }}</option>
                        </select>
                    </x-form-field>
                </x-modal>
            </div>
        </x-glass-card>

        <x-glass-card :title="__('ui.demo.table.title')">
            <x-table>
                <thead class="bg-white/75">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold">{{ __('ui.demo.table.name') }}</th>
                        <th class="px-4 py-3 text-left font-semibold">{{ __('ui.demo.table.status') }}</th>
                        <th class="px-4 py-3 text-left font-semibold">{{ __('ui.demo.table.note') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/70">
                    <tr>
                        <td class="px-4 py-3">{{ __('ui.demo.table.row_shell') }}</td>
                        <td class="px-4 py-3"><x-badge variant="success">{{ __('ui.demo.table.ready') }}</x-badge></td>
                        <td class="px-4 py-3">{{ __('ui.demo.table.note_ready') }}</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3">{{ __('ui.demo.table.row_select2') }}</td>
                        <td class="px-4 py-3"><x-badge>{{ __('ui.demo.table.active') }}</x-badge></td>
                        <td class="px-4 py-3">{{ __('ui.demo.table.note_active') }}</td>
                    </tr>
                </tbody>
            </x-table>
        </x-glass-card>

        <x-empty-state :title="__('ui.demo.empty.title')" :message="__('ui.demo.empty.message')" />
    </div>
</x-shell-layout>
