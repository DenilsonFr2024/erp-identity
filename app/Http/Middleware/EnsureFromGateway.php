<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFromGateway
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->is('api/*')) {
            return $next($request);
        }

        $expected = config('services.gateway.secret');
        $given = $request->header('X-Gateway-Secret');

        if (
            ! is_string($expected) || $expected === '' ||
            ! is_string($given) || ! hash_equals($expected, $given)
        ) {
            return response()->json([
                'success' => false,
                'data' => null,
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'Acesso permitido apenas através do Gateway.',
                    'details' => null,
                ],
                'meta' => [
                    'request_id' => $request->attributes->get('request_id'),
                    'timestamp' => now('UTC')->toIso8601String(),
                ],
            ], 403);
        }

        return $next($request);
    }
}
