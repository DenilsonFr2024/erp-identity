<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Públicas
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/password/forgot', [AuthController::class, 'forgotPassword']);
Route::post('/password/reset', [AuthController::class, 'resetPassword']);

// Protegidas
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/refresh', [AuthController::class, 'refresh']);
    Route::post('/admin/approve-user/{user_id}', [AuthController::class, 'approveUser']);
    Route::post('/password/change', [AuthController::class, 'changePassword']);
    Route::get('/user', fn(Request $request) => response()->json([
        'success' => true,
        'data' => $request->user(),
        'meta' => [
            'request_id' => $request->attributes->get('request_id'),
            'timestamp' => now('UTC')->toIso8601String(),
        ],
    ]));
});
