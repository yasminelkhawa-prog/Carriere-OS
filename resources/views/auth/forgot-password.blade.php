<x-auth-layout :title="__('auth.reset_password_title').' | '.config('app.name')">
    <x-glass-card :title="__('auth.reset_password_title')" :subtitle="__('auth.reset_password_subtitle')">
        @if (session('status'))
            <x-toast-alert type="success">{{ session('status') }}</x-toast-alert>
        @endif

        <form method="POST" action="{{ route('password.email') }}" class="mt-4 space-y-4">
            @csrf
            <x-form-field :label="__('auth.email')" name="email" required>
                <input type="email" name="email" value="{{ old('email') }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-slate-900 shadow-sm focus:border-aura-400 focus:ring-aura-300">
            </x-form-field>
            <button type="submit" class="rounded-xl border border-aura-300/50 bg-white/80 px-4 py-2 text-sm font-medium text-slate-900 transition-weightless hover:bg-white">{{ __('auth.send_reset_link') }}</button>
        </form>
    </x-glass-card>
</x-auth-layout>
