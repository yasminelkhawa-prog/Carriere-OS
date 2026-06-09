<x-auth-layout :title="__('platform.awaiting_title').' | '.config('app.name')">
    <x-glass-card :title="__('platform.awaiting_title')" :subtitle="__('platform.awaiting_subtitle')">
        @if(count($pendingCompanies) > 0)
            <ul class="space-y-2">
                @foreach($pendingCompanies as $companyName)
                    <li class="rounded-lg border border-primary-200/60 bg-primary-50/70 px-4 py-2 text-sm text-primary-900">{{ $companyName }}</li>
                @endforeach
            </ul>
        @endif
    </x-glass-card>
</x-auth-layout>
