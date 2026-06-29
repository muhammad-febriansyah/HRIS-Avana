<?php

use App\Http\Controllers\Avana\AccessController;
use App\Http\Controllers\Avana\AttendanceController;
use App\Http\Controllers\Avana\CompanySetupController;
use App\Http\Controllers\Avana\EmployeeController;
use App\Http\Controllers\Avana\FeatureController;
use App\Http\Controllers\Avana\LaporanController;
use App\Http\Controllers\Avana\LeaveController;
use App\Http\Controllers\Avana\PayrollController;
use App\Http\Controllers\Avana\PositionComponentController;
use Illuminate\Support\Facades\Route;

/*
 * AvanaHR (authenticated). Tenant scoping is enforced inside the controllers
 * via <Model>::forTenant($request->user()->tenant_id).
 */
Route::middleware(['auth', 'verified'])->prefix('avana')->name('avana.')->group(function () {
    Route::resource('employees', EmployeeController::class);

    Route::get('absensi', [AttendanceController::class, 'index'])->name('absensi');
    Route::post('absensi/corrections/{correction}/approve', [AttendanceController::class, 'approveCorrection'])->name('absensi.corrections.approve');
    Route::post('absensi/corrections/{correction}/reject', [AttendanceController::class, 'rejectCorrection'])->name('absensi.corrections.reject');

    Route::get('cuti', [LeaveController::class, 'index'])->name('cuti');
    Route::post('cuti', [LeaveController::class, 'store'])->name('cuti.store');
    Route::post('cuti/{leave}/approve', [LeaveController::class, 'approve'])->name('cuti.approve');
    Route::post('cuti/{leave}/reject', [LeaveController::class, 'reject'])->name('cuti.reject');

    Route::get('payroll', [PayrollController::class, 'index'])->name('payroll');
    Route::post('payroll/run', [PayrollController::class, 'run'])->name('payroll.run');
    Route::get('payroll/components', [PositionComponentController::class, 'index'])->name('payroll.components');
    Route::put('payroll/components', [PositionComponentController::class, 'update'])->name('payroll.components.update');

    Route::get('hak-akses', [AccessController::class, 'index'])->name('hak-akses');
    Route::post('hak-akses/permission/toggle', [AccessController::class, 'togglePermission'])->name('hak-akses.permission.toggle');
    Route::post('hak-akses/roles', [AccessController::class, 'storeRole'])->name('hak-akses.roles.store');

    Route::get('fitur', [FeatureController::class, 'index'])->name('fitur');
    Route::post('fitur/toggle', [FeatureController::class, 'toggle'])->name('fitur.toggle');

    Route::get('perusahaan', [CompanySetupController::class, 'index'])->name('perusahaan');
    Route::post('perusahaan/{entity}', [CompanySetupController::class, 'store'])->name('perusahaan.store');
    Route::put('perusahaan/{entity}/{record}', [CompanySetupController::class, 'update'])->name('perusahaan.update');
    Route::delete('perusahaan/{entity}/{record}', [CompanySetupController::class, 'destroy'])->name('perusahaan.destroy');

    Route::get('laporan', [LaporanController::class, 'index'])->name('laporan');
    Route::get('laporan/export/{type}', [LaporanController::class, 'export'])->name('laporan.export');
});
