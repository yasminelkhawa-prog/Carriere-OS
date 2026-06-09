<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AutoVerifyEmailForLocalTesting
{
    public function handle(Request $request, Closure $next): Response
    {
        $enabled = filter_var(env('APP_AUTO_VERIFY_EMAIL', false), FILTER_VALIDATE_BOOL);

        if ($enabled && app()->environment(['local', 'testing'])) {
            $user = $request->user();

            if ($user instanceof MustVerifyEmail && ! $user->hasVerifiedEmail()) {
                $user->markEmailAsVerified();
            }
        }

        return $next($request);
    }
}
