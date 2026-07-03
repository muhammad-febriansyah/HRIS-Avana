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
use App\Models\Tenant;
use App\Models\WebsiteSetting;
use Illuminate\Database\Seeder;
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

        $this->seedLogo($tenant);
        $employees = $this->seedEmployees($tenant, $branches, $departments, $jobLevel);
        $this->seedAvatars($employees);
        $this->seedMoods($tenant, $employees);
        $this->seedAttendance($tenant, $employees);

        $this->command?->info('Presentation demo seeded: '.count($employees).' employees with avatars, logo, moods & attendance.');
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
    private function seedEmployees(Tenant $tenant, mixed $branches, mixed $departments, ?JobLevel $jobLevel): array
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
                    'work_location_id' => null,
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
