<?php

use App\Http\Controllers\Api\AnnouncementController;
use App\Http\Controllers\Api\AppConfigController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\LeaveController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OvertimeController;
use App\Http\Controllers\Api\PayslipController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ReimbursementController;
use App\Http\Controllers\Api\WfhController;
use Illuminate\Support\Facades\Route;

/*
 * AvanaHR mobile API (Flutter). JWT auth via the `api` guard. ESS endpoints
 * (/me/*) are scoped to the caller's own employee; MSS endpoints (/mss/*) to a
 * manager's team.
 */
Route::prefix('v1')->group(function (): void {
    Route::get('app-config', [AppConfigController::class, 'show']);

    Route::prefix('auth')->group(function (): void {
        Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');

        Route::middleware('auth:api')->group(function (): void {
            Route::get('me', [AuthController::class, 'me']);
            Route::post('refresh', [AuthController::class, 'refresh']);
            Route::post('logout', [AuthController::class, 'logout']);
        });
    });

    Route::middleware('auth:api')->group(function (): void {
        // Employee self-service
        Route::prefix('me')->group(function (): void {
            Route::get('profile', [ProfileController::class, 'show']);
            Route::put('profile', [ProfileController::class, 'update']);

            Route::get('attendance/today', [AttendanceController::class, 'today']);
            Route::get('attendance', [AttendanceController::class, 'history']);
            Route::post('attendance/clock', [AttendanceController::class, 'clock']);

            Route::get('leave/balances', [LeaveController::class, 'balances']);
            Route::get('leave-types', [LeaveController::class, 'types']);
            Route::get('leave-requests', [LeaveController::class, 'index']);
            Route::post('leave-requests', [LeaveController::class, 'store']);

            Route::get('overtime', [OvertimeController::class, 'index']);
            Route::post('overtime', [OvertimeController::class, 'store']);
            Route::get('permissions', [PermissionController::class, 'index']);
            Route::post('permissions', [PermissionController::class, 'store']);
            Route::get('wfh', [WfhController::class, 'index']);
            Route::post('wfh', [WfhController::class, 'store']);
            Route::get('announcements', [AnnouncementController::class, 'index']);

            Route::get('payslips', [PayslipController::class, 'index']);
            Route::get('payslips/{item}', [PayslipController::class, 'show']);

            Route::post('reimbursements', [ReimbursementController::class, 'store']);
            Route::get('reimbursements', [ReimbursementController::class, 'index']);

            Route::get('notifications', [NotificationController::class, 'index']);
            Route::post('notifications/read-all', [NotificationController::class, 'markAllRead']);
            Route::post('notifications/{notification}/read', [NotificationController::class, 'markRead']);
        });
    });
});
