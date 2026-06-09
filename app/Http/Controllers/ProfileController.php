<?php

namespace App\Http\Controllers;

use App\Models\UserSecureSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        $companyId = (string) session('active_company_id', '');
        $zoomLink = UserSecureSetting::withoutGlobalScopes()
            ->where('user_id', $request->user()->id)
            ->where('company_id', $companyId !== '' ? $companyId : null)
            ->where('setting_key', UserSecureSetting::KEY_ZOOM_PMR_LINK)
            ->value('setting_value');

        return view('profile.edit', [
            'user' => $request->user()->load('profile'),
            'zoomLink' => $zoomLink,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'locale' => ['required', Rule::in(['en', 'fr'])],
            'avatar' => ['nullable', 'image', 'max:2048', 'mimes:jpg,jpeg,png,webp'],
        ]);

        $profile = $request->user()->profile;

        if ($profile === null) {
            $profile = $request->user()->profile()->make();
            $profile->user_id = $request->user()->id;
        }

        if ($request->hasFile('avatar')) {
            $avatarPath = $request->file('avatar')->store('avatars', 'local');
            $profile->avatar_url = $avatarPath;
        }

        $profile->full_name = $validated['full_name'];
        $profile->locale = $validated['locale'];
        $profile->save();

        session(['locale' => $validated['locale']]);

        return back()->with('status', __('profile.updated'));
    }

    public function destroyAvatar(Request $request): RedirectResponse
    {
        $profile = $request->user()->profile;

        if ($profile !== null && $profile->avatar_url) {
            Storage::disk('local')->delete($profile->avatar_url);
            $profile->avatar_url = null;
            $profile->save();
        }

        return back()->with('status', __('profile.avatar_removed'));
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'current_password.required' => __('profile.errors.current_password_required'),
            'password.required' => __('profile.errors.password_required'),
            'password.min' => __('profile.errors.password_min'),
            'password.confirmed' => __('profile.errors.password_confirmed'),
        ]);

        $user = $request->user();
        if (! Hash::check((string) $validated['current_password'], (string) $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => __('profile.errors.current_password_invalid'),
            ]);
        }

        $user->forceFill([
            'password' => Hash::make((string) $validated['password']),
        ])->save();

        return back()->with('status', __('profile.password_updated'));
    }
}
