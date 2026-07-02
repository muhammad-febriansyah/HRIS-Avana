<?php

use App\Http\Controllers\Api\AnnouncementController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClaimController;
use App\Http\Controllers\Api\LeaveController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OvertimeController;
use App\Http\Controllers\Api\PayslipController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\WfhController;
use Illuminate\Support\Facades\Route;

/*
 * AvanaHR mobile API (Flutter employee self-service). JWT auth via the `api`
 * guard. Every ESS endpoint is scoped to the authenticated user's own employee
 * record and tenant (see ResolvesApiEmployee).
 */
Route::prefix('v1')->group(function (): void {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');

    Route::middleware('auth:api')->group(function (): void {
        // Auth / profile
        Route::get('me', [AuthController::class, 'me']);
        Route::post('refresh', [AuthController::class, 'refresh']);
        Route::post('logout', [AuthController::class, 'logout']);
        Route::put('profile', [ProfileController::class, 'update']);

        // Attendance
        Route::get('attendance', [AttendanceController::class, 'index']);
        Route::get('attendance/today', [AttendanceController::class, 'today']);
        Route::post('attendance/clock-in', [AttendanceController::class, 'clockIn']);
        Route::post('attendance/clock-out', [AttendanceController::class, 'clockOut']);

        // Leave
        Route::get('leave-types', [LeaveController::class, 'types']);
        Route::get('leave/balance', [LeaveController::class, 'balance']);
        Route::get('leave', [LeaveController::class, 'index']);
        Route::post('leave', [LeaveController::class, 'store']);

        // Overtime / permission / WFH
        Route::get('overtime', [OvertimeController::class, 'index']);
        Route::post('overtime', [OvertimeController::class, 'store']);
        Route::get('permissions', [PermissionController::class, 'index']);
        Route::post('permissions', [PermissionController::class, 'store']);
        Route::get('wfh', [WfhController::class, 'index']);
        Route::post('wfh', [WfhController::class, 'store']);

        // Claims / payslips
        Route::get('claims', [ClaimController::class, 'index']);
        Route::post('claims', [ClaimController::class, 'store']);
        Route::get('payslips', [PayslipController::class, 'index']);
        Route::get('payslips/{item}', [PayslipController::class, 'show']);

        // Engagement
        Route::get('announcements', [AnnouncementController::class, 'index']);
        Route::get('notifications', [NotificationController::class, 'index']);
        Route::post('notifications/{notification}/read', [NotificationController::class, 'markRead']);
        Route::post('notifications/read-all', [NotificationController::class, 'markAllRead']);
    });
});
