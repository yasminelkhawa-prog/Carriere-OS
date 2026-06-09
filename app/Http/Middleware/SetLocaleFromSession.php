<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocaleFromSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->session()->get('locale');

        if ($locale === null) {
            $user = $request->user();
            if ($user instanceof User) {
                $locale = $user->profile?->locale;
            }
        }

        if (! in_array((string) $locale, ['en', 'fr'], true)) {
            $locale = config('app.locale');
        }

        app()->setLocale((string) $locale);

        return $next($request);
    }
}

