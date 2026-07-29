<?php

use App\Http\Controllers\Avana\AccessController;
use App\Http\Controllers\Avana\AiAssistantController;
use App\Http\Controllers\Avana\AiSettingController;
use App\Http\Controllers\Avana\AiTokenPackController;
use App\Http\Controllers\Avana\AnalyticsController;
use App\Http\Controllers\Avana\AnnouncementController;
use App\Http\Controllers\Avana\ApprovalController;
use App\Http\Controllers\Avana\ApprovalDelegationController;
use App\Http\Controllers\Avana\ApprovalWorkflowController;
use App\Http\Controllers\Avana\AssetController;
use App\Http\Controllers\Avana\AttendanceController;
use App\Http\Controllers\Avana\AttendancePenaltyController;
use App\Http\Controllers\Avana\AttendancePolicyController;
use App\Http\Controllers\Avana\AttritionController;
use App\Http\Controllers\Avana\AuditController;
use App\Http\Controllers\Avana\BenefitController;
use App\Http\Controllers\Avana\BillingController;
use App\Http\Controllers\Avana\BudgetController;
use App\Http\Controllers\Avana\CalendarController;
use App\Http\Controllers\Avana\CashAdvanceController;
use App\Http\Controllers\Avana\ClaimController;
use App\Http\Controllers\Avana\CompanySetupController;
use App\Http\Controllers\Avana\CompetencyController;
use App\Http\Controllers\Avana\ContractController;
use App\Http\Controllers\Avana\CrmController;
use App\Http\Controllers\Avana\CustomFieldController;
use App\Http\Controllers\Avana\DayCalcMethodController;
use App\Http\Controllers\Avana\DokumenController;
use App\Http\Controllers\Avana\DutyTravelController;
use App\Http\Controllers\Avana\DynamicReportController;
use App\Http\Controllers\Avana\EmailSettingController;
use App\Http\Controllers\Avana\EmployeeController;
use App\Http\Controllers\Avana\EssAiTokenController;
use App\Http\Controllers\Avana\EssAttendanceController;
use App\Http\Controllers\Avana\EssBenefitController;
use App\Http\Controllers\Avana\EssCalendarController;
use App\Http\Controllers\Avana\EssContractController;
use App\Http\Controllers\Avana\EssDirectoryController;
use App\Http\Controllers\Avana\EssDocumentController;
use App\Http\Controllers\Avana\EssLearningController;
use App\Http\Controllers\Avana\EssLeaveController;
use App\Http\Controllers\Avana\EssOnboardingController;
use App\Http\Controllers\Avana\EssOvertimeController;
use App\Http\Controllers\Avana\EssPayslipController;
use App\Http\Controllers\Avana\EssPerformanceController;
use App\Http\Controllers\Avana\EssPermissionController;
use App\Http\Controllers\Avana\EssProfileController;
use App\Http\Controllers\Avana\EssScheduleController;
use App\Http\Controllers\Avana\EssSocialController;
use App\Http\Controllers\Avana\EssSopController;
use App\Http\Controllers\Avana\EssTaskController;
use App\Http\Controllers\Avana\EssTravelController;
use App\Http\Controllers\Avana\FeatureCatalogController;
use App\Http\Controllers\Avana\FeatureController;
use App\Http\Controllers\Avana\FieldVisitController;
use App\Http\Controllers\Avana\HelpdeskController;
use App\Http\Controllers\Avana\HiringRequestController;
use App\Http\Controllers\Avana\JournalController;
use App\Http\Controllers\Avana\LaporanController;
use App\Http\Controllers\Avana\LearningController;
use App\Http\Controllers\Avana\LeaveController;
use App\Http\Controllers\Avana\LeaveTypeController;
use App\Http\Controllers\Avana\LetterTemplateController;
use App\Http\Controllers\Avana\LoanController;
use App\Http\Controllers\Avana\MenuBuilderController;
use App\Http\Controllers\Avana\MoodController;
use App\Http\Controllers\Avana\MovementController;
use App\Http\Controllers\Avana\NotificationController;
use App\Http\Controllers\Avana\OffboardingController;
use App\Http\Controllers\Avana\OkrController;
use App\Http\Controllers\Avana\OnboardingController;
use App\Http\Controllers\Avana\OnboardingSlideController;
use App\Http\Controllers\Avana\OvertimeController;
use App\Http\Controllers\Avana\PackageController;
use App\Http\Controllers\Avana\PaydayController;
use App\Http\Controllers\Avana\PayrollConfigController;
use App\Http\Controllers\Avana\PayrollController;
use App\Http\Controllers\Avana\PayrollCorrectionController;
use App\Http\Controllers\Avana\PayrollKomponenController;
use App\Http\Controllers\Avana\PayrollUmrController;
use App\Http\Controllers\Avana\PerformanceController;
use App\Http\Controllers\Avana\PermissionRequestController;
use App\Http\Controllers\Avana\RecruitmentController;
use App\Http\Controllers\Avana\RecruitmentRequisitionController;
use App\Http\Controllers\Avana\ReimbursementController;
use App\Http\Controllers\Avana\ReportStudioController;
use App\Http\Controllers\Avana\RosterController;
use App\Http\Controllers\Avana\SalaryGradeStepController;
use App\Http\Controllers\Avana\SalaryMasterController;
use App\Http\Controllers\Avana\SalaryRapelController;
use App\Http\Controllers\Avana\SalaryStructureController;
use App\Http\Controllers\Avana\SalesOrderController;
use App\Http\Controllers\Avana\SearchController;
use App\Http\Controllers\Avana\SettlementController;
use App\Http\Controllers\Avana\ShiftSwapController;
use App\Http\Controllers\Avana\SocialController;
use App\Http\Controllers\Avana\SopController;
use App\Http\Controllers\Avana\SurveyController;
use App\Http\Controllers\Avana\TalentController;
use App\Http\Controllers\Avana\TenantAiTokenController;
use App\Http\Controllers\Avana\TenantAppearanceController;
use App\Http\Controllers\Avana\TenantController;
use App\Http\Controllers\Avana\TenantSubscriptionController;
use App\Http\Controllers\Avana\TimesheetController;
use App\Http\Controllers\Avana\UserController;
use App\Http\Controllers\Avana\ViewTenantController;
use App\Http\Controllers\Avana\WebsiteSettingController;
use App\Http\Controllers\Avana\WfhController;
use App\Http\Middleware\EnsureAvanaAccess;
use Illuminate\Support\Facades\Route;

/*
 * AvanaHR (authenticated). Tenant scoping is enforced inside the controllers
 * via <Model>::forTenant($request->user()->tenant_id).
 */
