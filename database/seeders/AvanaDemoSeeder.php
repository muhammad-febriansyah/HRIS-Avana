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
use App\Models\Position;
use App\Models\Pph21TerRate;
use App\Models\Role;
use App\Models\Shift;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkLocation;
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

        // emp no => [clock_in, clock_out, late_minutes, status]
        $rows = [
            1 => ['07:54', '17:08', 0, 'present'],
            2 => ['08:21', '17:30', 21, 'late'],
            3 => ['07:48', '17:02', 0, 'present'],
            4 => [null, null, 0, 'leave'],
            5 => ['07:59', '17:05', 0, 'present'],
            6 => ['12:55', null, 0, 'incomplete'],
            7 => ['08:05', '17:10', 5, 'late'],
            8 => ['07:50', '17:01', 0, 'present'],
            9 => [null, null, 0, 'absent'],
            10 => ['07:45', '17:03', 0, 'present'],
        ];

        foreach ($rows as $no => [$in, $out, $late, $status]) {
            $employee = $employees[$no];
            Attendance::firstOrCreate(
                ['tenant_id' => $tenant->id, 'employee_id' => $employee->id, 'date' => $today, 'shift_id' => $shift->id],
                [
                    'branch_id' => $employee->branch_id,
                    'clock_in_at' => $in ? $today.' '.$in.':00' : null,
                    'clock_out_at' => $out ? $today.' '.$out.':00' : null,
                    'late_minutes' => $late,
                    'work_minutes' => $in && $out ? 540 : 0,
                    'status' => $status,
                    'location_status' => $in ? 'inside' : null,
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
        $codes = [
            'hr_core' => 'HR Core', 'organization' => 'Organization', 'attendance' => 'Attendance',
            'leave' => 'Leave', 'overtime' => 'Overtime', 'wfh' => 'WFH', 'payroll' => 'Payroll',
            'bpjs' => 'BPJS', 'pph21' => 'PPh 21', 'recruitment' => 'Recruitment',
            'onboarding' => 'Onboarding', 'analytics' => 'Analytics',
        ];

        return collect($codes)->map(fn ($name, $code) => Feature::firstOrCreate(['code' => $code], ['name' => $name, 'module_group' => 'core']))->values();
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

    private function seedAdminUser(Tenant $tenant): User
    {
        $user = User::firstOrCreate(
            ['email' => 'admin@avanahr.co.id'],
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

        return $user;
    }

    /** Seed a Super Admin who can control the tenant's menu/features. */
    private function seedSuperAdmin(Tenant $tenant): void
    {
        $superAdmin = User::firstOrCreate(
            ['email' => 'superadmin@avanahr.co.id'],
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
            ['no' => 1, 'nama' => 'Putri Anjani', 'email' => 'putri.anjani@nusantara.co.id', 'dept' => 'Human Resources', 'jab' => 'HR Manager', 'cabang' => 'Jakarta Pusat', 'status' => 'Tetap', 'masuk' => '12 Jan 2021'],
            ['no' => 2, 'nama' => 'Bagus Pratama', 'email' => 'bagus.p@nusantara.co.id', 'dept' => 'Engineering', 'jab' => 'Software Engineer', 'cabang' => 'Bandung', 'status' => 'Kontrak', 'masuk' => '03 Mar 2024'],
            ['no' => 3, 'nama' => 'Siti Nurhaliza', 'email' => 'siti.n@nusantara.co.id', 'dept' => 'Finance', 'jab' => 'Finance Analyst', 'cabang' => 'Jakarta Pusat', 'status' => 'Tetap', 'masuk' => '19 Jul 2022'],
            ['no' => 4, 'nama' => 'Rizki Maulana', 'email' => 'rizki.m@nusantara.co.id', 'dept' => 'Sales', 'jab' => 'Sales Executive', 'cabang' => 'Surabaya', 'status' => 'Probation', 'masuk' => '02 Jun 2026'],
            ['no' => 5, 'nama' => 'Dewi Lestari', 'email' => 'dewi.l@nusantara.co.id', 'dept' => 'Marketing', 'jab' => 'Content Lead', 'cabang' => 'Jakarta Pusat', 'status' => 'Tetap', 'masuk' => '28 Sep 2021'],
            ['no' => 6, 'nama' => 'Andi Wijaya', 'email' => 'andi.w@nusantara.co.id', 'dept' => 'Operations', 'jab' => 'Ops Supervisor', 'cabang' => 'Bandung', 'status' => 'Tetap', 'masuk' => '14 Feb 2020'],
            ['no' => 7, 'nama' => 'Maya Saraswati', 'email' => 'maya.s@nusantara.co.id', 'dept' => 'Engineering', 'jab' => 'QA Engineer', 'cabang' => 'Bandung', 'status' => 'Kontrak', 'masuk' => '11 Nov 2023'],
            ['no' => 8, 'nama' => 'Fajar Nugroho', 'email' => 'fajar.n@nusantara.co.id', 'dept' => 'Finance', 'jab' => 'Accountant', 'cabang' => 'Surabaya', 'status' => 'Tetap', 'masuk' => '07 Agu 2022'],
            ['no' => 9, 'nama' => 'Intan Permata', 'email' => 'intan.p@nusantara.co.id', 'dept' => 'Human Resources', 'jab' => 'Recruiter', 'cabang' => 'Jakarta Pusat', 'status' => 'Resign', 'masuk' => '22 Apr 2023'],
            ['no' => 10, 'nama' => 'Yoga Saputra', 'email' => 'yoga.s@nusantara.co.id', 'dept' => 'Sales', 'jab' => 'Account Manager', 'cabang' => 'Surabaya', 'status' => 'Tetap', 'masuk' => '30 Jan 2021'],
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
                    'employment_status' => $employmentMap[$row['status']],
                    'join_date' => $this->parseIndoDate($row['masuk']),
                    'status' => $row['status'] === 'Resign' ? 'inactive' : 'active',
                ],
            );
        }

        // Set managers: HR Manager (no.1) leads the rest.
        foreach ($employees as $no => $employee) {
            if ($no !== 1 && $employee->manager_id === null) {
                $employee->update(['manager_id' => $employees[1]->id]);
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
            PayrollComponent::firstOrCreate(
                ['tenant_id' => $tenant->id, 'code' => $c['code']],
                ['name' => $c['name'], 'type' => $c['type'], 'is_taxable' => $c['is_taxable'] ?? true, 'status' => 'active'],
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

        $ter = [
            ['category' => 'A', 'min' => 0, 'max' => 5400000, 'rate' => 0.0],
            ['category' => 'A', 'min' => 5400000, 'max' => 5650000, 'rate' => 0.0025],
            ['category' => 'A', 'min' => 5650000, 'max' => 5950000, 'rate' => 0.005],
        ];
        foreach ($ter as $t) {
            Pph21TerRate::firstOrCreate(
                ['category' => $t['category'], 'income_min' => $t['min'], 'effective_start_date' => '2026-01-01'],
                ['income_max' => $t['max'], 'rate' => $t['rate'], 'is_active' => true],
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
