<?php

use App\Models\Announcement;
use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use App\Models\CalendarEvent;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\OvertimeRequest;
use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use App\Models\PermissionRequest;
use App\Models\User;
use App\Support\AvanaNav;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(AvanaDemoSeeder::class);

    $this->user = User::where('email', 'bagus.p@nusantara.co.id')->firstOrFail();
    $this->employee = $this->user->employee;
});

it('shows the self-service group in an employee sidebar', function (): void {
    $nav = AvanaNav::forUser($this->user->fresh());

    $group = collect($nav)->firstWhere('title', 'LAYANAN SAYA');

    expect($group)->not->toBeNull()
        ->and(collect($group['items'])->pluck('label'))
        ->toContain('Profil', 'Absensi', 'Cuti', 'Slip Gaji');
});

it('hides the self-service group from an account with no employee record', function (): void {
    $hrAdmin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();

    expect($hrAdmin->employee)->toBeNull();

    $titles = collect(AvanaNav::forUser($hrAdmin))->pluck('title');

    expect($titles)->not->toContain('LAYANAN SAYA');
});

it('renders every self-service page for an employee', function (string $path, string $component): void {
    $this->actingAs($this->user)
        ->get($path)
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component($component));
})->with([
    'profil' => ['/avana/saya/profil', 'avana/saya/profil'],
    'absensi' => ['/avana/saya/absensi', 'avana/saya/absensi'],
    'koreksi absensi' => ['/avana/saya/koreksi-absensi', 'avana/saya/koreksi-absensi'],
    'jadwal' => ['/avana/saya/jadwal', 'avana/saya/jadwal'],
    'struktur organisasi' => ['/avana/saya/organisasi', 'avana/employees/org-chart'],
    'cuti' => ['/avana/saya/cuti', 'avana/saya/cuti'],
    'lembur' => ['/avana/saya/lembur', 'avana/saya/lembur'],
    'izin' => ['/avana/saya/izin', 'avana/saya/izin'],
    'kontrak' => ['/avana/saya/kontrak', 'avana/saya/kontrak'],
    'kinerja' => ['/avana/saya/kinerja', 'avana/saya/kinerja'],
    'slip gaji' => ['/avana/saya/slip-gaji', 'avana/saya/slip-gaji'],
    'dokumen' => ['/avana/saya/dokumen', 'avana/saya/dokumen'],
    'onboarding' => ['/avana/saya/onboarding', 'avana/saya/onboarding'],
    'kalender' => ['/avana/saya/kalender', 'avana/saya/kalender'],
    'tugas' => ['/avana/saya/tugas', 'avana/saya/tugas'],
    'pembelajaran' => ['/avana/saya/pembelajaran', 'avana/saya/pembelajaran'],
    'benefit' => ['/avana/saya/benefit', 'avana/saya/benefit'],
    'perjalanan dinas' => ['/avana/saya/perjalanan-dinas', 'avana/saya/perjalanan-dinas'],
    'ajukan perjalanan dinas' => ['/avana/saya/perjalanan-dinas/ajukan', 'avana/saya/perjalanan-dinas-ajukan'],
]);

it('carries the colleague phone number on the self-service org chart', function (): void {
    $this->actingAs($this->user)
        ->get('/avana/saya/organisasi')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('nodes.0.phone'));
});

it('covers every self-service menu with a reachable page', function (): void {
    $employeeMenu = collect(AvanaNav::forUser($this->user->fresh()))
        ->firstWhere('title', 'LAYANAN SAYA');

    expect($employeeMenu)->not->toBeNull();

    foreach ($employeeMenu['items'] as $item) {
        $this->actingAs($this->user)
            ->get($item['href'])
            ->assertOk();
    }
});

it('gives an employee their own dashboard instead of the HR one', function (): void {
    $this->actingAs($this->user)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('avana/saya/dashboard'));
});

it('keeps the HR dashboard for an HR admin', function (): void {
    $this->actingAs(User::where('email', 'rina.a@nusantara.co.id')->firstOrFail())
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('dashboard'));
});

it('lists only the signed-in employee attendance', function (): void {
    $colleague = Employee::forTenant($this->employee->tenant_id)
        ->where('id', '!=', $this->employee->id)
        ->firstOrFail();

    Attendance::where('employee_id', $this->employee->id)->delete();

    Attendance::create([
        'tenant_id' => $this->employee->tenant_id,
        'employee_id' => $this->employee->id,
        'date' => now()->startOfMonth()->toDateString(),
        'work_minutes' => 480,
        'late_minutes' => 0,
        'status' => 'present',
    ]);

    Attendance::create([
        'tenant_id' => $colleague->tenant_id,
        'employee_id' => $colleague->id,
        'date' => now()->startOfMonth()->toDateString(),
        'work_minutes' => 480,
        'late_minutes' => 0,
        'status' => 'present',
    ]);

    $this->actingAs($this->user)
        ->get('/avana/saya/absensi')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('avana/saya/absensi')
            ->has('records', 1)
            ->where('summary.present', 1)
        );
});

