<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRequiredEnvironmentIsConfigured
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->runningUnitTests() || app()->environment('testing')) {
            return $next($request);
        }

        if ($this->isAllowedSetupRoute($request)) {
            return $next($request);
        }

        $missingVars = $this->missingRequiredVariables();

        if ($missingVars === []) {
            return $next($request);
        }

        if ($this->isAdminRequest($request)) {
            return response()->view('setup-checklist', ['missingVars' => $missingVars], 503);
        }

        abort(503);
    }

    /**
     * @return array<int, string>
     */
    protected function missingRequiredVariables(): array
    {
        $required = config('setup.required', []);
        $allowEmptyInLocal = config('setup.allow_empty_in_local', []);
        $missing = [];

        foreach ($required as $key => $value) {
            if (app()->environment('local') && in_array($key, $allowEmptyInLocal, true)) {
                continue;
            }

            $normalized = is_string($value) ? trim($value) : $value;

            if (is_string($normalized) && $normalized !== '') {
                continue;
            }

            if (is_numeric($normalized) || is_bool($normalized)) {
                continue;
            }

            $missing[] = $key;
        }

        return $missing;
    }

    protected function isAdminRequest(Request $request): bool
    {
        $user = $request->user();

        return $user instanceof User && $user->isSuperadmin();
    }

    protected function isAllowedSetupRoute(Request $request): bool
    {
        return $request->routeIs(
            'welcome',
            'login',
            'login.store',
            'password.request',
            'password.email',
            'password.reset',
            'password.store',
            'company.register',
            'company.register.store',
            'company.register.confirmation',
            'locale.switch',
        );
    }
}
