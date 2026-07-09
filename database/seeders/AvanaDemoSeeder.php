<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\BpjsProgram;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Feature;
use App\Models\JobLevel;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Package;
use App\Models\PayrollComponent;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\Permission;
use App\Models\PkpRate;
use App\Models\Position;
use App\Models\PtkpRate;
use App\Models\Role;
use App\Models\Shift;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkLocation;
use App\Support\AvanaNav;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

/**
 * Seeds the AvanaHR demo tenant (PT Nusantara Jaya) that backs the
 * /avana prototype screens: 4 roles, org structure, 10 employees,
 * leave types, payroll period, BPJS/PPh21 config, and a login account.
 */
final class AvanaDemoSeeder extends Seeder
{
    public function run(): void
    {
        $features = $this->seedFeatures();
        $package = Package::firstOrCreate(
            ['code' => 'pro'],
            ['name' => 'Pro', 'price' => 1500000, 'billing_cycle' => 'monthly', 'max_users' => 100, 'max_employees' => 2000, 'max_branches' => 20],
        );
        $package->features()->syncWithoutDetaching($features->pluck('id'));

        $tenant = Tenant::firstOrCreate(
            ['slug' => 'nusantara'],
            [
                'name' => 'PT Nusantara Jaya',
                'company_name' => 'PT Nusantara Jaya',
                'package_id' => $package->id,
                'status' => 'active',
                'max_users' => 100,
                'max_employees' => 2000,
                'max_branches' => 20,
                'billing_status' => 'active',
                'start_date' => '2026-01-01',
            ],
        );
        foreach ($features as $feature) {
            $tenant->features()->firstOrCreate(['feature_id' => $feature->id], ['is_enabled' => true]);
        }

        $this->seedPermissionsAndRoles($tenant);
        $this->seedMenuItems($tenant);
        $admin = $this->seedAdminUser($tenant);

        $company = Company::firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'PT Nusantara Jaya'],
            ['legal_name' => 'PT Nusantara Jaya', 'status' => 'active'],
        );

        $branches = $this->seedBranches($tenant, $company);
        $departments = $this->seedDepartments($tenant);
        $jobLevel = JobLevel::firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'STF'],
            ['name' => 'Staff', 'level_order' => 1],
        );

        $employees = $this->seedEmployees($tenant, $branches, $departments, $jobLevel, $admin);
        $this->seedEmployeeUser($tenant, $employees);
        $leaveTypes = $this->seedLeaveTypes($tenant);
        $this->seedLeaveBalances($tenant, $employees, $leaveTypes);
        $this->seedLeaveRequests($tenant, $employees, $leaveTypes, $admin);
        $this->seedAttendance($tenant, $employees);
        $this->seedPayroll($tenant, $branches);
        $this->seedStatutory();
    }

    /**
     * Seed a default shift + today's attendance rekap for the demo employees.
     *
     * @param  array<int, Employee>  $employees
     */
    private function seedAttendance(Tenant $tenant, array $employees): void
    {
        $shift = Shift::firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'PAGI'],
            ['name' => 'Pagi', 'start_time' => '08:00', 'end_time' => '17:00', 'late_tolerance_minutes' => 15, 'status' => 'active'],
        );

        $today = Carbon::today()->toDateString();

        $workLocation = WorkLocation::forTenant($tenant->id)
            ->where('name', 'Kantor Pusat Jakarta')
            ->first();

        // emp no => [clock_in, clock_out, late_minutes, status, [lat, lng]]
        // Most pins sit inside the Jakarta geofence; emp #2 is ~2 km away to
        // demo an "outside area" flag on the admin rekap map.
        $rows = [
            1 => ['07:54', '17:08', 0, 'present', [-6.21465, 106.84515]],
            2 => ['08:21', '17:30', 21, 'late', [-6.20500, 106.83000]],
            3 => ['07:48', '17:02', 0, 'present', [-6.21450, 106.84505]],
            4 => [null, null, 0, 'leave', null],
            5 => ['07:59', '17:05', 0, 'present', [-6.21470, 106.84520]],
            6 => ['12:55', null, 0, 'incomplete', [-6.21455, 106.84500]],
            7 => ['08:05', '17:10', 5, 'late', [-6.21440, 106.84495]],
            8 => ['07:50', '17:01', 0, 'present', [-6.21468, 106.84530]],
            9 => [null, null, 0, 'absent', null],
            10 => ['07:45', '17:03', 0, 'present', [-6.21452, 106.84508]],
        ];

        $radius = (int) ($workLocation->radius_meter ?? 0);

        foreach ($rows as $no => [$in, $out, $late, $status, $coords]) {
            $employee = $employees[$no];

            [$lat, $lng] = $coords ?? [null, null];
            $locationStatus = null;

            if ($in !== null && $lat !== null && $workLocation !== null) {
                $distance = $workLocation->distanceMeters($lat, $lng);
                $locationStatus = $distance !== null && $radius > 0 && $distance > $radius
                    ? 'outside'
                    : 'inside';
            }

            Attendance::firstOrCreate(
                ['tenant_id' => $tenant->id, 'employee_id' => $employee->id, 'date' => $today, 'shift_id' => $shift->id],
                [
                    'branch_id' => $employee->branch_id,
                    'work_location_id' => $in ? $workLocation?->id : null,
                    'clock_in_at' => $in ? $today.' '.$in.':00' : null,
                    'clock_out_at' => $out ? $today.' '.$out.':00' : null,
                    'clock_in_lat' => $in ? $lat : null,
                    'clock_in_lng' => $in ? $lng : null,
                    'clock_out_lat' => $out ? $lat : null,
                    'clock_out_lng' => $out ? $lng : null,
                    'late_minutes' => $late,
                    'work_minutes' => $in && $out ? 540 : 0,
                    'status' => $status,
                    'location_status' => $locationStatus,
                ],
            );
        }
    }

    /**
     * @param  array<int, Employee>  $employees
     * @param  array<int, LeaveType>  $leaveTypes
     */
    private function seedLeaveRequests(Tenant $tenant, array $employees, array $leaveTypes, User $admin): void
    {
        $byCode = collect($leaveTypes)->keyBy('code');
        $tahunan = $byCode->get('TAHUNAN') ?? $leaveTypes[0];
        $sakit = $byCode->get('SAKIT') ?? $leaveTypes[0];
        $penting = $byCode->get('PENTING') ?? $leaveTypes[0];

        $rows = [
            ['emp' => 1, 'type' => $tahunan, 'start' => '2026-07-01', 'end' => '2026-07-03', 'days' => 3, 'status' => 'pending', 'reason' => 'Liburan keluarga'],
            ['emp' => 3, 'type' => $sakit, 'start' => '2026-06-25', 'end' => '2026-06-26', 'days' => 2, 'status' => 'approved', 'reason' => 'Demam'],
            ['emp' => 2, 'type' => $tahunan, 'start' => '2026-07-10', 'end' => '2026-07-12', 'days' => 3, 'status' => 'pending', 'reason' => 'Acara keluarga'],
            ['emp' => 10, 'type' => $penting, 'start' => '2026-06-18', 'end' => '2026-06-18', 'days' => 1, 'status' => 'rejected', 'reason' => 'Keperluan pribadi'],
            ['emp' => 5, 'type' => $tahunan, 'start' => '2026-07-20', 'end' => '2026-07-22', 'days' => 3, 'status' => 'pending', 'reason' => 'Cuti tahunan'],
        ];

        foreach ($rows as $row) {
            $employee = $employees[$row['emp']];
            LeaveRequest::firstOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'employee_id' => $employee->id,
                    'leave_type_id' => $row['type']->id,
                    'start_date' => $row['start'],
                ],
                [
                    'branch_id' => $employee->branch_id,
                    'end_date' => $row['end'],
                    'total_days' => $row['days'],
                    'reason' => $row['reason'],
                    'current_approver_id' => $admin->id,
                    'status' => $row['status'],
                ],
            );
        }
    }

    private function seedFeatures()
    {
        // [code => [name, module_group]] — module_group drives the Menu & Fitur grouping.
        $codes = [
            // Core HR
            'hr_core' => ['HR Core', 'core'],
            'organization' => ['Organization', 'core'],
            'document' => ['Manajemen Dokumen', 'core'],
            'letter' => ['Template Surat', 'core'],
            'offboarding' => ['Offboarding & Clearance', 'core'],
            'helpdesk' => ['HR Helpdesk', 'core'],
            'delegation' => ['Delegasi Approval', 'core'],
            // Time & Attendance
            'attendance' => ['Attendance', 'time'],
            'leave' => ['Leave', 'time'],
            'overtime' => ['Overtime', 'time'],
            'wfh' => ['WFH', 'time'],
            'timesheet' => ['Timesheet', 'time'],
            'shift_swap' => ['Tukar Shift', 'time'],
            // Payroll & Finance
            'payroll' => ['Payroll', 'payroll'],
            'bpjs' => ['BPJS', 'payroll'],
            'pph21' => ['PPh 21', 'payroll'],
            'claim' => ['Klaim & Reimbursement', 'payroll'],
            'loan' => ['Pinjaman Karyawan', 'payroll'],
            'salary_structure' => ['Struktur & Skala Upah', 'payroll'],
            'journal' => ['Jurnal Akuntansi', 'payroll'],
            // Talent
            'recruitment' => ['Recruitment (ATS)', 'talent'],
            'onboarding' => ['Onboarding', 'talent'],
            'performance' => ['Manajemen Kinerja', 'talent'],
            'okr' => ['OKR & Goal', 'talent'],
            'competency' => ['Kompetensi', 'talent'],
            'talent' => ['Talenta & Suksesi', 'talent'],
            'learning' => ['Pembelajaran (LMS)', 'talent'],
            // Engagement & Self-Service
            'ess' => ['Employee Self-Service', 'engagement'],
            'announcement' => ['Pengumuman', 'engagement'],
            'survey' => ['Survei Karyawan', 'engagement'],
            // Analytics
            'analytics' => ['Analytics & Laporan', 'analytics'],
            'dynamic_report' => ['Dynamic Report', 'analytics'],
            // Asset & CRM
            'asset' => ['Manajemen Aset', 'asset'],
            'crm' => ['CRM', 'crm'],
            'calendar' => ['Kalender Acara', 'engagement'],
            'budget' => ['Anggaran (Budget)', 'payroll'],
            'ai' => ['AI Assistant', 'analytics'],
        ];

        return collect($codes)->map(fn ($meta, $code) => Feature::firstOrCreate(
            ['code' => $code],
            ['name' => $meta[0], 'module_group' => $meta[1]],
        ))->values();
    }

    private function seedPermissionsAndRoles(Tenant $tenant): void
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
            'team.leave.approve', 'team.attendance.view', 'team.overtime.approve',
            'own.profile.view', 'own.attendance.clock_in', 'own.leave.request', 'own.payslip.view',
            // Per-menu access modules so every sidebar item is role-configurable
            // from the Hak Akses matrix (not just feature-gated per tenant).
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
            ['code' => 'super_admin', 'name' => 'Super Admin', 'tenant_id' => null, 'is_system' => true],
            ['code' => 'admin_tenant_hr', 'name' => 'Admin Tenant / HR', 'tenant_id' => $tenant->id, 'is_system' => true],
            ['code' => 'manager', 'name' => 'Manager', 'tenant_id' => $tenant->id, 'is_system' => true],
            ['code' => 'employee', 'name' => 'Karyawan', 'tenant_id' => $tenant->id, 'is_system' => true],
        ];
        foreach ($roles as $data) {
            $role = Role::firstOrCreate(['tenant_id' => $data['tenant_id'], 'code' => $data['code']], ['name' => $data['name'], 'is_system' => $data['is_system']]);

            $assigned = match ($data['code']) {
                'super_admin' => $permModels,
                'admin_tenant_hr' => $permModels->reject(fn ($p) => str_starts_with($p->code, 'tenant.')),
                'manager' => $permModels->filter(fn ($p) => str_starts_with($p->code, 'team.') || str_starts_with($p->code, 'own.')),
                default => $permModels->filter(fn ($p) => str_starts_with($p->code, 'own.')),
            };
            $role->permissions()->syncWithoutDetaching($assigned->pluck('id'));
        }
    }

    /**
     * Seed the tenant's editable sidebar menu from the AvanaNav defaults so the
     * Menu Builder has content and the runtime nav is DB-driven.
     */
    private function seedMenuItems(Tenant $tenant): void
    {
        AvanaNav::seedDefaultsFor($tenant->id);
        // Platform (super-admin) menu — single null-tenant set, idempotent.
        AvanaNav::seedPlatformDefaults();
    }

    private function seedAdminUser(Tenant $tenant): User
    {
        $user = User::firstOrCreate(
            ['email' => 'rina.a@nusantara.co.id'],
            [
                'name' => 'Rina Anggraeni',
                'tenant_id' => $tenant->id,
                'password' => Hash::make('password'),
                'status' => 'active',
                'email_verified_at' => now(),
            ],
        );
        $user->forceFill(['tenant_id' => $tenant->id])->save();

        $role = Role::where('tenant_id', $tenant->id)->where('code', 'admin_tenant_hr')->first();
        if ($role) {
            $user->roles()->syncWithoutDetaching([$role->id]);
        }

        $this->seedSuperAdmin($tenant);
        $this->seedManager($tenant);

        return $user;
    }

    /**
     * Seed an employee (ESS) user linked to a real employee record, for the
     * Flutter self-service app.
     *
     * @param  array<int, Employee>  $employees
     */
    private function seedEmployeeUser(Tenant $tenant, array $employees): void
    {
        $employee = $employees[2] ?? reset($employees);

        if (! $employee) {
            return;
        }

        $user = User::firstOrCreate(
            ['email' => $employee->email ?? 'karyawan@nusantara.co.id'],
            [
                'name' => $employee->full_name,
                'tenant_id' => $tenant->id,
                'password' => Hash::make('password'),
                'status' => 'active',
                'email_verified_at' => now(),
            ],
        );
        $user->forceFill(['tenant_id' => $tenant->id])->save();

        $role = Role::where('tenant_id', $tenant->id)->where('code', 'employee')->first();
        if ($role) {
            $user->roles()->syncWithoutDetaching([$role->id]);
        }

        $workLocation = WorkLocation::forTenant($tenant->id)
            ->where('name', 'Kantor Pusat Jakarta')
            ->first();

        $employee->forceFill([
            'user_id' => $user->id,
            'work_location_id' => $workLocation?->id,
            'branch_id' => $workLocation?->branch_id ?? $employee->branch_id,
        ])->save();
    }

    /** Seed a Manager user (team approvals + limited read scope). */
    private function seedManager(Tenant $tenant): void
    {
        $manager = User::firstOrCreate(
            ['email' => 'budi.s@nusantara.co.id'],
            [
                'name' => 'Budi Santoso',
                'tenant_id' => $tenant->id,
                'password' => Hash::make('password'),
                'status' => 'active',
                'email_verified_at' => now(),
            ],
        );
        $manager->forceFill(['tenant_id' => $tenant->id])->save();

        $role = Role::where('tenant_id', $tenant->id)->where('code', 'manager')->first();
        if ($role) {
            $manager->roles()->syncWithoutDetaching([$role->id]);
        }
    }

    /** Seed a Super Admin who can control the tenant's menu/features. */
    private function seedSuperAdmin(Tenant $tenant): void
    {
        $superAdmin = User::firstOrCreate(
            ['email' => 'superadmin@avanahr.id'],
            [
                'name' => 'Super Admin',
                'tenant_id' => $tenant->id,
                'password' => Hash::make('password'),
                'status' => 'active',
                'email_verified_at' => now(),
            ],
        );
        $superAdmin->forceFill(['tenant_id' => $tenant->id])->save();

        $role = Role::where('code', 'super_admin')->first();
        if ($role) {
            $superAdmin->roles()->syncWithoutDetaching([$role->id]);
        }
    }

    /** @return array<string, Branch> */
    private function seedBranches(Tenant $tenant, Company $company): array
    {
        $rows = [
            'Jakarta Pusat' => 'JKT', 'Bandung' => 'BDG', 'Surabaya' => 'SBY',
        ];
        $branches = [];
        foreach ($rows as $name => $code) {
            $branches[$name] = Branch::firstOrCreate(
                ['tenant_id' => $tenant->id, 'code' => $code],
                ['company_id' => $company->id, 'name' => $name, 'status' => 'active'],
            );
        }
        WorkLocation::firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Kantor Pusat Jakarta'],
            ['branch_id' => $branches['Jakarta Pusat']->id, 'latitude' => -6.2146, 'longitude' => 106.8451, 'radius_meter' => 150, 'status' => 'active'],
        );

        return $branches;
    }

    /** @return array<string, Department> */
    private function seedDepartments(Tenant $tenant): array
    {
        $names = ['Human Resources' => 'HR', 'Engineering' => 'ENG', 'Finance' => 'FIN', 'Sales' => 'SAL', 'Marketing' => 'MKT', 'Operations' => 'OPS'];
        $departments = [];
        foreach ($names as $name => $code) {
            $departments[$name] = Department::firstOrCreate(
                ['tenant_id' => $tenant->id, 'code' => $code],
                ['name' => $name, 'status' => 'active'],
            );
        }

        return $departments;
    }

    /**
     * @param  array<string, Branch>  $branches
     * @param  array<string, Department>  $departments
     * @return array<int, Employee>
     */
    private function seedEmployees(Tenant $tenant, array $branches, array $departments, JobLevel $jobLevel, User $admin): array
    {
        $raw = [
            ['no' => 1, 'nama' => 'Putri Anjani', 'email' => 'putri.anjani@nusantara.co.id', 'dept' => 'Human Resources', 'jab' => 'HR Manager', 'cabang' => 'Jakarta Pusat', 'status' => 'Tetap', 'masuk' => '12 Jan 2021', 'lahir' => '05 Mei 1990'],
            ['no' => 2, 'nama' => 'Bagus Pratama', 'email' => 'bagus.p@nusantara.co.id', 'dept' => 'Engineering', 'jab' => 'Software Engineer', 'cabang' => 'Bandung', 'status' => 'Kontrak', 'masuk' => '03 Mar 2024', 'lahir' => '18 Agu 1995'],
            ['no' => 3, 'nama' => 'Siti Nurhaliza', 'email' => 'siti.n@nusantara.co.id', 'dept' => 'Finance', 'jab' => 'Finance Analyst', 'cabang' => 'Jakarta Pusat', 'status' => 'Tetap', 'masuk' => '19 Jul 2022', 'lahir' => '27 Feb 1993'],
            ['no' => 4, 'nama' => 'Rizki Maulana', 'email' => 'rizki.m@nusantara.co.id', 'dept' => 'Sales', 'jab' => 'Sales Executive', 'cabang' => 'Surabaya', 'status' => 'Probation', 'masuk' => '02 Jun 2026', 'lahir' => '09 Nov 1998'],
            ['no' => 5, 'nama' => 'Dewi Lestari', 'email' => 'dewi.l@nusantara.co.id', 'dept' => 'Marketing', 'jab' => 'Content Lead', 'cabang' => 'Jakarta Pusat', 'status' => 'Tetap', 'masuk' => '28 Sep 2021', 'lahir' => '14 Apr 1991'],
            ['no' => 6, 'nama' => 'Andi Wijaya', 'email' => 'andi.w@nusantara.co.id', 'dept' => 'Operations', 'jab' => 'Ops Supervisor', 'cabang' => 'Bandung', 'status' => 'Tetap', 'masuk' => '14 Feb 2020', 'lahir' => '03 Jun 1987'],
            ['no' => 7, 'nama' => 'Maya Saraswati', 'email' => 'maya.s@nusantara.co.id', 'dept' => 'Engineering', 'jab' => 'QA Engineer', 'cabang' => 'Bandung', 'status' => 'Kontrak', 'masuk' => '11 Nov 2023', 'lahir' => '21 Des 1996'],
            ['no' => 8, 'nama' => 'Fajar Nugroho', 'email' => 'fajar.n@nusantara.co.id', 'dept' => 'Finance', 'jab' => 'Accountant', 'cabang' => 'Surabaya', 'status' => 'Tetap', 'masuk' => '07 Agu 2022', 'lahir' => '30 Jul 1992'],
            ['no' => 9, 'nama' => 'Intan Permata', 'email' => 'intan.p@nusantara.co.id', 'dept' => 'Human Resources', 'jab' => 'Recruiter', 'cabang' => 'Jakarta Pusat', 'status' => 'Resign', 'masuk' => '22 Apr 2023', 'lahir' => '16 Okt 1994'],
            ['no' => 10, 'nama' => 'Yoga Saputra', 'email' => 'yoga.s@nusantara.co.id', 'dept' => 'Sales', 'jab' => 'Account Manager', 'cabang' => 'Surabaya', 'status' => 'Tetap', 'masuk' => '30 Jan 2021', 'lahir' => '08 Sep 1989'],
        ];
        $employmentMap = ['Tetap' => 'permanent', 'Kontrak' => 'contract', 'Probation' => 'probation', 'Resign' => 'resigned'];

        $employees = [];
        foreach ($raw as $row) {
            $dept = $departments[$row['dept']];
            $position = Position::firstOrCreate(
                ['tenant_id' => $tenant->id, 'code' => strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $row['jab']), 0, 6)).'-'.$dept->code],
                ['department_id' => $dept->id, 'name' => $row['jab'], 'status' => 'active'],
            );
            $employees[$row['no']] = Employee::firstOrCreate(
                ['tenant_id' => $tenant->id, 'employee_number' => sprintf('EMP-%04d', $row['no'])],
                [
                    'branch_id' => $branches[$row['cabang']]->id,
                    'department_id' => $dept->id,
                    'position_id' => $position->id,
                    'job_level_id' => $jobLevel->id,
                    'full_name' => $row['nama'],
                    'email' => $row['email'],
                    'gender' => 'unspecified',
                    'birth_date' => $this->parseIndoDate($row['lahir']),
                    'employment_status' => $employmentMap[$row['status']],
                    'join_date' => $this->parseIndoDate($row['masuk']),
                    'status' => $row['status'] === 'Resign' ? 'inactive' : 'active',
                    'resign_date' => $row['status'] === 'Resign' ? now()->subMonthsNoOverflow(2)->format('Y-m-d') : null,
                ],
            );
        }

        // Two teammates share today's birthday so the dashboard "Ulang Tahun
        // Hari Ini" widget always has demo data, whatever day it is seeded.
        foreach ([1, 5] as $no) {
            $employees[$no]->update([
                'birth_date' => now()->subYears(28 + $no)->format('Y-m-d'),
            ]);
        }

        // Reporting lines form a real multi-level org chart: HR Manager (1) at
        // the top, department leads beneath, and their members one level deeper.
        $managerMap = [
            2 => 1,   // Bagus (Engineering lead) -> Putri
            3 => 1,   // Siti (Finance lead) -> Putri
            5 => 1,   // Dewi (Marketing) -> Putri
            6 => 1,   // Andi (Operations) -> Putri
            10 => 1,  // Yoga (Sales lead) -> Putri
            7 => 2,   // Maya (QA) -> Bagus
            8 => 3,   // Fajar (Accountant) -> Siti
            4 => 10,  // Rizki (Sales Exec) -> Yoga
            9 => 1,   // Intan (resigned) -> Putri
        ];
        foreach ($managerMap as $no => $managerNo) {
            if (isset($employees[$no], $employees[$managerNo])) {
                $employees[$no]->update(['manager_id' => $employees[$managerNo]->id]);
            }
        }

        return $employees;
    }

    /** @return array<int, LeaveType> */
    private function seedLeaveTypes(Tenant $tenant): array
    {
        $rows = [
            ['code' => 'TAHUNAN', 'name' => 'Cuti Tahunan', 'default_quota' => 12],
            ['code' => 'SAKIT', 'name' => 'Cuti Sakit', 'default_quota' => 12, 'requires_attachment' => true],
            ['code' => 'PENTING', 'name' => 'Cuti Penting', 'default_quota' => 2],
        ];

        return collect($rows)->map(fn ($r) => LeaveType::firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => $r['code']],
            ['name' => $r['name'], 'default_quota' => $r['default_quota'], 'requires_attachment' => $r['requires_attachment'] ?? false, 'status' => 'active'],
        ))->all();
    }

    /**
     * @param  array<int, Employee>  $employees
     * @param  array<int, LeaveType>  $leaveTypes
     */
    private function seedLeaveBalances(Tenant $tenant, array $employees, array $leaveTypes): void
    {
        foreach ($employees as $employee) {
            foreach ($leaveTypes as $type) {
                LeaveBalance::firstOrCreate(
                    ['tenant_id' => $tenant->id, 'employee_id' => $employee->id, 'leave_type_id' => $type->id, 'year' => 2026],
                    ['quota' => $type->default_quota, 'used' => 0, 'remaining' => $type->default_quota],
                );
            }
        }
    }

    /** @param array<string, Branch> $branches */
    private function seedPayroll(Tenant $tenant, array $branches): void
    {
        $components = [
            ['code' => 'BASIC', 'name' => 'Gaji Pokok', 'type' => 'earning'],
            ['code' => 'TJ-JAB', 'name' => 'Tunjangan Jabatan', 'type' => 'earning'],
            ['code' => 'TJ-TRP', 'name' => 'Tunjangan Transport', 'type' => 'earning', 'is_taxable' => false],
            ['code' => 'TJ-MKN', 'name' => 'Tunjangan Makan', 'type' => 'earning', 'is_taxable' => false],
            ['code' => 'POT-KOP', 'name' => 'Potongan Koperasi', 'type' => 'deduction'],
        ];
        foreach ($components as $c) {
            PayrollComponent::updateOrCreate(
                ['tenant_id' => $tenant->id, 'code' => $c['code']],
                [
                    'name' => $c['name'],
                    'type' => $c['type'],
                    'component_group' => $c['type'] === 'deduction' ? 'potongan' : 'penerimaan',
                    'is_taxable' => $c['is_taxable'] ?? true,
                    'status' => 'active',
                ],
            );
        }

        $period = PayrollPeriod::firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => '2026-06'],
            ['name' => 'Juni 2026', 'start_date' => '2026-06-01', 'end_date' => '2026-06-30', 'pay_date' => '2026-06-25', 'status' => 'draft'],
        );
        PayrollRun::firstOrCreate(
            ['tenant_id' => $tenant->id, 'payroll_period_id' => $period->id, 'branch_id' => null],
            ['status' => 'draft', 'total_gross' => 5120000000, 'total_deduction' => 186000000, 'total_tax' => 114000000, 'total_net' => 4820000000, 'employee_count' => 1248],
        );
    }

    private function seedStatutory(): void
    {
        $programs = [
            ['code' => 'KESEHATAN', 'name' => 'BPJS Kesehatan', 'type' => 'kesehatan', 'emp' => 0.01, 'co' => 0.04],
            ['code' => 'JHT', 'name' => 'BPJS JHT', 'type' => 'jht', 'emp' => 0.02, 'co' => 0.037],
            ['code' => 'JP', 'name' => 'BPJS JP', 'type' => 'jp', 'emp' => 0.01, 'co' => 0.02],
        ];
        foreach ($programs as $p) {
            $program = BpjsProgram::firstOrCreate(['code' => $p['code']], ['name' => $p['name'], 'type' => $p['type'], 'is_active' => true]);
            $program->rates()->firstOrCreate(
                ['effective_start_date' => '2026-01-01'],
                ['employee_rate' => $p['emp'], 'company_rate' => $p['co'], 'is_active' => true],
            );
        }

        foreach (Tenant::pluck('id') as $tenantId) {
            $this->seedTaxRates((int) $tenantId);
        }
    }

    /**
     * Seed the tenant's configurable Tarif PTKP + Tarif PKP (progressive Pasal 17)
     * used by the BPR-manual monthly PPh 21 calculation.
     */
    private function seedTaxRates(int $tenantId, int $year = 2026): void
    {
        $ptkp = [
            'TK/0' => 54_000_000, 'TK/1' => 58_500_000, 'TK/2' => 63_000_000, 'TK/3' => 67_500_000,
            'K/0' => 58_500_000, 'K/1' => 63_000_000, 'K/2' => 67_500_000, 'K/3' => 72_000_000,
        ];
        foreach ($ptkp as $status => $amount) {
            PtkpRate::updateOrCreate(
                ['tenant_id' => $tenantId, 'ptkp_status' => $status, 'year' => $year],
                ['amount' => $amount],
            );
        }

        $pkp = [
            [60_000_000, 0.05], [250_000_000, 0.15], [500_000_000, 0.25],
            [5_000_000_000, 0.30], [null, 0.35],
        ];
        foreach ($pkp as $i => [$upTo, $rate]) {
            PkpRate::updateOrCreate(
                ['tenant_id' => $tenantId, 'year' => $year, 'sort_order' => $i],
                ['up_to' => $upTo, 'rate' => $rate],
            );
        }
    }

    private function parseIndoDate(string $value): string
    {
        $map = ['Jan' => '01', 'Feb' => '02', 'Mar' => '03', 'Apr' => '04', 'Mei' => '05', 'Jun' => '06', 'Jul' => '07', 'Agu' => '08', 'Sep' => '09', 'Okt' => '10', 'Nov' => '11', 'Des' => '12'];
        [$day, $mon, $year] = explode(' ', $value);

        return Carbon::createFromFormat('Y-m-d', sprintf('%s-%s-%02d', $year, $map[$mon] ?? '01', (int) $day))->toDateString();
    }
}
