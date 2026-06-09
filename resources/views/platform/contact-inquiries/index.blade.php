<x-shell-layout :title="__('contact_inquiries.title').' | '.config('app.name')">
    <section class="space-y-4">
        @if(session('status'))
            <x-toast-alert type="success">{{ session('status') }}</x-toast-alert>
        @endif
        @if(session('error'))
            <x-toast-alert type="warning">{{ session('error') }}</x-toast-alert>
        @endif

        <div>
            <h1 class="panel-title text-3xl font-semibold tracking-tight text-slate-900">{{ __('contact_inquiries.heading') }}</h1>
            <p class="mt-1 text-sm text-slate-600">{{ __('contact_inquiries.subheading') }}</p>
        </div>

        <x-glass-card class="p-4">
            <form method="GET" action="{{ route('superadmin.contact-inquiries.index') }}" class="grid gap-3 md:grid-cols-4">
                <x-form-field :label="__('contact_inquiries.filters.status')" name="status">
                    <select name="status" data-placeholder="{{ __('contact_inquiries.filters.status_placeholder') }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2 text-sm">
                        <option value="">{{ __('contact_inquiries.filters.status_placeholder') }}</option>
                        @foreach($statuses as $statusOption)
                            <option value="{{ $statusOption }}" @selected((string) ($filters['status'] ?? '') === (string) $statusOption)>
                                {{ __('contact_inquiries.statuses.'.$statusOption) }}
                            </option>
                        @endforeach
                    </select>
                </x-form-field>

                <x-form-field :label="__('contact_inquiries.filters.search')" name="q">
                    <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2 text-sm">
                </x-form-field>

                <div class="flex items-end gap-2 md:col-span-2">
                    <button type="submit" class="rounded-xl bg-success-600 px-4 py-2.5 text-sm font-semibold text-white transition-weightless hover:bg-success-700">
                        {{ __('contact_inquiries.filters.apply') }}
                    </button>
                    <a href="{{ route('superadmin.contact-inquiries.index') }}" class="rounded-xl border border-aura-300/50 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition-weightless hover:bg-slate-50">
                        {{ __('contact_inquiries.filters.reset') }}
                    </a>
                </div>
            </form>
        </x-glass-card>

        <x-glass-card class="p-0">
            <x-table>
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-5 py-3 text-left text-xs uppercase tracking-wider text-slate-500">{{ __('contact_inquiries.table.name') }}</th>
                        <th class="px-5 py-3 text-left text-xs uppercase tracking-wider text-slate-500">{{ __('contact_inquiries.table.email') }}</th>
                        <th class="px-5 py-3 text-left text-xs uppercase tracking-wider text-slate-500">{{ __('contact_inquiries.table.subject') }}</th>
                        <th class="px-5 py-3 text-left text-xs uppercase tracking-wider text-slate-500">{{ __('contact_inquiries.table.status') }}</th>
                        <th class="px-5 py-3 text-left text-xs uppercase tracking-wider text-slate-500">{{ __('contact_inquiries.table.assigned') }}</th>
                        <th class="px-5 py-3 text-left text-xs uppercase tracking-wider text-slate-500">{{ __('contact_inquiries.table.created') }}</th>
                        <th class="px-5 py-3 text-left text-xs uppercase tracking-wider text-slate-500">{{ __('contact_inquiries.table.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse($inquiries as $inquiry)
                        @php
                            $status = (string) $inquiry->status;
                            $statusClass = match ($status) {
                                'new' => 'bg-primary-100 text-primary-900 border-primary-200',
                                'in_progress' => 'bg-primary-50 text-primary-800 border-primary-200',
                                'resolved' => 'bg-success-100 text-success-800 border-success-200',
                                default => 'bg-slate-100 text-slate-700 border-slate-200',
                            };
                        @endphp
                        <tr @class([$status === 'new' ? 'bg-primary-50/45' : ''])>
                            <td class="px-5 py-3 text-sm font-semibold text-slate-900">{{ $inquiry->full_name }}</td>
                            <td class="px-5 py-3 text-sm text-slate-700">{{ $inquiry->email }}</td>
                            <td class="px-5 py-3 text-sm text-slate-700">{{ \Illuminate\Support\Str::limit($inquiry->subject, 90) }}</td>
                            <td class="px-5 py-3 text-sm">
                                <span class="rounded-full border px-2.5 py-1 text-xs font-semibold {{ $statusClass }}">
                                    {{ __('contact_inquiries.statuses.'.$status) }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-sm text-slate-700">
                                {{ $inquiry->assignedTo?->profile?->full_name ?? $inquiry->assignedTo?->email ?? __('contact_inquiries.unassigned') }}
                            </td>
                            <td class="px-5 py-3 text-sm text-slate-700">{{ $inquiry->created_at?->format('Y-m-d H:i') }}</td>
                            <td class="px-5 py-3 text-sm">
                                <a href="{{ route('superadmin.contact-inquiries.show', ['contactInquiry' => $inquiry->id]) }}" class="rounded-lg border border-aura-300/50 bg-white px-3 py-1.5 text-xs font-semibold text-aura-900 transition-weightless hover:bg-aura-50">
                                    {{ __('contact_inquiries.table.open') }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-8">
                                <x-empty-state :title="__('contact_inquiries.empty_title')" :message="__('contact_inquiries.empty_message')" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </x-table>
        </x-glass-card>

        @if($inquiries->hasPages())
            <div>
                {{ $inquiries->links() }}
            </div>
        @endif
    </section>
</x-shell-layout>
