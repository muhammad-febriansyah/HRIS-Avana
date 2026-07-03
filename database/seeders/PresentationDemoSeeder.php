<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\JobLevel;
use App\Models\MoodCheckin;
use App\Models\Position;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WebsiteSetting;
use App\Models\WorkLocation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Rich demo dataset for presentations: brings the tenant to 40 realistic
 * employees, downloads a company logo + cartoon avatars into storage, and
 * populates mood check-ins and attendance so every dashboard looks alive.
 *
 * Run after AvanaDemoSeeder:  php artisan db:seed --class=PresentationDemoSeeder
 */
class PresentationDemoSeeder extends Seeder
{
    /** @var array<string, string> */
    private const POSITIONS = [
        'Human Resources' => 'People Ops',
        'Engineering' => 'Software Engineer',
        'Finance' => 'Finance Analyst',
        'Sales' => 'Sales Executive',
        'Marketing' => 'Digital Marketer',
        'Operations' => 'Ops Staff',
    ];

    /** Department → the seeded lead employee's number (from AvanaDemoSeeder). */
    private const DEPT_LEAD = [
        'Human Resources' => 1,
        'Engineering' => 2,
        'Finance' => 3,
        'Sales' => 10,
        'Marketing' => 5,
        'Operations' => 6,
    ];

    public function run(): void
    {
        $tenant = Tenant::query()->first();
        if ($tenant === null) {
            $this->command?->warn('No tenant found. Run AvanaDemoSeeder first.');

            return;
        }

        $branches = Branch::forTenant($tenant->id)->get()->keyBy('name');
        $departments = Department::forTenant($tenant->id)->get()->keyBy('name');
        $jobLevel = JobLevel::forTenant($tenant->id)->first();

        Storage::disk('public')->makeDirectory('branding');
        Storage::disk('public')->makeDirectory('avatars');

        $workLocations = $this->seedWorkLocations($tenant, $branches);

        $this->seedLogo($tenant);
        $employees = $this->seedEmployees($tenant, $branches, $departments, $jobLevel, $workLocations);
        $this->seedEmployeeLogins($tenant, $employees);
        $this->seedAvatars($employees);
        $this->seedStaffAvatars();
        $this->seedMoods($tenant, $employees);
        $this->seedAttendance($tenant, $employees);

        $this->command?->info('Presentation demo seeded: '.count($employees).' employees (all with ESS login) + avatars, logo, moods & attendance.');
    }

    /**
     * Ensure each branch has an active geofenced work location so any employee
     * can clock in during the demo.
     *
     * @return array<string, int> branch name => work_location_id
     */
    private function seedWorkLocations(Tenant $tenant, mixed $branches): array
    {
        $coords = [
            'Jakarta Pusat' => [-6.2146, 106.8451],
            'Bandung' => [-6.9147, 107.6098],
            'Surabaya' => [-7.2575, 112.7521],
        ];

        $map = [];
        foreach ($branches as $name => $branch) {
            [$lat, $lng] = $coords[$name] ?? [-6.2146, 106.8451];
            $wl = WorkLocation::firstOrCreate(
                ['tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'name' => 'Kantor '.$name],
                ['latitude' => $lat, 'longitude' => $lng, 'radius_meter' => 200, 'status' => 'active'],
            );
            $map[$name] = $wl->id;
        }

        return $map;
    }

    /**
     * Give every employee a mobile (ESS) login so any of them can sign in to
     * the Flutter app. Password is "password". Employees that already have a
     * linked user (e.g. the karyawan@ demo account) are left untouched.
     *
     * @param  array<int, Employee>  $employees
     */
    private function seedEmployeeLogins(Tenant $tenant, array $employees): void
    {
        $role = Role::where('tenant_id', $tenant->id)->where('code', 'employee')->first();
        $created = 0;

        foreach ($employees as $employee) {
            if ($employee->user_id !== null || $employee->email === null) {
                continue;
            }

            $user = User::firstOrCreate(
                ['email' => $employee->email],
                [
                    'name' => $employee->full_name,
                    'tenant_id' => $tenant->id,
                    'password' => Hash::make('password'),
                    'status' => 'active',
                    'email_verified_at' => now(),
                ],
            );

            if ($role !== null) {
                $user->roles()->syncWithoutDetaching([$role->id]);
            }

            $employee->forceFill(['user_id' => $user->id])->save();
            $created++;
        }

        $this->command?->info("ESS logins created: {$created} (password: 'password').");
    }

    private function seedLogo(Tenant $tenant): void
    {
        $name = $tenant->company_name ?? $tenant->name ?? 'Perusahaan';
        $url = 'https://ui-avatars.com/api/?'.http_build_query([
            'name' => $name,
            'size' => 256,
            'background' => '1E3A8A',
            'color' => 'ffffff',
            'bold' => 'true',
            'format' => 'png',
        ]);

        $bytes = $this->download($url);
        if ($bytes === null) {
            $this->command?->warn('Logo download failed; skipped.');

            return;
        }

        $path = 'branding/company-logo.png';
        Storage::disk('public')->put($path, $bytes);

        Company::query()->where('tenant_id', $tenant->id)->update(['logo_path' => $path]);
        WebsiteSetting::query()->limit(1)->update(['logo_path' => $path]);

        $this->command?->info('Company logo saved to storage.');
    }

    /**
     * @return array<int, Employee>
     */
    private function seedEmployees(Tenant $tenant, mixed $branches, mixed $departments, ?JobLevel $jobLevel, array $workLocations): array
    {
        $branchNames = $branches->keys()->all();
        $deptNames = array_keys(self::POSITIONS);
        $employmentPool = ['permanent', 'permanent', 'permanent', 'contract', 'contract', 'probation'];

        $people = $this->people();
        $employees = Employee::forTenant($tenant->id)->get()->keyBy('employee_number')->all();

        foreach ($people as $i => $person) {
            $no = 11 + $i;
            $number = sprintf('EMP-%04d', $no);
            $dept = $departments[$deptNames[$i % count($deptNames)]];
            $branch = $branches[$branchNames[$i % count($branchNames)]];
            $positionName = self::POSITIONS[$dept->name];

            $position = Position::firstOrCreate(
                ['tenant_id' => $tenant->id, 'code' => strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $positionName), 0, 6)).'-'.$dept->code],
                ['department_id' => $dept->id, 'name' => $positionName, 'status' => 'active'],
            );

