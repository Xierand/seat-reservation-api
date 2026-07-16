<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyInternalApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('services.internal.api_key');
        $provided = (string) $request->header('X-Internal-Api-Key', '');

        if ($expected === '' || $provided === '' || ! hash_equals($expected, $provided)) {
            return response()->json([
                'error' => 'unauthorized',
                'message' => 'Invalid or missing internal API key.',
            ], 401);
        }

        return $next($request);
    }
}
