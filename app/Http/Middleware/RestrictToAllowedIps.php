<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

class RestrictToAllowedIps
{
    public function handle(Request $request, Closure $next): Response
    {
        $allowedIps = config('services.internal.allowed_ips', []);

        if ($allowedIps === []) {
            return $next($request);
        }

        $clientIp = $request->ip();

        if ($clientIp === null || ! IpUtils::checkIp($clientIp, $allowedIps)) {
            return response()->json([
                'error' => 'forbidden',
                'message' => 'Request IP is not allowed.',
            ], 403);
        }

        return $next($request);
    }
}
