<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:100'],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $credentials = $validator->validated();

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
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

        $token = $user->createToken($credentials['device_name']);

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $user,
                'token' => $token->plainTextToken,
                'token_type' => 'Bearer',
            ],
            'meta' => [
                'request_id' => $request->attributes->get('request_id'),
                'timestamp' => now('UTC')->toIso8601String(),
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'data' => [
                'message' => 'Sessão terminada com sucesso.',
            ],
            'meta' => [
                'request_id' => $request->attributes->get('request_id'),
                'timestamp' => now('UTC')->toIso8601String(),
            ],
        ]);
    }
}
