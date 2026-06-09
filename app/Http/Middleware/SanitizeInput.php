<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SanitizeInput
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $request->query->replace($this->sanitizeArray($request->query->all()));
        $request->request->replace($this->sanitizeArray($request->request->all()));

        return $next($request);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function sanitizeArray(array $payload): array
    {
        foreach ($payload as $key => $value) {
            $payload[$key] = $this->sanitizeValue($value);
        }

        return $payload;
    }

    private function sanitizeValue(mixed $value): mixed
    {
        if (is_array($value)) {
            return $this->sanitizeArray($value);
        }

        if (! is_string($value)) {
            return $value;
        }

        $value = str_replace("\0", '', $value);
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);

        return is_string($value) ? $value : '';
    }
}
