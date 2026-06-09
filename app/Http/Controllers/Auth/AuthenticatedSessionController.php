<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\Audit\SensitiveEventRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function __construct(private readonly SensitiveEventRecorder $sensitiveEvents)
    {
    }

    public function create(Request $request): View|RedirectResponse
    {
        if ($request->user() !== null) {
            return redirect()->route('auth.company.dispatch');
        }

        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt([
            'email' => $credentials['email'],
            'password' => $credentials['password'],
            'active' => true,
        ], remember: (bool) $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => __('auth.invalid_credentials'),
            ]);
        }

        $request->session()->regenerate();
        $this->sensitiveEvents->loginSucceeded(
            userId: (string) $request->user()?->id,
            metadata: ['email' => $credentials['email']],
            actor: $request->user()
        );

        return redirect()->intended(route('auth.company.dispatch'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
