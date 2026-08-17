<?php

namespace Database\Seeders;

use App\Models\Announcement;
use App\Models\Applicant;
use App\Models\ApprovalLog;
use App\Models\ApprovalRequest;
use App\Models\ApprovalStep;
use App\Models\ApprovalWorkflow;
use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\Benefit;
use App\Models\Branch;
use App\Models\CalendarEvent;
use App\Models\CashAdvance;
use App\Models\Claim;
use App\Models\Competency;
use App\Models\CrmContact;
use App\Models\CrmDeal;
use App\Models\CustomField;
use App\Models\Department;
use App\Models\DutyTravel;
use App\Models\Employee;
use App\Models\EmployeeBankAccount;
use App\Models\EmployeeBenefit;
use App\Models\EmployeeBpjsProfile;
use App\Models\EmployeeCareerHistory;
use App\Models\EmployeeCompetency;
use App\Models\EmployeeContract;
use App\Models\EmployeeDependent;
use App\Models\EmployeeEmergencyContact;
use App\Models\EmployeeSalaryComponent;
use App\Models\JobPosting;
use App\Models\KeyResult;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Loan;
use App\Models\Objective;
use App\Models\OnboardingProgram;
use App\Models\OnboardingTask;
use App\Models\OvertimeRequest;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use App\Models\PerformanceCycle;
use App\Models\PerformanceReview;
use App\Models\Permission;
use App\Models\PermissionRequest;
use App\Models\Reimbursement;
use App\Models\Role;
use App\Models\SavedReport;
use App\Models\Shift;
use App\Models\Survey;
use App\Models\SurveyQuestion;
use App\Models\SurveyResponse;
use App\Models\TalentAssessment;
use App\Models\TalentPool;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\Training;
use App\Models\TrainingEnrollment;
use App\Models\User;
use App\Models\WfhRequest;
use App\Support\AvanaNav;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Populate every client tenant (id > 1) with realistic operational data across
 * all HR modules — attendance, leave, payroll, benefits, recruitment,
 * performance, learning, helpdesk, announcements, surveys, calendar, assets,
 * CRM and more — so each showcase company can be demoed end to end.
 *
 * Run manually (heavy):
 *   php artisan db:seed --class=ClientModuleDataSeeder
 */
class ClientModuleDataSeeder extends Seeder
{
    public function run(): void
    {
        // Primary demo tenant (PT Nusantara Jaya, id 1) is created by
        // AvanaDemoSeeder; enrich it here with the module data that seeder does
        // not cover, so no showcase page is left empty.
        $this->seedPrimaryDemoTenant();

        $tenants = Tenant::query()->where('id', '!=', 1)->get();

        if ($tenants->isEmpty()) {
            return;
        }

        foreach ($tenants as $tenant) {
            $employees = Employee::where('tenant_id', $tenant->id)->get()->all();

            if (count($employees) === 0) {
                continue;
            }

            $this->seedRolesAndMenu($tenant);

            $ctx = [
                'employees' => $employees,
                'branches' => Branch::where('tenant_id', $tenant->id)->get()->all(),
                'departments' => Department::where('tenant_id', $tenant->id)->get()->all(),
                'admin' => $this->ensureAdminUser($tenant),
            ];

            foreach ($this->moduleSeeders() as $label => $method) {
                try {
                    $this->{$method}($tenant, $ctx);
                } catch (\Throwable $e) {
                    $this->command?->warn("  [{$tenant->name}] {$label} skipped: ".$e->getMessage());
                }
            }

            $this->command?->info("Seeded modules for {$tenant->name}.");
        }

        $this->command?->info('Client module data seeded for '.$tenants->count().' tenants.');
    }

    /**
     * Enrich the primary demo tenant (id 1) with the modules AvanaDemoSeeder
     * leaves empty. Reuses the per-module seeders (attendance/leave/payroll/
     * recruitment are already handled elsewhere, so they are skipped) plus a
     * few demo-only fillers. Each module is guarded so re-seeding is idempotent.
     */
    private function seedPrimaryDemoTenant(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        $tenant = Tenant::find(1);

        if ($tenant === null) {
            return;
        }

        $employees = Employee::where('tenant_id', $tenant->id)->get()->all();

        if (count($employees) === 0) {
            return;
        }

        $ctx = [
            'employees' => $employees,
            'branches' => Branch::where('tenant_id', $tenant->id)->get()->all(),
            'departments' => Department::where('tenant_id', $tenant->id)->get()->all(),
            'admin' => User::where('tenant_id', $tenant->id)->orderBy('id')->first(),
        ];

        // sentinel model => seeder closure. The sentinel's presence for the
        // tenant means the module is already seeded, so it is skipped.
        $modules = [
            [Benefit::class, fn () => $this->seedBenefits($tenant, $ctx)],
            [Claim::class, fn () => $this->seedClaims($tenant, $ctx)],
            [DutyTravel::class, fn () => $this->seedDutyTravels($tenant, $ctx)],
            [CalendarEvent::class, fn () => $this->seedCalendar($tenant, $ctx)],
            [CustomField::class, fn () => $this->seedCustomFields($tenant, $ctx)],
            [WfhRequest::class, fn () => $this->seedWfh($tenant, $ctx)],
            [Ticket::class, fn () => $this->seedHelpdesk($tenant, $ctx)],
            [Competency::class, fn () => $this->seedCompetencies($tenant, $ctx)],
            [Training::class, fn () => $this->seedLearning($tenant, $ctx)],
            [Asset::class, fn () => $this->seedAssets($tenant, $ctx)],
            [CrmContact::class, fn () => $this->seedCrm($tenant, $ctx)],
            [SavedReport::class, fn () => $this->seedSavedReports($tenant, $ctx)],
            [Objective::class, fn () => $this->seedDemoOkr($tenant, $ctx)],
            [TalentPool::class, fn () => $this->seedDemoTalent($tenant, $ctx)],
            [OnboardingProgram::class, fn () => $this->seedDemoOnboarding($tenant, $ctx)],
            [EmployeeContract::class, fn () => $this->seedDemoEmployeeDetails($tenant, $ctx)],
        ];

        foreach ($modules as [$sentinel, $seed]) {
            try {
                if ($sentinel::where('tenant_id', $tenant->id)->exists()) {
                    continue;
                }

                $seed();
            } catch (\Throwable $e) {
                $this->command?->warn('  [demo tenant 1] '.class_basename($sentinel).' skipped: '.$e->getMessage());
            }
        }

        // Resign-risk signals so every attrition factor has data: a few
        // promotions and logged manager changes. Guarded on the promotion rows
        // (the base career history already exists from seedDemoEmployeeDetails).
        try {
            if (! EmployeeCareerHistory::where('tenant_id', $tenant->id)->where('movement_type', 'promotion')->exists()) {
                $this->seedDemoAttritionSignals($tenant, $ctx);
            }
        } catch (\Throwable $e) {
            $this->command?->warn('  [demo tenant 1] attrition signals skipped: '.$e->getMessage());
        }

        $this->command?->info('Enriched primary demo tenant.');
    }

    /**
     * Feed the resign-risk model's two thin factors: promotion history (so
     * "Tidak dipromosikan" is realistic) and manager-change audit logs (so
     * "Pergantian atasan" has data). Mixes recent and old events.
     *
     * @param  array{employees: array<int, Employee>, admin: ?User, branches: array<int, Branch>, departments: array<int, Department>}  $ctx
     */
    private function seedDemoAttritionSignals(Tenant $tenant, array $ctx): void
    {
        $employees = collect($ctx['employees'])->values();
        $now = Carbon::now();

        // [employee index, months since promotion]. Recent = low risk; old = high.
        foreach ([[0, 8], [1, 42], [4, 3], [6, 50]] as [$index, $monthsAgo]) {
            $employee = $employees->get($index);

            if ($employee === null) {
                continue;
            }

            EmployeeCareerHistory::create([
                'tenant_id' => $tenant->id,
                'employee_id' => $employee->id,
                'movement_type' => 'promotion',
                'effective_date' => $now->copy()->subMonthsNoOverflow($monthsAgo)->toDateString(),
                'position_id' => $employee->position_id,
                'department_id' => $employee->department_id,
                'branch_id' => $employee->branch_id,
                'employment_status' => $employee->employment_status,
                'notes' => 'Promosi jabatan',
            ]);
        }

        // Logged manager changes. Recent (< 6 bln) triggers the factor.
        foreach ([[2, 2], [3, 4], [7, 20]] as [$index, $monthsAgo]) {
            $employee = $employees->get($index);

            if ($employee === null) {
                continue;
            }

            $at = $now->copy()->subMonthsNoOverflow($monthsAgo);

            DB::table('audit_logs')->insert([
                'tenant_id' => $tenant->id,
                'user_id' => $ctx['admin']?->id,
                'auditable_type' => Employee::class,
                'auditable_id' => $employee->id,
                'action' => 'updated',
                'old_values' => json_encode(['manager_id' => null]),
                'new_values' => json_encode(['manager_id' => $employee->manager_id]),
                'ip_address' => '127.0.0.1',
                'created_at' => $at,
                'updated_at' => $at,
            ]);
        }
    }

    /**
     * OKR: a company/team/individual objective, each with two key results.
     *
     * @param  array{employees: array<int, Employee>, admin: ?User, branches: array<int, Branch>, departments: array<int, Department>}  $ctx
     */
    private function seedDemoOkr(Tenant $tenant, array $ctx): void
    {
        $employees = collect($ctx['employees']);

        $objectives = [
            ['company', 'financial', 'Tingkatkan pendapatan tahunan sebesar 20%', null],
            ['team', 'customer', 'Naikkan skor kepuasan pelanggan ke 90%', $employees->get(1)?->id],
            ['individual', 'learning', 'Selesaikan sertifikasi teknis inti', $employees->get(2)?->id],
        ];

        foreach ($objectives as [$level, $perspective, $title, $employeeId]) {
            $objective = Objective::create([
                'tenant_id' => $tenant->id,
                'title' => $title,
                'level' => $level,
                'perspective' => $perspective,
                'status' => 'active',
                'progress' => 45,
                'employee_id' => $employeeId,
            ]);

            foreach ([['Capaian utama', 100, 45], ['Indikator pendukung', 100, 60]] as [$krTitle, $target, $current]) {
                KeyResult::create([
                    'tenant_id' => $tenant->id,
                    'objective_id' => $objective->id,
                    'title' => $krTitle,
                    'target_value' => $target,
                    'current_value' => $current,
                    'progress' => $current,
                    'unit' => '%',
                ]);
            }
        }
    }

    /**
     * Talent: 3 pools + a 9-box assessment for the first handful of employees.
     *
     * @param  array{employees: array<int, Employee>, admin: ?User, branches: array<int, Branch>, departments: array<int, Department>}  $ctx
     */
    private function seedDemoTalent(Tenant $tenant, array $ctx): void
    {
        foreach (['High Potential', 'Future Leaders', 'Critical Talent'] as $name) {
            TalentPool::firstOrCreate(
                ['tenant_id' => $tenant->id, 'name' => $name],
                ['description' => 'Kumpulan talenta: '.$name, 'is_auto' => false],
            );
        }

        $grid = [['high', 'high'], ['high', 'medium'], ['medium', 'high'], ['medium', 'medium'], ['low', 'medium']];

        foreach (collect($ctx['employees'])->take(count($grid))->values() as $index => $employee) {
            [$performance, $potential] = $grid[$index];

            TalentAssessment::firstOrCreate(
                ['tenant_id' => $tenant->id, 'employee_id' => $employee->id],
                ['performance_level' => $performance, 'potential_level' => $potential, 'note' => 'Asesmen 9-box (demo)'],
            );
        }
    }

