<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // URL do frontend para o reset de password
        ResetPassword::createUrlUsing(function ($user, string $token) {
            return 'http://localhost:3000/reset-password?token=' . $token . '&email=' . urlencode($user->getEmailForPasswordReset());
        });

        // Rate limiting para o login
        RateLimiter::for('auth-login', function (Request $request) {
            $ip = $request->ip() ?? 'unknown';
            $email = $request->input('email');
            $email = is_string($email) ? strtolower(trim($email)) : '';

            $respond = function (Request $request, array $headers) {
                return response()->json([
                    'success' => false,
                    'data' => null,
                    'error' => [
                        'code' => 'TOO_MANY_REQUESTS',
                        'message' => 'Demasiadas tentativas. Tente novamente mais tarde.',
                        'details' => null,
                    ],
                    'meta' => [
                        'request_id' => $request->attributes->get('request_id'),
                        'timestamp' => now('UTC')->toIso8601String(),
                    ],
                ], 429, $headers);
            };

            return [
                Limit::perMinute(5)->by('login:' . $ip . '|' . $email)->response($respond),
                Limit::perMinute(20)->by('login-ip:' . $ip)->response($respond),
            ];
        });
    }
}