Route::middleware(['auth', 'verified', EnsureAvanaAccess::class])->prefix('avana')->name('avana.')->group(function () {
    Route::post('view-tenant', [ViewTenantController::class, 'store'])->name('view-tenant');
    Route::post('notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::post('notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::get('search', [SearchController::class, 'index'])->name('search');
    Route::get('organisasi', [EmployeeController::class, 'orgChart'])->name('organisasi');
    Route::put('organisasi/{employee}/atasan', [EmployeeController::class, 'updateManager'])->name('organisasi.atasan');
    Route::get('menu-builder', [MenuBuilderController::class, 'index'])->name('menu-builder');
    Route::post('menu-builder', [MenuBuilderController::class, 'store'])->name('menu-builder.store');
    Route::put('menu-builder/{menuItem}', [MenuBuilderController::class, 'update'])->name('menu-builder.update');
    Route::delete('menu-builder/{menuItem}', [MenuBuilderController::class, 'destroy'])->name('menu-builder.destroy');
    Route::post('menu-builder/{menuItem}/toggle', [MenuBuilderController::class, 'toggle'])->name('menu-builder.toggle');
    Route::post('menu-builder/{menuItem}/move', [MenuBuilderController::class, 'move'])->name('menu-builder.move');
    Route::post('menu-builder-reorder', [MenuBuilderController::class, 'reorder'])->name('menu-builder.reorder');
    Route::get('custom-fields', [CustomFieldController::class, 'index'])->name('custom-fields');
    Route::post('custom-fields', [CustomFieldController::class, 'store'])->name('custom-fields.store');
    Route::put('custom-fields/{field}', [CustomFieldController::class, 'update'])->name('custom-fields.update');
    Route::delete('custom-fields/{field}', [CustomFieldController::class, 'destroy'])->name('custom-fields.destroy');
    Route::get('employees/bulk', [EmployeeController::class, 'bulkCreate'])->name('employees.bulk');
    Route::get('employees/bulk/template', [EmployeeController::class, 'bulkTemplate'])->name('employees.bulk.template');
    Route::post('employees/bulk/preview', [EmployeeController::class, 'bulkPreview'])->name('employees.bulk.preview');
    Route::post('employees/bulk', [EmployeeController::class, 'bulkStore'])->name('employees.bulk.store');
    Route::delete('employees/bulk-destroy', [EmployeeController::class, 'bulkDestroy'])->name('employees.bulk-destroy');
    Route::post('employees/{employee}/reset-device', [EmployeeController::class, 'resetDevice'])->name('employees.reset-device');
    Route::post('employees/{employee}/toggle-account', [EmployeeController::class, 'toggleAccount'])->name('employees.toggle-account');
    Route::resource('employees', EmployeeController::class);

    Route::get('absensi', [AttendanceController::class, 'index'])->name('absensi');
    Route::get('absensi/monitor', [AttendanceController::class, 'monitor'])->name('absensi.monitor');
    Route::get('absensi/kebijakan', [AttendancePolicyController::class, 'edit'])->name('absensi.kebijakan');
    Route::put('absensi/kebijakan', [AttendancePolicyController::class, 'update'])->name('absensi.kebijakan.update');
    Route::get('absensi/{attendance}', [AttendanceController::class, 'show'])->name('absensi.show');
    Route::delete('absensi/{attendance}', [AttendanceController::class, 'destroy'])->name('absensi.destroy');
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

    // Jenis Cuti (leave types)
    Route::get('cuti/jenis', [LeaveTypeController::class, 'index'])->name('cuti.jenis');
    Route::get('cuti/jenis/create', [LeaveTypeController::class, 'create'])->name('cuti.jenis.create');
    Route::get('cuti/jenis/{leaveType}/edit', [LeaveTypeController::class, 'edit'])->name('cuti.jenis.edit');
    Route::post('cuti/jenis', [LeaveTypeController::class, 'store'])->name('cuti.jenis.store');
    Route::put('cuti/jenis/{leaveType}', [LeaveTypeController::class, 'update'])->name('cuti.jenis.update');
    Route::delete('cuti/jenis/{leaveType}', [LeaveTypeController::class, 'destroy'])->name('cuti.jenis.destroy');

    // Approval Center (unified pending approvals across modules)
    Route::get('approval', [ApprovalController::class, 'index'])->name('approval');
    Route::post('approval/{type}/{id}/approve', [ApprovalController::class, 'approve'])->name('approval.approve');
    Route::post('approval/{type}/{id}/reject', [ApprovalController::class, 'reject'])->name('approval.reject');

    Route::get('payroll', [PayrollController::class, 'index'])->name('payroll');
    Route::get('payroll/periods/create', [PayrollController::class, 'createPeriod'])->name('payroll.periods.create');
    Route::post('payroll/periods', [PayrollController::class, 'storePeriod'])->name('payroll.periods.store');
    Route::get('payroll/perhitungan-hari', [DayCalcMethodController::class, 'index'])->name('payroll.perhitungan-hari');
    Route::post('payroll/perhitungan-hari', [DayCalcMethodController::class, 'store'])->name('payroll.perhitungan-hari.store');
    Route::put('payroll/perhitungan-hari/{perhitunganHari}', [DayCalcMethodController::class, 'update'])->name('payroll.perhitungan-hari.update');
    Route::delete('payroll/perhitungan-hari/{perhitunganHari}', [DayCalcMethodController::class, 'destroy'])->name('payroll.perhitungan-hari.destroy');
    Route::get('payroll/nilai-upah', [SalaryGradeStepController::class, 'index'])->name('payroll.nilai-upah');
    Route::post('payroll/nilai-upah', [SalaryGradeStepController::class, 'store'])->name('payroll.nilai-upah.store');
    Route::delete('payroll/nilai-upah/{nilaiUpah}', [SalaryGradeStepController::class, 'destroy'])->name('payroll.nilai-upah.destroy');
    Route::get('payroll/payday', [PaydayController::class, 'index'])->name('payroll.payday');
    Route::post('payroll/payday', [PaydayController::class, 'store'])->name('payroll.payday.store');
    Route::put('payroll/payday/{payday}', [PaydayController::class, 'update'])->name('payroll.payday.update');
    Route::delete('payroll/payday/{payday}', [PaydayController::class, 'destroy'])->name('payroll.payday.destroy');
    Route::post('payroll/payday/{payday}/assign', [PaydayController::class, 'assign'])->name('payroll.payday.assign');
    Route::post('payroll/run', [PayrollController::class, 'run'])->name('payroll.run');
    Route::post('payroll/approve', [PayrollController::class, 'approve'])->name('payroll.approve');
    Route::post('payroll/reject', [PayrollController::class, 'reject'])->name('payroll.reject');
    Route::post('payroll/lock', [PayrollController::class, 'lock'])->name('payroll.lock');
    Route::post('payroll/unlock', [PayrollController::class, 'unlock'])->name('payroll.unlock');
    Route::post('payroll/thr', [PayrollController::class, 'thr'])->name('payroll.thr');
    Route::get('payroll/transfer', [PayrollController::class, 'transferFile'])->name('payroll.transfer');
    Route::get('payroll/payslip/{item}/pdf', [PayrollController::class, 'payslipPdf'])->name('payroll.payslip.pdf');
    Route::get('payroll/bpjs-export', [PayrollController::class, 'bpjsFile'])->name('payroll.bpjs.export');
    Route::get('payroll/1721/{employee}', [PayrollController::class, 'taxForm1721'])->name('payroll.tax1721');

    // Payroll config: BPJS programs/rates + PPh21 TER
    Route::get('payroll/konfigurasi', [PayrollConfigController::class, 'index'])->name('payroll.konfigurasi');
    Route::put('payroll/konfigurasi/settings', [PayrollConfigController::class, 'updateSettings'])->name('payroll.konfigurasi.settings');
    Route::post('payroll/konfigurasi/bpjs', [PayrollConfigController::class, 'storeBpjsProgram'])->name('payroll.konfigurasi.bpjs.store');
    Route::put('payroll/konfigurasi/bpjs/{program}', [PayrollConfigController::class, 'updateBpjsProgram'])->name('payroll.konfigurasi.bpjs.update');
    Route::delete('payroll/konfigurasi/bpjs/{program}', [PayrollConfigController::class, 'destroyBpjsProgram'])->name('payroll.konfigurasi.bpjs.destroy');
    Route::post('payroll/konfigurasi/ptkp', [PayrollConfigController::class, 'storePtkpRate'])->name('payroll.konfigurasi.ptkp.store');
    Route::delete('payroll/konfigurasi/ptkp/{rate}', [PayrollConfigController::class, 'destroyPtkpRate'])->name('payroll.konfigurasi.ptkp.destroy');
    Route::post('payroll/konfigurasi/pkp', [PayrollConfigController::class, 'storePkpRate'])->name('payroll.konfigurasi.pkp.store');
    Route::delete('payroll/konfigurasi/pkp/{rate}', [PayrollConfigController::class, 'destroyPkpRate'])->name('payroll.konfigurasi.pkp.destroy');
    Route::post('payroll/konfigurasi/tax-profile', [PayrollConfigController::class, 'upsertTaxProfile'])->name('payroll.konfigurasi.tax-profile.upsert');

    // Master Komponen & Master Formula (BPR-manual payroll config)
    Route::get('payroll/komponen', [PayrollKomponenController::class, 'index'])->name('payroll.komponen');
    Route::post('payroll/komponen/component', [PayrollKomponenController::class, 'storeComponent'])->name('payroll.komponen.component.store');
    Route::put('payroll/komponen/component/{component}', [PayrollKomponenController::class, 'updateComponent'])->name('payroll.komponen.component.update');
    Route::delete('payroll/komponen/component/{component}', [PayrollKomponenController::class, 'destroyComponent'])->name('payroll.komponen.component.destroy');
    Route::post('payroll/komponen/component/{component}/toggle', [PayrollKomponenController::class, 'toggleComponent'])->name('payroll.komponen.component.toggle');
    Route::post('payroll/komponen/component/{component}/nilai', [PayrollKomponenController::class, 'storeComponentValue'])->name('payroll.komponen.nilai.store');
    Route::delete('payroll/komponen/nilai/{value}', [PayrollKomponenController::class, 'destroyComponentValue'])->name('payroll.komponen.nilai.destroy');
    Route::post('payroll/komponen/formula', [PayrollKomponenController::class, 'storeFormula'])->name('payroll.komponen.formula.store');
    Route::delete('payroll/komponen/formula/{formula}', [PayrollKomponenController::class, 'destroyFormula'])->name('payroll.komponen.formula.destroy');
    Route::post('payroll/komponen/formula/{formula}/item', [PayrollKomponenController::class, 'storeFormulaItem'])->name('payroll.komponen.formula.item.store');
    Route::delete('payroll/komponen/formula-item/{item}', [PayrollKomponenController::class, 'destroyFormulaItem'])->name('payroll.komponen.formula.item.destroy');

    // Master Gaji (BPR-manual salary template)
    Route::get('payroll/master-gaji', [SalaryMasterController::class, 'index'])->name('payroll.master-gaji');
    Route::get('payroll/master-gaji/{master}/setting', [SalaryMasterController::class, 'setting'])->name('payroll.master-gaji.setting');
    Route::post('payroll/master-gaji', [SalaryMasterController::class, 'store'])->name('payroll.master-gaji.store');
    Route::put('payroll/master-gaji/{master}', [SalaryMasterController::class, 'update'])->name('payroll.master-gaji.update');
    Route::delete('payroll/master-gaji/{master}', [SalaryMasterController::class, 'destroy'])->name('payroll.master-gaji.destroy');
    Route::post('payroll/master-gaji/{master}/component', [SalaryMasterController::class, 'setComponent'])->name('payroll.master-gaji.component');
    Route::post('payroll/master-gaji/{master}/component-amount', [SalaryMasterController::class, 'setComponentAmount'])->name('payroll.master-gaji.component-amount');
    Route::post('payroll/master-gaji/{master}/assign', [SalaryMasterController::class, 'assign'])->name('payroll.master-gaji.assign');

    // UMR (regional minimum wage)
    Route::get('payroll/umr', [PayrollUmrController::class, 'index'])->name('payroll.umr');
    Route::post('payroll/umr', [PayrollUmrController::class, 'store'])->name('payroll.umr.store');
    Route::delete('payroll/umr/{umr}', [PayrollUmrController::class, 'destroy'])->name('payroll.umr.destroy');

    // Koreksi Gaji (payroll corrections)
    Route::get('payroll/koreksi', [PayrollCorrectionController::class, 'index'])->name('payroll.koreksi');
    Route::post('payroll/koreksi', [PayrollCorrectionController::class, 'store'])->name('payroll.koreksi.store');
    Route::post('payroll/koreksi/{correction}/approve', [PayrollCorrectionController::class, 'approve'])->name('payroll.koreksi.approve');
    Route::delete('payroll/koreksi/{correction}', [PayrollCorrectionController::class, 'destroy'])->name('payroll.koreksi.destroy');

    Route::get('payroll/sales-order', [SalesOrderController::class, 'index'])->name('payroll.sales-order');
    Route::post('payroll/sales-order/{salesOrder}/map', [SalesOrderController::class, 'map'])->name('payroll.sales-order.map');
    Route::post('payroll/sales-order/{salesOrder}/forward', [SalesOrderController::class, 'forward'])->name('payroll.sales-order.forward');

    Route::get('payroll/rapel', [SalaryRapelController::class, 'index'])->name('payroll.rapel');
    Route::post('payroll/rapel', [SalaryRapelController::class, 'store'])->name('payroll.rapel.store');
    Route::post('payroll/rapel/{rapel}/approve', [SalaryRapelController::class, 'approve'])->name('payroll.rapel.approve');
    Route::delete('payroll/rapel/{rapel}', [SalaryRapelController::class, 'destroy'])->name('payroll.rapel.destroy');

    Route::get('hak-akses', [AccessController::class, 'index'])->name('hak-akses');
    Route::post('hak-akses/permission/toggle', [AccessController::class, 'togglePermission'])->name('hak-akses.permission.toggle');
    Route::post('hak-akses/feature/toggle', [AccessController::class, 'toggleFeature'])->name('hak-akses.feature.toggle');
    Route::post('hak-akses/menu/toggle', [AccessController::class, 'toggleMenu'])->name('hak-akses.menu.toggle');
    Route::post('hak-akses/menu/visibility', [AccessController::class, 'toggleMenuVisibility'])->name('hak-akses.menu.visibility');
    Route::get('hak-akses/peran/baru', [AccessController::class, 'createRole'])->name('hak-akses.roles.create');
    Route::post('hak-akses/roles', [AccessController::class, 'storeRole'])->name('hak-akses.roles.store');
    Route::post('hak-akses/roles/{role}/mobile', [AccessController::class, 'toggleRoleMobile'])->name('hak-akses.roles.mobile');
    Route::post('hak-akses/roles/{role}/users', [AccessController::class, 'attachRoleUser'])->name('hak-akses.roles.users.attach');
    Route::delete('hak-akses/roles/{role}/users/{member}', [AccessController::class, 'detachRoleUser'])->name('hak-akses.roles.users.detach');
    Route::post('hak-akses/mobile-menu/visibility', [AccessController::class, 'toggleMobileMenuVisibility'])->name('hak-akses.mobile-menu.visibility');
    Route::put('hak-akses/mobile-menu', [AccessController::class, 'updateMobileMenu'])->name('hak-akses.mobile-menu.update');
    Route::put('hak-akses/mobile-menu/order', [AccessController::class, 'reorderMobileMenu'])->name('hak-akses.mobile-menu.order');

    Route::get('fitur', [FeatureController::class, 'index'])->name('fitur');
    Route::post('fitur/toggle', [FeatureController::class, 'toggle'])->name('fitur.toggle');

    Route::get('perusahaan', [CompanySetupController::class, 'index'])->name('perusahaan');
    Route::put('perusahaan/profile', [CompanySetupController::class, 'updateProfile'])->name('perusahaan.profile');
    Route::post('perusahaan/{entity}', [CompanySetupController::class, 'store'])->name('perusahaan.store');
    Route::put('perusahaan/{entity}/{record}', [CompanySetupController::class, 'update'])->name('perusahaan.update');
    Route::delete('perusahaan/{entity}/{record}', [CompanySetupController::class, 'destroy'])->name('perusahaan.destroy');

    Route::get('laporan', [LaporanController::class, 'index'])->name('laporan');
    Route::get('laporan/export/{type}', [LaporanController::class, 'export'])->name('laporan.export');

    // Audit trail
    Route::get('audit', [AuditController::class, 'index'])->name('audit');

    // Mood karyawan (HR view of daily mood check-ins)
    Route::get('mood', [MoodController::class, 'index'])->name('mood');

    // Kontrak kerja (employee contracts)
    Route::get('kontrak', [ContractController::class, 'index'])->name('kontrak');
    Route::get('kontrak/create', [ContractController::class, 'create'])->name('kontrak.create');
    Route::get('kontrak/{contract}/edit', [ContractController::class, 'edit'])->name('kontrak.edit');
    Route::post('kontrak', [ContractController::class, 'store'])->name('kontrak.store');
    Route::put('kontrak/{contract}', [ContractController::class, 'update'])->name('kontrak.update');
    Route::delete('kontrak/{contract}', [ContractController::class, 'destroy'])->name('kontrak.destroy');

    // Roster shift
    Route::get('roster', [RosterController::class, 'index'])->name('roster');
    Route::post('roster', [RosterController::class, 'store'])->name('roster.store');
    Route::post('roster/bulk', [RosterController::class, 'bulkStore'])->name('roster.bulk');
    Route::post('roster/copy-week', [RosterController::class, 'copyPreviousWeek'])->name('roster.copy-week');
    Route::delete('roster/{schedule}', [RosterController::class, 'destroy'])->name('roster.destroy');

    // Mutasi / pergerakan karir karyawan
    Route::get('mutasi', [MovementController::class, 'index'])->name('mutasi');
    Route::get('mutasi/create', [MovementController::class, 'create'])->name('mutasi.create');
    Route::post('mutasi', [MovementController::class, 'store'])->name('mutasi.store');

    // Cash advance (uang muka operasional) — pengajuan, approval, pencairan
    Route::get('kasbon', [CashAdvanceController::class, 'index'])->name('kasbon');
    Route::get('kasbon/create', [CashAdvanceController::class, 'create'])->name('kasbon.create');
    Route::get('kasbon/{cashAdvance}/edit', [CashAdvanceController::class, 'edit'])->name('kasbon.edit');
    Route::post('kasbon', [CashAdvanceController::class, 'store'])->name('kasbon.store');
    Route::put('kasbon/{cashAdvance}', [CashAdvanceController::class, 'update'])->name('kasbon.update');
    Route::delete('kasbon/{cashAdvance}', [CashAdvanceController::class, 'destroy'])->name('kasbon.destroy');
    Route::post('kasbon/{cashAdvance}/approve', [CashAdvanceController::class, 'approve'])->name('kasbon.approve');
    Route::post('kasbon/{cashAdvance}/reject', [CashAdvanceController::class, 'reject'])->name('kasbon.reject');
    Route::post('kasbon/{cashAdvance}/disburse', [CashAdvanceController::class, 'disburse'])->name('kasbon.disburse');
    Route::post('kasbon/{cashAdvance}/settle', [CashAdvanceController::class, 'settle'])->name('kasbon.settle');
    Route::get('kasbon/{cashAdvance}', [CashAdvanceController::class, 'show'])->name('kasbon.show');

    // Settlement — expense claim: submit, manager approve, Finance verify & pay
    Route::get('settlement', [SettlementController::class, 'index'])->name('settlement');
    Route::get('settlement/create', [SettlementController::class, 'create'])->name('settlement.create');
    Route::get('settlement/{settlement}/edit', [SettlementController::class, 'edit'])->name('settlement.edit');
    Route::get('settlement/{settlement}', [SettlementController::class, 'show'])->name('settlement.show');
    Route::post('settlement', [SettlementController::class, 'store'])->name('settlement.store');
    Route::post('settlement/{settlement}', [SettlementController::class, 'update'])->name('settlement.update');
    Route::delete('settlement/{settlement}', [SettlementController::class, 'destroy'])->name('settlement.destroy');
    Route::post('settlement/{settlement}/submit', [SettlementController::class, 'submit'])->name('settlement.submit');
    Route::post('settlement/{settlement}/manager-approve', [SettlementController::class, 'managerApprove'])->name('settlement.manager-approve');
    Route::post('settlement/{settlement}/finance-verify', [SettlementController::class, 'financeVerify'])->name('settlement.finance-verify');
    Route::post('settlement/{settlement}/rescan', [SettlementController::class, 'rescan'])->name('settlement.rescan');
    Route::post('settlement/{settlement}/reject', [SettlementController::class, 'reject'])->name('settlement.reject');

    // Benefit management
    Route::get('benefit', [BenefitController::class, 'index'])->name('benefit');
    Route::get('benefit/create', [BenefitController::class, 'create'])->name('benefit.create');
    Route::get('benefit/{benefit}/edit', [BenefitController::class, 'edit'])->name('benefit.edit');
    Route::post('benefit', [BenefitController::class, 'store'])->name('benefit.store');
    Route::put('benefit/{benefit}', [BenefitController::class, 'update'])->name('benefit.update');
    Route::delete('benefit/{benefit}', [BenefitController::class, 'destroy'])->name('benefit.destroy');
    Route::post('benefit/assign', [BenefitController::class, 'assign'])->name('benefit.assign');
    Route::delete('benefit/assign/{employeeBenefit}', [BenefitController::class, 'unassign'])->name('benefit.unassign');

    // Rekrutmen (ATS) — HumanCore-style multi-page module
    Route::get('rekrutmen', [RecruitmentController::class, 'dashboard'])->name('rekrutmen');
    Route::get('rekrutmen/headcount', [RecruitmentController::class, 'headcount'])->name('rekrutmen.headcount');
    Route::post('rekrutmen/headcount', [RecruitmentController::class, 'storeHeadcount'])->name('rekrutmen.headcount.store');
    Route::post('rekrutmen/headcount/{headcountRequest}/decide', [RecruitmentController::class, 'decideHeadcount'])->name('rekrutmen.headcount.decide');
    Route::get('rekrutmen/sales-order', [RecruitmentController::class, 'salesOrders'])->name('rekrutmen.sales-order');
    Route::post('rekrutmen/sales-order/{salesOrder}/decide', [RecruitmentController::class, 'decideSalesOrder'])->name('rekrutmen.sales-order.decide');
    Route::get('rekrutmen/jobs', [RecruitmentController::class, 'jobs'])->name('rekrutmen.jobs');
    Route::get('rekrutmen/pipeline', [RecruitmentController::class, 'pipeline'])->name('rekrutmen.pipeline');
    Route::get('rekrutmen/candidates', [RecruitmentController::class, 'candidates'])->name('rekrutmen.candidates');
    Route::get('rekrutmen/ai', [RecruitmentController::class, 'aiIntelligence'])->name('rekrutmen.ai');
    Route::post('rekrutmen/ai/analyze', [RecruitmentController::class, 'analyzeAiIntelligence'])->name('rekrutmen.ai.analyze');
    Route::get('rekrutmen/pools', [RecruitmentController::class, 'pools'])->name('rekrutmen.pools');
    Route::post('rekrutmen/pools', [RecruitmentController::class, 'storePool'])->name('rekrutmen.pools.store');
    Route::get('rekrutmen/interviews', [RecruitmentController::class, 'interviews'])->name('rekrutmen.interviews');
    Route::get('rekrutmen/offers', [RecruitmentController::class, 'offers'])->name('rekrutmen.offers');
    Route::post('rekrutmen/offers/{applicant}/decide', [RecruitmentController::class, 'decideOffer'])->name('rekrutmen.offers.decide');
    Route::get('rekrutmen/analytics', [RecruitmentController::class, 'analytics'])->name('rekrutmen.analytics');
    Route::get('rekrutmen/create', [RecruitmentController::class, 'create'])->name('rekrutmen.create');
    Route::get('rekrutmen/{jobPosting}/edit', [RecruitmentController::class, 'edit'])->name('rekrutmen.edit');
    Route::post('rekrutmen', [RecruitmentController::class, 'store'])->name('rekrutmen.store');
    Route::put('rekrutmen/{jobPosting}', [RecruitmentController::class, 'update'])->name('rekrutmen.update');
    Route::delete('rekrutmen/{jobPosting}', [RecruitmentController::class, 'destroy'])->name('rekrutmen.destroy');
    Route::post('rekrutmen/pelamar', [RecruitmentController::class, 'storeApplicant'])->name('rekrutmen.pelamar.store');
    Route::get('rekrutmen/pelamar/{applicant}', [RecruitmentController::class, 'showApplicant'])->name('rekrutmen.pelamar.show');
    Route::post('rekrutmen/pelamar/{applicant}/stage', [RecruitmentController::class, 'moveStage'])->name('rekrutmen.pelamar.stage');
    Route::put('rekrutmen/pelamar/{applicant}', [RecruitmentController::class, 'updateApplicant'])->name('rekrutmen.pelamar.update');
    Route::post('rekrutmen/pelamar/{applicant}/cv', [RecruitmentController::class, 'uploadCv'])->name('rekrutmen.pelamar.cv');
    Route::post('rekrutmen/pelamar/{applicant}/interview', [RecruitmentController::class, 'scheduleInterview'])->name('rekrutmen.pelamar.interview');
    Route::post('rekrutmen/pelamar/{applicant}/offer', [RecruitmentController::class, 'makeOffer'])->name('rekrutmen.pelamar.offer');
    Route::post('rekrutmen/pelamar/{applicant}/medical', [RecruitmentController::class, 'storeMedicalCheck'])->name('rekrutmen.pelamar.medical');
    Route::post('rekrutmen/pelamar/{applicant}/background', [RecruitmentController::class, 'storeBackgroundCheck'])->name('rekrutmen.pelamar.background');
    Route::post('rekrutmen/pelamar/{applicant}/blacklist', [RecruitmentController::class, 'toggleBlacklist'])->name('rekrutmen.pelamar.blacklist');
    Route::post('rekrutmen/pelamar/{applicant}/interview-result', [RecruitmentController::class, 'recordInterviewResult'])->name('rekrutmen.pelamar.interview-result');
    Route::post('rekrutmen/pelamar/{applicant}/activate', [RecruitmentController::class, 'activateEmployee'])->name('rekrutmen.pelamar.activate');
    Route::post('rekrutmen/pelamar/{applicant}/onboarding/start', [RecruitmentController::class, 'startOnboarding'])->name('rekrutmen.pelamar.onboarding.start');
    Route::post('rekrutmen/onboarding-item/{item}/toggle', [RecruitmentController::class, 'toggleOnboardingItem'])->name('rekrutmen.onboarding-item.toggle');

    // Rekrutmen ATS flow — stage 1 (Hiring Request) & stage 9 (Candidate Progress)
    Route::get('rekrutmen/hiring-request', [HiringRequestController::class, 'index'])->name('rekrutmen.hiring-request');
    Route::post('rekrutmen/hiring-request', [HiringRequestController::class, 'store'])->name('rekrutmen.hiring-request.store');
    Route::put('rekrutmen/hiring-request/{hiringRequest}', [HiringRequestController::class, 'update'])->name('rekrutmen.hiring-request.update');
    Route::post('rekrutmen/hiring-request/{hiringRequest}/close', [HiringRequestController::class, 'close'])->name('rekrutmen.hiring-request.close');
    Route::delete('rekrutmen/hiring-request/{hiringRequest}', [HiringRequestController::class, 'destroy'])->name('rekrutmen.hiring-request.destroy');
    Route::get('rekrutmen/progress', [HiringRequestController::class, 'progress'])->name('rekrutmen.progress');

    // Rekrutmen ATS flow — stage 2 (Requisition) & stage 3 (Publish Vacancy)
    Route::get('rekrutmen/requisition', [RecruitmentRequisitionController::class, 'index'])->name('rekrutmen.requisition');
    Route::post('rekrutmen/requisition', [RecruitmentRequisitionController::class, 'store'])->name('rekrutmen.requisition.store');
    Route::put('rekrutmen/requisition/{requisition}', [RecruitmentRequisitionController::class, 'update'])->name('rekrutmen.requisition.update');
    Route::post('rekrutmen/requisition/{requisition}/publish', [RecruitmentRequisitionController::class, 'publish'])->name('rekrutmen.requisition.publish');
    Route::delete('rekrutmen/requisition/{requisition}', [RecruitmentRequisitionController::class, 'destroy'])->name('rekrutmen.requisition.destroy');

    // Perjalanan dinas (duty travel)
    Route::get('dinas', [DutyTravelController::class, 'index'])->name('dinas');
    Route::get('dinas/create', [DutyTravelController::class, 'create'])->name('dinas.create');
    Route::post('dinas', [DutyTravelController::class, 'store'])->name('dinas.store');
    Route::post('dinas/{dutyTravel}/approve', [DutyTravelController::class, 'approve'])->name('dinas.approve');
    Route::post('dinas/{dutyTravel}/reject', [DutyTravelController::class, 'reject'])->name('dinas.reject');

    // Sanksi absensi (attendance penalties)
    Route::get('sanksi', [AttendancePenaltyController::class, 'index'])->name('sanksi');
    Route::get('sanksi/create', [AttendancePenaltyController::class, 'create'])->name('sanksi.create');
    Route::post('sanksi', [AttendancePenaltyController::class, 'store'])->name('sanksi.store');
    Route::post('sanksi/generate', [AttendancePenaltyController::class, 'generate'])->name('sanksi.generate');
    Route::delete('sanksi/{penalty}', [AttendancePenaltyController::class, 'destroy'])->name('sanksi.destroy');

    // Visiting pekerjaan (field visits)
    Route::get('visiting', [FieldVisitController::class, 'index'])->name('visiting');
    Route::get('visiting/create', [FieldVisitController::class, 'create'])->name('visiting.create');
    Route::get('visiting/{visit}', [FieldVisitController::class, 'show'])->name('visiting.show');
    Route::post('visiting', [FieldVisitController::class, 'store'])->name('visiting.store');
    Route::get('visiting/{visit}/edit', [FieldVisitController::class, 'edit'])->name('visiting.edit');
    Route::post('visiting/{visit}', [FieldVisitController::class, 'update'])->name('visiting.update');
    Route::post('visiting/{visit}/tasks/{task}/toggle', [FieldVisitController::class, 'toggleTask'])->name('visiting.tasks.toggle');
    Route::delete('visiting/{visit}', [FieldVisitController::class, 'destroy'])->name('visiting.destroy');

    // User management (Pengguna)
    Route::get('pengguna', [UserController::class, 'index'])->name('pengguna');
    Route::get('pengguna/create', [UserController::class, 'create'])->name('pengguna.create');
    Route::get('pengguna/{user}/edit', [UserController::class, 'edit'])->name('pengguna.edit');
    Route::post('pengguna', [UserController::class, 'store'])->name('pengguna.store');
    Route::put('pengguna/{user}', [UserController::class, 'update'])->name('pengguna.update');
    Route::put('pengguna/{user}/overrides', [UserController::class, 'updateOverrides'])->name('pengguna.overrides');
    Route::delete('pengguna/{user}', [UserController::class, 'destroy'])->name('pengguna.destroy');
    Route::post('pengguna/{user}/toggle', [UserController::class, 'toggleStatus'])->name('pengguna.toggle');
    Route::post('pengguna/{user}/reset-device', [UserController::class, 'resetDevice'])->name('pengguna.reset-device');

    // Tenant / client management (super admin)
    Route::get('klien', [TenantController::class, 'index'])->name('klien');
    Route::get('klien/create', [TenantController::class, 'create'])->name('klien.create');
    Route::get('klien/{tenant}', [TenantController::class, 'show'])->name('klien.show');
    Route::get('klien/{tenant}/edit', [TenantController::class, 'edit'])->name('klien.edit');
    Route::post('klien', [TenantController::class, 'store'])->name('klien.store');
    Route::put('klien/{tenant}', [TenantController::class, 'update'])->name('klien.update');
    Route::delete('klien/{tenant}', [TenantController::class, 'destroy'])->name('klien.destroy');
    Route::post('klien/{tenant}/feature', [TenantController::class, 'toggleFeature'])->name('klien.feature.toggle');
    Route::post('klien/{tenant}/admin', [TenantController::class, 'storeAdmin'])->name('klien.admin.store');
    Route::post('klien/{tenant}/admin/{user}/password', [TenantController::class, 'resetAdminPassword'])->name('klien.admin.password');

    // Billing & Invoice (super admin) — client subscriptions + invoices
    Route::get('billing', [BillingController::class, 'index'])->name('billing');
    Route::get('billing/subscription/create', [BillingController::class, 'createSubscription'])->name('billing.subscription.create');
    Route::get('billing/invoice/create', [BillingController::class, 'createInvoice'])->name('billing.invoice.create');
    Route::post('billing/subscription', [BillingController::class, 'storeSubscription'])->name('billing.subscription.store');
    Route::put('billing/subscription/{subscription}', [BillingController::class, 'updateSubscription'])->name('billing.subscription.update');
    Route::post('billing/invoice', [BillingController::class, 'storeInvoice'])->name('billing.invoice.store');
    Route::post('billing/subscription/{subscription}/generate', [BillingController::class, 'generateInvoice'])->name('billing.invoice.generate');
    Route::get('billing/invoice/{invoice}/cetak', [BillingController::class, 'printInvoice'])->name('billing.invoice.print');
    Route::post('billing/invoice/{invoice}/pay', [BillingController::class, 'markPaid'])->name('billing.invoice.pay');
    Route::post('billing/invoice/{invoice}/cancel', [BillingController::class, 'cancelInvoice'])->name('billing.invoice.cancel');
    Route::delete('billing/invoice/{invoice}', [BillingController::class, 'destroyInvoice'])->name('billing.invoice.destroy');

    // Katalog Fitur (super admin) — CRUD the product feature catalog; new rows
    // appear in the Hak Akses matrix + become enable-able per tenant, no code.
    Route::get('katalog-fitur', [FeatureCatalogController::class, 'index'])->name('katalog-fitur');
    Route::post('katalog-fitur', [FeatureCatalogController::class, 'store'])->name('katalog-fitur.store');
    Route::put('katalog-fitur/{feature}', [FeatureCatalogController::class, 'update'])->name('katalog-fitur.update');
    Route::delete('katalog-fitur/{feature}', [FeatureCatalogController::class, 'destroy'])->name('katalog-fitur.destroy');

    // Pengaturan website (super admin) — edit-only, single settings row
    Route::get('website-settings', [WebsiteSettingController::class, 'edit'])->name('website-settings');
    Route::post('website-settings', [WebsiteSettingController::class, 'update'])->name('website-settings.update');

    // Pengaturan AI (super admin) — provider, API key & model for the assistant
    Route::get('ai-settings', [AiSettingController::class, 'edit'])->name('ai-settings');
    Route::post('ai-settings', [AiSettingController::class, 'update'])->name('ai-settings.update');

    // Paket Token AI (super admin) — pricing catalogue + tenant purchase overview
    Route::get('token-packs', [AiTokenPackController::class, 'index'])->name('token-packs');
    Route::post('token-packs', [AiTokenPackController::class, 'store'])->name('token-packs.store');
    Route::put('token-packs/{pack}', [AiTokenPackController::class, 'update'])->name('token-packs.update');
    Route::delete('token-packs/{pack}', [AiTokenPackController::class, 'destroy'])->name('token-packs.destroy');

    // Subscription packages / pricing tiers (super admin)
    Route::get('paket', [PackageController::class, 'index'])->name('paket');
    Route::post('paket', [PackageController::class, 'store'])->name('paket.store');
    Route::put('paket/{package}', [PackageController::class, 'update'])->name('paket.update');
    Route::delete('paket/{package}', [PackageController::class, 'destroy'])->name('paket.destroy');

    // Pengaturan Email (super admin = platform default, admin tenant = override)
    Route::get('email-settings', [EmailSettingController::class, 'edit'])->name('email-settings');
    Route::post('email-settings', [EmailSettingController::class, 'update'])->name('email-settings.update');
    Route::post('email-settings/test', [EmailSettingController::class, 'test'])->name('email-settings.test');

    // Onboarding slides — mobile app intro carousel (super admin)
    Route::get('onboarding-slides', [OnboardingSlideController::class, 'index'])->name('onboarding-slides');
    Route::post('onboarding-slides', [OnboardingSlideController::class, 'store'])->name('onboarding-slides.store');
    Route::post('onboarding-slides/{onboardingSlide}', [OnboardingSlideController::class, 'update'])->name('onboarding-slides.update');
    Route::delete('onboarding-slides/{onboardingSlide}', [OnboardingSlideController::class, 'destroy'])->name('onboarding-slides.destroy');

    // Kinerja (performance management)
    Route::get('kinerja', [PerformanceController::class, 'index'])->name('kinerja');
    Route::get('kinerja/hav', [PerformanceController::class, 'hav'])->name('kinerja.hav');
    Route::get('kinerja/create', [PerformanceController::class, 'create'])->name('kinerja.create');
    Route::get('kinerja/{review}/edit', [PerformanceController::class, 'edit'])->name('kinerja.edit');
    Route::post('kinerja', [PerformanceController::class, 'store'])->name('kinerja.store');
    Route::put('kinerja/{review}', [PerformanceController::class, 'update'])->name('kinerja.update');
    Route::delete('kinerja/{review}', [PerformanceController::class, 'destroy'])->name('kinerja.destroy');
    Route::post('kinerja/cycle', [PerformanceController::class, 'storeCycle'])->name('kinerja.cycle.store');
    Route::post('kinerja/{review}/score', [PerformanceController::class, 'submitScore'])->name('kinerja.score');
    Route::post('kinerja/{review}/calibrate', [PerformanceController::class, 'calibrate'])->name('kinerja.calibrate');
    // 360 feedback on a performance review
    Route::post('kinerja/{review}/feedback', [PerformanceController::class, 'storeFeedback'])->name('kinerja.feedback.store');
    Route::delete('kinerja/feedback/{feedback}', [PerformanceController::class, 'destroyFeedback'])->name('kinerja.feedback.destroy');

    // OKR & Goal (objectives + key results)
    Route::get('okr', [OkrController::class, 'index'])->name('okr');
    Route::get('okr/create', [OkrController::class, 'create'])->name('okr.create');
    Route::get('okr/{objective}/edit', [OkrController::class, 'edit'])->name('okr.edit');
    Route::post('okr', [OkrController::class, 'store'])->name('okr.store');
    Route::put('okr/{objective}', [OkrController::class, 'update'])->name('okr.update');
    Route::delete('okr/{objective}', [OkrController::class, 'destroy'])->name('okr.destroy');
    Route::post('okr/{objective}/key-result', [OkrController::class, 'storeKeyResult'])->name('okr.kr.store');
    Route::put('okr/key-result/{keyResult}', [OkrController::class, 'updateKeyResult'])->name('okr.kr.update');
    Route::delete('okr/key-result/{keyResult}', [OkrController::class, 'destroyKeyResult'])->name('okr.kr.destroy');

    // Dokumen Karyawan (employee documents)
    Route::get('dokumen', [DokumenController::class, 'index'])->name('dokumen');
    Route::post('dokumen', [DokumenController::class, 'store'])->name('dokumen.store');
    Route::delete('dokumen/{document}', [DokumenController::class, 'destroy'])->name('dokumen.destroy');

    // Sosmed (employee social wall: moderation + category master + leaderboard)
    Route::get('sosmed', [SocialController::class, 'index'])->name('sosmed');
    Route::post('sosmed/kategori', [SocialController::class, 'storeCategory'])->name('sosmed.kategori.store');
    Route::put('sosmed/kategori/{socialCategory}', [SocialController::class, 'updateCategory'])->name('sosmed.kategori.update');
    Route::delete('sosmed/kategori/{socialCategory}', [SocialController::class, 'destroyCategory'])->name('sosmed.kategori.destroy');
    Route::put('sosmed/post/{post}/visibility', [SocialController::class, 'toggleVisibility'])->name('sosmed.post.visibility');
    Route::delete('sosmed/post/{post}', [SocialController::class, 'destroyPost'])->name('sosmed.post.destroy');
    Route::post('sosmed/eotm/periode', [SocialController::class, 'storePeriod'])->name('sosmed.eotm.store');
    Route::put('sosmed/eotm/periode/{eotmPeriod}', [SocialController::class, 'updatePeriod'])->name('sosmed.eotm.update');
    Route::post('sosmed/eotm/periode/{eotmPeriod}/tutup', [SocialController::class, 'closePeriod'])->name('sosmed.eotm.close');
    Route::post('sosmed/eotm/periode/{eotmPeriod}/buka-ulang', [SocialController::class, 'reopenPeriod'])->name('sosmed.eotm.reopen');
    Route::post('sosmed/eotm/core-value', [SocialController::class, 'storeCoreValue'])->name('sosmed.eotm.value.store');
    Route::delete('sosmed/eotm/core-value/{eotmCoreValue}', [SocialController::class, 'destroyCoreValue'])->name('sosmed.eotm.value.destroy');

    // SOP (jenis SOP master + PDF documents fed to the AI assistant)
    Route::get('sop', [SopController::class, 'index'])->name('sop');
    Route::post('sop', [SopController::class, 'store'])->name('sop.store');
    // `sop/jenis*` must stay ahead of `sop/{sop}` so the literal segment wins.
    Route::post('sop/jenis', [SopController::class, 'storeCategory'])->name('sop.jenis.store');
    Route::put('sop/jenis/{sopCategory}', [SopController::class, 'updateCategory'])->name('sop.jenis.update');
    Route::delete('sop/jenis/{sopCategory}', [SopController::class, 'destroyCategory'])->name('sop.jenis.destroy');
    Route::get('sop/{sop}/unduh', [SopController::class, 'download'])->name('sop.download');
    // POST (not PUT) so the edit form can carry a replacement PDF upload.
    Route::post('sop/{sop}', [SopController::class, 'update'])->name('sop.update');
    Route::delete('sop/{sop}', [SopController::class, 'destroy'])->name('sop.destroy');

    // Template Surat (HR letter templates + generated letters)
    Route::get('surat', [LetterTemplateController::class, 'index'])->name('surat');
    Route::get('surat/create', [LetterTemplateController::class, 'create'])->name('surat.create');
    Route::get('surat/{letterTemplate}/edit', [LetterTemplateController::class, 'edit'])->name('surat.edit');
    Route::post('surat', [LetterTemplateController::class, 'store'])->name('surat.store');
    Route::put('surat/{letterTemplate}', [LetterTemplateController::class, 'update'])->name('surat.update');
    Route::delete('surat/{letterTemplate}', [LetterTemplateController::class, 'destroy'])->name('surat.destroy');
    Route::post('surat/generate', [LetterTemplateController::class, 'generate'])->name('surat.generate');
    Route::get('surat/cetak/{generatedLetter}', [LetterTemplateController::class, 'print'])->name('surat.print');
    Route::delete('surat/dokumen/{generatedLetter}', [LetterTemplateController::class, 'destroyGenerated'])->name('surat.generated.destroy');

    // Pembelajaran (learning / LMS)
    Route::get('pembelajaran', [LearningController::class, 'index'])->name('pembelajaran');
    Route::get('pembelajaran/create', [LearningController::class, 'create'])->name('pembelajaran.create');
    Route::get('pembelajaran/{training}/edit', [LearningController::class, 'edit'])->name('pembelajaran.edit');
    Route::post('pembelajaran', [LearningController::class, 'store'])->name('pembelajaran.store');
    Route::put('pembelajaran/{training}', [LearningController::class, 'update'])->name('pembelajaran.update');
    Route::delete('pembelajaran/{training}', [LearningController::class, 'destroy'])->name('pembelajaran.destroy');
    Route::post('pembelajaran/enroll', [LearningController::class, 'enroll'])->name('pembelajaran.enroll');
    Route::put('pembelajaran/enroll/{enrollment}', [LearningController::class, 'updateEnrollment'])->name('pembelajaran.enroll.update');

    // Klaim & reimbursement
    Route::get('klaim', [ClaimController::class, 'index'])->name('klaim');
    Route::get('klaim/create', [ClaimController::class, 'create'])->name('klaim.create');
    Route::get('klaim/{claim}/edit', [ClaimController::class, 'edit'])->name('klaim.edit');
    Route::post('klaim', [ClaimController::class, 'store'])->name('klaim.store');
    Route::put('klaim/{claim}', [ClaimController::class, 'update'])->name('klaim.update');
    Route::delete('klaim/{claim}', [ClaimController::class, 'destroy'])->name('klaim.destroy');
    Route::post('klaim/{claim}/approve', [ClaimController::class, 'approve'])->name('klaim.approve');
    Route::post('klaim/{claim}/reject', [ClaimController::class, 'reject'])->name('klaim.reject');
    Route::post('klaim/{claim}/pay', [ClaimController::class, 'markPaid'])->name('klaim.pay');

    // Reimbursement — penggantian biaya yang sudah ditalangi karyawan
    Route::get('reimbursement', [ReimbursementController::class, 'index'])->name('reimbursement');
    Route::get('reimbursement/create', [ReimbursementController::class, 'create'])->name('reimbursement.create');
    Route::get('reimbursement/{reimbursement}/edit', [ReimbursementController::class, 'edit'])->name('reimbursement.edit');
    Route::post('reimbursement', [ReimbursementController::class, 'store'])->name('reimbursement.store');
    Route::put('reimbursement/{reimbursement}', [ReimbursementController::class, 'update'])->name('reimbursement.update');
    Route::delete('reimbursement/{reimbursement}', [ReimbursementController::class, 'destroy'])->name('reimbursement.destroy');
    Route::post('reimbursement/{reimbursement}/approve', [ReimbursementController::class, 'approve'])->name('reimbursement.approve');
    Route::post('reimbursement/{reimbursement}/reject', [ReimbursementController::class, 'reject'])->name('reimbursement.reject');
    Route::post('reimbursement/{reimbursement}/pay', [ReimbursementController::class, 'markPaid'])->name('reimbursement.pay');

    // HR Helpdesk (ticketing)
    Route::get('helpdesk', [HelpdeskController::class, 'index'])->name('helpdesk');
    Route::get('helpdesk/create', [HelpdeskController::class, 'create'])->name('helpdesk.create');
    Route::get('helpdesk/{ticket}/edit', [HelpdeskController::class, 'edit'])->name('helpdesk.edit');
    Route::post('helpdesk', [HelpdeskController::class, 'store'])->name('helpdesk.store');
    Route::put('helpdesk/{ticket}', [HelpdeskController::class, 'update'])->name('helpdesk.update');
    Route::delete('helpdesk/{ticket}', [HelpdeskController::class, 'destroy'])->name('helpdesk.destroy');
    Route::post('helpdesk/{ticket}/assign', [HelpdeskController::class, 'assign'])->name('helpdesk.assign');
    Route::post('helpdesk/{ticket}/status', [HelpdeskController::class, 'changeStatus'])->name('helpdesk.status');
    Route::post('helpdesk/{ticket}/reply', [HelpdeskController::class, 'reply'])->name('helpdesk.reply');

    // HR Analytics + Dynamic Report
    Route::get('analytics', [AnalyticsController::class, 'index'])->name('analytics');
    Route::get('attrition', [AttritionController::class, 'index'])->name('attrition');
    Route::get('attrition/settings', [AttritionController::class, 'settings'])->name('attrition.settings');
    Route::put('attrition/settings', [AttritionController::class, 'updateSettings'])->name('attrition.settings.update');
    Route::get('attrition/{employee}', [AttritionController::class, 'show'])->name('attrition.show');
    Route::get('dynamic-report', [DynamicReportController::class, 'index'])->name('dynamic-report');
    Route::post('dynamic-report', [DynamicReportController::class, 'store'])->name('dynamic-report.store');
    Route::get('dynamic-report/{report}/run', [DynamicReportController::class, 'run'])->name('dynamic-report.run');
    Route::get('dynamic-report/{report}/export', [DynamicReportController::class, 'export'])->name('dynamic-report.export');
    Route::delete('dynamic-report/{report}', [DynamicReportController::class, 'destroy'])->name('dynamic-report.destroy');
    Route::get('report-studio', [ReportStudioController::class, 'index'])->name('report-studio');
    Route::post('report-studio/run', [ReportStudioController::class, 'run'])->name('report-studio.run');
    Route::get('report-studio/export', [ReportStudioController::class, 'export'])->name('report-studio.export');
    Route::post('report-studio/reports', [ReportStudioController::class, 'store'])->name('report-studio.store');
    Route::delete('report-studio/reports/{report}', [ReportStudioController::class, 'destroy'])->name('report-studio.destroy');

    // Manajemen aset
    Route::get('aset', [AssetController::class, 'index'])->name('aset');
    Route::get('aset/create', [AssetController::class, 'create'])->name('aset.create');
    Route::get('aset/{asset}/edit', [AssetController::class, 'edit'])->name('aset.edit');
    Route::get('aset/{asset}/qr', [AssetController::class, 'qr'])->name('aset.qr');
    Route::get('aset/{asset}', [AssetController::class, 'show'])->name('aset.show');
    Route::post('aset', [AssetController::class, 'store'])->name('aset.store');
    Route::put('aset/{asset}', [AssetController::class, 'update'])->name('aset.update');
    Route::delete('aset/{asset}', [AssetController::class, 'destroy'])->name('aset.destroy');
    Route::post('aset/{asset}/assign', [AssetController::class, 'assign'])->name('aset.assign');
    Route::post('aset/assignment/{assignment}/return', [AssetController::class, 'returnAsset'])->name('aset.return');

    // ===== Enterprise-tier modules (wave: CRM, Talent, lifecycle, finance, planning) =====

    // CRM (sales pipeline)
    Route::get('crm', [CrmController::class, 'index'])->name('crm');
    Route::post('crm/contact', [CrmController::class, 'storeContact'])->name('crm.contact.store');
    Route::post('crm/deal', [CrmController::class, 'storeDeal'])->name('crm.deal.store');
    Route::put('crm/deal/{deal}', [CrmController::class, 'updateDeal'])->name('crm.deal.update');
    Route::post('crm/deal/{deal}/stage', [CrmController::class, 'moveStage'])->name('crm.deal.stage');
    Route::delete('crm/deal/{deal}', [CrmController::class, 'destroyDeal'])->name('crm.deal.destroy');
    Route::get('crm/insights', [CrmController::class, 'insights'])->name('crm.insights');
    Route::get('crm/deal/{deal}', [CrmController::class, 'show'])->name('crm.deal.show');
    Route::post('crm/deal/{deal}/activity', [CrmController::class, 'storeActivity'])->name('crm.deal.activity.store');
    Route::delete('crm/activity/{activity}', [CrmController::class, 'destroyActivity'])->name('crm.activity.destroy');
    Route::post('crm/deal/{deal}/task', [CrmController::class, 'storeTask'])->name('crm.deal.task.store');
    Route::post('crm/task/{task}/toggle', [CrmController::class, 'toggleTask'])->name('crm.task.toggle');
    Route::delete('crm/task/{task}', [CrmController::class, 'destroyTask'])->name('crm.task.destroy');
    Route::post('crm/deal/{deal}/member', [CrmController::class, 'storeMember'])->name('crm.deal.member.store');
    Route::delete('crm/member/{member}', [CrmController::class, 'destroyMember'])->name('crm.member.destroy');
    Route::post('crm/deal/{deal}/project', [CrmController::class, 'linkProject'])->name('crm.deal.project');

    // Talenta & Suksesi (9-box + succession)
    Route::get('talenta', [TalentController::class, 'index'])->name('talenta');
    Route::post('talenta', [TalentController::class, 'store'])->name('talenta.store');
    Route::put('talenta/{assessment}', [TalentController::class, 'update'])->name('talenta.update');
    Route::delete('talenta/{assessment}', [TalentController::class, 'destroy'])->name('talenta.destroy');

    // Kompetensi (competency framework)
    Route::get('kompetensi', [CompetencyController::class, 'index'])->name('kompetensi');
    Route::post('kompetensi', [CompetencyController::class, 'store'])->name('kompetensi.store');
    Route::put('kompetensi/{competency}', [CompetencyController::class, 'update'])->name('kompetensi.update');
    Route::delete('kompetensi/{competency}', [CompetencyController::class, 'destroy'])->name('kompetensi.destroy');
    Route::post('kompetensi/assess', [CompetencyController::class, 'assess'])->name('kompetensi.assess');

    // Onboarding
    Route::get('onboarding', [OnboardingController::class, 'index'])->name('onboarding');
    Route::post('onboarding', [OnboardingController::class, 'store'])->name('onboarding.store');
    Route::post('onboarding/{program}/task', [OnboardingController::class, 'storeTask'])->name('onboarding.task.store');
    Route::post('onboarding/task/{task}/toggle', [OnboardingController::class, 'toggleTask'])->name('onboarding.task.toggle');
    Route::delete('onboarding/{program}', [OnboardingController::class, 'destroy'])->name('onboarding.destroy');

    // Offboarding & clearance
    Route::get('offboarding', [OffboardingController::class, 'index'])->name('offboarding');
    Route::post('offboarding', [OffboardingController::class, 'store'])->name('offboarding.store');
    Route::post('offboarding/item/{item}/toggle', [OffboardingController::class, 'toggleItem'])->name('offboarding.item.toggle');
    Route::post('offboarding/{case}/settlement', [OffboardingController::class, 'settlement'])->name('offboarding.settlement');
    Route::delete('offboarding/{case}', [OffboardingController::class, 'destroy'])->name('offboarding.destroy');

    // Pengumuman (announcements)
    Route::get('pengumuman', [AnnouncementController::class, 'index'])->name('pengumuman');
    Route::post('pengumuman', [AnnouncementController::class, 'store'])->name('pengumuman.store');
    Route::post('pengumuman/{announcement}/publish', [AnnouncementController::class, 'publish'])->name('pengumuman.publish');
    // POST, not PUT: the edit form carries a file, and multipart cannot be sent
    // over a spoofed PUT. Must stay below the literal `publish` segment.
    Route::post('pengumuman/{announcement}', [AnnouncementController::class, 'update'])->name('pengumuman.update');
    Route::delete('pengumuman/{announcement}', [AnnouncementController::class, 'destroy'])->name('pengumuman.destroy');

    // Survei Karyawan
    Route::get('survei', [SurveyController::class, 'index'])->name('survei');
    Route::post('survei', [SurveyController::class, 'store'])->name('survei.store');
    Route::post('survei/{survey}/question', [SurveyController::class, 'storeQuestion'])->name('survei.question.store');
    Route::post('survei/{survey}/respond', [SurveyController::class, 'respond'])->name('survei.respond');
    Route::delete('survei/{survey}', [SurveyController::class, 'destroy'])->name('survei.destroy');

    // Pinjaman (employee loans)
    Route::get('pinjaman', [LoanController::class, 'index'])->name('pinjaman');
    Route::post('pinjaman', [LoanController::class, 'store'])->name('pinjaman.store');
    Route::post('pinjaman/{loan}/approve', [LoanController::class, 'approve'])->name('pinjaman.approve');
    Route::post('pinjaman/{loan}/reject', [LoanController::class, 'reject'])->name('pinjaman.reject');
    Route::delete('pinjaman/{loan}', [LoanController::class, 'destroy'])->name('pinjaman.destroy');

    // Timesheet (project time)
    Route::get('timesheet', [TimesheetController::class, 'index'])->name('timesheet');
    Route::post('timesheet/project', [TimesheetController::class, 'storeProject'])->name('timesheet.project.store');
    Route::post('timesheet', [TimesheetController::class, 'store'])->name('timesheet.store');
    Route::delete('timesheet/{timesheet}', [TimesheetController::class, 'destroy'])->name('timesheet.destroy');

    // Tukar Shift (shift swap)
    Route::get('shift-swap', [ShiftSwapController::class, 'index'])->name('shift-swap');
    Route::post('shift-swap', [ShiftSwapController::class, 'store'])->name('shift-swap.store');
    Route::post('shift-swap/{swap}/approve', [ShiftSwapController::class, 'approve'])->name('shift-swap.approve');
    Route::post('shift-swap/{swap}/reject', [ShiftSwapController::class, 'reject'])->name('shift-swap.reject');
    Route::delete('shift-swap/{swap}', [ShiftSwapController::class, 'destroy'])->name('shift-swap.destroy');

    // Delegasi Approval
    Route::get('delegasi', [ApprovalDelegationController::class, 'index'])->name('delegasi');
    Route::post('delegasi', [ApprovalDelegationController::class, 'store'])->name('delegasi.store');
    Route::post('delegasi/{delegation}/toggle', [ApprovalDelegationController::class, 'toggle'])->name('delegasi.toggle');
    Route::delete('delegasi/{delegation}', [ApprovalDelegationController::class, 'destroy'])->name('delegasi.destroy');

    // Setup Alur Persetujuan (approval workflow builder)
    Route::get('approval-workflow', [ApprovalWorkflowController::class, 'index'])->name('approval-workflow');
    Route::post('approval-workflow', [ApprovalWorkflowController::class, 'store'])->name('approval-workflow.store');
    Route::put('approval-workflow/{workflow}', [ApprovalWorkflowController::class, 'update'])->name('approval-workflow.update');
    Route::post('approval-workflow/{workflow}/toggle', [ApprovalWorkflowController::class, 'toggle'])->name('approval-workflow.toggle');
    Route::delete('approval-workflow/{workflow}', [ApprovalWorkflowController::class, 'destroy'])->name('approval-workflow.destroy');

    // Jurnal Akuntansi (GL export from payroll)
    Route::get('jurnal', [JournalController::class, 'index'])->name('jurnal');
    Route::post('jurnal/generate', [JournalController::class, 'generate'])->name('jurnal.generate');
    Route::delete('jurnal/{entry}', [JournalController::class, 'destroy'])->name('jurnal.destroy');

    // Struktur & Skala Upah (salary grades)
    Route::get('struktur-upah', [SalaryStructureController::class, 'index'])->name('struktur-upah');
    Route::post('struktur-upah', [SalaryStructureController::class, 'store'])->name('struktur-upah.store');
    Route::put('struktur-upah/{grade}', [SalaryStructureController::class, 'update'])->name('struktur-upah.update');
    Route::delete('struktur-upah/{grade}', [SalaryStructureController::class, 'destroy'])->name('struktur-upah.destroy');

    // Kalender / COE (calendar of events)
    Route::get('kalender', [CalendarController::class, 'index'])->name('kalender');
    Route::post('kalender', [CalendarController::class, 'store'])->name('kalender.store');
    Route::put('kalender/{event}', [CalendarController::class, 'update'])->name('kalender.update');
    Route::delete('kalender/{event}', [CalendarController::class, 'destroy'])->name('kalender.destroy');

    // Anggaran / Budget Planner
    Route::get('anggaran', [BudgetController::class, 'index'])->name('anggaran');
    Route::post('anggaran', [BudgetController::class, 'store'])->name('anggaran.store');
    Route::put('anggaran/{budget}', [BudgetController::class, 'update'])->name('anggaran.update');
    Route::delete('anggaran/{budget}', [BudgetController::class, 'destroy'])->name('anggaran.destroy');

    // AI Assistant (Prism + OpenAI streaming)
    Route::get('ai', [AiAssistantController::class, 'index'])->name('ai');
    Route::post('ai/stream', [AiAssistantController::class, 'stream'])->name('ai.stream');
    Route::delete('ai/conversation/{conversation}', [AiAssistantController::class, 'destroyConversation'])->name('ai.conversation.destroy');

    // Tampilan & Tema (tenant admin/HR) — recolour sidebar & topbar
    Route::get('tampilan', [TenantAppearanceController::class, 'edit'])->name('tampilan');
    Route::post('tampilan', [TenantAppearanceController::class, 'update'])->name('tampilan.update');
    Route::post('tampilan/reset', [TenantAppearanceController::class, 'reset'])->name('tampilan.reset');
    Route::post('tampilan/logo', [TenantAppearanceController::class, 'updateLogo'])->name('tampilan.logo');
    Route::delete('tampilan/logo', [TenantAppearanceController::class, 'removeLogo'])->name('tampilan.logo.remove');

    // Layanan Saya (employee self-service) — every route is scoped to the
    // signed-in user's own employee record, mirroring the /api/v1/me endpoints
    // the mobile app uses.
    Route::prefix('saya')->name('saya.')->group(function () {
        Route::get('profil', [EssProfileController::class, 'edit'])->name('profil');
        Route::put('profil', [EssProfileController::class, 'update'])->name('profil.update');
        Route::post('profil/foto', [EssProfileController::class, 'updatePhoto'])->name('profil.foto');

        Route::get('absensi', [EssAttendanceController::class, 'index'])->name('absensi');

        Route::get('koreksi-absensi', [EssAttendanceController::class, 'corrections'])->name('koreksi-absensi');
        Route::post('koreksi-absensi', [EssAttendanceController::class, 'storeCorrection'])->name('koreksi-absensi.store');

        Route::get('jadwal', [EssScheduleController::class, 'index'])->name('jadwal');

        Route::get('organisasi', [EssDirectoryController::class, 'index'])->name('organisasi');

        Route::get('token-ai', [EssAiTokenController::class, 'index'])->name('token-ai');
        Route::post('token-ai/beli', [EssAiTokenController::class, 'purchase'])->name('token-ai.purchase');
        Route::get('token-ai/callback', [EssAiTokenController::class, 'callback'])->name('token-ai.callback');

        Route::get('cuti', [EssLeaveController::class, 'index'])->name('cuti');
        Route::post('cuti', [EssLeaveController::class, 'store'])->name('cuti.store');

        Route::get('lembur', [EssOvertimeController::class, 'index'])->name('lembur');
        Route::post('lembur', [EssOvertimeController::class, 'store'])->name('lembur.store');

        Route::get('izin', [EssPermissionController::class, 'index'])->name('izin');
        Route::post('izin', [EssPermissionController::class, 'store'])->name('izin.store');

        Route::get('kontrak', [EssContractController::class, 'index'])->name('kontrak');

        Route::get('kalender', [EssCalendarController::class, 'index'])->name('kalender');
        Route::get('tugas', [EssTaskController::class, 'index'])->name('tugas');
        Route::get('pembelajaran', [EssLearningController::class, 'index'])->name('pembelajaran');
        Route::get('benefit', [EssBenefitController::class, 'index'])->name('benefit');
        Route::get('perjalanan-dinas', [EssTravelController::class, 'index'])->name('perjalanan-dinas');
        Route::get('perjalanan-dinas/ajukan', [EssTravelController::class, 'create'])->name('perjalanan-dinas.create');
        Route::post('perjalanan-dinas', [EssTravelController::class, 'store'])->name('perjalanan-dinas.store');

        Route::get('kinerja', [EssPerformanceController::class, 'index'])->name('kinerja');
        Route::post('kinerja/{review}/nilai-mandiri', [EssPerformanceController::class, 'submitSelfScore'])->name('kinerja.self-score');

        Route::get('slip-gaji', [EssPayslipController::class, 'index'])->name('slip-gaji');
        Route::get('slip-gaji/{item}', [EssPayslipController::class, 'show'])->name('slip-gaji.show');

        Route::get('dokumen', [EssDocumentController::class, 'index'])->name('dokumen');
        Route::post('dokumen', [EssDocumentController::class, 'store'])->name('dokumen.store');

        Route::get('sop', [EssSopController::class, 'index'])->name('sop');
        Route::get('sop/{sop}/unduh', [EssSopController::class, 'download'])->name('sop.download');

        // Employee social wall — the desktop half of the app's "Sosmed" tab.
        // Distinct from /avana/sosmed, which is HR's moderation screen.
        Route::get('sosmed', [EssSocialController::class, 'index'])->name('sosmed');
        Route::post('sosmed', [EssSocialController::class, 'store'])->name('sosmed.store');
        Route::delete('sosmed/{post}', [EssSocialController::class, 'destroy'])->name('sosmed.destroy');
        Route::post('sosmed/{post}/suka', [EssSocialController::class, 'toggleLike'])->name('sosmed.like');
        Route::get('sosmed/{post}/komentar', [EssSocialController::class, 'comments'])->name('sosmed.comments');
        Route::post('sosmed/{post}/komentar', [EssSocialController::class, 'storeComment'])->name('sosmed.comment.store');
        Route::delete('sosmed/komentar/{comment}', [EssSocialController::class, 'destroyComment'])->name('sosmed.comment.destroy');
        Route::post('sosmed/{post}/lapor', [EssSocialController::class, 'report'])->name('sosmed.report');

        // Employee of the Month voting, shown as a tab beside the leaderboard.
        Route::get('sosmed/eotm/kandidat', [EssSocialController::class, 'nominees'])->name('sosmed.eotm.nominees');
        Route::post('sosmed/eotm/vote', [EssSocialController::class, 'vote'])->name('sosmed.eotm.vote');

        Route::get('onboarding', [EssOnboardingController::class, 'index'])->name('onboarding');
        Route::patch('onboarding/tugas/{task}', [EssOnboardingController::class, 'toggleTask'])->name('onboarding.task');
    });

    // Langganan (tenant admin/HR) — self-service renewal paid via Pakasir.
    // `terkunci` is the lock notice a lapsed tenant is redirected to; it stays
    // open to every role while everything else is closed.
    Route::get('terkunci', [TenantSubscriptionController::class, 'locked'])->name('locked');
    Route::get('langganan', [TenantSubscriptionController::class, 'index'])->name('langganan');
    Route::post('langganan/perpanjang', [TenantSubscriptionController::class, 'purchase'])->name('langganan.purchase');
    Route::get('langganan/callback', [TenantSubscriptionController::class, 'callback'])->name('langganan.callback');

    // Token AI (tenant admin/HR) — top up wallet via Pakasir + per-user caps
    Route::get('token-ai', [TenantAiTokenController::class, 'index'])->name('token-ai');
    Route::post('token-ai/purchase', [TenantAiTokenController::class, 'purchase'])->name('token-ai.purchase');
    Route::get('token-ai/callback', [TenantAiTokenController::class, 'callback'])->name('token-ai.callback');
    Route::put('token-ai/allocation', [TenantAiTokenController::class, 'updateAllocation'])->name('token-ai.allocation');
});
