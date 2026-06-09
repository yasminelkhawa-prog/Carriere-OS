<x-auth-layout :title="__('auth.new_password_title').' | '.config('app.name')">
    <x-glass-card :title="__('auth.new_password_title')">
        <form method="POST" action="{{ route('password.store') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="token" value="{{ $request->route('token') }}">

            <x-form-field :label="__('auth.email')" name="email" required>
                <input type="email" name="email" value="{{ old('email', $request->email) }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-slate-900 shadow-sm focus:border-aura-400 focus:ring-aura-300">
            </x-form-field>

            <x-form-field :label="__('auth.password')" name="password" required>
                <div class="relative">
                    <input id="reset-password" type="password" name="password" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 pr-12 text-slate-900 shadow-sm focus:border-aura-400 focus:ring-aura-300">
                    <button
                        type="button"
                        data-password-toggle
                        data-password-target="reset-password"
                        data-show-label="{{ __('auth.toggle_show') }}"
                        data-hide-label="{{ __('auth.toggle_hide') }}"
                        aria-label="{{ __('auth.toggle_show') }}"
                        class="absolute inset-y-0 right-0 my-1 mr-1 inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 transition-weightless hover:bg-slate-50 hover:text-slate-900"
                    >
                        <svg data-eye-open xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z" />
                            <circle cx="12" cy="12" r="3" />
                        </svg>
                        <svg data-eye-closed xmlns="http://www.w3.org/2000/svg" class="hidden h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M17.94 17.94A10.94 10.94 0 0 1 12 20C5 20 1 12 1 12a21.66 21.66 0 0 1 5.06-6.94" />
                            <path d="M9.9 4.24A10.96 10.96 0 0 1 12 4c7 0 11 8 11 8a21.58 21.58 0 0 1-2.16 3.19" />
                            <path d="M14.12 14.12a3 3 0 1 1-4.24-4.24" />
                            <path d="m1 1 22 22" />
                        </svg>
                        <span class="sr-only" data-password-toggle-label>{{ __('auth.toggle_show') }}</span>
                    </button>
                </div>
            </x-form-field>

            <x-form-field :label="__('auth.password_confirmation')" name="password_confirmation" required>
                <div class="relative">
                    <input id="reset-password-confirmation" type="password" name="password_confirmation" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 pr-12 text-slate-900 shadow-sm focus:border-aura-400 focus:ring-aura-300">
                    <button
                        type="button"
                        data-password-toggle
                        data-password-target="reset-password-confirmation"
                        data-show-label="{{ __('auth.toggle_show') }}"
                        data-hide-label="{{ __('auth.toggle_hide') }}"
                        aria-label="{{ __('auth.toggle_show') }}"
                        class="absolute inset-y-0 right-0 my-1 mr-1 inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 transition-weightless hover:bg-slate-50 hover:text-slate-900"
                    >
                        <svg data-eye-open xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z" />
                            <circle cx="12" cy="12" r="3" />
                        </svg>
                        <svg data-eye-closed xmlns="http://www.w3.org/2000/svg" class="hidden h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M17.94 17.94A10.94 10.94 0 0 1 12 20C5 20 1 12 1 12a21.66 21.66 0 0 1 5.06-6.94" />
                            <path d="M9.9 4.24A10.96 10.96 0 0 1 12 4c7 0 11 8 11 8a21.58 21.58 0 0 1-2.16 3.19" />
                            <path d="M14.12 14.12a3 3 0 1 1-4.24-4.24" />
                            <path d="m1 1 22 22" />
                        </svg>
                        <span class="sr-only" data-password-toggle-label>{{ __('auth.toggle_show') }}</span>
                    </button>
                </div>
            </x-form-field>

            <button type="submit" class="rounded-xl border border-aura-300/50 bg-white/80 px-4 py-2 text-sm font-medium text-slate-900 transition-weightless hover:bg-white">{{ __('auth.reset_password_action') }}</button>
        </form>
    </x-glass-card>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('[data-password-toggle]').forEach(function (button) {
                const targetId = button.getAttribute('data-password-target') || '';
                if (targetId === '') {
                    return;
                }

                const input = document.getElementById(targetId);
                if (!(input instanceof HTMLInputElement)) {
                    return;
                }

                const showLabel = button.getAttribute('data-show-label') || 'Show';
                const hideLabel = button.getAttribute('data-hide-label') || 'Hide';
                const eyeOpen = button.querySelector('[data-eye-open]');
                const eyeClosed = button.querySelector('[data-eye-closed]');
                const srLabel = button.querySelector('[data-password-toggle-label]');

                button.addEventListener('click', function () {
                    const shouldShow = input.type === 'password';
                    input.type = shouldShow ? 'text' : 'password';

                    if (eyeOpen instanceof SVGElement && eyeClosed instanceof SVGElement) {
                        eyeOpen.classList.toggle('hidden', shouldShow);
                        eyeClosed.classList.toggle('hidden', !shouldShow);
                    }

                    const nextLabel = shouldShow ? hideLabel : showLabel;
                    button.setAttribute('aria-label', nextLabel);
                    if (srLabel instanceof HTMLElement) {
                        srLabel.textContent = nextLabel;
                    }
                });
            });
        });
    </script>
</x-auth-layout>