it('files a leave request against the signed-in employee', function (): void {
    $type = LeaveType::forTenant($this->employee->tenant_id)->where('code', 'TAHUNAN')->firstOrFail();

    $this->actingAs($this->user)
        ->post('/avana/saya/cuti', [
            'leave_type_id' => $type->id,
            'start_date' => now()->addWeek()->toDateString(),
            'end_date' => now()->addWeek()->toDateString(),
            'reason' => 'Acara keluarga',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $leave = LeaveRequest::forTenant($this->employee->tenant_id)
        ->where('reason', 'Acara keluarga')
        ->firstOrFail();

    expect((int) $leave->employee_id)->toBe((int) $this->employee->id)
        ->and($leave->status)->toBe('pending');
});

it('rejects a leave request that exceeds the remaining balance', function (): void {
    $type = LeaveType::forTenant($this->employee->tenant_id)->where('code', 'TAHUNAN')->firstOrFail();
    $type->update(['allow_negative' => false]);

    $this->actingAs($this->user)
        ->post('/avana/saya/cuti', [
            'leave_type_id' => $type->id,
            'start_date' => now()->addWeek()->toDateString(),
            'end_date' => now()->addWeek()->addDays(400)->toDateString(),
            'reason' => 'Terlalu panjang',
        ])
        ->assertSessionHasErrors('leave_type_id');

    expect(LeaveRequest::forTenant($this->employee->tenant_id)->where('reason', 'Terlalu panjang')->exists())->toBeFalse();
});

it('files overtime, izin, and an attendance correction for the employee', function (): void {
    $this->actingAs($this->user)
        ->post('/avana/saya/lembur', [
            'date' => now()->subDay()->toDateString(),
            'start_time' => '18:00',
            'end_time' => '20:00',
            'reason' => 'Rilis fitur',
        ])
        ->assertSessionHasNoErrors();

    $this->actingAs($this->user)
        ->post('/avana/saya/izin', [
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'type' => 'sakit',
            'reason' => 'Demam',
        ])
        ->assertSessionHasNoErrors();

    $this->actingAs($this->user)
        ->post('/avana/saya/koreksi-absensi', [
            'date' => now()->subDay()->toDateString(),
            'requested_clock_in' => '08:00',
            'requested_clock_out' => '17:00',
            'reason' => 'Lupa absen pulang',
        ])
        ->assertSessionHasNoErrors();

    expect(OvertimeRequest::where('employee_id', $this->employee->id)->where('reason', 'Rilis fitur')->exists())->toBeTrue()
        ->and(PermissionRequest::where('employee_id', $this->employee->id)->where('reason', 'Demam')->exists())->toBeTrue()
        ->and(AttendanceCorrection::where('employee_id', $this->employee->id)->where('reason', 'Lupa absen pulang')->exists())->toBeTrue();
});

it('rejects a correction whose clock out precedes its clock in', function (): void {
    $this->actingAs($this->user)
        ->post('/avana/saya/koreksi-absensi', [
            'date' => now()->subDay()->toDateString(),
            'requested_clock_in' => '17:00',
            'requested_clock_out' => '08:00',
            'reason' => 'Terbalik',
        ])
        ->assertSessionHasErrors('requested_clock_out');

    expect(AttendanceCorrection::where('reason', 'Terbalik')->exists())->toBeFalse();
});

it('rejects an izin clock time spanning more than one day', function (): void {
    $this->actingAs($this->user)
        ->post('/avana/saya/izin', [
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDays(3)->toDateString(),
            'type' => 'pribadi',
            'start_time' => '09:00',
            'end_time' => '11:00',
            'reason' => 'Multi hari berjam',
        ])
        ->assertSessionHasErrors('start_time');

    expect(PermissionRequest::where('reason', 'Multi hari berjam')->exists())->toBeFalse();
});

it('blocks an employee from opening another employee payslip', function (): void {
    $colleague = Employee::forTenant($this->employee->tenant_id)
        ->where('id', '!=', $this->employee->id)
        ->firstOrFail();

    $run = PayrollRun::forTenant($this->employee->tenant_id)->firstOrFail();

    // A payslip in the same tenant belonging to somebody else — the only thing
    // keeping it out of reach is the ownership check.
    $foreignPayslip = PayrollRunItem::create([
        'tenant_id' => $this->employee->tenant_id,
        'payroll_run_id' => $run->id,
        'payroll_period_id' => $run->payroll_period_id,
        'employee_id' => $colleague->id,
        'gross_salary' => 10_000_000,
        'total_allowance' => 0,
        'total_deduction' => 500_000,
        'bpjs_employee_total' => 0,
        'bpjs_company_total' => 0,
        'pph21_total' => 0,
        'net_salary' => 9_500_000,
        'status' => 'final',
    ]);

    $this->actingAs($this->user)
        ->get("/avana/saya/slip-gaji/{$foreignPayslip->id}")
        ->assertNotFound();
});

it('keeps the admin employee console shut to a plain employee', function (): void {
    // The self-service org chart is open, but the admin screens behind
    // EmployeePolicy stay closed.
    $this->actingAs($this->user)->get('/avana/saya/organisasi')->assertOk();
    $this->actingAs($this->user)->get('/avana/organisasi')->assertForbidden();
    $this->actingAs($this->user)->get('/avana/employees')->assertForbidden();
});

it('refuses self-service to an account with no employee record', function (): void {
    $this->actingAs(User::where('email', 'rina.a@nusantara.co.id')->firstOrFail())
        ->get('/avana/saya/profil')
        ->assertForbidden();
});

it('updates only the personal fields an employee owns', function (): void {
    $originalName = $this->employee->full_name;

    $this->actingAs($this->user)
        ->put('/avana/saya/profil', [
            'phone' => '081234567890',
            'address' => 'Jl. Merdeka 10',
            'email' => 'bagus.p@nusantara.co.id',
            'full_name' => 'Nama Palsu',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $this->employee->refresh();

    expect($this->employee->phone)->toBe('081234567890')
        ->and($this->employee->address)->toBe('Jl. Merdeka 10')
        // full_name is org-owned: the mass-assignment must not have taken it.
        ->and($this->employee->full_name)->toBe($originalName);
});

it('feeds the employee dashboard with their own documents, colleagues, announcements and calendar', function (): void {
    $tenantId = $this->employee->tenant_id;
    $colleague = Employee::forTenant($tenantId)
        ->where('id', '!=', $this->employee->id)
        ->firstOrFail();

    EmployeeDocument::create([
        'tenant_id' => $tenantId,
        'employee_id' => $this->employee->id,
        'name' => 'Sertifikat.pdf',
        'file_path' => 'documents/sertifikat.pdf',
        'file_size' => 524_288,
        'uploaded_at' => Carbon::today()->toDateString(),
    ]);
    EmployeeDocument::create([
        'tenant_id' => $tenantId,
        'employee_id' => $colleague->id,
        'name' => 'Bukan Milik Saya.pdf',
        'file_path' => 'documents/lain.pdf',
    ]);

    Announcement::create([
        'tenant_id' => $tenantId,
        'title' => 'Libur Bersama',
        'body' => 'Kantor tutup pekan depan.',
        'category' => 'Kebijakan',
        'status' => 'published',
        'published_at' => Carbon::now(),
    ]);
    Announcement::create([
        'tenant_id' => $tenantId,
        'title' => 'Draf Internal',
        'body' => 'Belum terbit.',
        'status' => 'draft',
    ]);

    CalendarEvent::create([
        'tenant_id' => $tenantId,
        'title' => 'Rapat Tim',
        'type' => 'meeting',
        'start_date' => Carbon::today()->toDateString(),
        'all_day' => true,
    ]);

    $this->actingAs($this->user)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/saya/dashboard')
            ->has('documents', 1)
            ->where('documents.0.name', 'Sertifikat.pdf')
            ->where('documents.0.meta', fn (string $meta): bool => str_contains($meta, '512.0 KB'))
            ->has('announcements', 1)
            ->where('announcements.0.title', 'Libur Bersama')
            ->has('calendar.events', 1)
            ->where('calendar.events.0.type_label', 'Rapat')
            ->where('calendar.month', Carbon::today()->format('Y-m'))
            ->has('stats.tasks')
            ->has('stats.week_hours')
            ->etc());
});

it('loads another month into the dashboard calendar without leaking other months', function (): void {
    $tenantId = $this->employee->tenant_id;
    $nextMonth = Carbon::today()->startOfMonth()->addMonth();

    CalendarEvent::create([
        'tenant_id' => $tenantId,
        'title' => 'Bulan Ini',
        'type' => 'event',
        'start_date' => Carbon::today()->toDateString(),
    ]);
    CalendarEvent::create([
        'tenant_id' => $tenantId,
        'title' => 'Bulan Depan',
        'type' => 'event',
        'start_date' => $nextMonth->copy()->addDays(3)->toDateString(),
    ]);

    $this->actingAs($this->user)
        ->get('/dashboard?month='.$nextMonth->format('Y-m'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('calendar.month', $nextMonth->format('Y-m'))
            ->has('calendar.events', 1)
            ->where('calendar.events.0.title', 'Bulan Depan')
            ->etc());
});
