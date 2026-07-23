<?php

namespace Database\Seeders;

use App\Models\Applicant;
use App\Models\ApprovalWorkflow;
use App\Models\Attendance;
use App\Models\AttendancePolicy;
use App\Models\BpjsProgram;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeSalaryComponent;
use App\Models\Feature;
use App\Models\FieldVisit;
use App\Models\JobLevel;
use App\Models\JobPosting;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\OvertimeRequest;
use App\Models\Package;
use App\Models\PayrollComponent;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\PerformanceCycle;
use App\Models\PerformanceReview;
use App\Models\Permission;
use App\Models\PkpRate;
use App\Models\Position;
use App\Models\PtkpRate;
use App\Models\Role;
use App\Models\Shift;
use App\Models\Survey;
use App\Models\SurveyQuestion;
use App\Models\SurveyResponse;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkLocation;
use App\Support\AvanaNav;
use App\Support\PermissionCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

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
        $this->seedDirectorUser($tenant, $employees);
        $leaveTypes = $this->seedLeaveTypes($tenant);
        $this->seedLeaveBalances($tenant, $employees, $leaveTypes);
        $this->seedLeaveRequests($tenant, $employees, $leaveTypes, $admin);
        $this->seedAttendance($tenant, $employees);
        $this->seedAttendancePolicy($tenant);
        $this->seedFieldVisits($tenant, $employees);
        $this->seedPayroll($tenant, $branches);
        $this->seedStatutory();
        $this->seedAttritionInputs($tenant, $employees);
        $this->seedSalaries($tenant, $employees);
        $this->seedRecruitment($tenant, $departments);
        $this->seedApprovalWorkflows($tenant, $admin);
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
     * Lock down attendance face verification for the demo tenant: enrollment is
     * mandatory and a face mismatch hard-blocks the clock-in (rather than only
     * flagging it), so the MobileFaceNet 1:1 match actually gates every punch.
     */
    private function seedAttendancePolicy(Tenant $tenant): void
    {
        // Skip under tests: the attendance suites assume the default policy
        // (enrollment not required) and set a stricter one themselves when
        // needed. Forcing enrollment here would 422 every clock-in test.
        if (app()->runningUnitTests()) {
            return;
        }

        AttendancePolicy::updateOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'require_face_enrollment' => true,
                'face_enforcement' => 'block',
            ],
        );
    }

    /**
     * Seed the inputs the attrition (resign-risk) scorer reads — engagement
     * survey, overtime, performance history, extra late days, stale salary and
     * a manager change — so the Prediksi Resign dashboard shows a realistic
     * spread of low/medium/high risk instead of empty factors.
     *
     * Skipped under tests: the attendance/payroll/analytics suites assume the
     * baseline demo data and would break on the extra rows; the scorer's own
     * tests craft their inputs directly.
     *
     * @param  array<int, Employee>  $employees
     */
    private function seedAttritionInputs(Tenant $tenant, array $employees): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        $now = Carbon::today();

        // emp no => engagement rating, heavy overtime, [prev, latest] perf
        // scores (null = skip), late days in the last month, stale salary
        // (no raise > 2y), manager changed recently.
        $plan = [
            1 => ['eng' => 3, 'ot' => false, 'perf' => [80, 78], 'late' => 1, 'stale_salary' => false, 'mgr' => false],
            2 => ['eng' => 4, 'ot' => true, 'perf' => [75, 80], 'late' => 2, 'stale_salary' => false, 'mgr' => true],
            3 => ['eng' => 4, 'ot' => false, 'perf' => [82, 85], 'late' => 0, 'stale_salary' => false, 'mgr' => false],
            4 => ['eng' => 2, 'ot' => false, 'perf' => null, 'late' => 3, 'stale_salary' => false, 'mgr' => false],
            5 => ['eng' => 2, 'ot' => true, 'perf' => [85, 70], 'late' => 4, 'stale_salary' => true, 'mgr' => true],
            6 => ['eng' => 2, 'ot' => true, 'perf' => [80, 68], 'late' => 7, 'stale_salary' => true, 'mgr' => false],
            7 => ['eng' => 5, 'ot' => false, 'perf' => [78, 80], 'late' => 6, 'stale_salary' => false, 'mgr' => false],
            8 => ['eng' => 3, 'ot' => false, 'perf' => [75, 74], 'late' => 1, 'stale_salary' => false, 'mgr' => false],
            10 => ['eng' => 4, 'ot' => false, 'perf' => [80, 82], 'late' => 0, 'stale_salary' => false, 'mgr' => false],
        ];

        // Engagement survey (non-anonymous so responses tie to an employee).
        $survey = Survey::firstOrCreate(
            ['tenant_id' => $tenant->id, 'title' => 'Employee Engagement 2026'],
            ['description' => 'Survei kepuasan & keterikatan karyawan', 'status' => 'closed', 'is_anonymous' => false],
        );
        $question = SurveyQuestion::firstOrCreate(
            ['survey_id' => $survey->id, 'question' => 'Seberapa puas Anda bekerja di perusahaan ini?'],
            ['tenant_id' => $tenant->id, 'type' => 'rating', 'options' => null],
        );

        // Two closed cycles so a performance trend is computable.
        $cyclePrev = PerformanceCycle::firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Penilaian Semester 1 2025'],
            ['period_start' => '2025-01-01', 'period_end' => '2025-06-30', 'status' => 'closed'],
        );
        $cycleLatest = PerformanceCycle::firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Penilaian Semester 2 2025'],
            ['period_start' => '2025-07-01', 'period_end' => '2025-12-31', 'status' => 'closed'],
        );

        $shift = Shift::forTenant($tenant->id)->where('code', 'PAGI')->first();
        $admin = User::where('email', 'rina.a@nusantara.co.id')->first();

        foreach ($plan as $no => $cfg) {
            $employee = $employees[$no] ?? null;

            if ($employee === null) {
                continue;
            }

            // Engagement rating.
            SurveyResponse::updateOrCreate(
                ['survey_id' => $survey->id, 'survey_question_id' => $question->id, 'employee_id' => $employee->id],
                ['tenant_id' => $tenant->id, 'answer' => (string) $cfg['eng']],
            );

            // Heavy overtime across the last three months.
            if ($cfg['ot']) {
                for ($i = 1; $i <= 3; $i++) {
                    OvertimeRequest::firstOrCreate(
                        [
                            'tenant_id' => $tenant->id,
                            'employee_id' => $employee->id,
                            'date' => $now->copy()->subMonthsNoOverflow($i)->day(15)->toDateString(),
                        ],
                        ['branch_id' => $employee->branch_id, 'hours' => 52, 'reason' => 'Lembur proyek', 'status' => 'approved'],
                    );
                }
            }

            // Performance scores across the two cycles.
            if (is_array($cfg['perf'])) {
                [$prev, $latest] = $cfg['perf'];
                PerformanceReview::firstOrCreate(
                    ['tenant_id' => $tenant->id, 'cycle_id' => $cyclePrev->id, 'employee_id' => $employee->id],
                    ['final_score' => $prev, 'status' => 'completed', 'review_date' => '2025-06-30'],
                );
                PerformanceReview::firstOrCreate(
                    ['tenant_id' => $tenant->id, 'cycle_id' => $cycleLatest->id, 'employee_id' => $employee->id],
                    ['final_score' => $latest, 'status' => 'completed', 'review_date' => '2025-12-31'],
                );
            }

            // Extra late days within the trailing month.
            for ($k = 1; $k <= $cfg['late']; $k++) {
                $date = $now->copy()->subDays($k * 3);
                Attendance::firstOrCreate(
                    ['tenant_id' => $tenant->id, 'employee_id' => $employee->id, 'date' => $date->toDateString(), 'shift_id' => $shift?->id],
                    [
                        'branch_id' => $employee->branch_id,
                        'clock_in_at' => $date->copy()->setTime(8, 25)->toDateTimeString(),
                        'clock_out_at' => $date->copy()->setTime(17, 5)->toDateTimeString(),
                        'late_minutes' => 25,
                        'work_minutes' => 520,
                        'status' => 'late',
                    ],
                );
            }

            // A salary set > 2 years ago with no later raise.
            if ($cfg['stale_salary']) {
                EmployeeSalaryComponent::firstOrCreate(
                    ['employee_id' => $employee->id, 'payroll_component_id' => 1],
                    ['tenant_id' => $tenant->id, 'amount' => 8000000, 'effective_start_date' => '2022-06-01'],
                );
            }

            // A recent manager change, recorded on the audit trail.
            if ($cfg['mgr'] && $admin !== null) {
                $exists = DB::table('audit_logs')
                    ->where('auditable_type', Employee::class)
                    ->where('auditable_id', $employee->id)
                    ->whereJsonContains('new_values->manager_id', $employee->manager_id ?? 1)
                    ->exists();

                if (! $exists) {
                    DB::table('audit_logs')->insert([
                        'tenant_id' => $tenant->id,
                        'user_id' => $admin->id,
                        'auditable_type' => Employee::class,
                        'auditable_id' => $employee->id,
                        'action' => 'updated',
                        'old_values' => json_encode(['manager_id' => 2]),
                        'new_values' => json_encode(['manager_id' => $employee->manager_id ?? 1]),
                        'ip_address' => null,
                        'created_at' => $now->copy()->subMonthsNoOverflow(2),
                        'updated_at' => $now->copy()->subMonthsNoOverflow(2),
                    ]);
                }
            }
        }
    }

    /**
     * Give every active employee a salary (basic + transport) so the payroll
     * run shows realistic figures instead of Rp 0. Payroll items are then
     * (re)generated by running payroll in the app.
     *
     * Skipped under tests: the payroll/PPh21 suites assume the baseline salary
     * setup and would break on the extra components.
     *
     * @param  array<int, Employee>  $employees
     */
    private function seedSalaries(Tenant $tenant, array $employees): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        $basic = PayrollComponent::where('tenant_id', $tenant->id)->where('code', 'BASIC')->first();
        $transport = PayrollComponent::where('tenant_id', $tenant->id)->where('code', 'TJ-TRP')->first();

        if ($basic === null) {
            return;
        }

        // emp no => monthly basic salary (rupiah), by role.
        $basicByNo = [
            0 => 25_000_000,  // Direktur Utama
            1 => 10_000_000,  // HR Manager
            2 => 12_000_000,  // Software Engineer
            3 => 9_000_000,   // Finance Analyst
            4 => 7_000_000,   // Sales Executive
            5 => 8_000_000,   // Content Lead
            6 => 10_000_000,  // Ops Supervisor
            7 => 8_500_000,   // QA Engineer
            8 => 8_000_000,   // Accountant
            10 => 11_000_000, // Account Manager
        ];

        $handledIds = [];
        foreach ($basicByNo as $no => $amount) {
            $employee = $employees[$no] ?? null;

            if ($employee === null) {
                continue;
            }

            $handledIds[] = $employee->id;
            $this->setSalaryComponent($tenant, $employee, $basic->id, $amount);

            if ($transport !== null) {
                $this->setSalaryComponent($tenant, $employee, $transport->id, 500_000);
            }
        }

        // Any other active employee (e.g. a candidate hired via recruitment)
        // gets a baseline so payroll never lists them at Rp 0.
        Employee::forTenant($tenant->id)
            ->where('status', 'active')
            ->whereNotIn('id', $handledIds)
            ->get()
            ->each(function (Employee $employee) use ($tenant, $basic): void {
                $this->setSalaryComponent($tenant, $employee, $basic->id, 6_000_000);
            });
    }

    private function setSalaryComponent(Tenant $tenant, Employee $employee, int $componentId, int $amount): void
    {
        EmployeeSalaryComponent::firstOrCreate(
            ['employee_id' => $employee->id, 'payroll_component_id' => $componentId],
            ['tenant_id' => $tenant->id, 'amount' => $amount, 'effective_start_date' => $employee->join_date],
        );
    }

    /**
     * Seed a richer recruitment pipeline — several job postings and applicants
     * spread across every stage (with upcoming interviews) so the ATS boards,
     * candidate list and interview widget are populated for the demo.
     *
     * Skipped under tests, which craft their own recruitment fixtures.
     *
     * @param  array<string, Department>  $departments
     */
    private function seedRecruitment(Tenant $tenant, array $departments): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        $now = Carbon::today();

        // title => [department, employment_type, location, quota, status]
        $jobsDef = [
            'Backend Engineer' => [$departments['Engineering'] ?? null, 'tetap', 'Jakarta', 2, 'open'],
            'Frontend Engineer' => [$departments['Engineering'] ?? null, 'tetap', 'Bandung', 2, 'open'],
            'Digital Marketing Specialist' => [$departments['Marketing'] ?? null, 'kontrak', 'Jakarta', 1, 'open'],
            'Account Executive' => [$departments['Sales'] ?? null, 'tetap', 'Surabaya', 3, 'open'],
            'HR Generalist' => [$departments['Human Resources'] ?? null, 'tetap', 'Jakarta', 1, 'closed'],
        ];

        $jobs = [];
        foreach ($jobsDef as $title => [$dept, $type, $location, $quota, $status]) {
            $jobs[$title] = JobPosting::firstOrCreate(
                ['tenant_id' => $tenant->id, 'title' => $title],
                [
                    'department_id' => $dept?->id,
                    'location' => $location,
                    'employment_type' => $type,
                    'quota' => $quota,
                    'status' => $status,
                    'description' => "Kami membuka lowongan {$title}. Kandidat diharapkan berpengalaman, komunikatif, dan mampu bekerja dalam tim.",
                    'posted_date' => $now->copy()->subDays(30)->toDateString(),
                    'closing_date' => $now->copy()->addDays(30)->toDateString(),
                ],
            );
        }

        // [name, job title, stage, source, applied days-ago, interview offset
        // days (null=none, negative=past)]. The trailing number is unused now
        // that AI scores come from a real analysis run.
        $applicants = [
            ['Budi Santoso', 'Backend Engineer', 'applied', 'LinkedIn', 2, null, 72],
            ['Sari Melati', 'Backend Engineer', 'applied', 'JobStreet', 4, null, 65],
            ['Agus Firmansyah', 'Frontend Engineer', 'applied', 'Referral', 3, null, 80],
            ['Nadia Putri', 'Digital Marketing Specialist', 'applied', 'Website Karier', 5, null, 58],
            ['Rangga Pratama', 'Account Executive', 'applied', 'Glints', 1, null, 61],
            ['Wulan Sari', 'Backend Engineer', 'screening', 'LinkedIn', 8, null, 77],
            ['Dimas Ananda', 'Frontend Engineer', 'screening', 'Referral', 10, null, 83],
            ['Fitri Handayani', 'Digital Marketing Specialist', 'screening', 'JobStreet', 7, null, 69],
            ['Bayu Nugroho', 'Backend Engineer', 'shortlisted', 'LinkedIn', 12, null, 88],
            ['Indah Kusuma', 'Account Executive', 'shortlisted', 'Walk-in', 14, null, 74],
            ['Galih Ramadhan', 'Frontend Engineer', 'interview', 'Referral', 15, 2, 90],
            ['Ratna Cahyani', 'Backend Engineer', 'interview', 'LinkedIn', 16, 4, 85],
            ['Eko Wibowo', 'Digital Marketing Specialist', 'offer', 'Glints', 20, -3, 87],
            ['Ayu Lestari', 'Account Executive', 'hired', 'JobStreet', 30, -10, 92],
            ['Arif Setiawan', 'Backend Engineer', 'rejected', 'Website Karier', 25, -8, 44],
        ];

        foreach ($applicants as [$name, $jobTitle, $stage, $source, $daysAgo, $interviewIn]) {
            $job = $jobs[$jobTitle] ?? null;

            if ($job === null) {
                continue;
            }

            $applied = $now->copy()->subDays($daysAgo);

            Applicant::firstOrCreate(
                ['tenant_id' => $tenant->id, 'email' => str_replace(' ', '.', strtolower($name)).'@gmail.com'],
                [
                    'job_posting_id' => $job->id,
                    'position' => $job->title,
                    'name' => $name,
                    'phone' => '0812'.mt_rand(1000000, 9999999),
                    'source' => $source,
                    'stage' => $stage,
                    'applied_date' => $applied->toDateString(),
                    'interview_at' => $interviewIn !== null ? $now->copy()->addDays($interviewIn)->setTime(10, 0) : null,
                    'interview_type' => $interviewIn !== null ? 'Onsite' : null,
                    'interview_status' => $interviewIn !== null ? ($interviewIn >= 0 ? 'scheduled' : 'done') : null,
                    'offered_at' => in_array($stage, ['offer', 'hired'], true) ? $applied->copy()->addDays(18) : null,
                    'offer_status' => $stage === 'offer' ? 'sent' : ($stage === 'hired' ? 'accepted' : null),
                    // AI match score/recommendation are intentionally left null:
                    // they are produced by the real "Analisa dengan AI" run, not
                    // seeded, so a fresh install shows no fake scores.
                ],
            );
        }
    }

    /**
     * Seed ready-made approval flows (Cuti, Izin, Koreksi Absen) so the Setup
     * Alur Persetujuan screen and the approval engine have working examples for
     * the demo. The final approver is the HR admin.
     *
     * Skipped under tests, which build their own approval fixtures.
     */
    private function seedApprovalWorkflows(Tenant $tenant, User $admin): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        // name => [request_type, [step approver types in order]]
        $workflows = [
            'Cuti 2 Level (Demo)' => ['leave', ['direct_manager', 'specific_user']],
            'Izin 2 Level (Demo)' => ['permission', ['direct_manager', 'specific_user']],
            'Koreksi 1 Level (Demo)' => ['attendance_correction', ['specific_user']],
        ];

        foreach ($workflows as $name => [$requestType, $steps]) {
            $workflow = ApprovalWorkflow::firstOrCreate(
                ['tenant_id' => $tenant->id, 'name' => $name],
                ['request_type' => $requestType, 'approval_mode' => 'sequential', 'is_active' => true],
            );

            if ($workflow->steps()->exists()) {
                continue;
            }

            foreach ($steps as $index => $approverType) {
                $workflow->steps()->create([
                    'tenant_id' => $tenant->id,
                    'step_order' => $index + 1,
                    'approver_type' => $approverType,
                    'approver_user_id' => $approverType === 'specific_user' ? $admin->id : null,
                ]);
            }
        }
    }

    /**
     * Seed a handful of field visits (Visiting Pekerjaan) for the demo, each
     * with a worked-through tasklist and photo evidence so the TUGAS and FOTO
     * columns render populated rather than empty.
     *
     * @param  array<int, Employee>  $employees
     */
    private function seedFieldVisits(Tenant $tenant, array $employees): void
    {
        $today = Carbon::today();

        // Each visit: filing employee no., days ago, place, tasks (title=>done),
        // photos (label + RGB tint), and a Jakarta-area GPS pin.
        $visits = [
            [
                'emp' => 2, 'days_ago' => 1, 'attendees' => [2, 5],
                'location' => 'Jakarta', 'client' => 'PT Smart Solusi',
                'purpose' => 'Demo produk & negosiasi kontrak',
                'notes' => 'Klien tertarik paket enterprise, tindak lanjut minggu depan.',
                'lat' => -6.19510, 'lng' => 106.82330,
                'tasks' => [
                    'Presentasi demo produk' => true,
                    'Diskusi kebutuhan integrasi' => true,
                    'Susun proposal harga' => false,
                    'Jadwalkan follow-up call' => false,
                ],
                'photos' => [
                    ['Demo Produk', [37, 99, 235]],
                    ['Ruang Meeting Klien', [22, 163, 74]],
                ],
            ],
            [
                'emp' => 3, 'days_ago' => 3, 'attendees' => [3],
                'location' => 'Bekasi', 'client' => 'CV Maju Bersama',
                'purpose' => 'Survei lokasi & pemasangan perangkat',
                'notes' => 'Instalasi selesai, unit berjalan normal.',
                'lat' => -6.23880, 'lng' => 106.99270,
                'tasks' => [
                    'Survei titik pemasangan' => true,
                    'Pasang perangkat' => true,
                    'Uji koneksi jaringan' => true,
                    'Serah terima ke klien' => true,
                ],
                'photos' => [
                    ['Instalasi Perangkat', [217, 119, 6]],
                    ['Uji Koneksi', [139, 92, 246]],
                    ['Serah Terima', [14, 165, 233]],
                ],
            ],
            [
                'emp' => 5, 'days_ago' => 5, 'attendees' => [5, 8],
                'location' => 'Tangerang', 'client' => 'PT Cahaya Abadi',
                'purpose' => 'Maintenance rutin & training operator',
                'notes' => 'Training 4 operator, materi tersampaikan.',
                'lat' => -6.17830, 'lng' => 106.63190,
                'tasks' => [
                    'Cek kondisi perangkat' => true,
                    'Ganti sparepart aus' => true,
                    'Training operator baru' => false,
                ],
                'photos' => [
                    ['Maintenance', [236, 72, 153]],
                    ['Sesi Training', [244, 63, 94]],
                ],
            ],
            [
                'emp' => 8, 'days_ago' => 8, 'attendees' => [8],
                'location' => 'Depok', 'client' => 'Toko Sinar Jaya',
                'purpose' => 'Penagihan & pengambilan dokumen',
                'notes' => 'Invoice lunas, dokumen lengkap diterima.',
                'lat' => -6.40250, 'lng' => 106.79420,
                'tasks' => [
                    'Serahkan invoice' => true,
                    'Terima pembayaran' => true,
                    'Ambil dokumen kontrak' => true,
                ],
                'photos' => [
                    ['Bukti Penagihan', [16, 185, 129]],
                ],
            ],
        ];

        foreach ($visits as $data) {
            $employee = $employees[$data['emp']];
            $visitDate = $today->copy()->subDays($data['days_ago']);

            $visit = FieldVisit::firstOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'employee_id' => $employee->id,
                    'visit_date' => $visitDate->toDateString(),
                    'location' => $data['location'],
                ],
                [
                    'branch_id' => $employee->branch_id,
                    'client_name' => $data['client'],
                    'purpose' => $data['purpose'],
                    'notes' => $data['notes'],
                    'latitude' => $data['lat'],
                    'longitude' => $data['lng'],
                    'status' => 'submitted',
                ],
            );

            if (! $visit->wasRecentlyCreated) {
                continue;
            }

            $visit->syncAttendees(array_map(
                fn (int $no): int => $employees[$no]->id,
                $data['attendees'],
            ));

            $order = 0;
            foreach ($data['tasks'] as $title => $done) {
                $visit->tasks()->create([
                    'tenant_id' => $tenant->id,
                    'title' => $title,
                    'sort_order' => $order++,
                    'is_done' => $done,
                    'done_at' => $done ? $visitDate->copy()->setTime(14, 0) : null,
                ]);
            }

            foreach ($data['photos'] as $index => [$label, $rgb]) {
                $path = 'field-visits/demo-'.$visit->id.'-'.($index + 1).'.jpg';
                $this->makeVisitPhoto($path, $data['client'], $label, $rgb);

                $visit->photos()->create([
                    'tenant_id' => $tenant->id,
                    'employee_id' => $employee->id,
                    'file_path' => $path,
                ]);
            }
        }
    }

    /**
     * Render a labelled placeholder JPEG onto the public disk so a seeded field
     * visit has real photo evidence to link to.
     *
     * @param  array{0: int, 1: int, 2: int}  $rgb
     */
    private function makeVisitPhoto(string $relativePath, string $topLabel, string $bottomLabel, array $rgb): void
    {
        $width = 480;
        $height = 360;
        $image = imagecreatetruecolor($width, $height);

        // Vertical gradient from the tint down to a darker shade.
        [$r, $g, $b] = $rgb;
        for ($y = 0; $y < $height; $y++) {
            $factor = 1 - ($y / $height) * 0.55;
            $color = imagecolorallocate(
                $image,
                (int) ($r * $factor),
                (int) ($g * $factor),
                (int) ($b * $factor),
            );
            imagefilledrectangle($image, 0, $y, $width, $y, $color);
        }

        $white = imagecolorallocate($image, 255, 255, 255);
        imagestring($image, 5, 24, 150, $topLabel, $white);
        imagestring($image, 4, 24, 178, $bottomLabel, $white);

        Storage::disk('public')->makeDirectory('field-visits');
        imagejpeg($image, Storage::disk('public')->path($relativePath), 85);
        imagedestroy($image);
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
        // [code => [name, module_group, permission_modules]] — module_group drives
        // the grouping; permission_modules are the permission prefixes the feature
        // owns in the Hak Akses matrix (empty = feature-only row, no per-role
        // actions — access governed by a related feature, e.g. cash_advance via
        // payroll/claim, ess is mobile-only).
        $codes = [
            // Core HR
            'hr_core' => ['HR Core', 'core', ['employee']],
            'organization' => ['Organization', 'core', ['branch', 'department', 'position', 'organization']],
            'document' => ['Manajemen Dokumen', 'core', ['document']],
            'letter' => ['Template Surat', 'core', ['letter']],
            'offboarding' => ['Offboarding & Clearance', 'core', ['offboarding']],
            'helpdesk' => ['HR Helpdesk', 'core', ['helpdesk']],
            'delegation' => ['Delegasi Approval', 'core', ['delegation']],
            // Time & Attendance
            'attendance' => ['Attendance', 'time', ['attendance']],
            'leave' => ['Leave', 'time', ['leave']],
            'overtime' => ['Overtime', 'time', ['overtime']],
            'wfh' => ['WFH', 'time', ['wfh']],
            'timesheet' => ['Timesheet', 'time', ['timesheet']],
            'shift_swap' => ['Tukar Shift', 'time', ['shift_swap']],
            // Payroll & Finance
            'payroll' => ['Payroll', 'payroll', ['payroll']],
            'bpjs' => ['BPJS', 'payroll', ['bpjs']],
            'pph21' => ['PPh 21', 'payroll', ['pph21']],
            'claim' => ['Klaim & Reimbursement', 'payroll', ['claim']],
            'reimbursement' => ['Reimbursement', 'payroll', []],
            'cash_advance' => ['Cash Advance & Settlement', 'payroll', []],
            'loan' => ['Pinjaman Karyawan', 'payroll', ['loan']],
            'salary_structure' => ['Struktur & Skala Upah', 'payroll', ['salary_structure']],
            'journal' => ['Jurnal Akuntansi', 'payroll', ['journal']],
            // Talent
            'recruitment' => ['Recruitment (ATS)', 'talent', ['recruitment']],
            'onboarding' => ['Onboarding', 'talent', ['onboarding']],
            'performance' => ['Manajemen Kinerja', 'talent', ['performance']],
            'okr' => ['OKR & Goal', 'talent', ['okr']],
            'competency' => ['Kompetensi', 'talent', ['competency']],
            'talent' => ['Talenta & Suksesi', 'talent', ['talent']],
            'learning' => ['Pembelajaran (LMS)', 'talent', ['learning']],
            // Engagement & Self-Service
            'ess' => ['Employee Self-Service', 'engagement', []],
            'announcement' => ['Pengumuman', 'engagement', ['announcement']],
            'survey' => ['Survei Karyawan', 'engagement', ['survey']],
            // Analytics
            'analytics' => ['Analytics & Laporan', 'analytics', ['report']],
            'dynamic_report' => ['Dynamic Report', 'analytics', ['dynamic_report']],
            'attrition' => ['Prediksi Resign', 'analytics', ['attrition']],
            // Asset & CRM
            'asset' => ['Manajemen Aset', 'asset', ['asset']],
            'crm' => ['CRM', 'crm', ['crm']],
            'calendar' => ['Kalender Acara', 'engagement', ['calendar']],
            'budget' => ['Anggaran (Budget)', 'payroll', ['budget']],
            'ai' => ['AI Assistant', 'analytics', ['ai']],
        ];

        return collect($codes)->map(fn ($meta, $code) => Feature::updateOrCreate(
            ['code' => $code],
            ['name' => $meta[0], 'module_group' => $meta[1], 'permission_modules' => $meta[2]],
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
            'team.leave.approve', 'team.attendance.view', 'team.overtime.approve', 'team.claim.approve',
            'own.profile.view', 'own.attendance.clock_in', 'own.leave.request', 'own.payslip.view',
            // Per-menu access modules so every sidebar item is role-configurable
            // from the Hak Akses matrix (not just feature-gated per tenant).
            'document.view', 'letter.view', 'offboarding.view', 'organization.view',
            'timesheet.view', 'shift_swap.view', 'delegation.view',
            'claim.view', 'loan.view', 'journal.view', 'budget.view', 'salary_structure.view',
            'recruitment.view', 'onboarding.view',
            'performance.view', 'okr.view', 'competency.view', 'talent.view', 'learning.view',
            'helpdesk.view', 'announcement.view', 'survey.view', 'calendar.view', 'ai.view',
            'asset.view', 'crm.view', 'dynamic_report.view', 'attrition.view',
        ];
        $permModels = collect($perms)->map(function (string $code) {
            [$module, $action] = array_pad(explode('.', $code, 2), 2, '');

            return Permission::firstOrCreate(['code' => $code], ['module' => $module, 'action' => $action, 'name' => $code]);
        });

        $roles = [
            ['code' => 'super_admin', 'name' => 'Super Admin', 'tenant_id' => null, 'is_system' => true],
            ['code' => 'admin_tenant_hr', 'name' => 'Admin Tenant / HR', 'tenant_id' => $tenant->id, 'is_system' => true],
            ['code' => 'manager', 'name' => 'Manager', 'tenant_id' => $tenant->id, 'is_system' => true],
            ['code' => 'finance', 'name' => 'Finance', 'tenant_id' => $tenant->id, 'is_system' => true],
            ['code' => 'employee', 'name' => 'Karyawan', 'tenant_id' => $tenant->id, 'is_system' => true],
        ];
        foreach ($roles as $data) {
            $role = Role::firstOrCreate(['tenant_id' => $data['tenant_id'], 'code' => $data['code']], ['name' => $data['name'], 'is_system' => $data['is_system']]);

            $assigned = match ($data['code']) {
                'super_admin' => $permModels,
                'admin_tenant_hr' => $permModels->reject(fn ($p) => str_starts_with($p->code, 'tenant.')),
                'manager' => $permModels->filter(fn ($p) => str_starts_with($p->code, 'team.') || str_starts_with($p->code, 'own.')),
                // Finance settles the money: claims, loans, journals, budgets,
                // payroll — but none of the HR/people administration.
                'finance' => $permModels->filter(fn ($p) => in_array($p->module, ['claim', 'loan', 'journal', 'budget', 'salary_structure', 'payroll', 'report'], true)
                    || str_starts_with($p->code, 'own.')),
                default => $permModels->filter(fn ($p) => str_starts_with($p->code, 'own.')),
            };
            $role->permissions()->syncWithoutDetaching($assigned->pluck('id'));

            // Fail-open baseline: for every module the role already holds, grant
            // its full action set (view/create/update/archive/export/approve) so
            // action-level enforcement never removes access a role had before.
            $actionCodes = PermissionCatalog::actionCodesForModules($assigned->pluck('module')->all());
            $role->permissions()->syncWithoutDetaching(
                Permission::whereIn('code', $actionCodes)->pluck('id'),
            );
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
        $this->seedFinance($tenant);

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
        $role = Role::where('tenant_id', $tenant->id)->where('code', 'employee')->first();

        $workLocation = WorkLocation::forTenant($tenant->id)
            ->where('name', 'Kantor Pusat Jakarta')
            ->first();

        // ESS (employee self-service) test logins — Bagus plus three more so the
        // employee view can be demoed from several people. Password: password.
        foreach ([2, 3, 4, 7] as $index) {
            $employee = $employees[$index] ?? null;

            if ($employee === null) {
                continue;
            }

            $user = User::firstOrCreate(
                ['email' => $employee->email ?? "karyawan{$index}@nusantara.co.id"],
                [
                    'name' => $employee->full_name,
                    'tenant_id' => $tenant->id,
                    'password' => Hash::make('password'),
                    'status' => 'active',
                    'email_verified_at' => now(),
                ],
            );
            $user->forceFill(['tenant_id' => $tenant->id])->save();

            if ($role) {
                $user->roles()->syncWithoutDetaching([$role->id]);
            }

            $employee->forceFill([
                'user_id' => $user->id,
                'work_location_id' => $workLocation?->id,
                'branch_id' => $workLocation?->branch_id ?? $employee->branch_id,
            ])->save();
        }
    }

    /**
     * Seed the Director's login, linked to the top-approver employee, so the
     * mobile app can be demoed as a director: ESS features plus Manager mode.
     *
     * @param  array<int, Employee>  $employees
     */
    private function seedDirectorUser(Tenant $tenant, array $employees): void
    {
        $employee = $employees[0] ?? null;

        if (! $employee) {
            return;
        }

        $user = User::firstOrCreate(
            ['email' => $employee->email ?? 'direktur@nusantara.co.id'],
            [
                'name' => $employee->full_name,
                'tenant_id' => $tenant->id,
                'password' => Hash::make('password'),
                'status' => 'active',
                'email_verified_at' => now(),
            ],
        );
        $user->forceFill(['tenant_id' => $tenant->id])->save();

        $roleIds = Role::where('tenant_id', $tenant->id)
            ->whereIn('code', ['employee', 'manager'])
            ->pluck('id');
        if ($roleIds->isNotEmpty()) {
            $user->roles()->syncWithoutDetaching($roleIds->all());
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

    /**
     * Seed a Finance officer. Settlements are verified and paid out by someone
     * other than whoever approved them, so the tenant needs a second person who
     * can reach the money screens without being an HR admin.
     */
    private function seedFinance(Tenant $tenant): void
    {
        $finance = User::firstOrCreate(
            ['email' => 'dewi.f@nusantara.co.id'],
            [
                'name' => 'Dewi Fitriani',
                'tenant_id' => $tenant->id,
                'password' => Hash::make('password'),
                'status' => 'active',
                'email_verified_at' => now(),
            ],
        );
        $finance->forceFill(['tenant_id' => $tenant->id])->save();

        $role = Role::where('tenant_id', $tenant->id)->where('code', 'finance')->first();
        if ($role) {
            $finance->roles()->syncWithoutDetaching([$role->id]);
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

        // The director tops the org chart: a real employee (uses the ESS app
        // like anyone) flagged as top approver. is_top_approver auto-approves
        // their own requests and — via ResolvesApiEmployee — unlocks Manager
        // mode in the mobile app even without direct reports. They are left at
        // the head of the chart (manager_id null) rather than made the HR
        // Manager's boss, because current_approver_id carries an *employee* id
        // yet is FK-constrained to users; routing a request to an employee with
        // no matching user id would fail the insert.
        $direksi = Department::firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'DIR'],
            ['name' => 'Direksi', 'status' => 'active'],
        );
        $execLevel = JobLevel::firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'EXEC'],
            ['name' => 'Direktur', 'level_order' => 10],
        );
        $direkturPosition = Position::firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'DIRUT-DIR'],
            ['department_id' => $direksi->id, 'name' => 'Direktur Utama', 'status' => 'active'],
        );
        $employees[0] = Employee::firstOrCreate(
            ['tenant_id' => $tenant->id, 'employee_number' => 'EMP-0000'],
            [
                'branch_id' => $branches['Jakarta Pusat']->id,
                'department_id' => $direksi->id,
                'position_id' => $direkturPosition->id,
                'job_level_id' => $execLevel->id,
                'full_name' => 'Hendra Wijaya',
                'email' => 'direktur@nusantara.co.id',
                'gender' => 'unspecified',
                'birth_date' => $this->parseIndoDate('11 Mar 1978'),
                'employment_status' => 'permanent',
                'join_date' => $this->parseIndoDate('02 Jan 2018'),
                'status' => 'active',
                'is_top_approver' => true,
            ],
        );
        $employees[0]->forceFill(['is_top_approver' => true])->save();

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
