<?php

use App\Http\Controllers\Api\ActivityController;
use App\Http\Controllers\Api\AiAssistantController;
use App\Http\Controllers\Api\AiTokenPurchaseController;
use App\Http\Controllers\Api\AnnouncementController;
use App\Http\Controllers\Api\AppConfigController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AttendanceCorrectionController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CashAdvanceController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DirectoryController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\EotmController;
use App\Http\Controllers\Api\FaceController;
use App\Http\Controllers\Api\FieldVisitController;
use App\Http\Controllers\Api\FinanceController;
use App\Http\Controllers\Api\LeaveController;
use App\Http\Controllers\Api\MeetingController;
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
use App\Http\Controllers\Api\SettlementController;
use App\Http\Controllers\Api\ShiftSwapController;
use App\Http\Controllers\Api\SocialController;
use App\Http\Controllers\Api\SopController;
use App\Http\Controllers\Api\TaxController;
use App\Http\Controllers\Api\WfhController;
use App\Http\Controllers\PakasirWebhookController;
use Illuminate\Support\Facades\Route;

/*
 * AvanaHR mobile API (Flutter). JWT auth via the `api` guard. ESS endpoints
 * (/me/*) are scoped to the caller's own employee; MSS endpoints (/mss/*) to a
 * manager's team.
 */
// Public Pakasir payment callback for AI token top-ups and subscription
// renewals (tenant resolved from the order; verified server-to-server before
// crediting). Unversioned: the callback URL is configured in the Pakasir
// dashboard without the /v1 prefix, and it is answered identically below.
Route::post('pakasir/webhook', [PakasirWebhookController::class, 'handle'])->middleware('throttle:60,1');