            $leadNo = self::DEPT_LEAD[$dept->name] ?? 1;
            $manager = $employees[sprintf('EMP-%04d', $leadNo)] ?? null;

            $joinYear = 2019 + ($i % 7);
            $joinMonth = 1 + ($i % 12);
            $birthYear = 1988 + ($i % 12);

            $employee = Employee::updateOrCreate(
                ['tenant_id' => $tenant->id, 'employee_number' => $number],
                [
                    'branch_id' => $branch->id,
                    'work_location_id' => $workLocations[$branch->name] ?? null,
                    'department_id' => $dept->id,
                    'position_id' => $position->id,
                    'job_level_id' => $jobLevel?->id,
                    'manager_id' => $manager?->id,
                    'full_name' => $person['name'],
                    'email' => $person['email'],
                    'gender' => $person['gender'],
                    'birth_date' => sprintf('%04d-%02d-%02d', $birthYear, 1 + ($i % 12), 1 + ($i % 27)),
                    'marital_status' => $i % 3 === 0 ? 'single' : 'married',
                    'employment_status' => $employmentPool[$i % count($employmentPool)],
                    'join_date' => sprintf('%04d-%02d-%02d', $joinYear, $joinMonth, 1 + ($i % 27)),
                    'status' => 'active',
                ],
            );

