<x-shell-layout :title="__('platform.company_detail_title').' | '.config('app.name')">
    <x-glass-card :title="__('platform.company_detail_title')" :subtitle="$company->name">
        @if (session('status'))
            <x-toast-alert type="success">{{ session('status') }}</x-toast-alert>
        @endif
        @if (session('warning'))
            <x-toast-alert type="warning">{{ session('warning') }}</x-toast-alert>
        @endif

        <div class="mt-4 grid gap-4 md:grid-cols-2">
            <div class="rounded-xl border border-white/70 bg-white/70 p-4">
                <p class="text-xs uppercase tracking-wider text-aura-700/80">{{ __('platform.company_name') }}</p>
                <p class="mt-1 text-sm font-semibold text-slate-900">{{ $company->name }}</p>
            </div>
            <div class="rounded-xl border border-white/70 bg-white/70 p-4">
                <p class="text-xs uppercase tracking-wider text-aura-700/80">{{ __('platform.company_slug') }}</p>
                <p class="mt-1 text-sm font-semibold text-slate-900">{{ $company->slug }}</p>
            </div>
            <div class="rounded-xl border border-white/70 bg-white/70 p-4">
                <p class="text-xs uppercase tracking-wider text-aura-700/80">{{ __('platform.request_status') }}</p>
                <p class="mt-1 text-sm font-semibold text-slate-900">{{ __('platform.request_status_'.$registrationRequest->status) }}</p>
            </div>
            <div class="rounded-xl border border-white/70 bg-white/70 p-4">
                <p class="text-xs uppercase tracking-wider text-aura-700/80">{{ __('platform.requested_by') }}</p>
                <p class="mt-1 text-sm font-semibold text-slate-900">{{ $registrationRequest->requestedBy?->profile?->full_name ?? $registrationRequest->requestedBy?->email }}</p>
            </div>
        </div>

        <div class="mt-6 rounded-xl border border-white/70 bg-white/70 p-4">
            <p class="text-xs uppercase tracking-wider text-aura-700/80">{{ __('platform.request_payload') }}</p>
            <pre class="mt-2 overflow-x-auto rounded-lg bg-slate-100/80 p-3 text-xs text-slate-800">{{ json_encode($registrationRequest->request_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
        </div>

        @if($registrationRequest->status === \App\Models\CompanyRegistrationRequest::STATUS_PENDING)
            <div class="mt-6 grid gap-4 lg:grid-cols-2">
                <form method="POST" action="{{ route('platform.company-approvals.approve', $company) }}" class="rounded-xl border border-success-200/50 bg-success-50/50 p-4">
                    @csrf
                    <p class="text-sm text-slate-700">{{ __('platform.approve_help') }}</p>
                    <button type="submit" class="mt-3 rounded-xl bg-success-600 px-4 py-2 text-sm font-medium text-white transition-weightless hover:bg-success-700">
                        {{ __('platform.approve_company') }}
                    </button>
                </form>

                <form method="POST" action="{{ route('platform.company-approvals.reject', $company) }}" class="rounded-xl border border-danger-200/50 bg-danger-50/50 p-4 space-y-3">
                    @csrf
                    <x-form-field :label="__('platform.rejection_reason')" name="rejection_reason" required>
                        <textarea name="rejection_reason" rows="4" class="w-full rounded-xl border border-danger-200 bg-white/90 px-3 py-2.5 text-slate-900 shadow-sm focus:border-danger-400 focus:ring-danger-300">{{ old('rejection_reason') }}</textarea>
                    </x-form-field>
                    <button type="submit" class="rounded-xl border border-danger-300/60 bg-white px-4 py-2 text-sm font-medium text-danger-800 transition-weightless hover:bg-danger-50">
                        {{ __('platform.reject_company') }}
                    </button>
                </form>
            </div>
        @endif
    </x-glass-card>
</x-shell-layout>