Route::prefix('v1')->group(function (): void {
    Route::get('app-config', [AppConfigController::class, 'show']);
    Route::get('onboarding-slides', [OnboardingSlideController::class, 'index']);

    Route::post('pakasir/webhook', [PakasirWebhookController::class, 'handle'])->middleware('throttle:60,1');

    Route::prefix('auth')->group(function (): void {
        Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');

        // The second half of a login for an account carrying two-factor. Sits
        // outside `auth:api` for the same reason `refresh` does: the caller has
        // no token yet — earning one is the point of the route.
        Route::post('two-factor', [AuthController::class, 'twoFactor'])->middleware('throttle:10,1');

        // Deliberately outside `auth:api`: the guard rejects an expired token
        // before the controller runs, which would make this route unreachable
        // at exactly the moment it is needed. It authenticates the bearer
        // itself, under the refresh flow that tolerates expiry.
        Route::post('refresh', [AuthController::class, 'refresh'])->middleware('throttle:30,1');

        Route::middleware(['auth:api', 'token.fresh'])->group(function (): void {
            Route::get('me', [AuthController::class, 'me']);
            Route::post('logout', [AuthController::class, 'logout']);
        });
    });

    Route::middleware(['auth:api', 'token.fresh'])->group(function (): void {
        // Employee self-service
        Route::prefix('me')->group(function (): void {
            Route::get('profile', [ProfileController::class, 'show']);
            Route::put('profile', [ProfileController::class, 'update']);
            Route::post('profile/photo', [ProfileController::class, 'updatePhoto']);

            Route::get('dashboard', [DashboardController::class, 'summary']);
            Route::get('birthdays', [DashboardController::class, 'birthdays']);

            Route::get('mood', [MoodController::class, 'today']);
            Route::post('mood', [MoodController::class, 'store']);

            Route::get('attendance/today', [AttendanceController::class, 'today']);
            Route::get('attendance', [AttendanceController::class, 'history']);
            Route::get('work-locations', [AttendanceController::class, 'workLocations']);
            Route::post('attendance/challenge', [AttendanceController::class, 'challenge']);
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
            Route::get('announcements/{announcement}', [AnnouncementController::class, 'show'])->whereNumber('announcement');
            Route::post('announcements/{announcement}/read', [AnnouncementController::class, 'markRead'])->whereNumber('announcement');
            Route::get('announcements/{announcement}/comments', [AnnouncementController::class, 'comments'])->whereNumber('announcement');
            Route::post('announcements/{announcement}/comments', [AnnouncementController::class, 'storeComment'])->whereNumber('announcement');
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

            Route::get('cash-advances', [CashAdvanceController::class, 'index']);
            Route::post('cash-advances', [CashAdvanceController::class, 'store']);
            Route::get('cash-advances/{cashAdvance}', [CashAdvanceController::class, 'show']);

            Route::get('settlements', [SettlementController::class, 'index']);
            Route::post('settlements', [SettlementController::class, 'store']);
            Route::get('settlements/{settlement}', [SettlementController::class, 'show']);

            Route::post('security/fcm-token', [SecurityController::class, 'registerFcmToken']);
            Route::get('security/devices', [SecurityController::class, 'devices']);
            Route::post('security/password', [SecurityController::class, 'changePassword']);
            Route::post('security/logout-all', [SecurityController::class, 'logoutAll']);

            Route::get('notifications', [NotificationController::class, 'index']);
            Route::post('notifications/read-all', [NotificationController::class, 'markAllRead']);
            Route::post('notifications/{notification}/read', [NotificationController::class, 'markRead']);

            Route::get('documents', [DocumentController::class, 'index']);
            Route::post('documents', [DocumentController::class, 'store']);

            Route::get('sop', [SopController::class, 'index']);
            Route::get('sop/{sop}/download', [SopController::class, 'download'])->whereNumber('sop');

            // Social wall: feed, posting, likes, comments, leaderboard.
            Route::get('social/categories', [SocialController::class, 'categories']);
            Route::get('social/feed', [SocialController::class, 'feed']);
            Route::get('social/leaderboard', [SocialController::class, 'leaderboard']);
            Route::post('social/posts', [SocialController::class, 'store']);
            Route::get('social/posts/{post}', [SocialController::class, 'show'])->whereNumber('post');
            // POST, not PUT: the edit form can carry a replacement photo.
            Route::post('social/posts/{post}/update', [SocialController::class, 'update'])->whereNumber('post');
            Route::delete('social/posts/{post}', [SocialController::class, 'destroy'])->whereNumber('post');
            Route::post('social/posts/{post}/like', [SocialController::class, 'toggleLike'])->whereNumber('post');
            Route::post('social/posts/{post}/report', [SocialController::class, 'report'])->whereNumber('post');
            Route::get('social/posts/{post}/comments', [SocialController::class, 'comments'])->whereNumber('post');
            Route::post('social/posts/{post}/comments', [SocialController::class, 'storeComment'])->whereNumber('post');
            Route::delete('social/comments/{comment}', [SocialController::class, 'destroyComment'])->whereNumber('comment');

            // Employee of the Month voting.
            Route::get('eotm', [EotmController::class, 'show']);
            Route::get('eotm/nominees', [EotmController::class, 'nominees']);
            Route::post('eotm/vote', [EotmController::class, 'vote']);

            Route::get('field-visits', [FieldVisitController::class, 'index']);
            Route::post('field-visits', [FieldVisitController::class, 'store']);
            Route::post('field-visits/{visit}/tasks/{task}/toggle', [FieldVisitController::class, 'toggleTask']);
            Route::post('field-visits/{visit}/tasks/{task}/after', [FieldVisitController::class, 'uploadTaskAfter']);

            Route::get('shift-swaps', [ShiftSwapController::class, 'index']);
            Route::post('shift-swaps', [ShiftSwapController::class, 'store']);
            Route::get('shift-swaps/colleagues', [ShiftSwapController::class, 'colleagues']);

            Route::get('face', [FaceController::class, 'status']);
            Route::post('face/enroll', [FaceController::class, 'enroll']);

            // AI assistant — own-data scoped chat (single-shot JSON replies).
            Route::get('ai', [AiAssistantController::class, 'session']);
            Route::post('ai/chat', [AiAssistantController::class, 'chat']);
            Route::get('ai/conversations/{conversation}', [AiAssistantController::class, 'conversation'])->whereNumber('conversation');
            Route::delete('ai/conversations/{conversation}', [AiAssistantController::class, 'destroyConversation'])->whereNumber('conversation');

            // AI Recorder — the phone records, the speech provider transcribes,
            // and the finished text is posted back here as it arrives.
            Route::get('meetings/status', [MeetingController::class, 'status']);
            Route::get('meetings', [MeetingController::class, 'index']);
            Route::post('meetings', [MeetingController::class, 'store']);
            Route::get('meetings/{meeting}', [MeetingController::class, 'show'])->whereNumber('meeting');
            // Throttled: a grant is minted per socket open, and a loop that
            // reconnects in a tight cycle would hammer the provider's key API.
            Route::get('meetings/{meeting}/stt-token', [MeetingController::class, 'sttToken'])
                ->whereNumber('meeting')
                ->middleware('throttle:60,1');
            Route::post('meetings/{meeting}/segments', [MeetingController::class, 'segments'])->whereNumber('meeting');
            Route::post('meetings/{meeting}/stop', [MeetingController::class, 'stop'])->whereNumber('meeting');
            Route::post('meetings/{meeting}/audio', [MeetingController::class, 'audio'])->whereNumber('meeting');
            Route::post('meetings/{meeting}/reprocess', [MeetingController::class, 'reprocess'])->whereNumber('meeting');
            Route::post('meetings/{meeting}/action-items', [MeetingController::class, 'storeActionItem'])->whereNumber('meeting');
            Route::put('meetings/{meeting}/action-items/{actionItem}', [MeetingController::class, 'updateActionItem'])
                ->whereNumber('meeting')
                ->whereNumber('actionItem');

            Route::get('ai/tokens', [AiTokenPurchaseController::class, 'index']);
            Route::post('ai/tokens', [AiTokenPurchaseController::class, 'store']);
            Route::get('ai/tokens/{orderNumber}', [AiTokenPurchaseController::class, 'show']);
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