            $employees[$number] = $employee;
        }

        return array_values($employees);
    }

    /**
     * Download a cartoon avatar per employee (DiceBear "avataaars").
     *
     * @param  array<int, Employee>  $employees
     */
    private function seedAvatars(array $employees): void
    {
        $saved = 0;
        foreach ($employees as $employee) {
            $path = 'avatars/'.strtolower($employee->employee_number).'.png';
            if (! Storage::disk('public')->exists($path)) {
                $url = 'https://api.dicebear.com/9.x/avataaars/png?'.http_build_query([
                    'seed' => $employee->full_name,
                    'size' => 200,
                    'radius' => 50,
                ]);
                $bytes = $this->download($url);
                if ($bytes === null) {
                    continue;
                }
                Storage::disk('public')->put($path, $bytes);
            }

            if ($employee->photo_path !== $path) {
                $employee->forceFill(['photo_path' => $path])->save();
            }
            $saved++;
        }

        $this->command?->info("Avatars saved: {$saved}.");
    }

    /**
     * Ensure every user row has its own avatar_path. Employee logins copy their
     * employee photo; admin-side accounts (super admin, tenant admin, manager)
     * that are not linked to an employee get a generated cartoon avatar.
     */
    private function seedStaffAvatars(): void
    {
        $saved = 0;
        foreach (User::query()->with('employee:id,user_id,photo_path')->get() as $user) {
            $path = $user->employee?->photo_path;

            if ($path === null) {
                $path = 'avatars/user-'.$user->id.'.png';
                if (! Storage::disk('public')->exists($path)) {
                    $url = 'https://api.dicebear.com/9.x/avataaars/png?'.http_build_query([
                        'seed' => $user->name,
                        'size' => 200,
                        'radius' => 50,
                    ]);
                    $bytes = $this->download($url);
                    if ($bytes === null) {
                        continue;
                    }
                    Storage::disk('public')->put($path, $bytes);
                }
            }

            if ($user->avatar_path !== $path) {
                $user->forceFill(['avatar_path' => $path])->save();
            }
            $saved++;
        }

        $this->command?->info("User avatars saved: {$saved}.");
    }

    /**
     * @param  array<int, Employee>  $employees
     */
    private function seedMoods(Tenant $tenant, array $employees): void
    {
        $moods = ['sangat_baik', 'baik', 'baik', 'biasa', 'biasa', 'baik', 'kurang', 'sangat_baik', 'baik', 'buruk'];

        for ($d = 0; $d < 7; $d++) {
            $date = now()->subDays($d)->toDateString();
            foreach ($employees as $idx => $employee) {
                // ~75% of the team checks in each day.
                if (($idx + $d) % 4 === 0) {
                    continue;
                }
                MoodCheckin::updateOrCreate(
                    ['employee_id' => $employee->id, 'date' => $date],
                    ['tenant_id' => $tenant->id, 'mood' => $moods[($idx + $d) % count($moods)]],
                );
            }
        }

        $this->command?->info('Mood check-ins seeded (7 days).');
    }

    /**
     * @param  array<int, Employee>  $employees
     */
    private function seedAttendance(Tenant $tenant, array $employees): void
    {
        for ($d = 0; $d < 5; $d++) {
            $day = now()->subDays($d);
            if ($day->isWeekend()) {
                continue;
            }
            $date = $day->toDateString();
            foreach ($employees as $idx => $employee) {
                $late = ($idx + $d) % 6 === 0;
                $inHour = $late ? 8 : 7;
                $inMin = $late ? 20 + ($idx % 30) : 45 + ($idx % 14);
                Attendance::updateOrCreate(
                    ['tenant_id' => $tenant->id, 'employee_id' => $employee->id, 'date' => $date],
                    [
                        'branch_id' => $employee->branch_id,
                        'clock_in_at' => sprintf('%s %02d:%02d:00', $date, $inHour, $inMin % 60),
                        'clock_out_at' => sprintf('%s 17:%02d:00', $date, 5 + ($idx % 50)),
                        'late_minutes' => $late ? 20 + ($idx % 30) : 0,
                        'work_minutes' => 540 - ($late ? 20 : 0),
                        'status' => 'present',
                        'location_status' => 'inside',
                    ],
                );
            }
        }

        $this->command?->info('Attendance seeded (recent workdays).');
    }

    private function download(string $url): ?string
    {
        try {
            $response = Http::timeout(30)->retry(2, 500)->get($url);

            return $response->successful() ? $response->body() : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * 30 realistic Indonesian employees (numbers 11-40).
     *
     * @return array<int, array{name: string, gender: string, email: string}>
     */
    private function people(): array
    {
        $rows = [
            ['Budi Santoso', 'male'], ['Rina Wijaya', 'female'], ['Dimas Hidayat', 'male'],
            ['Ayu Kusuma', 'female'], ['Eko Setiawan', 'male'], ['Sri Purnama', 'female'],
            ['Hendra Halim', 'male'], ['Fitri Ramadhan', 'female'], ['Wahyu Cahyono', 'male'],
            ['Wulan Susanto', 'female'], ['Arif Firmansyah', 'male'], ['Anisa Puspita', 'female'],
            ['Galih Gunawan', 'male'], ['Dinda Utami', 'female'], ['Reza Hakim', 'male'],
            ['Kartika Sari', 'female'], ['Ilham Nugroho', 'male'], ['Ratna Melati', 'female'],
            ['Surya Pradana', 'male'], ['Indah Permatasari', 'female'], ['Dedi Kurniawan', 'male'],
            ['Citra Lestari', 'female'], ['Bayu Anggara', 'male'], ['Lina Marlina', 'female'],
            ['Agus Salim', 'male'], ['Bella Safira', 'female'], ['Teguh Prakoso', 'male'],
            ['Nurul Aini', 'female'], ['Rian Hidayat', 'male'], ['Sari Wulandari', 'female'],
        ];

        return array_map(function (array $r, int $i): array {
            [$name, $gender] = $r;
            $parts = explode(' ', strtolower($name));
            $email = $parts[0].'.'.substr($parts[1] ?? 'x', 0, 3).(11 + $i).'@nusantara.co.id';

            return ['name' => $name, 'gender' => $gender, 'email' => $email];
        }, $rows, array_keys($rows));
    }
}
