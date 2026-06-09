<x-auth-layout :title="($isAwaitingApproval ? __('platform.awaiting_title') : __('platform.company_access_title')).' | '.config('app.name')">
    <x-glass-card
        :title="$isAwaitingApproval ? __('platform.awaiting_title') : __('platform.company_access_title')"
        :subtitle="$isAwaitingApproval ? __('platform.awaiting_subtitle') : __('platform.company_access_subtitle')"
    >
        @if(! $hasActiveMemberships)
            <x-empty-state :title="__('platform.no_active_companies_title')" :message="__('platform.no_active_companies_message')" />
        @else
            <ul class="space-y-2">
                @foreach($companies as $company)
                    <li class="rounded-lg border px-4 py-3 text-sm"
                        @class([
                            'border-primary-200/60 bg-primary-50/70 text-primary-900' => $company->status === \App\Models\Company::STATUS_PENDING,
                            'border-danger-200/60 bg-danger-50/70 text-danger-900' => $company->status === \App\Models\Company::STATUS_REJECTED,
                            'border-slate-300/60 bg-slate-100/70 text-slate-800' => $company->status === \App\Models\Company::STATUS_SUSPENDED,
                        ])
                    >
                        <p class="font-medium">{{ $company->name }}</p>
                        <p class="mt-1 text-xs">{{ __('platform.company_status_message_'.$company->status) }}</p>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-glass-card>
</x-auth-layout>
