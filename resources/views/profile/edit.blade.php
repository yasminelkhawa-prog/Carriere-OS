<x-shell-layout :title="__('profile.title').' | '.config('app.name')">
    <x-glass-card :title="__('profile.title')" :subtitle="__('profile.subtitle')">
        @if (session('status'))
            <x-toast-alert type="success">{{ session('status') }}</x-toast-alert>
        @endif

        <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="mt-4 space-y-4">
            @csrf
            @method('PATCH')

            <x-form-field :label="__('profile.full_name')" name="full_name" required>
                <input type="text" name="full_name" value="{{ old('full_name', $user->profile?->full_name) }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-slate-900 shadow-sm focus:border-aura-400 focus:ring-aura-300">
            </x-form-field>

            <x-form-field :label="__('profile.locale')" name="locale" required>
                <select name="locale" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-slate-900 shadow-sm">
                    <option value="en" @selected(old('locale', $user->profile?->locale ?? app()->getLocale()) === 'en')>{{ __('ui.locale.en') }}</option>
                    <option value="fr" @selected(old('locale', $user->profile?->locale ?? app()->getLocale()) === 'fr')>{{ __('ui.locale.fr') }}</option>
                </select>
            </x-form-field>

            <x-form-field :label="__('profile.avatar')" name="avatar">
                <input type="file" name="avatar" accept=".jpg,.jpeg,.png,.webp" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2 text-slate-900 shadow-sm">
            </x-form-field>

            <div class="flex flex-wrap gap-3">
                <button type="submit" class="rounded-xl bg-success-600 px-4 py-2 text-sm font-medium text-white transition-weightless hover:bg-success-700">{{ __('profile.save') }}</button>
            </div>
        </form>

        @if($user->profile?->avatar_url)
            <form method="POST" action="{{ route('profile.avatar.destroy') }}" class="mt-3">
                @csrf
                @method('DELETE')
                <button type="submit" class="rounded-xl border border-danger-300/60 bg-danger-50 px-4 py-2 text-sm font-medium text-danger-800 transition-weightless hover:bg-danger-100/80">{{ __('profile.remove_avatar') }}</button>
            </form>
        @endif

        <form method="POST" action="{{ route('profile.zoom-link.update') }}" class="mt-6 space-y-3">
            @csrf
            @method('PATCH')

            <x-form-field :label="__('profile.zoom_link')" name="zoom_personal_meeting_room_link">
                <input type="url" name="zoom_personal_meeting_room_link" value="{{ old('zoom_personal_meeting_room_link', $zoomLink) }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-slate-900 shadow-sm focus:border-aura-400 focus:ring-aura-300">
            </x-form-field>

            <button type="submit" class="rounded-xl bg-success-600 px-4 py-2 text-sm font-medium text-white transition-weightless hover:bg-success-700">{{ __('profile.save_zoom_link') }}</button>
        </form>

        <form method="POST" action="{{ route('profile.password.update') }}" class="mt-6 space-y-3">
            @csrf
            @method('PATCH')

            <p class="text-sm font-semibold text-slate-900">{{ __('profile.password_title') }}</p>
            <p class="text-xs text-slate-600">{{ __('profile.password_subtitle') }}</p>

            <x-form-field :label="__('profile.current_password')" name="current_password" required>
                <div class="relative">
                    <input id="profile-current-password" type="password" name="current_password" required autocomplete="current-password" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 pr-20 text-slate-900 shadow-sm focus:border-aura-400 focus:ring-aura-300">
                    <button
                        type="button"
                        data-password-toggle
                        data-password-target="profile-current-password"
                        data-show-label="{{ __('profile.toggle_show') }}"
                        data-hide-label="{{ __('profile.toggle_hide') }}"
                        aria-label="{{ __('profile.toggle_show') }}"
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
                        <span class="sr-only" data-password-toggle-label>{{ __('profile.toggle_show') }}</span>
                    </button>
                </div>
            </x-form-field>

            <x-form-field :label="__('profile.new_password')" name="password" required>
                <div class="relative">
                    <input id="profile-new-password" type="password" name="password" required autocomplete="new-password" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 pr-20 text-slate-900 shadow-sm focus:border-aura-400 focus:ring-aura-300">
                    <button
                        type="button"
                        data-password-toggle
                        data-password-target="profile-new-password"
                        data-show-label="{{ __('profile.toggle_show') }}"
                        data-hide-label="{{ __('profile.toggle_hide') }}"
                        aria-label="{{ __('profile.toggle_show') }}"
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
                        <span class="sr-only" data-password-toggle-label>{{ __('profile.toggle_show') }}</span>
                    </button>
                </div>
            </x-form-field>

            <x-form-field :label="__('profile.confirm_password')" name="password_confirmation" required>
                <div class="relative">
                    <input id="profile-confirm-password" type="password" name="password_confirmation" required autocomplete="new-password" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 pr-20 text-slate-900 shadow-sm focus:border-aura-400 focus:ring-aura-300">
                    <button
                        type="button"
                        data-password-toggle
                        data-password-target="profile-confirm-password"
                        data-show-label="{{ __('profile.toggle_show') }}"
                        data-hide-label="{{ __('profile.toggle_hide') }}"
                        aria-label="{{ __('profile.toggle_show') }}"
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
                        <span class="sr-only" data-password-toggle-label>{{ __('profile.toggle_show') }}</span>
                    </button>
                </div>
            </x-form-field>

            <button type="submit" class="rounded-xl bg-success-600 px-4 py-2 text-sm font-medium text-white transition-weightless hover:bg-success-700">{{ __('profile.save_password') }}</button>
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
</x-shell-layout>
