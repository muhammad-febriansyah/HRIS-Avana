<?php

use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    // Its own POST endpoint: PHP only parses a multipart body on POST, so an
    // avatar cannot ride along with the PATCH that saves the text fields.
    Route::post('settings/profile/foto', [ProfileController::class, 'updateAvatar'])->name('profile.avatar');
});

Route::middleware(['auth', 'verified'])->group(function () {
    // No self-service account deletion: a login here is the key to a tenant's
    // payroll, attendance and approval history, and deleting it would strand
    // the employee row that points at it. Removing an account is HR's call,
    // made from the Karyawan screen.
    Route::get('settings/security', [SecurityController::class, 'edit'])
        ->middleware(RequirePassword::class)
        ->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');
});

Route::get('.well-known/passkey-endpoints', function () {
    return response()->json([
        'enroll' => route('security.edit'),
        'manage' => route('security.edit'),
    ]);
})->name('well-known.passkeys');
