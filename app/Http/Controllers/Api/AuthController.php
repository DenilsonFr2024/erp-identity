<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Role;
use App\Models\User;
use App\Models\UserApplication;
use App\Models\UserRole;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\RateLimiter; // no topo
class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'username' => ['required', 'string', 'max:80', 'unique:users,username', 'regex:/^[a-z0-9._-]+$/'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'account_type' => ['required', 'in:internal,partner,client'],
            'applications' => ['required', 'array', 'min:1'],
            'applications.*' => ['string', 'exists:applications,code'],
            'role' => ['required', 'string', 'exists:roles,name'],
            'company_id' => ['nullable', 'uuid'],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $data = $validator->validated();

        // Internos ficam ativos; clientes/parceiros ficam pendentes
        $status = $data['account_type'] === 'internal' ? 'active' : 'pending_approval';

        $user = User::create([
            'username' => $data['username'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'account_type' => $data['account_type'],
            'status' => $status,
        ]);

        // Associar role
        $role = Role::where('name', $data['role'])->first();
        UserRole::create([
            'user_id' => $user->user_id,
            'role_id' => $role->role_id,
            'company_id' => $data['company_id'] ?? null,
        ]);

        // Associar aplicações
        foreach ($data['applications'] as $appCode) {
            $app = Application::where('code', $appCode)->first();
            UserApplication::create([
                'user_id' => $user->user_id,
                'application_id' => $app->application_id,
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'user_id' => $user->user_id,
                'username' => $user->username,
                'email' => $user->email,
                'account_type' => $user->account_type,
                'status' => $user->status,
            ],
            'meta' => [
                'request_id' => $request->attributes->get('request_id'),
                'timestamp' => now('UTC')->toIso8601String(),
            ],
        ], 201);
    }



    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'data' => ['message' => 'Sessão terminada com sucesso.'],
            'meta' => [
                'request_id' => $request->attributes->get('request_id'),
                'timestamp' => now('UTC')->toIso8601String(),
            ],
        ]);
    }
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'login' => ['required', 'string'], // username OU email
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:100'],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $credentials = $validator->validated();

        // Rate limiting por login (username ou email)
        $key = 'login:' . $credentials['login'];

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $retryAfter = RateLimiter::availableIn($key);

            return response()->json([
                'success' => false,
                'data' => null,
                'error' => [
                    'code' => 'TOO_MANY_REQUESTS',
                    'message' => 'Demasiadas tentativas. Tente novamente mais tarde.',
                    'details' => ['retry_after' => $retryAfter],
                ],
                'meta' => [
                    'request_id' => $request->attributes->get('request_id'),
                    'timestamp' => now('UTC')->toIso8601String(),
                ],
            ], 429)->header('Retry-After', $retryAfter);
        }

        $user = User::where('username', $credentials['login'])
            ->orWhere('email', $credentials['login'])
            ->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            RateLimiter::hit($key, 60); // 60 segundos de bloqueio
            return $this->invalidCredentials($request);
        }

        RateLimiter::clear($key);

        if (! $user->isActive()) {
            return response()->json([
                'success' => false,
                'data' => null,
                'error' => [
                    'code' => 'ACCOUNT_NOT_ACTIVE',
                    'message' => 'A conta não está ativa.',
                    'details' => ['status' => $user->status],
                ],
                'meta' => [
                    'request_id' => $request->attributes->get('request_id'),
                    'timestamp' => now('UTC')->toIso8601String(),
                ],
            ], 403);
        }

        $token = $user->createToken($credentials['device_name']);

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'user_id' => $user->user_id,
                    'username' => $user->username,
                    'email' => $user->email,
                    'account_type' => $user->account_type,
                ],
                'applications' => $user->applications()->pluck('code')->toArray(),
                'roles' => $user->roles()->pluck('name')->toArray(),
                'token' => $token->plainTextToken,
                'token_type' => 'Bearer',
            ],
            'meta' => [
                'request_id' => $request->attributes->get('request_id'),
                'timestamp' => now('UTC')->toIso8601String(),
            ],
        ]);
    }

    private function invalidCredentials(Request $request): JsonResponse
    {
        return response()->json([
            'success' => false,
            'data' => null,
            'error' => [
                'code' => 'INVALID_CREDENTIALS',
                'message' => 'As credenciais fornecidas são inválidas.',
                'details' => null,
            ],
            'meta' => [
                'request_id' => $request->attributes->get('request_id'),
                'timestamp' => now('UTC')->toIso8601String(),
            ],
        ], 401);
    }

    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user();

        // Apagar TODOS os tokens do utilizador (ou só o atual, se preferires)
        $user->tokens()->delete();

        // Criar um novo token com o mesmo device_name
        $deviceName = $request->input('device_name', 'api');
        $newToken = $user->createToken($deviceName);

        return response()->json([
            'success' => true,
            'data' => [
                'token' => $newToken->plainTextToken,
                'token_type' => 'Bearer',
            ],
            'meta' => [
                'request_id' => $request->attributes->get('request_id'),
                'timestamp' => now('UTC')->toIso8601String(),
            ],
        ]);
    }
}