    /**
     * Onboarding: an in-progress program with a checklist for two employees.
     *
     * @param  array{employees: array<int, Employee>, admin: ?User, branches: array<int, Branch>, departments: array<int, Department>}  $ctx
     */
    private function seedDemoOnboarding(Tenant $tenant, array $ctx): void
    {
        $tasks = [
            'Tanda tangan kontrak kerja',
            'Setup akun & email perusahaan',
            'Orientasi & pengenalan perusahaan',
            'Pengenalan tim & mentor',
            'Pelatihan tools internal',
        ];

        foreach (collect($ctx['employees'])->take(2) as $employee) {
            $program = OnboardingProgram::create([
                'tenant_id' => $tenant->id,
                'employee_id' => $employee->id,
                'start_date' => $employee->join_date,
                'status' => 'in_progress',
            ]);

            foreach ($tasks as $index => $title) {
                OnboardingTask::create([
                    'tenant_id' => $tenant->id,
                    'onboarding_program_id' => $program->id,
                    'title' => $title,
                    'category' => 'Umum',
                    'due_date' => Carbon::parse($employee->join_date)->addDays($index * 2),
                    'is_done' => $index < 2,
                ]);
            }
        }
    }

    /**
     * Employee detail tabs: contract, bank account, emergency contact,
     * dependent and a joining career-history row for every employee.
     *
     * @param  array{employees: array<int, Employee>, admin: ?User, branches: array<int, Branch>, departments: array<int, Department>}  $ctx
     */
    /**
     * The employee's basic salary, read from their BASIC payroll component.
     *
     * Falls back to zero for anyone without one — the demo only attaches
     * components to the employees the payroll screens exercise.
     */
    private function basicSalaryOf(Employee $employee): float
    {
        return (float) EmployeeSalaryComponent::where('employee_id', $employee->id)
            ->whereHas('component', fn ($query) => $query->where('code', 'BASIC'))
            ->value('amount');
    }

    private function seedDemoEmployeeDetails(Tenant $tenant, array $ctx): void
    {
        $banks = ['BCA', 'Mandiri', 'BNI', 'BRI'];

        foreach (collect($ctx['employees'])->values() as $index => $employee) {
            $isPermanent = $employee->employment_status === 'permanent';

            // The contract states the basic salary it was signed on, so it is
            // taken from the employee's own BASIC component. Left unset, every
            // row on the Kontrak screen reads Rp 0.
            EmployeeContract::create([
                'tenant_id' => $tenant->id,
                'employee_id' => $employee->id,
                'contract_number' => 'KTR-'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
                'contract_type' => $isPermanent ? 'PKWTT' : 'PKWT',
                'start_date' => $employee->join_date,
                'end_date' => $isPermanent ? null : Carbon::parse($employee->join_date)->addYear(),
                'basic_salary' => $this->basicSalaryOf($employee),
                'status' => 'active',
            ]);

            // Both membership numbers, so the employee page shows an enrolment
            // rather than two dashes. Same shape the payroll demo uses.
            EmployeeBpjsProfile::firstOrCreate(
                ['tenant_id' => $tenant->id, 'employee_id' => $employee->id],
                [
                    'bpjs_ketenagakerjaan_number' => str_pad((string) (20_000_000_000 + $employee->id), 11, '0', STR_PAD_LEFT),
                    'bpjs_kesehatan_number' => str_pad((string) (1_000_000_000_000 + $employee->id), 13, '0', STR_PAD_LEFT),
                    'registered_wage' => $this->basicSalaryOf($employee),
                    'jht_enabled' => true, 'jkk_enabled' => true, 'jkm_enabled' => true,
                    'jp_enabled' => true, 'kesehatan_enabled' => true,
                    'effective_start_date' => '2026-01-01',
                ],
            );

            EmployeeBankAccount::firstOrCreate(
                ['tenant_id' => $tenant->id, 'employee_id' => $employee->id],
                [
                    'bank_name' => $banks[$index % count($banks)],
                    'account_number' => '1'.str_pad((string) mt_rand(0, 999999999), 9, '0', STR_PAD_LEFT),
                    'account_holder' => $employee->full_name,
                    'is_primary' => true,
                ],
            );

            EmployeeEmergencyContact::create([
                'tenant_id' => $tenant->id,
                'employee_id' => $employee->id,
                'name' => 'Kontak Darurat '.$employee->full_name,
                'relationship' => 'Keluarga',
                'phone' => '0812'.mt_rand(1000000, 9999999),
            ]);

            EmployeeDependent::create([
                'tenant_id' => $tenant->id,
                'employee_id' => $employee->id,
                'name' => 'Tanggungan '.$employee->full_name,
                'relationship' => 'Anak',
                'birth_date' => Carbon::parse('2016-01-01')->addDays($index * 45),
            ]);

            EmployeeCareerHistory::create([
                'tenant_id' => $tenant->id,
                'employee_id' => $employee->id,
                'movement_type' => 'mutation',
                'effective_date' => $employee->join_date,
                'position_id' => $employee->position_id,
                'department_id' => $employee->department_id,
                'branch_id' => $employee->branch_id,
                'employment_status' => $employee->employment_status,
                'notes' => 'Bergabung dengan perusahaan',
            ]);
        }
    }

    /**
     * The module seed methods to run per tenant, in dependency-safe order.
     *
     * @return array<string, string>
     */
    private function moduleSeeders(): array
    {
        return [
            'Kehadiran' => 'seedAttendance',
            'Konfigurasi Cuti' => 'seedLeaveConfig',
            'Pengajuan Cuti' => 'seedLeaveRequests',
            'Lembur' => 'seedOvertime',
            'Izin' => 'seedPermissions',
            'WFH' => 'seedWfh',
            'Persetujuan' => 'seedApprovals',
            'Perjalanan Dinas' => 'seedDutyTravels',
            'Kalendar' => 'seedCalendar',
            'Field Custom' => 'seedCustomFields',
            'Payroll' => 'seedPayroll',
            'Benefit' => 'seedBenefits',
            'Klaim' => 'seedClaims',
            'Rekrutmen' => 'seedRecruitment',
            'Kinerja' => 'seedPerformance',
            'Kompetensi' => 'seedCompetencies',
            'LMS' => 'seedLearning',
            'Helpdesk' => 'seedHelpdesk',
            'Pengumuman' => 'seedAnnouncements',
            'Survei' => 'seedSurveys',
            'Aset' => 'seedAssets',
            'CRM' => 'seedCrm',
            'Saved Report' => 'seedSavedReports',
        ];
    }

    /**
     * Ensure the tenant has the standard roles (+ permissions) and a DB-driven
     * sidebar menu, so an admin can actually log in and see every module.
     */
    private function seedRolesAndMenu(Tenant $tenant): void
    {
        $perms = [
            'employee.view', 'employee.create', 'employee.update', 'employee.archive',
            'attendance.view', 'attendance.export', 'attendance.correction.approve',
            'leave.view', 'leave.manage', 'leave.approve',
            'overtime.view', 'overtime.approve', 'wfh.approve',
            'payroll.view', 'payroll.run', 'payroll.approve', 'payroll.publish', 'payroll.export',
            'bpjs.manage', 'pph21.manage', 'report.view', 'report.export',
            'role.manage', 'permission.assign', 'branch.manage', 'audit.view',
            'user.view', 'user.create', 'user.update', 'user.disable',
            'tenant.view', 'tenant.create', 'tenant.update', 'tenant.suspend',
            'team.leave.approve', 'team.attendance.view', 'team.overtime.approve', 'team.claim.approve',
            'own.profile.view', 'own.attendance.clock_in', 'own.leave.request', 'own.payslip.view',
            'document.view', 'letter.view', 'offboarding.view', 'organization.view',
            'timesheet.view', 'shift_swap.view', 'delegation.view',
            'claim.view', 'loan.view', 'journal.view', 'budget.view', 'salary_structure.view',
            'recruitment.view', 'onboarding.view',
            'performance.view', 'okr.view', 'competency.view', 'talent.view', 'learning.view',
            'helpdesk.view', 'announcement.view', 'survey.view', 'calendar.view', 'ai.view',
            'asset.view', 'crm.view', 'dynamic_report.view',
        ];

        $permModels = collect($perms)->map(function (string $code) {
            [$module, $action] = array_pad(explode('.', $code, 2), 2, '');

            return Permission::firstOrCreate(['code' => $code], ['module' => $module, 'action' => $action, 'name' => $code]);
        });

        $roles = [
            ['code' => 'admin_tenant_hr', 'name' => 'Admin Tenant / HR', 'tenant_id' => $tenant->id],
            ['code' => 'manager', 'name' => 'Manager', 'tenant_id' => $tenant->id],
            ['code' => 'finance', 'name' => 'Finance', 'tenant_id' => $tenant->id],
            ['code' => 'employee', 'name' => 'Karyawan', 'tenant_id' => $tenant->id],
        ];

        foreach ($roles as $data) {
            $role = Role::firstOrCreate(
                ['tenant_id' => $data['tenant_id'], 'code' => $data['code']],
                ['name' => $data['name'], 'is_system' => true],
            );

            $assigned = match ($data['code']) {
                'admin_tenant_hr' => $permModels->reject(fn ($p) => str_starts_with($p->code, 'tenant.')),
                'manager' => $permModels->filter(fn ($p) => str_starts_with($p->code, 'team.') || str_starts_with($p->code, 'own.')),
                // Finance settles the money and nothing else: claims, loans,
                // journals, budgets, payroll — no HR administration.
                'finance' => $permModels->filter(fn ($p) => in_array($p->module, ['claim', 'loan', 'journal', 'budget', 'salary_structure', 'payroll', 'report'], true)
                    || str_starts_with($p->code, 'own.')),
                default => $permModels->filter(fn ($p) => str_starts_with($p->code, 'own.')),
            };
            $role->permissions()->syncWithoutDetaching($assigned->pluck('id'));
        }

        AvanaNav::seedDefaultsFor($tenant->id);
    }

