<?php

use App\Http\Controllers\AccountDeletionController;
use App\Http\Controllers\AiTokenReturnController;
use App\Http\Controllers\Avana\DashboardController;
use App\Http\Controllers\CompanyRegistrationController;
use App\Http\Controllers\PartnerRegistrationController;
use App\Http\Controllers\PrivacyPolicyController;
use App\Http\Controllers\PrivateFileController;
use App\Http\Controllers\PublicNewsController;
use App\Http\Controllers\ReferralLeadController;
use App\Http\Controllers\TermsOfServiceController;
use App\Http\Controllers\WelcomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', WelcomeController::class)->name('home');

/*
 * Public marketing page for the Live Tracking feature. Static like the landing
 * page — no controller, no data, nothing behind auth.
 */
Route::inertia('live-tracking', 'public/live-tracking')->name('live-tracking');
Route::inertia('partner', 'public/partnership')->name('partnership');
Route::get('partner/daftar', [PartnerRegistrationController::class, 'create'])->name('partner-registration.create');
Route::post('partner/daftar', [PartnerRegistrationController::class, 'store'])->name('partner-registration.store');

/*
 * Public "Daftar Perusahaan" — what a partner's `?ref=` link points to.
 * A visitor with no valid referral cookie gets a plain inquiry form (queues a
 * lead for the super admin); one with a valid cookie gets the self-serve
 * signup wizard instead, which provisions a real tenant immediately. See
 * ReferralLeadController for the branch and CompanyRegistrationController
 * for the wizard's submit. Throttled: both create real DB rows.
 */
Route::get('daftar-perusahaan', [ReferralLeadController::class, 'create'])->name('referral.lead.create');
Route::post('daftar-perusahaan', [ReferralLeadController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('referral.lead.store');
Route::post('daftar-perusahaan/daftar', [CompanyRegistrationController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('company-registration.store');

/*
 * Public news/berita — read-only, published articles only. Separate from the
 * super-admin CMS at avana/berita (NewsPolicy gates that one to super admins).
 */
Route::get('berita', [PublicNewsController::class, 'index'])->name('berita');
Route::get('berita/{news:slug}', [PublicNewsController::class, 'show'])->name('berita.show');

/*
 * Legal pages linked from the footer — content is editable by a super admin
 * (Pengaturan Website → Kebijakan Privasi / Syarat & Ketentuan).
 */
Route::get('kebijakan-privasi', PrivacyPolicyController::class)->name('privacy');
Route::get('syarat-ketentuan', TermsOfServiceController::class)->name('terms');
Route::get('delete-account', AccountDeletionController::class)->name('account-deletion');

/*
 * Public return page for a personal AI token purchase paid from the phone. The
 * app hands payment to an external browser, which has no Laravel session, so a
 * signed-in return page would bounce the buyer to a login screen after they had
 * already paid. Throttled because it reaches out to the payment gateway.
 */
Route::get('bayar/token-ai/selesai', AiTokenReturnController::class)
    ->middleware('throttle:30,1')
    ->name('bayar.token-ai.selesai');

/*
 * Files kept off the public disk — personnel documents, claim receipts,
 * recruitment paperwork, employee photos. The signature on the link is the
 * authorisation, which is what lets the phone and the browser it hands a link
 * to read the same file without either of them carrying a session.
 */
Route::get('berkas/{path}', PrivateFileController::class)
    ->where('path', '.*')
    ->middleware('signed')
    ->name('berkas.show');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
});

require __DIR__.'/settings.php';
require __DIR__.'/avana.php';
require __DIR__.'/mitra.php';
