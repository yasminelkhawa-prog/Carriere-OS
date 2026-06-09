<x-auth-layout :title="__('auth.verify_email_title').' | '.config('app.name')">
    <x-glass-card :title="__('auth.verify_email_title')" :subtitle="__('auth.verify_email_subtitle')">
        @if (session('status'))
            <x-toast-alert type="success">{{ session('status') }}</x-toast-alert>
        @endif

        <div class="mt-4 flex flex-wrap gap-3">
            <form method="POST" action="{{ route('verification.send') }}">
                @csrf
                <button type="submit" class="rounded-xl border border-aura-300/50 bg-white/80 px-4 py-2 text-sm font-medium text-slate-900 transition-weightless hover:bg-white">{{ __('auth.resend_verification') }}</button>
            </form>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="rounded-xl border border-aura-300/50 bg-white/80 px-4 py-2 text-sm font-medium text-slate-900 transition-weightless hover:bg-white">{{ __('auth.logout') }}</button>
            </form>
        </div>
    </x-glass-card>
</x-auth-layout>