    /**
     * Create (once) a login-able HR admin for the tenant, used both as the
     * demo login and as the actor (created_by / approver) on seeded records.
     */
    private function ensureAdminUser(Tenant $tenant): User
    {
        $email = 'admin@'.$tenant->slug.'.avanahr.id';

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => 'Admin '.$tenant->name,
                'tenant_id' => $tenant->id,
                'password' => Hash::make('password'),
                'status' => 'active',
                'email_verified_at' => now(),
            ],
        );

        $role = Role::where('tenant_id', $tenant->id)->where('code', 'admin_tenant_hr')->first();
        if ($role !== null) {
            $user->roles()->syncWithoutDetaching([$role->id]);
        }

        return $user;
    }

    // ===================================================================
    // MODULE SEEDERS — assembled from verified per-cluster contributions.
    // Each: private function seedXxx(Tenant $tenant, array $ctx): void
    // ===================================================================

    /**
     * Seed ~14 working days of attendance for ~60% of the tenant's employees.
     *
     * @param  array{employees: array<int,Employee>, branches: array<int,Branch>, departments: array<int,Department>, admin: User}  $ctx
     */
    private function seedAttendance(Tenant $tenant, array $ctx): void
    {
        $employees = $ctx['employees'];
        if (empty($employees)) {
            return;
        }

        $shift = Shift::firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'PAGI'],
            ['name' => 'Pagi', 'start_time' => '08:00', 'end_time' => '17:00', 'late_tolerance_minutes' => 15, 'status' => 'active'],
        );

        $sample = collect($employees)->shuffle()->take((int) ceil(count($employees) * 0.6))->values();
        $baseLat = -6.2146500;
        $baseLng = 106.8451500;
        $rows = [];
        $now = Carbon::now();

        foreach ($sample as $employee) {
            for ($d = 1; $d <= 14; $d++) {
                $date = $now->copy()->subDays($d);
                if ($date->isWeekend()) {
                    continue;
                }
                $dateStr = $date->toDateString();
                $common = [
                    'tenant_id' => $tenant->id,
                    'employee_id' => $employee->id,
                    'branch_id' => $employee->branch_id,
                    'shift_id' => $shift->id,
                    'date' => $dateStr,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                $roll = mt_rand(1, 100);
                if ($roll <= 4) {
                    $rows[] = $common + [
                        'work_location_id' => null,
                        'clock_in_at' => null, 'clock_out_at' => null,
                        'clock_in_lat' => null, 'clock_in_lng' => null,
                        'clock_out_lat' => null, 'clock_out_lng' => null,
                        'late_minutes' => 0, 'work_minutes' => 0,
                        'status' => 'absent', 'location_status' => null,
                    ];

                    continue;
                }
                if ($roll <= 8) {
                    $rows[] = $common + [
                        'work_location_id' => null,
                        'clock_in_at' => null, 'clock_out_at' => null,
                        'clock_in_lat' => null, 'clock_in_lng' => null,
                        'clock_out_lat' => null, 'clock_out_lng' => null,
                        'late_minutes' => 0, 'work_minutes' => 0,
                        'status' => 'leave', 'location_status' => null,
                    ];

                    continue;
                }

                $clockInMin = mt_rand(1, 100) <= 25 ? mt_rand(481, 505) : mt_rand(455, 480);
                $late = max(0, $clockInMin - 480);
                $lat = round($baseLat + (mt_rand(-40, 40) / 100000), 7);
                $lng = round($baseLng + (mt_rand(-40, 40) / 100000), 7);
                $inTime = $date->copy()->startOfDay()->addMinutes($clockInMin);

                if ($roll <= 11) {
                    $rows[] = $common + [
                        'work_location_id' => null,
                        'clock_in_at' => $inTime->format('Y-m-d H:i:s'), 'clock_out_at' => null,
                        'clock_in_lat' => $lat, 'clock_in_lng' => $lng,
                        'clock_out_lat' => null, 'clock_out_lng' => null,
                        'late_minutes' => $late, 'work_minutes' => 0,
                        'status' => 'incomplete', 'location_status' => 'inside',
                    ];

                    continue;
                }

                $clockOutMin = mt_rand(1020, 1055);
                $outTime = $date->copy()->startOfDay()->addMinutes($clockOutMin);
                $rows[] = $common + [
                    'work_location_id' => null,
                    'clock_in_at' => $inTime->format('Y-m-d H:i:s'),
                    'clock_out_at' => $outTime->format('Y-m-d H:i:s'),
                    'clock_in_lat' => $lat, 'clock_in_lng' => $lng,
                    'clock_out_lat' => $lat, 'clock_out_lng' => $lng,
                    'late_minutes' => $late,
                    'work_minutes' => max(0, $clockOutMin - $clockInMin - 60),
                    'status' => $late > 0 ? 'late' : 'present',
                    'location_status' => 'inside',
                ];
            }
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('attendances')->insertOrIgnore($chunk);
        }
    }

    /**
     * Seed leave types (5) and one leave balance per employee per type.
     *
     * @param  array{employees: array<int,Employee>, admin: User}  $ctx
     */
    private function seedLeaveConfig(Tenant $tenant, array $ctx): void
    {
        $types = [
            ['code' => 'TAHUNAN', 'name' => 'Cuti Tahunan', 'default_quota' => 12],
            ['code' => 'SAKIT', 'name' => 'Cuti Sakit', 'default_quota' => 12, 'requires_attachment' => true],
            ['code' => 'PENTING', 'name' => 'Cuti Penting', 'default_quota' => 2],
            ['code' => 'MELAHIRKAN', 'name' => 'Cuti Melahirkan', 'default_quota' => 90, 'requires_attachment' => true],
            ['code' => 'BESAR', 'name' => 'Cuti Besar', 'default_quota' => 6, 'allow_negative' => true],
        ];

        $leaveTypes = collect($types)->map(fn ($r) => LeaveType::firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => $r['code']],
            [
                'name' => $r['name'],
                'default_quota' => $r['default_quota'],
                'allow_negative' => $r['allow_negative'] ?? false,
                'requires_attachment' => $r['requires_attachment'] ?? false,
                'status' => 'active',
            ],
        ))->all();

        $year = (int) Carbon::now()->year;
        foreach ($ctx['employees'] as $employee) {
            foreach ($leaveTypes as $type) {
                $used = $type->default_quota > 6 ? mt_rand(0, 6) : mt_rand(0, (int) $type->default_quota);
                LeaveBalance::firstOrCreate(
                    ['tenant_id' => $tenant->id, 'employee_id' => $employee->id, 'leave_type_id' => $type->id, 'year' => $year],
                    ['quota' => $type->default_quota, 'used' => $used, 'remaining' => $type->default_quota - $used],
                );
            }
        }
    }

    /**
     * Seed ~15 leave requests with mixed approved/pending/rejected statuses.
     *
     * @param  array{employees: array<int,Employee>, admin: User}  $ctx
     */
    private function seedLeaveRequests(Tenant $tenant, array $ctx): void
    {
        $employees = $ctx['employees'];
        $admin = $ctx['admin'];
        $leaveTypes = LeaveType::where('tenant_id', $tenant->id)->get();
        if ($employees === [] || $leaveTypes->isEmpty()) {
            return;
        }

        $reasons = ['Liburan keluarga', 'Acara pernikahan saudara', 'Demam dan flu', 'Keperluan pribadi', 'Mudik kampung halaman', 'Kontrol kesehatan', 'Urusan keluarga mendesak'];
        $statuses = ['approved', 'approved', 'approved', 'pending', 'pending', 'rejected'];
        $now = Carbon::now();

        for ($i = 0; $i < 15; $i++) {
            $employee = $employees[array_rand($employees)];
            $type = $leaveTypes->random();
            $start = $now->copy()->addDays(mt_rand(-25, 20))->startOfDay();
            $days = mt_rand(1, 4);
            $end = $start->copy()->addDays($days - 1);

            LeaveRequest::create([
                'tenant_id' => $tenant->id,
                'employee_id' => $employee->id,
                'branch_id' => $employee->branch_id,
                'leave_type_id' => $type->id,
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'total_days' => $days,
                'reason' => $reasons[array_rand($reasons)],
                'current_approver_id' => $admin->id,
                'status' => $statuses[array_rand($statuses)],
            ]);
        }
    }

    /**
     * Seed ~10 overtime requests with mixed statuses.
     *
     * @param  array{employees: array<int,Employee>, admin: User}  $ctx
     */
    private function seedOvertime(Tenant $tenant, array $ctx): void
    {
        $employees = $ctx['employees'];
        $admin = $ctx['admin'];
        if ($employees === []) {
            return;
        }

        $reasons = ['Menyelesaikan laporan bulanan', 'Deadline proyek klien', 'Maintenance server malam', 'Tutup buku akhir bulan', 'Persiapan audit', 'Backlog pekerjaan'];
        $statuses = ['approved', 'approved', 'pending', 'rejected'];
        $now = Carbon::now();

        for ($i = 0; $i < 10; $i++) {
            $employee = $employees[array_rand($employees)];
            $date = $now->copy()->subDays(mt_rand(1, 30));
            OvertimeRequest::create([
                'tenant_id' => $tenant->id,
                'employee_id' => $employee->id,
                'branch_id' => $employee->branch_id,
                'date' => $date->toDateString(),
                'day_type' => $date->isWeekend() ? 'holiday' : 'workday',
                // Up to the 4-hour daily ceiling of PP 35/2021.
                'hours' => mt_rand(2, 8) / 2,
                'reason' => $reasons[array_rand($reasons)],
                'current_approver_id' => $admin->id,
                'status' => $statuses[array_rand($statuses)],
            ]);
        }
    }

    /**
     * Seed ~8 permission (izin) requests.
     *
     * @param  array{employees: array<int,Employee>, admin: User}  $ctx
     */
    private function seedPermissions(Tenant $tenant, array $ctx): void
    {
        $employees = $ctx['employees'];
        $admin = $ctx['admin'];
        if ($employees === []) {
            return;
        }

        $types = ['izin_jam', 'keluar_kantor'];
        $reasons = ['Ke dokter gigi', 'Mengurus dokumen di bank', 'Antar anak sekolah', 'Keperluan keluarga', 'Meeting dengan vendor', 'Urusan pribadi mendesak'];
        $statuses = ['approved', 'approved', 'pending', 'rejected'];
        $now = Carbon::now();

        for ($i = 0; $i < 8; $i++) {
            $employee = $employees[array_rand($employees)];
            $startHour = mt_rand(9, 14);
            $duration = mt_rand(1, 3);
            $start = $now->copy()->subDays(mt_rand(1, 25));

            // Every third izin spans a couple of days, so the range shows up in
            // the demo data; the rest stay hourly on a single day.
            $spanDays = $i % 3 === 0 ? mt_rand(1, 2) : 0;

            PermissionRequest::create([
                'tenant_id' => $tenant->id,
                'employee_id' => $employee->id,
                'start_date' => $start->toDateString(),
                'end_date' => $start->copy()->addDays($spanDays)->toDateString(),
                'type' => $types[array_rand($types)],
                'start_time' => $spanDays === 0 ? sprintf('%02d:00', $startHour) : null,
                'end_time' => $spanDays === 0 ? sprintf('%02d:00', $startHour + $duration) : null,
                'reason' => $reasons[array_rand($reasons)],
                'current_approver_id' => $admin->id,
                'status' => $statuses[array_rand($statuses)],
            ]);
        }
    }

    /**
     * Seed ~8 work-from-home requests with mixed statuses.
     *
     * @param  array{employees: array<int,Employee>, admin: User}  $ctx
     */
    private function seedWfh(Tenant $tenant, array $ctx): void
    {
        $employees = $ctx['employees'];
        $admin = $ctx['admin'];
        if ($employees === []) {
            return;
        }

        $reasons = ['Kerja dari rumah karena sakit ringan', 'Menunggu perbaikan rumah', 'Kondisi cuaca ekstrem', 'Fokus menyelesaikan dokumentasi', 'Isolasi mandiri', 'Menjaga keluarga yang sakit'];
        $statuses = ['approved', 'approved', 'pending', 'rejected'];
        $now = Carbon::now();

        for ($i = 0; $i < 8; $i++) {
            $employee = $employees[array_rand($employees)];
            $start = $now->copy()->addDays(mt_rand(-15, 10))->startOfDay();
            $end = $start->copy()->addDays(mt_rand(0, 3));
            WfhRequest::create([
                'tenant_id' => $tenant->id,
                'employee_id' => $employee->id,
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'reason' => $reasons[array_rand($reasons)],
                'current_approver_id' => $admin->id,
                'status' => $statuses[array_rand($statuses)],
            ]);
        }
    }

    /**
     * Payroll: 3 recent monthly periods; runs + items + payslips for the two
     * most recent. Salaries scale with tenure; BPJS/PPh21 are rough but consistent.
     *
     * @param  array{employees: array<int,Employee>, admin: User}  $ctx
     */
    private function seedPayroll(Tenant $tenant, array $ctx): void
    {
        $employees = $ctx['employees'];
        $admin = $ctx['admin'];
        $now = Carbon::now();

        $monthNames = [1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'];

        $baseSalaries = [];
        foreach ($employees as $employee) {
            $joinDate = $employee->join_date !== null ? Carbon::parse($employee->join_date) : $now->copy()->subYears(2);
            $years = max(0, (int) $joinDate->diffInYears($now));
            $base = 4_500_000 + ($years * 900_000) + (mt_rand(0, 40) * 100_000);
            $baseSalaries[$employee->id] = (int) min(25_000_000, $base);
        }

        $periods = [];
        for ($monthsAgo = 3; $monthsAgo >= 1; $monthsAgo--) {
            $start = $now->copy()->subMonthsNoOverflow($monthsAgo)->startOfMonth();
            $end = $start->copy()->endOfMonth();
            $isLatest = $monthsAgo === 1;

            $period = PayrollPeriod::create([
                'tenant_id' => $tenant->id,
                'code' => 'MN-'.$start->format('Ymd'),
                'name' => $monthNames[(int) $start->format('n')].' '.$start->format('Y'),
                'cycle' => 'monthly',
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'pay_date' => $end->copy()->subDays(3)->toDateString(),
                'status' => $isLatest ? 'approved' : 'locked',
            ]);

            $periods[] = ['model' => $period, 'pay_date' => $end->copy()->subDays(3), 'latest' => $isLatest];
        }

        foreach (array_slice($periods, -2) as $entry) {
            $period = $entry['model'];
            $isLatest = $entry['latest'];
            $payDate = $entry['pay_date'];

            $run = PayrollRun::create([
                'tenant_id' => $tenant->id,
                'payroll_period_id' => $period->id,
                'branch_id' => null,
                'status' => $isLatest ? 'approved' : 'locked',
                'approved_by' => $admin->id,
                'approved_at' => $payDate->copy()->subDays(2),
                'total_gross' => 0,
                'total_deduction' => 0,
                'total_tax' => 0,
                'total_net' => 0,
                'employee_count' => 0,
            ]);

            $totalGross = 0.0;
            $totalDeduction = 0.0;
            $totalTax = 0.0;
            $totalNet = 0.0;
            $count = 0;

            foreach ($employees as $employee) {
                if ($employee->employment_status === 'resigned') {
                    continue;
                }

                $base = $baseSalaries[$employee->id];
                $tjJabatan = (int) round($base * 0.20);
                $tjTransport = 500_000 + (mt_rand(0, 5) * 100_000);
                $tjMakan = 300_000 + (mt_rand(0, 4) * 100_000);

                $earnings = [
                    ['name' => 'Gaji Pokok', 'amount' => $base],
                    ['name' => 'Tunjangan Jabatan', 'amount' => $tjJabatan],
                    ['name' => 'Tunjangan Transport', 'amount' => $tjTransport],
                    ['name' => 'Tunjangan Makan', 'amount' => $tjMakan],
                ];

                $gross = (float) array_sum(array_column($earnings, 'amount'));

                $bpjsEmployee = (float) round($base * 0.04);
                $bpjsCompany = (float) round($base * 0.107);
                $pph21 = $gross > 5_400_000 ? (float) round(($gross - 4_500_000) * 0.05) : 0.0;

                $deductions = [];
                if ($bpjsEmployee > 0) {
                    $deductions[] = ['name' => 'BPJS (Karyawan)', 'amount' => $bpjsEmployee];
                }
                if ($pph21 > 0) {
                    $deductions[] = ['name' => 'PPh 21', 'amount' => $pph21];
                }

                $deduction = (float) array_sum(array_column($deductions, 'amount'));
                $net = $gross - $deduction;
                $presentDays = mt_rand(20, 23);

                PayrollRunItem::create([
                    'tenant_id' => $tenant->id,
                    'payroll_run_id' => $run->id,
                    'payroll_period_id' => $period->id,
                    'employee_id' => $employee->id,
                    'gross_salary' => $gross,
                    'total_allowance' => $gross - $base,
                    'total_deduction' => $deduction,
                    'bpjs_employee_total' => $bpjsEmployee,
                    'bpjs_company_total' => $bpjsCompany,
                    'pph21_total' => $pph21,
                    'net_salary' => $net,
                    'calculation_snapshot' => [
                        'earnings' => $earnings,
                        'deductions' => $deductions,
                        'present_days' => $presentDays,
                        'overtime_hours' => 0.0,
                        'proration_factor' => 1.0,
                        'loan_ids' => [],
                        'advance_ids' => [],
                        'gross' => $gross,
                        'deduction' => $deduction,
                        'bpjs' => ['employee' => $bpjsEmployee, 'company' => $bpjsCompany],
                        'tax' => ['amount' => $pph21],
                        'net' => $net,
                    ],
                    'status' => 'calculated',
                ]);

                $totalGross += $gross;
                $totalDeduction += $deduction;
                $totalTax += $pph21;
                $totalNet += $net;
                $count++;
            }

            $run->update([
                'total_gross' => $totalGross,
                'total_deduction' => $totalDeduction,
                'total_tax' => $totalTax,
                'total_net' => $totalNet,
                'employee_count' => $count,
            ]);
        }
    }

    /**
     * Benefits catalogue (~5) plus per-employee enrolments.
     *
     * @param  array{employees: array<int,Employee>, admin: User}  $ctx
     */
    private function seedBenefits(Tenant $tenant, array $ctx): void
    {
        $employees = $ctx['employees'];
        $now = Carbon::now();

        $catalogue = [
            ['code' => 'BPJS-KES', 'name' => 'BPJS Kesehatan', 'type' => 'insurance', 'value' => 0, 'description' => 'Jaminan kesehatan nasional, iuran 5% (4% perusahaan, 1% karyawan).'],
            ['code' => 'BPJS-TK', 'name' => 'BPJS Ketenagakerjaan', 'type' => 'insurance', 'value' => 0, 'description' => 'Program JHT, JKK, JKM, dan JP.'],
            ['code' => 'TJ-TRP', 'name' => 'Tunjangan Transport', 'type' => 'allowance', 'value' => 750_000, 'description' => 'Tunjangan transportasi bulanan.'],
            ['code' => 'ASR-SWA', 'name' => 'Asuransi Kesehatan Swasta', 'type' => 'insurance', 'value' => 500_000, 'description' => 'Asuransi kesehatan tambahan (rawat inap & rawat jalan).'],
            ['code' => 'TJ-MKN', 'name' => 'Tunjangan Makan', 'type' => 'allowance', 'value' => 600_000, 'description' => 'Tunjangan makan bulanan.'],
        ];

        $benefits = [];
        foreach ($catalogue as $item) {
            $benefits[] = Benefit::create([
                'tenant_id' => $tenant->id,
                'code' => $item['code'],
                'name' => $item['name'],
                'type' => $item['type'],
                'value' => $item['value'],
                'description' => $item['description'],
                'status' => 'active',
            ]);
        }

        $mandatory = array_slice($benefits, 0, 2);
        $optional = array_slice($benefits, 2);

        foreach ($employees as $employee) {
            if ($employee->employment_status === 'resigned') {
                continue;
            }

            $startDate = $employee->join_date !== null ? Carbon::parse($employee->join_date) : $now->copy()->subYear();

            $assigned = $mandatory;
            foreach ($optional as $benefit) {
                if (mt_rand(0, 1) === 1) {
                    $assigned[] = $benefit;
                }
            }

            foreach ($assigned as $benefit) {
                EmployeeBenefit::create([
                    'tenant_id' => $tenant->id,
                    'employee_id' => $employee->id,
                    'benefit_id' => $benefit->id,
                    'start_date' => $startDate->toDateString(),
                    'end_date' => null,
                    'status' => 'active',
                    'notes' => null,
                ]);
            }
        }
    }

    /**
     * Claims (~12), cash advances (~6) and loans (~5) with mixed statuses.
     *
     * @param  array{employees: array<int,Employee>, admin: User}  $ctx
     */
    private function seedClaims(Tenant $tenant, array $ctx): void
    {
        $employees = $ctx['employees'];
        $admin = $ctx['admin'];
        $now = Carbon::now();

        $activeEmployees = array_values(array_filter($employees, static fn (Employee $e): bool => $e->employment_status !== 'resigned'));
        if ($activeEmployees === []) {
            $activeEmployees = array_values($employees);
        }

        $pick = static fn (): Employee => $activeEmployees[array_rand($activeEmployees)];

        $claimTypes = [
            'medical' => ['Reimburse Rawat Jalan', 'Reimburse Rawat Inap', 'Reimburse Obat'],
            'transport' => ['Klaim Transport Dinas', 'Klaim Taksi Meeting Klien', 'Klaim BBM Perjalanan Dinas'],
            'meal' => ['Klaim Konsumsi Lembur', 'Klaim Jamuan Klien'],
            'glasses' => ['Reimburse Kacamata'],
            'other' => ['Klaim Pelatihan', 'Klaim Perlengkapan Kerja'],
        ];
        $statuses = ['pending', 'approved', 'approved', 'paid', 'paid', 'rejected'];

        for ($i = 0; $i < 12; $i++) {
            $employee = $pick();
            $type = array_rand($claimTypes);
            $title = $claimTypes[$type][array_rand($claimTypes[$type])];
            $status = $statuses[array_rand($statuses)];
            $claimDate = $now->copy()->subDays(mt_rand(3, 90));
            $isDecided = in_array($status, ['approved', 'paid', 'rejected'], true);

            Claim::create([
                'tenant_id' => $tenant->id,
                'employee_id' => $employee->id,
                'claim_type' => $type,
                'title' => $title,
                'amount' => mt_rand(1, 30) * 100_000,
                'claim_date' => $claimDate->toDateString(),
                'description' => 'Pengajuan klaim '.$title.' sesuai bukti terlampir.',
                'receipt_path' => null,
                'status' => $status,
                'approver_id' => $isDecided ? $admin->id : null,
                'approved_at' => $isDecided ? $claimDate->copy()->addDays(mt_rand(1, 5)) : null,
                'notes' => $status === 'rejected' ? 'Bukti tidak lengkap.' : null,
            ]);
        }

        $advanceStatuses = ['pending', 'approved', 'disbursed', 'disbursed', 'settled', 'rejected'];
        $advancePurposes = [
            'Uang muka perjalanan dinas',
            'Uang muka pembelian operasional',
            'Uang muka biaya representasi klien',
            'Uang muka kegiatan lapangan',
        ];
        for ($i = 0; $i < 6; $i++) {
            $employee = $pick();
            $amount = mt_rand(10, 50) * 100_000;
            $status = $advanceStatuses[array_rand($advanceStatuses)];
            $requestDate = $now->copy()->subDays(mt_rand(10, 120));
            $isDecided = in_array($status, ['approved', 'disbursed', 'settled', 'rejected'], true);
            $isDisbursed = in_array($status, ['disbursed', 'settled'], true);

            CashAdvance::create([
                'tenant_id' => $tenant->id,
                'employee_id' => $employee->id,
                'amount' => $amount,
                'purpose' => $advancePurposes[array_rand($advancePurposes)],
                'request_date' => $requestDate->toDateString(),
                'needed_date' => $requestDate->copy()->addDays(mt_rand(3, 14))->toDateString(),
                'reason' => 'Dibutuhkan untuk kegiatan operasional di lapangan.',
                'status' => $status,
                'approved_by' => $isDecided ? $admin->id : null,
                'approved_at' => $isDecided ? $requestDate->copy()->addDays(mt_rand(1, 3)) : null,
                'disbursed_at' => $isDisbursed ? $requestDate->copy()->addDays(mt_rand(3, 6)) : null,
                'disbursed_by' => $isDisbursed ? $admin->id : null,
                'disbursement_method' => $isDisbursed ? 'transfer' : null,
                'settled_at' => $status === 'settled' ? $requestDate->copy()->addDays(mt_rand(10, 30)) : null,
            ]);
        }

        $loanStatuses = ['pending', 'approved', 'approved', 'paid', 'rejected'];
        $purposes = ['Renovasi rumah', 'Biaya pendidikan anak', 'Dana darurat', 'Pembelian kendaraan', 'Modal usaha keluarga'];
        for ($i = 0; $i < 5; $i++) {
            $employee = $pick();
            $amount = mt_rand(50, 500) * 100_000;
            $tenor = [6, 12, 18, 24][array_rand([6, 12, 18, 24])];
            $rate = [0, 1, 2, 5][array_rand([0, 1, 2, 5])];
            $status = $loanStatuses[array_rand($loanStatuses)];
            $monthly = round($amount * (1 + $rate / 100) / $tenor, 2);
            $paid = in_array($status, ['approved', 'paid'], true) ? mt_rand(0, $tenor) : 0;
            if ($status === 'paid') {
                $paid = $tenor;
            }

            Loan::create([
                'tenant_id' => $tenant->id,
                'employee_id' => $employee->id,
                'amount' => $amount,
                'tenor_months' => $tenor,
                'interest_rate' => $rate,
                'monthly_installment' => $monthly,
                'paid_installments' => $paid,
                'purpose' => $purposes[array_rand($purposes)],
                'status' => $status,
                'approved_at' => in_array($status, ['approved', 'paid'], true) ? $now->copy()->subDays(mt_rand(5, 100)) : null,
            ]);
        }
    }

    /**
     * Approval workflows (4) + steps + ~15 requests with logs.
     *
     * @param  array{employees: array<int,Employee>, admin: User}  $ctx
     */
    private function seedApprovals(Tenant $tenant, array $ctx): void
    {
        $admin = $ctx['admin'];

        $workflowBlueprints = [
            ['name' => 'Persetujuan Cuti', 'request_type' => 'leave', 'approval_mode' => 'sequential'],
            // The keys are the canonical ones ApprovalEngine routes on; the
            // older `lembur` / `reimburse` aliases seeded flows the engine never
            // matched, so they looked configured and did nothing.
            ['name' => 'Persetujuan Lembur', 'request_type' => 'overtime', 'approval_mode' => 'sequential'],
            ['name' => 'Persetujuan Reimburse', 'request_type' => 'reimbursement', 'approval_mode' => 'parallel'],
            ['name' => 'Persetujuan Perjalanan Dinas', 'request_type' => 'duty_travel', 'approval_mode' => 'sequential'],
        ];

        $stepRoleSequence = ['manager', 'hr', 'director'];

        $morphMap = [
            'leave' => LeaveRequest::class,
            'overtime' => OvertimeRequest::class,
            'reimbursement' => Reimbursement::class,
            'duty_travel' => DutyTravel::class,
        ];

        $workflows = [];

        foreach ($workflowBlueprints as $blueprint) {
            $workflow = ApprovalWorkflow::create([
                'tenant_id' => $tenant->id,
                'name' => $blueprint['name'],
                'request_type' => $blueprint['request_type'],
                'approval_mode' => $blueprint['approval_mode'],
                'is_active' => true,
                'conditions' => null,
                'created_at' => Carbon::now()->subDays(mt_rand(90, 180)),
                'updated_at' => Carbon::now()->subDays(mt_rand(1, 30)),
            ]);

            $stepCount = mt_rand(1, 3);

            for ($order = 1; $order <= $stepCount; $order++) {
                $role = $stepRoleSequence[$order - 1];

                ApprovalStep::create([
                    'tenant_id' => $tenant->id,
                    'approval_workflow_id' => $workflow->id,
                    'step_order' => $order,
                    'approver_type' => $role,
                    // EMPLOYEE id — see ApprovalStep::approverEmployee().
                    'approver_user_id' => $role === 'manager'
                        ? null
                        : Employee::forTenant($tenant->id)->where('user_id', $admin->id)->value('id'),
                    'approver_role_id' => null,
                ]);
            }

            $workflows[] = [
                'id' => $workflow->id,
                'request_type' => $blueprint['request_type'],
                'steps' => $stepCount,
            ];
        }

        $statusPool = ['pending', 'pending', 'pending', 'approved', 'approved', 'approved', 'approved', 'rejected'];

        for ($i = 0; $i < 15; $i++) {
            $workflow = $workflows[array_rand($workflows)];
            $status = $statusPool[array_rand($statusPool)];
            $stepCount = $workflow['steps'];
            $currentStep = $status === 'pending' ? mt_rand(1, $stepCount) : $stepCount;
            $createdAt = Carbon::now()->subDays(mt_rand(1, 60))->setTime(mt_rand(8, 16), mt_rand(0, 59));

            $request = ApprovalRequest::create([
                'tenant_id' => $tenant->id,
                'approvable_type' => $morphMap[$workflow['request_type']] ?? LeaveRequest::class,
                'approvable_id' => mt_rand(1, 999),
                'requester_id' => $admin->id,
                'current_approver_id' => $status === 'pending' ? $admin->id : null,
                'approval_workflow_id' => $workflow['id'],
                'current_step' => $currentStep,
                'status' => $status,
                'created_at' => $createdAt,
                'updated_at' => $createdAt->copy()->addHours(mt_rand(1, 48)),
            ]);

            ApprovalLog::create([
                'tenant_id' => $tenant->id,
                'approval_request_id' => $request->id,
                'approver_id' => $admin->id,
                'action' => 'submitted',
                'step_order' => 1,
                'note' => 'Pengajuan dibuat',
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            $decidedSteps = $status === 'pending' ? $currentStep - 1 : $stepCount;

            for ($order = 1; $order <= $decidedSteps; $order++) {
                $isFinalRejection = $status === 'rejected' && $order === $stepCount;

                ApprovalLog::create([
                    'tenant_id' => $tenant->id,
                    'approval_request_id' => $request->id,
                    'approver_id' => $admin->id,
                    'action' => $isFinalRejection ? 'rejected' : 'approved',
                    'step_order' => $order,
                    'note' => $isFinalRejection ? 'Ditolak: dokumen tidak lengkap' : 'Disetujui pada tahap '.$order,
                    'created_at' => $createdAt->copy()->addHours($order * mt_rand(2, 12)),
                    'updated_at' => $createdAt->copy()->addHours($order * mt_rand(2, 12)),
                ]);
            }
        }
    }

    /**
     * Duty travels (~12) with mixed statuses across Indonesian cities.
     *
     * @param  array{employees: array<int,Employee>, admin: User}  $ctx
     */
    private function seedDutyTravels(Tenant $tenant, array $ctx): void
    {
        $employees = $ctx['employees'];
        $admin = $ctx['admin'];

        $destinations = ['Jakarta', 'Surabaya', 'Bandung', 'Medan', 'Semarang', 'Yogyakarta', 'Makassar', 'Denpasar', 'Balikpapan', 'Batam', 'Palembang', 'Pekanbaru', 'Manado', 'Pontianak'];
        $purposes = [
            'Kunjungan klien dan negosiasi kontrak',
            'Menghadiri rapat koordinasi regional',
            'Audit cabang dan tinjauan operasional',
            'Pelatihan dan sertifikasi karyawan',
            'Survei lokasi proyek baru',
            'Pendampingan implementasi sistem',
            'Menghadiri pameran industri',
            'Pertemuan dengan mitra distributor',
        ];
        $transports = ['Pesawat', 'Kereta Api', 'Mobil Dinas', 'Travel', 'Bus'];
        $statusPool = ['pending', 'pending', 'pending', 'approved', 'approved', 'approved', 'completed', 'completed', 'completed', 'rejected'];

        for ($i = 0; $i < 12; $i++) {
            $employee = $employees[array_rand($employees)];
            $status = $statusPool[array_rand($statusPool)];

            if ($status === 'completed') {
                $start = Carbon::now()->subDays(mt_rand(20, 90));
            } elseif ($status === 'pending') {
                $start = Carbon::now()->addDays(mt_rand(3, 45));
            } else {
                $start = Carbon::now()->addDays(mt_rand(-30, 30));
            }

            $duration = mt_rand(1, 5);
            $end = $start->copy()->addDays($duration);

            $perDiem = mt_rand(3, 9) * 100000;
            $estimatedCost = ($perDiem * ($duration + 1)) + (mt_rand(10, 60) * 100000);

            $isDecided = in_array($status, ['approved', 'completed'], true);

            DutyTravel::create([
                'tenant_id' => $tenant->id,
                'employee_id' => $employee->id,
                'destination' => $destinations[array_rand($destinations)],
                'purpose' => $purposes[array_rand($purposes)],
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'transport' => $transports[array_rand($transports)],
                'estimated_cost' => $estimatedCost,
                'per_diem' => $perDiem,
                'status' => $status,
                'approved_by' => $isDecided ? $admin->id : null,
                'notes' => $status === 'rejected' ? 'Anggaran perjalanan belum tersedia' : null,
                'created_at' => Carbon::now()->subDays(mt_rand(5, 40)),
                'updated_at' => Carbon::now()->subDays(mt_rand(0, 4)),
            ]);
        }
    }

    /**
     * Calendar events (~15) across event types + a couple birthdays.
     *
     * @param  array{employees: array<int,Employee>, admin: User}  $ctx
     */
    private function seedCalendar(Tenant $tenant, array $ctx): void
    {
        $colors = [
            'holiday' => '#ef4444',
            'meeting' => '#3b82f6',
            'training' => '#8b5cf6',
            'event' => '#22c55e',
            'deadline' => '#f59e0b',
        ];

        $events = [
            ['title' => 'Rapat Bulanan Manajemen', 'type' => 'meeting', 'offset' => -12, 'days' => 0, 'desc' => 'Evaluasi kinerja bulan berjalan'],
            ['title' => 'Rapat Koordinasi Tim', 'type' => 'meeting', 'offset' => 5, 'days' => 0, 'desc' => 'Sinkronisasi target mingguan'],
            ['title' => 'Pelatihan K3 Karyawan', 'type' => 'training', 'offset' => 14, 'days' => 1, 'desc' => 'Sertifikasi keselamatan kerja'],
            ['title' => 'Pelatihan Leadership', 'type' => 'training', 'offset' => -20, 'days' => 2, 'desc' => 'Program pengembangan manajer'],
            ['title' => 'Gajian Bulanan', 'type' => 'event', 'offset' => 3, 'days' => 0, 'desc' => 'Pembayaran gaji karyawan'],
            ['title' => 'Gajian Bulanan', 'type' => 'event', 'offset' => -27, 'days' => 0, 'desc' => 'Pembayaran gaji karyawan'],
            ['title' => 'Batas Pengumpulan Laporan Bulanan', 'type' => 'deadline', 'offset' => 7, 'days' => 0, 'desc' => 'Deadline laporan divisi'],
            ['title' => 'Deadline Pengajuan Klaim', 'type' => 'deadline', 'offset' => 21, 'days' => 0, 'desc' => 'Batas akhir reimburse bulan ini'],
            ['title' => 'Libur Hari Kemerdekaan', 'type' => 'holiday', 'offset' => 18, 'days' => 0, 'desc' => 'Hari Kemerdekaan RI'],
            ['title' => 'Cuti Bersama', 'type' => 'holiday', 'offset' => 30, 'days' => 1, 'desc' => 'Cuti bersama nasional'],
            ['title' => 'Gathering Karyawan', 'type' => 'event', 'offset' => 40, 'days' => 1, 'desc' => 'Acara kebersamaan tahunan'],
            ['title' => 'Town Hall Meeting', 'type' => 'meeting', 'offset' => -5, 'days' => 0, 'desc' => 'Update strategi perusahaan'],
            ['title' => 'Pelatihan Sistem Baru', 'type' => 'training', 'offset' => -40, 'days' => 0, 'desc' => 'Onboarding aplikasi internal'],
        ];

        foreach ($events as $event) {
            $start = Carbon::now()->addDays($event['offset']);
            $end = $event['days'] > 0 ? $start->copy()->addDays($event['days']) : null;

            CalendarEvent::create([
                'tenant_id' => $tenant->id,
                'title' => $event['title'],
                'type' => $event['type'],
                'start_date' => $start->toDateString(),
                'end_date' => $end?->toDateString(),
                'all_day' => $event['type'] !== 'meeting',
                'color' => $colors[$event['type']],
                'description' => $event['desc'],
                'created_at' => Carbon::now()->subDays(mt_rand(10, 40)),
                'updated_at' => Carbon::now()->subDays(mt_rand(0, 9)),
            ]);
        }

        $candidates = array_values($ctx['employees']);
        shuffle($candidates);

        foreach (array_slice($candidates, 0, 2) as $employee) {
            $start = Carbon::now()->addDays(mt_rand(-50, 55));

            CalendarEvent::create([
                'tenant_id' => $tenant->id,
                'title' => 'Ulang Tahun '.$employee->full_name,
                'type' => 'event',
                'start_date' => $start->toDateString(),
                'end_date' => null,
                'all_day' => true,
                'color' => $colors['event'],
                'description' => 'Selamat ulang tahun!',
                'created_at' => Carbon::now()->subDays(mt_rand(10, 40)),
                'updated_at' => Carbon::now()->subDays(mt_rand(0, 9)),
            ]);
        }
    }

    /**
     * Custom fields (~6) on the employee entity.
     *
     * @param  array{employees: array<int,Employee>, admin: User}  $ctx
     */
    private function seedCustomFields(Tenant $tenant, array $ctx): void
    {
        $fields = [
            ['key' => 'ukuran_seragam', 'label' => 'Ukuran Seragam', 'type' => 'select', 'options' => ['S', 'M', 'L', 'XL', 'XXL'], 'is_required' => false],
            ['key' => 'golongan_darah', 'label' => 'Golongan Darah', 'type' => 'select', 'options' => ['A', 'B', 'AB', 'O'], 'is_required' => false],
            ['key' => 'nomor_darurat', 'label' => 'Nomor Kontak Darurat', 'type' => 'text', 'options' => null, 'is_required' => true],
            ['key' => 'nama_kontak_darurat', 'label' => 'Nama Kontak Darurat', 'type' => 'text', 'options' => null, 'is_required' => true],
            ['key' => 'jumlah_tanggungan', 'label' => 'Jumlah Tanggungan', 'type' => 'number', 'options' => null, 'is_required' => false],
            ['key' => 'tanggal_masuk_asuransi', 'label' => 'Tanggal Aktif Asuransi', 'type' => 'date', 'options' => null, 'is_required' => false],
        ];

        $sortOrder = 1;

        foreach ($fields as $field) {
            CustomField::create([
                'tenant_id' => $tenant->id,
                'entity' => 'employee',
                'key' => $field['key'],
                'label' => $field['label'],
                'type' => $field['type'],
                'options' => $field['options'],
                'is_required' => $field['is_required'],
                'sort_order' => $sortOrder++,
                'status' => 'active',
            ]);
        }

        // Populate demo employees' custom_data so the custom fields show real
        // values (e.g. in Report Studio) rather than empty groups.
        $golongan = ['A', 'B', 'AB', 'O'];
        $ukuran = ['M', 'L', 'XL', 'M', 'L'];
        foreach (array_values($ctx['employees']) as $i => $employee) {
            $employee->forceFill(['custom_data' => array_merge((array) $employee->custom_data, [
                'ukuran_seragam' => $ukuran[$i % count($ukuran)],
                'golongan_darah' => $golongan[$i % count($golongan)],
                'jumlah_tanggungan' => $i % 4,
                'nomor_darurat' => '0812'.str_pad((string) ($i + 1), 8, '0', STR_PAD_LEFT),
                'nama_kontak_darurat' => 'Kontak Darurat '.($i + 1),
            ])])->save();
        }
    }

    /**
     * Helpdesk tickets (~12) with replies.
     *
     * @param  array{employees: array<int,Employee>, admin: User}  $ctx
     */
    private function seedHelpdesk(Tenant $tenant, array $ctx): void
    {
        $employees = $ctx['employees'];
        $admin = $ctx['admin'];

        $categories = ['it', 'hr', 'payroll', 'facility', 'other'];
        $priorities = ['low', 'medium', 'high', 'urgent'];
        $statuses = ['open', 'open', 'in_progress', 'in_progress', 'resolved', 'resolved', 'closed'];

        $subjectsByCategory = [
            'it' => ['Laptop tidak bisa menyala', 'Reset password email kantor', 'Akses VPN error saat WFH', 'Printer tidak terdeteksi di jaringan', 'Permintaan instalasi software akuntansi'],
            'hr' => ['Pertanyaan sisa cuti tahunan', 'Update data keluarga di sistem', 'Permintaan surat keterangan kerja', 'Klarifikasi jadwal shift bulan depan'],
            'payroll' => ['Selisih perhitungan lembur', 'Slip gaji bulan lalu belum diterima', 'Pertanyaan potongan BPJS', 'Perubahan nomor rekening payroll'],
            'facility' => ['AC ruang meeting tidak dingin', 'Permintaan kursi kerja baru', 'Lampu ruangan mati'],
            'other' => ['Usulan kegiatan gathering karyawan', 'Pertanyaan umum kebijakan kantor'],
        ];

        $agentReplies = [
            'Terima kasih atas laporannya, tim kami sedang menindaklanjuti.',
            'Mohon informasikan detail tambahan agar dapat kami proses lebih cepat.',
            'Kendala sudah kami cek, akan kami update perkembangannya segera.',
            'Tiket Anda sudah kami eskalasi ke tim terkait.',
            'Permintaan Anda telah selesai diproses, mohon konfirmasinya.',
        ];

        for ($i = 1; $i <= 12; $i++) {
            $category = $categories[array_rand($categories)];
            $subject = $subjectsByCategory[$category][array_rand($subjectsByCategory[$category])];
            $status = $statuses[array_rand($statuses)];
            $requester = $employees[array_rand($employees)];
            $createdAt = Carbon::now()->subDays(mt_rand(1, 75))->setTime(mt_rand(8, 17), mt_rand(0, 59));

            $isHandled = $status !== 'open';
            $resolvedAt = in_array($status, ['resolved', 'closed'], true)
                ? $createdAt->copy()->addDays(mt_rand(1, 5))
                : null;

            $ticket = Ticket::create([
                'tenant_id' => $tenant->id,
                'ticket_no' => 'TIK-'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'requester_id' => $requester->id,
                'category' => $category,
                'subject' => $subject,
                'description' => $subject.'. Mohon bantuannya untuk segera ditindaklanjuti. Terima kasih.',
                'priority' => $priorities[array_rand($priorities)],
                'status' => $status,
                'assignee_id' => $isHandled ? $admin->id : null,
                'resolved_at' => $resolvedAt,
                'created_at' => $createdAt,
                'updated_at' => $resolvedAt ?? $createdAt,
            ]);

            $replyCount = mt_rand(1, 3);
            for ($r = 0; $r < $replyCount; $r++) {
                $replyAt = $createdAt->copy()->addHours(($r + 1) * mt_rand(2, 12));
                TicketReply::create([
                    'tenant_id' => $tenant->id,
                    'ticket_id' => $ticket->id,
                    'user_id' => $admin->id,
                    'message' => $agentReplies[array_rand($agentReplies)],
                    'created_at' => $replyAt,
                    'updated_at' => $replyAt,
                ]);
            }
        }
    }

    /**
     * Announcements (~8), a mix of published + draft, some pinned.
     *
     * @param  array{employees: array<int,Employee>, admin: User}  $ctx
     */
    private function seedAnnouncements(Tenant $tenant, array $ctx): void
    {
        $items = [
            ['Libur Nasional Idul Fitri', 'Kantor akan libur pada tanggal yang ditetapkan pemerintah. Selamat merayakan Hari Raya Idul Fitri bagi yang menjalankan.', 'Umum'],
            ['Kebijakan Kerja Hybrid Terbaru', 'Mulai bulan ini karyawan dapat bekerja dari rumah maksimal 2 hari per minggu dengan persetujuan atasan.', 'Kebijakan'],
            ['Pembaruan Sistem HRIS', 'Sistem HRIS akan mengalami pemeliharaan pada akhir pekan. Mohon selesaikan pengajuan cuti sebelum jadwal maintenance.', 'IT'],
            ['Employee Gathering Tahunan', 'Acara gathering karyawan akan diselenggarakan bulan depan. Detail lokasi dan rundown akan menyusul.', 'Event'],
            ['Pengumpulan Data BPJS Ketenagakerjaan', 'Mohon seluruh karyawan melengkapi data BPJS melalui portal HR paling lambat akhir bulan.', 'HR'],
            ['Perubahan Jam Operasional Kantor', 'Jam operasional kantor menjadi pukul 08.00 - 17.00 efektif mulai minggu depan.', 'Umum'],
            ['Program Vaksinasi Karyawan', 'Perusahaan bekerja sama dengan klinik menyediakan vaksinasi gratis bagi seluruh karyawan.', 'HR'],
            ['Penghargaan Karyawan Terbaik', 'Selamat kepada karyawan terbaik kuartal ini. Terima kasih atas dedikasi dan kontribusinya.', 'Event'],
        ];

        foreach ($items as $idx => [$title, $body, $category]) {
            $isDraft = $idx >= 6;
            $publishedAt = $isDraft
                ? null
                : Carbon::now()->subDays(mt_rand(1, 90))->setTime(mt_rand(8, 16), mt_rand(0, 59));

            Announcement::create([
                'tenant_id' => $tenant->id,
                'title' => $title,
                'body' => $body,
                'category' => $category,
                'status' => $isDraft ? 'draft' : 'published',
                'published_at' => $publishedAt,
                'pinned' => $idx < 2,
                'created_at' => $publishedAt ?? Carbon::now()->subDays(mt_rand(1, 10)),
                'updated_at' => $publishedAt ?? Carbon::now(),
            ]);
        }
    }

    /**
     * Surveys (3) with questions + responses from a sample of employees.
     *
     * @param  array{employees: array<int,Employee>, admin: User}  $ctx
     */
    private function seedSurveys(Tenant $tenant, array $ctx): void
    {
        $employees = $ctx['employees'];

        $surveyDefs = [
            [
                'title' => 'Employee Engagement Survey',
                'description' => 'Survei keterlibatan karyawan untuk mengukur kepuasan dan motivasi kerja.',
                'status' => 'active',
                'is_anonymous' => true,
                'questions' => [
                    ['Seberapa puas Anda bekerja di perusahaan ini?', 'rating', null],
                    ['Apakah Anda merasa dihargai oleh atasan?', 'rating', null],
                    ['Bagaimana keseimbangan kehidupan kerja Anda?', 'rating', null],
                    ['Apakah Anda akan merekomendasikan perusahaan ini?', 'choice', ['Ya', 'Mungkin', 'Tidak']],
                    ['Masukan atau saran untuk perusahaan?', 'text', null],
                ],
            ],
            [
                'title' => 'Kepuasan Kerja Karyawan',
                'description' => 'Mengukur tingkat kepuasan karyawan terhadap lingkungan dan fasilitas kerja.',
                'status' => 'active',
                'is_anonymous' => false,
                'questions' => [
                    ['Bagaimana penilaian Anda terhadap fasilitas kantor?', 'rating', null],
                    ['Seberapa jelas komunikasi dari manajemen?', 'rating', null],
                    ['Apakah beban kerja Anda sudah sesuai?', 'choice', ['Terlalu ringan', 'Sesuai', 'Terlalu berat']],
                    ['Aspek apa yang paling perlu ditingkatkan?', 'choice', ['Gaji', 'Fasilitas', 'Manajemen', 'Jenjang Karier']],
                    ['Komentar tambahan?', 'text', null],
                ],
            ],
            [
                'title' => 'Evaluasi Pelatihan dan Pengembangan',
                'description' => 'Umpan balik atas program pelatihan yang telah diselenggarakan.',
                'status' => 'closed',
                'is_anonymous' => true,
                'questions' => [
                    ['Seberapa bermanfaat pelatihan yang diberikan?', 'rating', null],
                    ['Apakah materi pelatihan mudah dipahami?', 'rating', null],
                    ['Topik pelatihan apa yang Anda inginkan berikutnya?', 'text', null],
                    ['Apakah durasi pelatihan sudah cukup?', 'choice', ['Kurang', 'Cukup', 'Terlalu lama']],
                    ['Apakah Anda ingin mengikuti pelatihan lanjutan?', 'choice', ['Ya', 'Tidak']],
                ],
            ],
        ];

        $textAnswers = [
            'Secara keseluruhan sudah baik, mohon dipertahankan.',
            'Perlu peningkatan pada fasilitas dan komunikasi internal.',
            'Lingkungan kerja nyaman dan mendukung produktivitas.',
            'Harapan saya proses kerja bisa lebih efisien.',
            'Terima kasih atas perhatian manajemen selama ini.',
        ];

        foreach ($surveyDefs as $def) {
            $survey = Survey::create([
                'tenant_id' => $tenant->id,
                'title' => $def['title'],
                'description' => $def['description'],
                'status' => $def['status'],
                'is_anonymous' => $def['is_anonymous'],
            ]);

            $questions = [];
            foreach ($def['questions'] as [$question, $type, $options]) {
                $questions[] = SurveyQuestion::create([
                    'tenant_id' => $tenant->id,
                    'survey_id' => $survey->id,
                    'question' => $question,
                    'type' => $type,
                    'options' => $type === 'choice' ? $options : null,
                ]);
            }

            $sample = collect($employees)->shuffle()
                ->take((int) ceil(count($employees) * (mt_rand(50, 70) / 100)))
                ->all();

            foreach ($sample as $employee) {
                $respondedAt = Carbon::now()->subDays(mt_rand(1, 40))->setTime(mt_rand(8, 17), mt_rand(0, 59));
                foreach ($questions as $question) {
                    $answer = match ($question->type) {
                        'rating' => (string) mt_rand(3, 5),
                        'choice' => $question->options[array_rand($question->options)],
                        default => $textAnswers[array_rand($textAnswers)],
                    };

                    SurveyResponse::create([
                        'tenant_id' => $tenant->id,
                        'survey_id' => $survey->id,
                        'survey_question_id' => $question->id,
                        'employee_id' => $def['is_anonymous'] ? null : $employee->id,
                        'answer' => $answer,
                        'created_at' => $respondedAt,
                        'updated_at' => $respondedAt,
                    ]);
                }
            }
        }
    }

    /**
     * Assets (~20) + assignments (~60% to employees).
     *
     * @param  array{employees: array<int,Employee>, admin: User}  $ctx
     */
    private function seedAssets(Tenant $tenant, array $ctx): void
    {
        $employees = $ctx['employees'];

        $catalog = [
            ['Laptop', 'Elektronik', 8_000_000, 25_000_000],
            ['Monitor', 'Elektronik', 1_500_000, 5_000_000],
            ['Smartphone', 'Elektronik', 3_000_000, 18_000_000],
            ['Printer', 'Elektronik', 1_000_000, 6_000_000],
            ['Proyektor', 'Elektronik', 4_000_000, 12_000_000],
            ['Meja Kerja', 'Furnitur', 800_000, 3_000_000],
            ['Kursi Kantor', 'Furnitur', 700_000, 2_500_000],
            ['Lemari Arsip', 'Furnitur', 1_200_000, 4_000_000],
            ['Motor Operasional', 'Kendaraan', 18_000_000, 30_000_000],
            ['Mobil Operasional', 'Kendaraan', 150_000_000, 350_000_000],
            ['Lisensi Office', 'Perangkat Lunak', 1_500_000, 5_000_000],
            ['Software Akuntansi', 'Perangkat Lunak', 5_000_000, 20_000_000],
            ['Mesin Fotokopi', 'Peralatan Kantor', 8_000_000, 25_000_000],
            ['Dispenser Air', 'Peralatan Kantor', 500_000, 2_000_000],
        ];

        $conditions = ['good', 'good', 'good', 'fair', 'damaged'];

        $assets = [];
        for ($i = 1; $i <= 20; $i++) {
            $item = $catalog[array_rand($catalog)];
            $assets[] = Asset::create([
                'tenant_id' => $tenant->id,
                'code' => 'AST-'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'name' => $item[0].' Unit '.chr(64 + mt_rand(1, 8)).mt_rand(1, 99),
                'category' => $item[1],
                'purchase_date' => Carbon::now()->subDays(mt_rand(60, 1095)),
                'purchase_cost' => mt_rand((int) ($item[2] / 100_000), (int) ($item[3] / 100_000)) * 100_000,
                'depreciation_years' => mt_rand(3, 8),
                'condition' => $conditions[array_rand($conditions)],
                'status' => 'available',
                'notes' => mt_rand(0, 1) === 1 ? 'Dibeli untuk kebutuhan operasional kantor.' : null,
            ]);
        }

        $assignCount = (int) round(count($assets) * 0.6);
        $toAssign = collect($assets)->shuffle()->take($assignCount);

        foreach ($toAssign as $asset) {
            $employee = $employees[array_rand($employees)];
            $assignedDate = Carbon::now()->subDays(mt_rand(10, 300));
            $isReturned = mt_rand(0, 3) === 0;
            $returnedDate = $isReturned ? $assignedDate->copy()->addDays(mt_rand(20, 120)) : null;

            AssetAssignment::create([
                'tenant_id' => $tenant->id,
                'asset_id' => $asset->id,
                'employee_id' => $employee->id,
                'assigned_date' => $assignedDate,
                'returned_date' => $returnedDate,
                'condition_note' => $isReturned ? 'Dikembalikan dalam kondisi baik.' : 'Sedang digunakan karyawan.',
            ]);

            if (! $isReturned) {
                $asset->update(['status' => 'assigned']);
            }
        }
    }

    /**
     * CRM contacts (~15) + deals (~10).
     *
     * @param  array{employees: array<int,Employee>, admin: User}  $ctx
     */
    private function seedCrm(Tenant $tenant, array $ctx): void
    {
        $employees = $ctx['employees'];

        $companies = [
            'PT Maju Bersama Sejahtera', 'CV Karya Nusantara', 'PT Sinar Abadi Group',
            'PT Bintang Timur Logistik', 'CV Sumber Rejeki', 'PT Cahaya Digital Indonesia',
            'PT Nusantara Sukses Mandiri', 'CV Berkah Jaya', 'PT Global Teknindo',
            'PT Anugerah Prima', 'CV Mitra Solusi', 'PT Sentosa Makmur',
            'PT Indah Permai', 'CV Cipta Kreasi', 'PT Trijaya Elektronik',
        ];

        $firstNames = ['Budi', 'Siti', 'Andi', 'Dewi', 'Rudi', 'Rina', 'Agus', 'Maya', 'Hendra', 'Lestari', 'Fajar', 'Putri', 'Bayu', 'Wulan', 'Dimas'];
        $lastNames = ['Santoso', 'Wijaya', 'Kusuma', 'Pratama', 'Hidayat', 'Nugroho', 'Saputra', 'Halim', 'Permana', 'Utami'];

        $contacts = [];
        foreach ($companies as $company) {
            $name = $firstNames[array_rand($firstNames)].' '.$lastNames[array_rand($lastNames)];
            $parts = explode(' ', $company);
            $slug = Str::slug($parts[1] ?? 'mitra');

            $contacts[] = CrmContact::create([
                'tenant_id' => $tenant->id,
                'name' => $name,
                'company' => $company,
                'email' => strtolower(str_replace(' ', '.', $name)).'@'.$slug.'.co.id',
                'phone' => '08'.mt_rand(11, 99).mt_rand(1000000, 9999999),
                'notes' => 'Lead dari '.$company.'.',
            ]);
        }

        $stages = ['lead', 'lead', 'qualified', 'qualified', 'proposal', 'won', 'lost'];
        $dealTitles = [
            'Implementasi Sistem HRIS', 'Langganan Paket Enterprise', 'Pengadaan Modul Payroll',
            'Kerjasama Rekrutmen', 'Upgrade Lisensi Tahunan', 'Konsultasi Manajemen SDM',
            'Integrasi Absensi Fingerprint', 'Paket Training Karyawan', 'Migrasi Data Karyawan',
            'Kontrak Maintenance Sistem',
        ];

        foreach ($dealTitles as $title) {
            $contact = $contacts[array_rand($contacts)];
            $stage = $stages[array_rand($stages)];
            $owner = $employees[array_rand($employees)];

            CrmDeal::create([
                'tenant_id' => $tenant->id,
                'contact_id' => $contact->id,
                'title' => $title,
                'value' => mt_rand(15, 500) * 1_000_000,
                'stage' => $stage,
                'owner_id' => $owner->id,
                'expected_close' => in_array($stage, ['won', 'lost'], true)
                    ? Carbon::now()->subDays(mt_rand(5, 60))
                    : Carbon::now()->addDays(mt_rand(15, 120)),
                'notes' => $title.' untuk '.$contact->company.'.',
            ]);
        }
    }

    /**
     * Saved (dynamic) reports (~4) across allowlisted entities.
     *
     * @param  array{employees: array<int,Employee>, admin: User}  $ctx
     */
    private function seedSavedReports(Tenant $tenant, array $ctx): void
    {
        $admin = $ctx['admin'];

        $reports = [
            ['name' => 'Daftar Karyawan Aktif', 'entity' => 'employees', 'columns' => ['employee_number', 'full_name', 'department', 'employment_status', 'status', 'join_date'], 'filters' => ['status' => 'active']],
            ['name' => 'Rekap Cuti Disetujui', 'entity' => 'leave', 'columns' => ['employee', 'leave_type', 'start_date', 'end_date', 'total_days', 'status'], 'filters' => ['status' => 'approved']],
            ['name' => 'Klaim Menunggu Persetujuan', 'entity' => 'claims', 'columns' => ['employee', 'claim_type', 'title', 'amount', 'claim_date', 'status'], 'filters' => ['status' => 'pending']],
            ['name' => 'Laporan Keterlambatan Absensi', 'entity' => 'attendance', 'columns' => ['employee', 'date', 'clock_in_at', 'late_minutes', 'status'], 'filters' => ['status' => 'late']],
        ];

        foreach ($reports as $report) {
            SavedReport::create([
                'tenant_id' => $tenant->id,
                'name' => $report['name'],
                'entity' => $report['entity'],
                'columns' => $report['columns'],
                'filters' => $report['filters'],
                'created_by' => $admin->id,
            ]);
        }
    }

    /**
     * Recruitment: ~5 job postings + ~20 applicants across pipeline stages.
     *
     * @param  array{employees: array<int,Employee>, departments: array<int,Department>, admin: User}  $ctx
     */
    private function seedRecruitment(Tenant $tenant, array $ctx): void
    {
        $departments = $ctx['departments'];

        $positions = [
            'Software Engineer', 'Sales Executive', 'HR Generalist',
            'Staff Akuntansi', 'Digital Marketing Specialist',
            'Customer Service Officer', 'Operations Supervisor',
            'Business Development Manager',
        ];
        $employmentTypes = ['tetap', 'kontrak', 'magang', 'harian'];
        $locations = ['Jakarta', 'Bandung', 'Surabaya', 'Yogyakarta', 'Remote'];
        $sources = ['LinkedIn', 'JobStreet', 'Referral', 'Website Karier', 'Glints', 'Walk-in'];
        $firstNames = ['Budi', 'Siti', 'Andi', 'Dewi', 'Rizki', 'Putri', 'Agus', 'Rina', 'Fajar', 'Wulan', 'Hendra', 'Maya', 'Bayu', 'Sri', 'Dimas', 'Ayu', 'Eko', 'Nur', 'Yoga', 'Indah', 'Rangga', 'Arif', 'Fitri', 'Galih', 'Ratna'];
        $lastNames = ['Santoso', 'Wijaya', 'Nugroho', 'Pratama', 'Susanti', 'Kurniawan', 'Hartono', 'Permata', 'Setiawan', 'Anggraini', 'Saputra', 'Handayani', 'Ramadhan', 'Cahyani', 'Firmansyah', 'Maulana', 'Puspita', 'Wibowo', 'Utami', 'Halim'];

        $postings = [];
        for ($i = 0; $i < 5; $i++) {
            $title = $positions[$i % count($positions)];
            $department = $departments === [] ? null : $departments[array_rand($departments)];
            $postedDate = Carbon::now()->subDays(mt_rand(20, 120));

            $postings[] = JobPosting::create([
                'tenant_id' => $tenant->id,
                'department_id' => $department?->id,
                'title' => $title,
                'location' => $locations[array_rand($locations)],
                'employment_type' => $employmentTypes[array_rand($employmentTypes)],
                'quota' => mt_rand(1, 5),
                'status' => $i < 3 ? 'open' : 'closed',
                'description' => "Kami membuka lowongan {$title}. Kandidat diharapkan memiliki pengalaman relevan, komunikatif, dan mampu bekerja dalam tim.",
                'posted_date' => $postedDate->toDateString(),
                'closing_date' => $postedDate->copy()->addDays(mt_rand(21, 60))->toDateString(),
            ]);
        }

        $stageDistribution = array_merge(
            array_fill(0, 6, 'applied'),
            array_fill(0, 4, 'screening'),
            array_fill(0, 3, 'interview'),
            array_fill(0, 2, 'offer'),
            array_fill(0, 3, 'hired'),
            array_fill(0, 2, 'rejected'),
        );
        shuffle($stageDistribution);

        foreach ($stageDistribution as $stage) {
            $first = $firstNames[array_rand($firstNames)];
            $last = $lastNames[array_rand($lastNames)];
            $posting = $postings[array_rand($postings)];
            $appliedDate = Carbon::now()->subDays(mt_rand(3, 90));
            $blacklisted = $stage === 'rejected' && mt_rand(1, 100) <= 25;

            Applicant::create([
                'tenant_id' => $tenant->id,
                'job_posting_id' => $posting->id,
                'position' => $posting->title,
                'name' => "{$first} {$last}",
                'email' => strtolower($first.'.'.$last).mt_rand(1, 99).'@gmail.com',
                'phone' => '08'.mt_rand(11, 99).mt_rand(1000000, 9999999),
                'source' => $sources[array_rand($sources)],
                'linkedin_url' => 'https://www.linkedin.com/in/'.strtolower($first.'-'.$last).'-'.mt_rand(100, 999),
                'stage' => $stage,
                'blacklisted' => $blacklisted,
                'blacklist_reason' => $blacklisted ? 'Tidak lolos verifikasi referensi kerja sebelumnya.' : null,
                'applied_date' => $appliedDate->toDateString(),
                'interview_at' => in_array($stage, ['interview', 'offer', 'hired'], true)
                    ? $appliedDate->copy()->addDays(mt_rand(3, 10))->setTime(mt_rand(9, 15), 0)
                    : null,
                'offered_at' => in_array($stage, ['offer', 'hired'], true)
                    ? $appliedDate->copy()->addDays(mt_rand(11, 20))
                    : null,
                'offer_note' => $stage === 'offer'
                    ? 'Penawaran sesuai struktur gaji perusahaan, menunggu konfirmasi kandidat.'
                    : null,
                'notes' => mt_rand(0, 1) === 1 ? 'Pelamar untuk posisi '.$posting->title.'. Portofolio cukup relevan.' : null,
            ]);
        }
    }

    /**
     * Performance: 2 cycles, reviews for a sample, objectives + key results.
     *
     * @param  array{employees: array<int,Employee>, admin: User}  $ctx
     */
    private function seedPerformance(Tenant $tenant, array $ctx): void
    {
        $employees = $ctx['employees'];
        $admin = $ctx['admin'];

        $cyclesData = [
            ['name' => 'Penilaian Tahunan 2025', 'start' => '2025-01-01', 'end' => '2025-12-31', 'status' => 'closed'],
            ['name' => 'Penilaian Semester 1 2026', 'start' => '2026-01-01', 'end' => '2026-06-30', 'status' => 'active'],
        ];
        $cycles = [];
        foreach ($cyclesData as $data) {
            $cycles[] = PerformanceCycle::create([
                'tenant_id' => $tenant->id,
                'name' => $data['name'],
                'period_start' => $data['start'],
                'period_end' => $data['end'],
                'status' => $data['status'],
                'description' => 'Siklus penilaian kinerja karyawan periode '.$data['name'].'.',
            ]);
        }

        $activeStatuses = ['pending', 'self_review', 'manager_review', 'calibration'];
        $sample = array_slice($employees, 0, min(count($employees), 12));

        foreach ($sample as $index => $employee) {
            $cycle = $cycles[$index % count($cycles)];
            $completed = $cycle->status === 'closed';
            $status = $completed ? 'completed' : $activeStatuses[array_rand($activeStatuses)];

            $reviewer = null;
            if (count($employees) > 1) {
                do {
                    $candidate = $employees[array_rand($employees)];
                } while ($candidate->id === $employee->id);
                $reviewer = $candidate;
            }

            $selfScore = mt_rand(60, 95);
            $managerScore = mt_rand(60, 95);
            $hasManagerScore = in_array($status, ['manager_review', 'calibration', 'completed'], true);
            $finalScore = $completed ? min(100, max(0, (int) round(($selfScore + $managerScore) / 2) + mt_rand(-3, 3))) : null;
            $finalized = $completed ? Carbon::parse($cycle->period_end)->addDays(mt_rand(5, 20)) : null;

            PerformanceReview::create([
                'tenant_id' => $tenant->id,
                'cycle_id' => $cycle->id,
                'employee_id' => $employee->id,
                'reviewer_id' => $reviewer?->id,
                'self_score' => $status === 'pending' ? null : $selfScore,
                'manager_score' => $hasManagerScore ? $managerScore : null,
                'final_score' => $finalScore,
                'calibrated_score' => $completed ? $finalScore : null,
                'calibrated_by' => $completed ? $admin->id : null,
                'calibrated_at' => $finalized,
                'status' => $status,
                'notes' => $completed ? 'Kinerja sesuai ekspektasi; fokus pengembangan pada kompetensi kepemimpinan.' : null,
                'review_date' => $finalized?->toDateString(),
            ]);
        }

        $objectiveTitles = [
            'Meningkatkan Kepuasan Pelanggan',
            'Menaikkan Pendapatan Penjualan',
            'Meningkatkan Efisiensi Operasional',
            'Memperkuat Employer Branding',
            'Menurunkan Tingkat Turnover Karyawan',
            'Mempercepat Waktu Rekrutmen',
            'Meningkatkan Kualitas Produk',
            'Digitalisasi Proses Bisnis',
        ];
        $levels = ['company', 'team', 'individual'];
        $perspectives = ['financial', 'customer', 'internal', 'learning'];
        $krUnits = ['%', 'unit', 'orang', 'hari', 'Rp juta'];
        $activeCycle = $cycles[array_key_last($cycles)];

        foreach ($objectiveTitles as $i => $title) {
            $level = $levels[$i % count($levels)];
            $objectiveEmployee = ($level === 'company' || $employees === []) ? null : $employees[array_rand($employees)];
            $status = match (true) {
                $i < 4 => 'active',
                $i < 6 => 'done',
                $i === 6 => 'draft',
                default => 'cancelled',
            };

            $objective = Objective::create([
                'tenant_id' => $tenant->id,
                'cycle_id' => $activeCycle->id,
                'employee_id' => $objectiveEmployee?->id,
                'title' => $title,
                'description' => 'Objective strategis untuk '.$title.' pada periode berjalan.',
                'level' => $level,
                'perspective' => $perspectives[$i % count($perspectives)],
                'status' => $status,
                'progress' => 0,
            ]);

            $progresses = [];
            $krCount = mt_rand(2, 3);
            for ($k = 0; $k < $krCount; $k++) {
                $target = mt_rand(1, 10) * 10;
                $current = $status === 'done' ? $target : mt_rand(0, $target);
                $progress = (int) round(min(100, max(0, $current / $target * 100)));
                $progresses[] = $progress;

                KeyResult::create([
                    'tenant_id' => $tenant->id,
                    'objective_id' => $objective->id,
                    'title' => 'Key Result '.($k + 1).': '.$title,
                    'target_value' => $target,
                    'current_value' => $current,
                    'unit' => $krUnits[array_rand($krUnits)],
                    'progress' => $progress,
                ]);
            }

            $objective->update([
                'progress' => (int) round(array_sum($progresses) / count($progresses)),
            ]);
        }
    }

    /**
     * Competencies (6) + per-employee assessments for a ~60% sample.
     *
     * @param  array{employees: array<int,Employee>, admin: User}  $ctx
     */
    private function seedCompetencies(Tenant $tenant, array $ctx): void
    {
        $employees = $ctx['employees'];

        $competencyDefs = [
            ['Komunikasi', 'Soft Skill'],
            ['Kepemimpinan', 'Leadership'],
            ['Kerja Sama Tim', 'Soft Skill'],
            ['Analisis & Pemecahan Masalah', 'Technical'],
            ['Manajemen Waktu', 'Soft Skill'],
            ['Orientasi pada Hasil', 'Core Values'],
        ];

        $competencies = [];
        foreach ($competencyDefs as $def) {
            $competencies[] = Competency::create([
                'tenant_id' => $tenant->id,
                'name' => $def[0],
                'category' => $def[1],
                'description' => 'Kompetensi '.$def[0].' yang dinilai dalam matriks kompetensi karyawan.',
            ]);
        }

        if ($employees === []) {
            return;
        }

        $subset = array_slice($employees, 0, max(1, (int) ceil(count($employees) * 0.6)));

        foreach ($subset as $employee) {
            $picked = $competencies;
            shuffle($picked);
            $count = mt_rand(3, count($competencies));

            foreach (array_slice($picked, 0, $count) as $competency) {
                EmployeeCompetency::create([
                    'tenant_id' => $tenant->id,
                    'employee_id' => $employee->id,
                    'competency_id' => $competency->id,
                    'level' => mt_rand(1, 5),
                    'assessed_at' => Carbon::now()->subDays(mt_rand(10, 120))->toDateString(),
                ]);
            }
        }
    }

    /**
     * Learning (LMS): 6 trainings + ~30 enrollments correlated to status.
     *
     * @param  array{employees: array<int,Employee>, admin: User}  $ctx
     */
    private function seedLearning(Tenant $tenant, array $ctx): void
    {
        $employees = $ctx['employees'];

        $trainingDefs = [
            ['Pelatihan K3 Dasar', 'Keselamatan Kerja', 'internal', 'Budi Hartono'],
            ['Leadership Development Program', 'Kepemimpinan', 'external', 'Lembaga Manajemen PPM'],
            ['Digital Marketing Fundamental', 'Pemasaran', 'online', 'Skill Academy'],
            ['Microsoft Excel untuk Analisis Data', 'Teknologi', 'online', 'MyEduSolve'],
            ['Service Excellence', 'Layanan Pelanggan', 'internal', 'Rina Kurniawan'],
            ['Manajemen Proyek (Persiapan PMP)', 'Manajemen', 'external', 'PMI Indonesia'],
        ];
        $trainingStatuses = ['completed', 'completed', 'ongoing', 'ongoing', 'planned', 'planned'];

        $trainings = [];
        foreach ($trainingDefs as $i => $def) {
            $status = $trainingStatuses[$i];
            $start = match ($status) {
                'completed' => Carbon::now()->subDays(mt_rand(60, 150)),
                'ongoing' => Carbon::now()->subDays(mt_rand(3, 20)),
                default => Carbon::now()->addDays(mt_rand(10, 45)),
            };

            $trainings[] = Training::create([
                'tenant_id' => $tenant->id,
                'title' => $def[0],
                'category' => $def[1],
                'type' => $def[2],
                'start_date' => $start->toDateString(),
                'end_date' => $start->copy()->addDays(mt_rand(1, 5))->toDateString(),
                'cost' => $def[2] === 'internal' ? 0 : mt_rand(2, 25) * 500000,
                'instructor' => $def[3],
                'quota' => mt_rand(15, 40),
                'status' => $status,
                'description' => 'Program pelatihan '.$def[0].' untuk pengembangan kompetensi karyawan.',
            ]);
        }

        if ($employees === []) {
            return;
        }

        $pairs = [];
        foreach ($trainings as $training) {
            foreach ($employees as $employee) {
                $pairs[] = [$training, $employee];
            }
        }
        shuffle($pairs);

        $target = min(30, count($pairs));

        for ($i = 0; $i < $target; $i++) {
            [$training, $employee] = $pairs[$i];
            $statusPool = match ($training->status) {
                'completed' => ['completed', 'completed', 'completed', 'attended'],
                'ongoing' => ['attended', 'attended', 'enrolled'],
                default => ['enrolled'],
            };
            $status = $statusPool[array_rand($statusPool)];
            $completed = $status === 'completed';

            TrainingEnrollment::create([
                'tenant_id' => $tenant->id,
                'training_id' => $training->id,
                'employee_id' => $employee->id,
                'status' => $status,
                'score' => $completed ? mt_rand(70, 100) : null,
                'certificate_no' => $completed
                    ? 'CERT/'.$tenant->id.'/'.$training->id.'/'.str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT)
                    : null,
                'completed_date' => $completed
                    ? Carbon::parse($training->end_date)->addDays(mt_rand(1, 7))->toDateString()
                    : null,
            ]);
        }
    }
}
