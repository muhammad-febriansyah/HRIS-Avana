<?php

use App\Http\Controllers\Avana\DashboardController;
use App\Http\Controllers\PublicCareerController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
});

// Public careers portal (unauthenticated) — each tenant at /karir/{slug}
Route::get('karir/{tenant:slug}', [PublicCareerController::class, 'index'])->name('careers');
Route::get('karir/{tenant:slug}/{jobPosting}', [PublicCareerController::class, 'show'])->name('careers.show');
Route::post('karir/{tenant:slug}/{jobPosting}/apply', [PublicCareerController::class, 'apply'])->name('careers.apply');

require __DIR__.'/settings.php';
require __DIR__.'/avana.php';
