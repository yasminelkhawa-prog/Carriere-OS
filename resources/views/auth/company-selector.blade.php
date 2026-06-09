<x-auth-layout :title="__('platform.company_selector_title').' | '.config('app.name')">
    <x-glass-card :title="__('platform.company_selector_title')" :subtitle="__('platform.company_selector_subtitle')">
        <form method="POST" action="{{ route('company.select.store') }}" class="space-y-4">
            @csrf
            <x-form-field :label="__('platform.company_label')" name="company_id" required>
                <select name="company_id" data-placeholder="{{ __('platform.company_placeholder') }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-slate-900 shadow-sm">
                    <option value="">{{ __('platform.company_placeholder') }}</option>
                    @foreach($memberships as $membership)
                        <option value="{{ $membership->company_id }}">{{ $membership->company->name }}</option>
                    @endforeach
                </select>
            </x-form-field>

            <button type="submit" class="rounded-xl border border-aura-300/50 bg-white/80 px-4 py-2 text-sm font-medium text-slate-900 transition-weightless hover:bg-white">
                {{ __('platform.enter_workspace') }}
            </button>
        </form>
    </x-glass-card>
</x-auth-layout>
