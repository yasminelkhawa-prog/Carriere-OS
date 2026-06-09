<x-shell-layout :title="__('contact_inquiries.detail_title').' | '.config('app.name')">
    <section class="space-y-4">
        @if(session('status'))
            <x-toast-alert type="success">{{ session('status') }}</x-toast-alert>
        @endif
        @if($errors->any())
            <x-toast-alert type="error">{{ $errors->first() }}</x-toast-alert>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="panel-title text-3xl font-semibold tracking-tight text-slate-900">{{ __('contact_inquiries.detail_title') }}</h1>
                <p class="mt-1 text-sm text-slate-600">{{ __('contact_inquiries.detail_subtitle') }}</p>
            </div>
            <a href="{{ route('superadmin.contact-inquiries.index') }}" class="rounded-xl border border-aura-300/50 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition-weightless hover:bg-slate-50">
                {{ __('contact_inquiries.back_to_list') }}
            </a>
        </div>

        <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_24rem]">
            <x-glass-card class="space-y-4 p-5">
                <div class="grid gap-3 md:grid-cols-2">
                    <div>
                        <p class="text-xs uppercase tracking-wider text-slate-500">{{ __('contact_inquiries.fields.name') }}</p>
                        <p class="mt-1 text-sm font-semibold text-slate-900">{{ $inquiry->full_name }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wider text-slate-500">{{ __('contact_inquiries.fields.email') }}</p>
                        <p class="mt-1 text-sm font-semibold text-slate-900">{{ $inquiry->email }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wider text-slate-500">{{ __('contact_inquiries.fields.phone') }}</p>
                        <p class="mt-1 text-sm font-semibold text-slate-900">{{ $inquiry->phone ?: __('contact_inquiries.not_available') }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wider text-slate-500">{{ __('contact_inquiries.fields.source') }}</p>
                        <p class="mt-1 text-sm font-semibold text-slate-900">{{ __('contact_inquiries.sources.'.$inquiry->source) }}</p>
                    </div>
                </div>

                <div>
                    <p class="text-xs uppercase tracking-wider text-slate-500">{{ __('contact_inquiries.fields.subject') }}</p>
                    <p class="mt-1 text-base font-semibold text-slate-900">{{ $inquiry->subject }}</p>
                </div>

                <div>
                    <p class="text-xs uppercase tracking-wider text-slate-500">{{ __('contact_inquiries.fields.message') }}</p>
                    <p class="mt-1 whitespace-pre-line rounded-2xl border border-slate-200 bg-white/70 p-4 text-sm leading-relaxed text-slate-700">{{ $inquiry->message }}</p>
                </div>

                <div class="grid gap-3 md:grid-cols-2">
                    <div>
                        <p class="text-xs uppercase tracking-wider text-slate-500">{{ __('contact_inquiries.fields.created_at') }}</p>
                        <p class="mt-1 text-sm text-slate-900">{{ $inquiry->created_at?->format('Y-m-d H:i:s') }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wider text-slate-500">{{ __('contact_inquiries.fields.updated_at') }}</p>
                        <p class="mt-1 text-sm text-slate-900">{{ $inquiry->updated_at?->format('Y-m-d H:i:s') }}</p>
                    </div>
                </div>
            </x-glass-card>

            <x-glass-card class="p-5">
                <h2 class="text-lg font-semibold text-slate-900">{{ __('contact_inquiries.manage_title') }}</h2>
                <form method="POST" action="{{ route('superadmin.contact-inquiries.update', ['contactInquiry' => $inquiry->id]) }}" class="mt-4 space-y-3">
                    @csrf
                    @method('PATCH')

                    <x-form-field :label="__('contact_inquiries.fields.status')" name="status" required>
                        <select name="status" required data-placeholder="{{ __('contact_inquiries.filters.status_placeholder') }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2 text-sm">
                            @foreach($statuses as $statusOption)
                                <option value="{{ $statusOption }}" @selected((string) old('status', $inquiry->status) === (string) $statusOption)>
                                    {{ __('contact_inquiries.statuses.'.$statusOption) }}
                                </option>
                            @endforeach
                        </select>
                    </x-form-field>

                    <x-form-field :label="__('contact_inquiries.fields.assigned_to')" name="assigned_to_user_id">
                        <select name="assigned_to_user_id" data-placeholder="{{ __('contact_inquiries.unassigned') }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2 text-sm">
                            <option value="">{{ __('contact_inquiries.unassigned') }}</option>
                            @foreach($superadmins as $superadmin)
                                <option value="{{ $superadmin->id }}" @selected((string) old('assigned_to_user_id', (string) ($inquiry->assigned_to_user_id ?? '')) === (string) $superadmin->id)>
                                    {{ $superadmin->profile?->full_name ?? $superadmin->email }}
                                </option>
                            @endforeach
                        </select>
                    </x-form-field>

                    <x-form-field :label="__('contact_inquiries.fields.notes')" name="notes">
                        <textarea name="notes" rows="6" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2 text-sm">{{ old('notes', $inquiry->notes) }}</textarea>
                    </x-form-field>

                    <button type="submit" class="rounded-xl bg-success-600 px-4 py-2.5 text-sm font-semibold text-white transition-weightless hover:bg-success-700">
                        {{ __('contact_inquiries.actions.save') }}
                    </button>
                </form>
            </x-glass-card>
        </div>
    </section>
</x-shell-layout>
