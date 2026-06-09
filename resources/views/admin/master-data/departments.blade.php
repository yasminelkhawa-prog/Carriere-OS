<x-shell-layout :title="__('master.departments.title').' | '.config('app.name')">
    <x-glass-card :title="__('master.departments.title')" :subtitle="__('master.departments.subtitle')">
        @if (session('status'))
            <x-toast-alert type="success">{{ session('status') }}</x-toast-alert>
        @endif

        <form method="GET" action="{{ route('admin.departments.index') }}" class="mt-4">
            @if(auth()->user()?->isSuperadmin())
                <x-form-field :label="__('master.company_filter.label')" name="company_id">
                    <select name="company_id" data-placeholder="{{ __('master.company_filter.placeholder') }}" class="w-full max-w-md rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-slate-900 shadow-sm">
                        <option value="">{{ __('master.company_filter.placeholder') }}</option>
                        @foreach($companies as $company)
                            <option value="{{ $company->id }}" @selected((string) $selectedCompanyId === (string) $company->id)>{{ $company->name }}</option>
                        @endforeach
                    </select>
                </x-form-field>
            @endif
        </form>

        @if($requiresCompanySelection)
            <div class="mt-4">
                <x-empty-state :title="__('master.company_filter.label')" :message="__('master.common.company_scope_required')" />
            </div>
        @else
            <form method="POST" action="{{ route('admin.departments.store') }}" class="mt-5 grid gap-3 md:grid-cols-[1fr_auto]">
                @csrf
                @if(auth()->user()?->isSuperadmin())
                    <input type="hidden" name="company_id" value="{{ $selectedCompanyId }}">
                @endif
                <x-form-field :label="__('master.departments.name')" name="name">
                    <input type="text" name="name" value="{{ old('name') }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-slate-900 shadow-sm focus:border-aura-400 focus:ring-aura-300">
                </x-form-field>
                <div class="flex items-end">
                    <button type="submit" class="rounded-xl bg-success-600 px-4 py-2 text-sm font-medium text-white transition-weightless hover:bg-success-700">
                        {{ __('master.departments.create_action') }}
                    </button>
                </div>
            </form>

            <div class="mt-6 space-y-3">
                @forelse($departments as $department)
                    <div class="rounded-xl border border-white/80 bg-white/70 p-4">
                        <form method="POST" action="{{ route('admin.departments.update', $department) }}" class="grid gap-3 md:grid-cols-[1fr_auto_auto]">
                            @csrf
                            @method('PATCH')
                            @if(auth()->user()?->isSuperadmin())
                                <input type="hidden" name="company_id" value="{{ $selectedCompanyId }}">
                            @endif
                            <input type="text" name="name" value="{{ old('name', $department->name) }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-aura-400 focus:ring-aura-300">
                            <button type="submit" class="rounded-xl bg-success-600 px-3 py-2 text-sm text-white transition-weightless hover:bg-success-700">{{ __('master.departments.update_action') }}</button>
                        </form>
                        <form method="POST" action="{{ route('admin.departments.destroy', $department) }}" class="mt-2">
                            @csrf
                            @method('DELETE')
                            @if(auth()->user()?->isSuperadmin())
                                <input type="hidden" name="company_id" value="{{ $selectedCompanyId }}">
                            @endif
                            <button type="submit" class="rounded-xl border border-danger-300/60 bg-danger-50 px-3 py-2 text-xs text-danger-800 transition-weightless hover:bg-danger-100/80">{{ __('master.departments.delete_action') }}</button>
                        </form>
                    </div>
                @empty
                    <x-empty-state :title="__('master.departments.empty_title')" :message="__('master.departments.empty_message')" />
                @endforelse
            </div>
        @endif
    </x-glass-card>
</x-shell-layout>
