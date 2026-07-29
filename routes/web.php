<?php

use App\Http\Controllers\AiTokenReturnController;
use App\Http\Controllers\Avana\DashboardController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

/*
 * Public return page for a personal AI token purchase paid from the phone. The
 * app hands payment to an external browser, which has no Laravel session, so a
 * signed-in return page would bounce the buyer to a login screen after they had
 * already paid. Throttled because it reaches out to the payment gateway.
 */
Route::get('bayar/token-ai/selesai', AiTokenReturnController::class)
    ->middleware('throttle:30,1')
    ->name('bayar.token-ai.selesai');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
});

require __DIR__.'/settings.php';
require __DIR__.'/avana.php';
