<?php

use App\Http\Controllers\Api\ActivityController;
use App\Http\Controllers\Api\AnnouncementController;
use App\Http\Controllers\Api\AppConfigController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AttendanceCorrectionController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DirectoryController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\FaceController;
use App\Http\Controllers\Api\FieldVisitController;
use App\Http\Controllers\Api\FinanceController;
use App\Http\Controllers\Api\LeaveController;
use App\Http\Controllers\Api\MoodController;
use App\Http\Controllers\Api\MssController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OnboardingController;
use App\Http\Controllers\Api\OnboardingSlideController;
use App\Http\Controllers\Api\OvertimeController;
use App\Http\Controllers\Api\PayslipController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ReimbursementController;
use App\Http\Controllers\Api\ScheduleController;
use App\Http\Controllers\Api\SecurityController;
use App\Http\Controllers\Api\ShiftSwapController;
use App\Http\Controllers\Api\TaxController;
use App\Http\Controllers\Api\WfhController;
use Illuminate\Support\Facades\Route;

/*
 * AvanaHR mobile API (Flutter). JWT auth via the `api` guard. ESS endpoints
 * (/me/*) are scoped to the caller's own employee; MSS endpoints (/mss/*) to a
 * manager's team.
 */
Route::prefix('v1')->group(function (): void {
    Route::get('app-config', [AppConfigController::class, 'show']);
    Route::get('onboarding-slides', [OnboardingSlideController::class, 'index']);

    Route::prefix('auth')->group(function (): void {
        Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');

        Route::middleware(['auth:api', 'token.fresh'])->group(function (): void {
            Route::get('me', [AuthController::class, 'me']);
            Route::post('refresh', [AuthController::class, 'refresh']);
            Route::post('logout', [AuthController::class, 'logout']);
        });
    });

    Route::middleware(['auth:api', 'token.fresh'])->group(function (): void {
        // Employee self-service
        Route::prefix('me')->group(function (): void {
            Route::get('profile', [ProfileController::class, 'show']);
            Route::put('profile', [ProfileController::class, 'update']);

            Route::get('dashboard', [DashboardController::class, 'summary']);

            Route::get('mood', [MoodController::class, 'today']);
            Route::post('mood', [MoodController::class, 'store']);

            Route::get('attendance/today', [AttendanceController::class, 'today']);
            Route::get('attendance', [AttendanceController::class, 'history']);
            Route::get('work-locations', [AttendanceController::class, 'workLocations']);
            Route::post('attendance/clock', [AttendanceController::class, 'clock']);
            Route::get('attendance/corrections', [AttendanceCorrectionController::class, 'index']);
            Route::post('attendance/corrections', [AttendanceCorrectionController::class, 'store']);
            Route::get('schedule', [ScheduleController::class, 'index']);

            Route::get('leave/balances', [LeaveController::class, 'balances']);
            Route::get('leave/calendar', [LeaveController::class, 'calendar']);
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
            Route::get('activities', [ActivityController::class, 'index']);

            Route::get('directory', [DirectoryController::class, 'index']);
            Route::get('directory/{employee}', [DirectoryController::class, 'show'])->whereNumber('employee');

            Route::get('onboarding', [OnboardingController::class, 'onboarding']);
            Route::patch('onboarding/tasks/{task}', [OnboardingController::class, 'toggleTask']);
            Route::get('offboarding', [OnboardingController::class, 'offboarding']);

            Route::get('payslips', [PayslipController::class, 'index']);
            Route::get('payslips/{item}', [PayslipController::class, 'show']);
            Route::get('payslips/{item}/pdf', [PayslipController::class, 'pdf']);

            Route::get('tax-forms', [TaxController::class, 'index']);
            Route::get('tax-forms/{year}/pdf', [TaxController::class, 'pdf'])->whereNumber('year');

            Route::post('reimbursements', [ReimbursementController::class, 'store']);
            Route::get('reimbursements', [ReimbursementController::class, 'index']);

            Route::get('security/devices', [SecurityController::class, 'devices']);
            Route::post('security/password', [SecurityController::class, 'changePassword']);
            Route::post('security/logout-all', [SecurityController::class, 'logoutAll']);

            Route::get('notifications', [NotificationController::class, 'index']);
            Route::post('notifications/read-all', [NotificationController::class, 'markAllRead']);
            Route::post('notifications/{notification}/read', [NotificationController::class, 'markRead']);

            Route::get('documents', [DocumentController::class, 'index']);
            Route::post('documents', [DocumentController::class, 'store']);

            Route::get('field-visits', [FieldVisitController::class, 'index']);
            Route::post('field-visits', [FieldVisitController::class, 'store']);

            Route::get('shift-swaps', [ShiftSwapController::class, 'index']);
            Route::post('shift-swaps', [ShiftSwapController::class, 'store']);
            Route::get('shift-swaps/colleagues', [ShiftSwapController::class, 'colleagues']);

            Route::get('face', [FaceController::class, 'status']);
            Route::post('face/enroll', [FaceController::class, 'enroll']);
        });

        // Finance/payroll: reimbursement disbursement (role-gated in-controller).
        Route::prefix('finance')->group(function (): void {
            Route::get('reimbursements', [FinanceController::class, 'reimbursements']);
            Route::post('reimbursements/{claim}/pay', [FinanceController::class, 'payReimbursement']);
        });

        // Manager Self-Service: requests routed to the caller + their team.
        Route::prefix('mss')->group(function (): void {
            Route::get('approvals', [MssController::class, 'approvals']);
            Route::get('history', [MssController::class, 'history']);
            Route::post('approvals/bulk', [MssController::class, 'bulk']);
            Route::post('approvals/{key}/act', [MssController::class, 'act']);
            Route::get('team', [MssController::class, 'team']);
            Route::get('attendance/recap', [MssController::class, 'teamAttendance']);
            Route::get('attendance/recap/export', [MssController::class, 'teamAttendanceExport']);
            Route::get('team/{employee}', [MssController::class, 'member']);
            Route::get('shifts', [MssController::class, 'shifts']);
            Route::post('schedule', [MssController::class, 'assignShift']);
        });
    });
});
