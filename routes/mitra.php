<?php

use App\Http\Controllers\Mitra\PortalController;
use App\Http\Middleware\EnsurePartner;
use Illuminate\Support\Facades\Route;

/*
 * Referral partner portal. Kept entirely separate from /avana — a partner has
 * no tenant and no employee record, so none of that AvanaNav-gated area
 * applies to them; EnsureAvanaAccess blocks them from it outright, and
 * EnsurePartner here is the mirror: the only area this login can reach.
 */
Route::middleware(['auth', 'verified', EnsurePartner::class])->prefix('mitra')->name('mitra.')->group(function () {
    Route::get('/', [PortalController::class, 'index'])->name('dashboard');
    Route::post('rekening', [PortalController::class, 'updateProfile'])->name('rekening.update');
    Route::post('penarikan', [PortalController::class, 'requestWithdrawal'])->name('penarikan.store');
});
