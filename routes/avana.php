<?php

use App\Http\Controllers\Avana\AccessController;
use App\Http\Controllers\Avana\AiAssistantController;
use App\Http\Controllers\Avana\AnalyticsController;
use App\Http\Controllers\Avana\AnnouncementController;
use App\Http\Controllers\Avana\ApprovalController;
use App\Http\Controllers\Avana\ApprovalDelegationController;
use App\Http\Controllers\Avana\AssetController;
use App\Http\Controllers\Avana\AttendanceController;
use App\Http\Controllers\Avana\AttendancePenaltyController;
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
use App\Http\Controllers\Avana\DokumenController;
use App\Http\Controllers\Avana\DutyTravelController;
use App\Http\Controllers\Avana\DynamicReportController;
use App\Http\Controllers\Avana\EmployeeController;
use App\Http\Controllers\Avana\FeatureController;
use App\Http\Controllers\Avana\FieldVisitController;
use App\Http\Controllers\Avana\HelpdeskController;
use App\Http\Controllers\Avana\JournalController;
use App\Http\Controllers\Avana\LaporanController;
use App\Http\Controllers\Avana\LearningController;
use App\Http\Controllers\Avana\LeaveController;
use App\Http\Controllers\Avana\LeaveTypeController;
use App\Http\Controllers\Avana\LetterTemplateController;
use App\Http\Controllers\Avana\LoanController;
use App\Http\Controllers\Avana\MovementController;
use App\Http\Controllers\Avana\OffboardingController;
use App\Http\Controllers\Avana\OkrController;
use App\Http\Controllers\Avana\OnboardingController;
use App\Http\Controllers\Avana\OvertimeController;
use App\Http\Controllers\Avana\PayrollConfigController;
use App\Http\Controllers\Avana\PayrollController;
use App\Http\Controllers\Avana\PerformanceController;
use App\Http\Controllers\Avana\PermissionRequestController;
use App\Http\Controllers\Avana\PositionComponentController;
use App\Http\Controllers\Avana\RecruitmentController;
use App\Http\Controllers\Avana\RosterController;
use App\Http\Controllers\Avana\SalaryStructureController;
use App\Http\Controllers\Avana\ShiftSwapController;
use App\Http\Controllers\Avana\SurveyController;
use App\Http\Controllers\Avana\TalentController;
use App\Http\Controllers\Avana\TenantController;
use App\Http\Controllers\Avana\TimesheetController;
use App\Http\Controllers\Avana\UserController;
use App\Http\Controllers\Avana\WebsiteSettingController;
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
    Route::post('payroll/run', [PayrollController::class, 'run'])->name('payroll.run');
    Route::post('payroll/lock', [PayrollController::class, 'lock'])->name('payroll.lock');
    Route::post('payroll/thr', [PayrollController::class, 'thr'])->name('payroll.thr');
    Route::get('payroll/transfer', [PayrollController::class, 'transferFile'])->name('payroll.transfer');
    Route::get('payroll/components', [PositionComponentController::class, 'index'])->name('payroll.components');
    Route::put('payroll/components', [PositionComponentController::class, 'update'])->name('payroll.components.update');
    Route::put('payroll/components/basis', [PositionComponentController::class, 'updateBasis'])->name('payroll.components.basis');

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

    // Audit trail
    Route::get('audit', [AuditController::class, 'index'])->name('audit');

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
    Route::delete('roster/{schedule}', [RosterController::class, 'destroy'])->name('roster.destroy');

    // Mutasi / pergerakan karir karyawan
    Route::get('mutasi', [MovementController::class, 'index'])->name('mutasi');
    Route::get('mutasi/create', [MovementController::class, 'create'])->name('mutasi.create');
    Route::post('mutasi', [MovementController::class, 'store'])->name('mutasi.store');

    // Kasbon / cash advance
    Route::get('kasbon', [CashAdvanceController::class, 'index'])->name('kasbon');
    Route::get('kasbon/create', [CashAdvanceController::class, 'create'])->name('kasbon.create');
    Route::post('kasbon', [CashAdvanceController::class, 'store'])->name('kasbon.store');
    Route::post('kasbon/{cashAdvance}/approve', [CashAdvanceController::class, 'approve'])->name('kasbon.approve');
    Route::post('kasbon/{cashAdvance}/reject', [CashAdvanceController::class, 'reject'])->name('kasbon.reject');

    // Benefit management
    Route::get('benefit', [BenefitController::class, 'index'])->name('benefit');
    Route::get('benefit/create', [BenefitController::class, 'create'])->name('benefit.create');
    Route::get('benefit/{benefit}/edit', [BenefitController::class, 'edit'])->name('benefit.edit');
    Route::post('benefit', [BenefitController::class, 'store'])->name('benefit.store');
    Route::put('benefit/{benefit}', [BenefitController::class, 'update'])->name('benefit.update');
    Route::delete('benefit/{benefit}', [BenefitController::class, 'destroy'])->name('benefit.destroy');
    Route::post('benefit/assign', [BenefitController::class, 'assign'])->name('benefit.assign');
    Route::delete('benefit/assign/{employeeBenefit}', [BenefitController::class, 'unassign'])->name('benefit.unassign');

    // Rekrutmen (ATS)
    Route::get('rekrutmen', [RecruitmentController::class, 'index'])->name('rekrutmen');
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
    Route::post('visiting', [FieldVisitController::class, 'store'])->name('visiting.store');
    Route::delete('visiting/{visit}', [FieldVisitController::class, 'destroy'])->name('visiting.destroy');

    // User management (Pengguna)
    Route::get('pengguna', [UserController::class, 'index'])->name('pengguna');
    Route::get('pengguna/create', [UserController::class, 'create'])->name('pengguna.create');
    Route::get('pengguna/{user}/edit', [UserController::class, 'edit'])->name('pengguna.edit');
    Route::post('pengguna', [UserController::class, 'store'])->name('pengguna.store');
    Route::put('pengguna/{user}', [UserController::class, 'update'])->name('pengguna.update');
    Route::delete('pengguna/{user}', [UserController::class, 'destroy'])->name('pengguna.destroy');
    Route::post('pengguna/{user}/toggle', [UserController::class, 'toggleStatus'])->name('pengguna.toggle');

    // Tenant / client management (super admin)
    Route::get('klien', [TenantController::class, 'index'])->name('klien');
    Route::get('klien/create', [TenantController::class, 'create'])->name('klien.create');
    Route::get('klien/{tenant}/edit', [TenantController::class, 'edit'])->name('klien.edit');
    Route::post('klien', [TenantController::class, 'store'])->name('klien.store');
    Route::put('klien/{tenant}', [TenantController::class, 'update'])->name('klien.update');
    Route::delete('klien/{tenant}', [TenantController::class, 'destroy'])->name('klien.destroy');
    Route::post('klien/{tenant}/feature', [TenantController::class, 'toggleFeature'])->name('klien.feature.toggle');

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

    // Pengaturan website (super admin) — edit-only, single settings row
    Route::get('website-settings', [WebsiteSettingController::class, 'edit'])->name('website-settings');
    Route::post('website-settings', [WebsiteSettingController::class, 'update'])->name('website-settings.update');

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
    Route::get('dynamic-report', [DynamicReportController::class, 'index'])->name('dynamic-report');
    Route::post('dynamic-report', [DynamicReportController::class, 'store'])->name('dynamic-report.store');
    Route::get('dynamic-report/{report}/run', [DynamicReportController::class, 'run'])->name('dynamic-report.run');
    Route::get('dynamic-report/{report}/export', [DynamicReportController::class, 'export'])->name('dynamic-report.export');
    Route::delete('dynamic-report/{report}', [DynamicReportController::class, 'destroy'])->name('dynamic-report.destroy');

    // Manajemen aset
    Route::get('aset', [AssetController::class, 'index'])->name('aset');
    Route::get('aset/create', [AssetController::class, 'create'])->name('aset.create');
    Route::get('aset/{asset}/edit', [AssetController::class, 'edit'])->name('aset.edit');
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
    Route::put('pengumuman/{announcement}', [AnnouncementController::class, 'update'])->name('pengumuman.update');
    Route::post('pengumuman/{announcement}/publish', [AnnouncementController::class, 'publish'])->name('pengumuman.publish');
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
});
