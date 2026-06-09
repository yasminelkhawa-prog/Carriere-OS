<x-shell-layout :title="__('ui.nav.company_approvals').' | '.config('app.name')">
    <x-glass-card :title="__('ui.nav.company_approvals')" :subtitle="__('platform.approvals_subtitle')">
        @if (session('status'))
            <x-toast-alert type="success">{{ session('status') }}</x-toast-alert>
        @endif
        @if (session('warning'))
            <x-toast-alert type="warning">{{ session('warning') }}</x-toast-alert>
        @endif

        <form method="GET" action="{{ route('platform.company-approvals') }}" class="mt-4 grid gap-3 md:grid-cols-[18rem_auto] md:items-end">
            <x-form-field :label="__('platform.filter_status')" name="status">
                <select name="status" data-placeholder="{{ __('platform.filter_status') }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-slate-900 shadow-sm">
                    <option value="all" @selected($selectedStatus === 'all')>{{ __('platform.request_status_all') }}</option>
                    <option value="pending" @selected($selectedStatus === 'pending')>{{ __('platform.request_status_pending') }}</option>
                    <option value="approved" @selected($selectedStatus === 'approved')>{{ __('platform.request_status_approved') }}</option>
                    <option value="rejected" @selected($selectedStatus === 'rejected')>{{ __('platform.request_status_rejected') }}</option>
                </select>
            </x-form-field>

            <div>
                <button type="submit" class="rounded-xl border border-aura-300/50 bg-white px-4 py-2 text-sm font-medium text-slate-900 transition-weightless hover:bg-slate-50">
                    {{ __('platform.apply_filters') }}
                </button>
            </div>
        </form>

        <div class="mt-4 space-y-3">
            @forelse($requests as $registrationRequest)
                @php($company = $registrationRequest->company)
                <div class="flex items-center justify-between rounded-xl border border-slate-200 bg-white p-4">
                    <div>
                        <p class="font-semibold text-slate-900">{{ $company?->name }}</p>
                        <p class="text-xs text-slate-600">{{ $registrationRequest->created_at?->toDateTimeString() }}</p>
                        <p class="mt-1 text-xs text-slate-600">{{ __('platform.company_slug') }}: {{ $company?->slug }}</p>
                    </div>
                    @if($company)
                        <a href="{{ route('platform.company-approvals.show', $company) }}" class="rounded-xl border border-aura-300/50 bg-white px-4 py-2 text-sm font-medium text-slate-900 transition-weightless hover:bg-slate-50">
                            {{ __('platform.view_company_detail') }}
                        </a>
                    @endif
                </div>
            @empty
                <x-empty-state :title="__('platform.no_requests_title')" :message="__('platform.no_requests_message')" />
            @endforelse
        </div>

        <div class="mt-6">{{ $requests->links() }}</div>
    </x-glass-card>
</x-shell-layout>
