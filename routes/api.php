<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

/*
 * AvanaHR mobile API (Flutter employee self-service). Token auth via Sanctum.
 * All data is scoped to the authenticated user's tenant and, for ESS, to their
 * own employee record.
 */
Route::prefix('v1')->group(function (): void {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');

    Route::middleware('auth:api')->group(function (): void {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('refresh', [AuthController::class, 'refresh']);
        Route::get('me', [AuthController::class, 'me']);
    });
});
