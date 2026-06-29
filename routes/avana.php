<?php

use App\Http\Controllers\Avana\EmployeeController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
 * AvanaHR template prototype — frontend-only screens with dummy data.
 * No auth/backend yet; data lives in resources/js/lib/avana.tsx.
 */
Route::prefix('avana')->name('avana.')->group(function () {
    Route::inertia('login', 'avana/login')->name('login');
    Route::inertia('dashboard', 'avana/dashboard')->name('dashboard');

    Route::inertia('karyawan', 'avana/karyawan/index')->name('karyawan');
    Route::inertia('karyawan/create', 'avana/karyawan/form')->name('karyawan.create');
    Route::get('karyawan/{id}', fn (int $id) => Inertia::render('avana/karyawan/show', ['id' => $id]))->name('karyawan.show');

    Route::inertia('absensi', 'avana/absensi')->name('absensi');
    Route::inertia('cuti', 'avana/cuti')->name('cuti');
    Route::inertia('payroll', 'avana/payroll')->name('payroll');
    Route::inertia('ess', 'avana/ess')->name('ess');
    Route::inertia('hak-akses', 'avana/hak-akses')->name('hak-akses');
    Route::inertia('laporan', 'avana/stub')->name('laporan');
});

/*
 * AvanaHR backend (authenticated). Tenant scoping is enforced inside the
 * controllers via Employee::forTenant($request->user()->tenant_id).
 */
Route::middleware(['auth', 'verified'])->prefix('avana')->name('avana.')->group(function () {
    Route::resource('employees', EmployeeController::class);
});
