<x-shell-layout :title="__('admin.users.title').' | '.config('app.name')">
    <x-glass-card :title="__('admin.users.title')" :subtitle="__('admin.users.subtitle')">
        @if (session('status'))
            <x-toast-alert type="success">{{ session('status') }}</x-toast-alert>
        @endif
        @if (session('warning'))
            <x-toast-alert type="warning">{{ session('warning') }}</x-toast-alert>
        @endif
        @if ($errors->any())
            <x-toast-alert type="warning">{{ $errors->first() }}</x-toast-alert>
        @endif

        @can('create', App\Models\User::class)
        <form method="POST" action="{{ route('admin.users.store') }}" class="mt-4 rounded-xl border border-aura-200/40 bg-aura-50/40 p-5">
            @csrf
            <p class="mb-4 text-sm font-semibold text-slate-800">{{ __('admin.users.add_user_title') }}</p>
            <div class="grid gap-3 md:grid-cols-3 md:items-end">
                <x-form-field :label="__('admin.users.email')" name="email">
                    <input type="email" name="email" value="{{ old('email') }}" required
                        class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-slate-900 shadow-sm focus:border-aura-400 focus:ring-aura-300"
                        placeholder="user@example.com">
                </x-form-field>
                <x-form-field :label="__('admin.users.name')" name="full_name">
                    <input type="text" name="full_name" value="{{ old('full_name') }}"
                        class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-slate-900 shadow-sm focus:border-aura-400 focus:ring-aura-300"
                        placeholder="{{ __('admin.users.name') }}">
                </x-form-field>
                <x-form-field :label="__('admin.users.role')" name="role">
                    <select name="role" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-slate-900 shadow-sm">
                        @foreach($roles as $role)
                            <option value="{{ $role }}" @selected(old('role') === $role)>{{ __('admin.roles.'.$role) }}</option>
                        @endforeach
                    </select>
                </x-form-field>
            </div>
            <div class="mt-4 flex justify-center">
                <button type="submit" class="inline-flex size-11 items-center justify-center rounded-2xl border border-success-300/60 bg-success-50 text-success-800 shadow-sm transition-weightless hover:bg-success-100/80" title="{{ __('admin.users.add_user_action') }}" aria-label="{{ __('admin.users.add_user_action') }}">
                    <svg class="size-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    <span class="sr-only">{{ __('admin.users.add_user_action') }}</span>
                </button>
            </div>
            @error('email')
                <p class="mt-2 text-xs text-danger-700">{{ $message }}</p>
            @enderror
        </form>
        @endcan

        <div class="mt-5">
            <x-table>
                <thead class="bg-white/75">
                    <tr>
                        <th class="px-4 py-3 text-left text-sm font-semibold text-slate-800">{{ __('admin.users.name') }}</th>
                        <th class="px-4 py-3 text-left text-sm font-semibold text-slate-800">{{ __('admin.users.email') }}</th>
                        <th class="px-4 py-3 text-left text-sm font-semibold text-slate-800">{{ __('admin.users.current_role') }}</th>
                        <th class="px-4 py-3 text-left text-sm font-semibold text-slate-800">{{ __('admin.users.role') }}</th>
                        <th class="px-4 py-3 text-left text-sm font-semibold text-slate-800">{{ __('admin.users.confirm') }}</th>
                        <th class="px-4 py-3 text-left text-sm font-semibold text-slate-800">{{ __('admin.users.joined_at') }}</th>
                        <th class="px-4 py-3 text-left text-sm font-semibold text-slate-800">{{ __('admin.users.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/70">
                    @forelse($memberships as $membership)
                        @php($managedUser = $membership->user)
                        @php($formId = 'update-role-'.$managedUser->id)
                        <tr class="transition-colors hover:bg-aura-50/30">
                            <td class="px-4 py-3 text-sm font-semibold text-slate-900">
                                <input type="text"
                                       form="{{ $formId }}"
                                       name="full_name"
                                       value="{{ $managedUser->profile?->full_name }}"
                                       class="w-full min-w-[12rem] rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2 text-sm font-medium text-slate-900 shadow-sm">
                                @if(($managedUser->profile?->full_name ?? '') === '')
                                    <p class="mt-1 text-xs font-normal text-slate-500">{{ __('admin.users.name_fallback') }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-slate-700">{{ $managedUser->email }}</td>
                            <td class="px-4 py-3">
                                <x-badge>{{ __('admin.roles.'.$membership->company_role) }}</x-badge>
                            </td>
                            <td class="px-4 py-3">
                                <select name="role" form="{{ $formId }}" data-placeholder="{{ __('admin.users.role_placeholder') }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2 text-sm text-slate-900 shadow-sm">
                                    @foreach($roles as $role)
                                        <option value="{{ $role }}" @selected($membership->company_role === $role)>{{ __('admin.roles.'.$role) }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="px-4 py-3">
                                <label class="inline-flex items-center gap-2 text-xs text-slate-700">
                                    <input type="checkbox" form="{{ $formId }}" name="confirm_role_change" value="1" class="rounded border-aura-300 text-aura-600 focus:ring-aura-400">
                                    <span class="sr-only">{{ __('admin.users.confirm_role_change') }}</span>
                                </label>
                                @if($errors->has('confirm_role_change'))
                                    <p class="mt-1 text-xs text-danger-700">{{ $errors->first('confirm_role_change') }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-600">{{ $membership->created_at?->diffForHumans() }}</td>
                            <td class="px-4 py-3">
                                <form id="{{ $formId }}" method="POST" action="{{ route('admin.users.update-role', $managedUser) }}" class="inline">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="inline-flex size-9 items-center justify-center rounded-lg border border-success-300/60 bg-success-50 text-success-800 transition-weightless hover:bg-success-100/80" title="{{ __('admin.users.update_role_action') }}" aria-label="{{ __('admin.users.update_role_action') }}">
                                        <svg class="size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                        </svg>
                                        <span class="sr-only">{{ __('admin.users.update_role_action') }}</span>
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('admin.users.destroy', $managedUser) }}" class="inline ml-2" onsubmit="return confirm(@js(__('admin.users.delete_confirm')))">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="inline-flex size-9 items-center justify-center rounded-lg border border-danger-300/60 bg-white/80 text-danger-700 transition-weightless hover:bg-danger-50" title="{{ __('admin.users.delete_action') }}" aria-label="{{ __('admin.users.delete_action') }}">
                                        <svg class="size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0V4.875c0-1.243-.97-2.25-2.166-2.25h-3.168c-1.196 0-2.166 1.007-2.166 2.25V5.25m7.5 0h-7.5" />
                                        </svg>
                                        <span class="sr-only">{{ __('admin.users.delete_action') }}</span>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-sm text-slate-600">{{ __('admin.users.empty') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </x-table>
        </div>

        <div class="mt-6">{{ $memberships->links() }}</div>
    </x-glass-card>
</x-shell-layout>
