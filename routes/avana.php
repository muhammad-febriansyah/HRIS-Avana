<?php

use App\Http\Controllers\Avana\AccessController;
use App\Http\Controllers\Avana\AttendanceController;
use App\Http\Controllers\Avana\CompanySetupController;
use App\Http\Controllers\Avana\EmployeeController;
use App\Http\Controllers\Avana\FeatureController;
use App\Http\Controllers\Avana\LaporanController;
use App\Http\Controllers\Avana\LeaveController;
use App\Http\Controllers\Avana\OvertimeController;
use App\Http\Controllers\Avana\PayrollConfigController;
use App\Http\Controllers\Avana\PayrollController;
use App\Http\Controllers\Avana\PermissionRequestController;
use App\Http\Controllers\Avana\PositionComponentController;
use App\Http\Controllers\Avana\TenantController;
use App\Http\Controllers\Avana\UserController;
use App\Http\Controllers\Avana\WfhController;
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

    // Lembur (overtime)
    Route::post('cuti/lembur', [OvertimeController::class, 'store'])->name('cuti.lembur.store');
    Route::post('cuti/lembur/{overtime}/approve', [OvertimeController::class, 'approve'])->name('cuti.lembur.approve');
    Route::post('cuti/lembur/{overtime}/reject', [OvertimeController::class, 'reject'])->name('cuti.lembur.reject');

    // Izin / keluar kantor (permission requests)
    Route::post('cuti/izin', [PermissionRequestController::class, 'store'])->name('cuti.izin.store');
    Route::post('cuti/izin/{permissionRequest}/approve', [PermissionRequestController::class, 'approve'])->name('cuti.izin.approve');
    Route::post('cuti/izin/{permissionRequest}/reject', [PermissionRequestController::class, 'reject'])->name('cuti.izin.reject');

    // WFH
    Route::post('cuti/wfh', [WfhController::class, 'store'])->name('cuti.wfh.store');
    Route::post('cuti/wfh/{wfh}/approve', [WfhController::class, 'approve'])->name('cuti.wfh.approve');
    Route::post('cuti/wfh/{wfh}/reject', [WfhController::class, 'reject'])->name('cuti.wfh.reject');

    Route::get('payroll', [PayrollController::class, 'index'])->name('payroll');
    Route::post('payroll/run', [PayrollController::class, 'run'])->name('payroll.run');
    Route::get('payroll/components', [PositionComponentController::class, 'index'])->name('payroll.components');
    Route::put('payroll/components', [PositionComponentController::class, 'update'])->name('payroll.components.update');

    // Payroll config: BPJS programs/rates + PPh21 TER
    Route::get('payroll/konfigurasi', [PayrollConfigController::class, 'index'])->name('payroll.konfigurasi');
    Route::post('payroll/konfigurasi/bpjs', [PayrollConfigController::class, 'storeBpjsProgram'])->name('payroll.konfigurasi.bpjs.store');
    Route::put('payroll/konfigurasi/bpjs/{program}', [PayrollConfigController::class, 'updateBpjsProgram'])->name('payroll.konfigurasi.bpjs.update');
    Route::delete('payroll/konfigurasi/bpjs/{program}', [PayrollConfigController::class, 'destroyBpjsProgram'])->name('payroll.konfigurasi.bpjs.destroy');
    Route::post('payroll/konfigurasi/pph21', [PayrollConfigController::class, 'storeTerRate'])->name('payroll.konfigurasi.pph21.store');
    Route::put('payroll/konfigurasi/pph21/{rate}', [PayrollConfigController::class, 'updateTerRate'])->name('payroll.konfigurasi.pph21.update');
    Route::delete('payroll/konfigurasi/pph21/{rate}', [PayrollConfigController::class, 'destroyTerRate'])->name('payroll.konfigurasi.pph21.destroy');

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

    // User management (Pengguna)
    Route::get('pengguna', [UserController::class, 'index'])->name('pengguna');
    Route::post('pengguna', [UserController::class, 'store'])->name('pengguna.store');
    Route::put('pengguna/{user}', [UserController::class, 'update'])->name('pengguna.update');
    Route::delete('pengguna/{user}', [UserController::class, 'destroy'])->name('pengguna.destroy');
    Route::post('pengguna/{user}/toggle', [UserController::class, 'toggleStatus'])->name('pengguna.toggle');

    // Tenant / client management (super admin)
    Route::get('klien', [TenantController::class, 'index'])->name('klien');
    Route::post('klien', [TenantController::class, 'store'])->name('klien.store');
    Route::put('klien/{tenant}', [TenantController::class, 'update'])->name('klien.update');
    Route::delete('klien/{tenant}', [TenantController::class, 'destroy'])->name('klien.destroy');
    Route::post('klien/{tenant}/feature', [TenantController::class, 'toggleFeature'])->name('klien.feature.toggle');
});
