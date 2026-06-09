<x-shell-layout :title="__('master.values.title').' | '.config('app.name')">
    <x-glass-card :title="__('master.values.title')" :subtitle="__('master.values.subtitle')">
        @if (session('status'))
            <x-toast-alert type="success">{{ session('status') }}</x-toast-alert>
        @endif

        <form method="GET" action="{{ route('admin.values.index') }}" class="mt-4">
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
            <form method="POST" action="{{ route('admin.values.store') }}" class="mt-5 grid gap-3 lg:grid-cols-4">
                @csrf
                @if(auth()->user()?->isSuperadmin())
                    <input type="hidden" name="company_id" value="{{ $selectedCompanyId }}">
                @endif
                <x-form-field :label="__('master.values.value_title')" name="title">
                    <input type="text" name="title" value="{{ old('title') }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-slate-900 shadow-sm focus:border-aura-400 focus:ring-aura-300">
                </x-form-field>
                <x-form-field :label="__('master.values.icon_name')" name="icon_name">
                    <input type="text" name="icon_name" value="{{ old('icon_name') }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-slate-900 shadow-sm focus:border-aura-400 focus:ring-aura-300">
                </x-form-field>
                <x-form-field :label="__('master.values.display_order')" name="display_order">
                    <input type="number" min="1" name="display_order" value="{{ old('display_order', 1) }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-slate-900 shadow-sm focus:border-aura-400 focus:ring-aura-300">
                </x-form-field>
                <div class="flex items-end">
                    <button type="submit" class="rounded-xl bg-success-600 px-4 py-2 text-sm font-medium text-white transition-weightless hover:bg-success-700">
                        {{ __('master.values.create_action') }}
                    </button>
                </div>
                <div class="lg:col-span-4">
                    <x-form-field :label="__('master.values.description')" name="description">
                        <textarea name="description" rows="3" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-slate-900 shadow-sm focus:border-aura-400 focus:ring-aura-300">{{ old('description') }}</textarea>
                    </x-form-field>
                </div>
            </form>

            <div class="mt-6">
                <h3 class="text-sm font-semibold text-slate-900">{{ __('master.values.preview_title') }}</h3>
                <div class="mt-3 grid gap-3 md:grid-cols-2">
                    @forelse($values as $value)
                        <div class="rounded-2xl border border-white/70 bg-white/60 p-4 shadow-aura backdrop-blur-2xl">
                            <p class="text-xs uppercase tracking-wider text-aura-700/80">{{ $value->icon_name ?: __('master.values.icon_fallback') }}</p>
                            <p class="mt-2 text-lg font-semibold text-slate-900">{{ $value->title }}</p>
                            <p class="mt-2 text-sm text-slate-700">{{ $value->description }}</p>
                            <p class="mt-3 text-xs text-slate-500">#{{ $value->display_order }}</p>
                        </div>
                    @empty
                        <x-empty-state :title="__('master.values.empty_title')" :message="__('master.values.empty_message')" />
                    @endforelse
                </div>
            </div>

            <div class="mt-6 space-y-3">
                @foreach($values as $value)
                    <div class="rounded-xl border border-white/80 bg-white/70 p-4">
                        <form method="POST" action="{{ route('admin.values.update', $value) }}" class="grid gap-3 lg:grid-cols-4">
                            @csrf
                            @method('PATCH')
                            @if(auth()->user()?->isSuperadmin())
                                <input type="hidden" name="company_id" value="{{ $selectedCompanyId }}">
                            @endif
                            <input type="text" name="title" value="{{ old('title', $value->title) }}" class="rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2 text-sm text-slate-900 shadow-sm">
                            <input type="text" name="icon_name" value="{{ old('icon_name', $value->icon_name) }}" class="rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2 text-sm text-slate-900 shadow-sm">
                            <input type="number" min="1" name="display_order" value="{{ old('display_order', $value->display_order) }}" class="rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2 text-sm text-slate-900 shadow-sm">
                            <button type="submit" class="rounded-xl bg-success-600 px-3 py-2 text-sm text-white transition-weightless hover:bg-success-700">{{ __('master.values.update_action') }}</button>
                            <textarea name="description" rows="2" class="lg:col-span-4 rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2 text-sm text-slate-900 shadow-sm">{{ old('description', $value->description) }}</textarea>
                        </form>
                        <form method="POST" action="{{ route('admin.values.destroy', $value) }}" class="mt-2">
                            @csrf
                            @method('DELETE')
                            @if(auth()->user()?->isSuperadmin())
                                <input type="hidden" name="company_id" value="{{ $selectedCompanyId }}">
                            @endif
                            <button type="submit" class="rounded-xl border border-danger-300/60 bg-danger-50 px-3 py-2 text-xs text-danger-800 transition-weightless hover:bg-danger-100/80">{{ __('master.values.delete_action') }}</button>
                        </form>
                    </div>
                @endforeach
            </div>
        @endif
    </x-glass-card>
</x-shell-layout>
