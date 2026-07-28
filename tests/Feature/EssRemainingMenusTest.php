<?php

use App\Models\Benefit;
use App\Models\CalendarEvent;
use App\Models\DutyTravel;
use App\Models\Employee;
use App\Models\EmployeeBenefit;
use App\Models\FieldVisit;
use App\Models\Training;
use App\Models\TrainingEnrollment;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    $this->seed(AvanaDemoSeeder::class);

    $this->user = User::where('email', 'bagus.p@nusantara.co.id')->firstOrFail();
    $this->employee = $this->user->employee;
    $this->tenantId = (int) $this->employee->tenant_id;
    $this->colleague = Employee::forTenant($this->tenantId)
        ->where('id', '!=', $this->employee->id)
        ->firstOrFail();
});

it('lists only the signed-in employee benefits, with Indonesian type labels', function (): void {
    EmployeeBenefit::query()->delete();

    $benefit = Benefit::create([
        'tenant_id' => $this->tenantId,
        'code' => 'TJ-UJI',
        'name' => 'Tunjangan Uji',
        'type' => 'allowance',
        'value' => 750_000,
        'status' => 'active',
    ]);

    EmployeeBenefit::create([
        'tenant_id' => $this->tenantId,
        'employee_id' => $this->employee->id,
        'benefit_id' => $benefit->id,
        'start_date' => now()->subMonth()->toDateString(),
        'status' => 'active',
    ]);

    EmployeeBenefit::create([
        'tenant_id' => $this->tenantId,
        'employee_id' => $this->colleague->id,
        'benefit_id' => $benefit->id,
        'start_date' => now()->subMonth()->toDateString(),
        'status' => 'active',
    ]);

    $this->actingAs($this->user)
        ->get('/avana/saya/benefit')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('avana/saya/benefit')
            ->has('benefits', 1)
            // The column stores "allowance"; the screen must not.
            ->where('benefits.0.type', 'Tunjangan')
            ->where('benefits.0.is_running', true)
            ->where('summary.total_value', 750_000)
        );
});

it('marks a benefit whose window has closed as no longer running', function (): void {
    EmployeeBenefit::query()->delete();

    $benefit = Benefit::create([
        'tenant_id' => $this->tenantId,
        'code' => 'ASR-UJI',
        'name' => 'Asuransi Uji',
        'type' => 'insurance',
        'value' => 100_000,
        'status' => 'active',
    ]);

    EmployeeBenefit::create([
        'tenant_id' => $this->tenantId,
        'employee_id' => $this->employee->id,
        'benefit_id' => $benefit->id,
        'start_date' => now()->subYear()->toDateString(),
        'end_date' => now()->subMonth()->toDateString(),
        'status' => 'active',
    ]);

    $this->actingAs($this->user)
        ->get('/avana/saya/benefit')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('benefits.0.type', 'Asuransi')
            ->where('benefits.0.is_running', false)
            ->where('summary.total_value', 0)
        );
});

it('lists only the signed-in employee training, and offers the rest as a catalogue', function (): void {
    TrainingEnrollment::query()->delete();
    Training::query()->delete();

    $mine = Training::create([
        'tenant_id' => $this->tenantId,
        'title' => 'Pelatihan Milik Saya',
        'category' => 'Teknologi',
        'type' => 'online',
        'start_date' => now()->subWeek()->toDateString(),
        'end_date' => now()->subWeek()->addDay()->toDateString(),
        'status' => 'completed',
    ]);

    $other = Training::create([
        'tenant_id' => $this->tenantId,
        'title' => 'Pelatihan Terbuka',
        'category' => 'Manajemen',
        'type' => 'external',
        'start_date' => now()->addWeek()->toDateString(),
        'status' => 'scheduled',
    ]);

    TrainingEnrollment::create([
        'tenant_id' => $this->tenantId,
        'training_id' => $mine->id,
        'employee_id' => $this->employee->id,
        'status' => 'completed',
        'score' => 88,
        'certificate_no' => 'CERT-UJI-1',
    ]);

    TrainingEnrollment::create([
        'tenant_id' => $this->tenantId,
        'training_id' => $other->id,
        'employee_id' => $this->colleague->id,
        'status' => 'enrolled',
    ]);

    $this->actingAs($this->user)
        ->get('/avana/saya/pembelajaran')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('avana/saya/pembelajaran')
            ->has('enrollments', 1)
            ->where('enrollments.0.title', 'Pelatihan Milik Saya')
            ->where('enrollments.0.type', 'Daring')
            ->where('enrollments.0.status_label', 'Selesai')
            // Already enrolled, so it must not also appear on offer.
            ->has('catalogue', 1)
            ->where('catalogue.0.title', 'Pelatihan Terbuka')
            ->where('catalogue.0.type', 'Eksternal')
            ->where('summary.certificates', 1)
        );
});

it('lists only the signed-in employee duty travel', function (): void {
    DutyTravel::query()->delete();

    DutyTravel::create([
        'tenant_id' => $this->tenantId,
        'employee_id' => $this->employee->id,
        'destination' => 'Surabaya',
        'purpose' => 'Audit cabang',
        'transport' => 'Pesawat',
        'start_date' => now()->addWeek()->toDateString(),
        'end_date' => now()->addWeek()->addDays(2)->toDateString(),
        'estimated_cost' => 4_000_000,
        'per_diem' => 900_000,
        'status' => 'approved',
    ]);

    DutyTravel::create([
        'tenant_id' => $this->tenantId,
        'employee_id' => $this->colleague->id,
        'destination' => 'Medan',
        'start_date' => now()->addWeek()->toDateString(),
        'end_date' => now()->addWeek()->toDateString(),
        'status' => 'approved',
    ]);

    $this->actingAs($this->user)
        ->get('/avana/saya/perjalanan-dinas')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('avana/saya/perjalanan-dinas')
            ->has('travels', 1)
            ->where('travels.0.destination', 'Surabaya')
            ->where('travels.0.status_label', 'Disetujui')
            ->where('summary.total_per_diem', 900_000)
        );
});

it('files a duty travel request against the signed-in employee', function (): void {
    DutyTravel::query()->delete();

    $this->actingAs($this->user)
        ->post('/avana/saya/perjalanan-dinas', [
            'destination' => 'Kantor Cabang Bandung',
            'purpose' => 'Audit cabang',
            'start_date' => now()->addWeek()->toDateString(),
            'end_date' => now()->addWeek()->addDays(2)->toDateString(),
            'transport' => 'Kereta Api',
            'estimated_cost' => 2_500_000,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $travel = DutyTravel::forTenant($this->tenantId)
        ->where('destination', 'Kantor Cabang Bandung')
        ->firstOrFail();

    expect((int) $travel->employee_id)->toBe((int) $this->employee->id)
        ->and($travel->status)->toBe('pending')
        ->and((int) $travel->estimated_cost)->toBe(2_500_000)
        // The allowance is left at the column default for whoever approves the
        // trip to set; the employee never prices their own.
        ->and((float) $travel->per_diem)->toBe(0.0);
});

it('rejects a duty travel request that returns before it departs', function (): void {
    DutyTravel::query()->delete();

    $this->actingAs($this->user)
        ->post('/avana/saya/perjalanan-dinas', [
            'destination' => 'Terbalik',
            'start_date' => now()->addWeek()->addDays(2)->toDateString(),
            'end_date' => now()->addWeek()->toDateString(),
        ])
        ->assertSessionHasErrors('end_date');

    expect(DutyTravel::where('destination', 'Terbalik')->exists())->toBeFalse();
});

it('rejects a duty travel request with no destination', function (): void {
    $this->actingAs($this->user)
        ->post('/avana/saya/perjalanan-dinas', [
            'start_date' => now()->addWeek()->toDateString(),
            'end_date' => now()->addWeek()->toDateString(),
        ])
        ->assertSessionHasErrors('destination');
});

it('filters duty travel by status without rewriting the totals', function (): void {
    DutyTravel::query()->delete();

    foreach (['pending', 'approved'] as $status) {
        DutyTravel::create([
            'tenant_id' => $this->tenantId,
            'employee_id' => $this->employee->id,
            'destination' => 'Tujuan '.$status,
            'start_date' => now()->addWeek()->toDateString(),
            'end_date' => now()->addWeek()->toDateString(),
            'per_diem' => 500_000,
            'status' => $status,
        ]);
    }

    $this->actingAs($this->user)
        ->get('/avana/saya/perjalanan-dinas?status=pending')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('travels', 1)
            ->where('travels.0.status', 'pending')
            ->where('status', 'pending')
            // Totals still describe the whole history, not the filtered slice.
            ->where('summary.total', 2)
            ->where('summary.total_per_diem', 500_000)
        );
});

it('ignores a status filter it does not recognise', function (): void {
    DutyTravel::query()->delete();

    DutyTravel::create([
        'tenant_id' => $this->tenantId,
        'employee_id' => $this->employee->id,
        'destination' => 'Tetap Tampil',
        'start_date' => now()->addWeek()->toDateString(),
        'end_date' => now()->addWeek()->toDateString(),
        'status' => 'approved',
    ]);

    $this->actingAs($this->user)
        ->get('/avana/saya/perjalanan-dinas?status=makan-siang')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('travels', 1)
            ->where('status', null)
        );
});

it('keeps an open-ended event from an earlier month out of this month', function (): void {
    CalendarEvent::query()->delete();

    CalendarEvent::create([
        'tenant_id' => $this->tenantId,
        'title' => 'Agenda Bulan Lalu',
        'type' => 'event',
        'start_date' => now()->subMonth()->startOfMonth()->toDateString(),
        // No end date: a one-day event, and it belongs to last month only.
        'end_date' => null,
    ]);

    CalendarEvent::create([
        'tenant_id' => $this->tenantId,
        'title' => 'Agenda Bulan Ini',
        'type' => 'event',
        'start_date' => now()->startOfMonth()->addDays(2)->toDateString(),
        'end_date' => null,
    ]);

    $this->actingAs($this->user)
        ->get('/avana/saya/kalender')
        ->assertOk()
        ->assertInertia(function ($page) {
            $props = $page->toArray()['props'];
            $titles = collect([...$props['upcoming'], ...$props['past']])->pluck('title');

            expect($titles)
                ->toContain('Agenda Bulan Ini')
                ->not->toContain('Agenda Bulan Lalu');

            return $page;
        });
});

it('shows company, department, and personal events but not another department', function (): void {
    // The calendar loads one month at a time, so pin today to mid-month:
    // otherwise the events seeded a few days out land in the next month and
    // the assertions fail purely on when the suite happens to run.
    $this->travelTo(Carbon::create(2026, 6, 10, 9));

    CalendarEvent::query()->delete();

    $otherDepartment = Employee::forTenant($this->tenantId)
        ->whereNotNull('department_id')
        ->where('department_id', '!=', $this->employee->department_id)
        ->value('department_id');

    CalendarEvent::create([
        'tenant_id' => $this->tenantId,
        'title' => 'Agenda Perusahaan',
        'type' => 'event',
        'start_date' => now()->addDays(2)->toDateString(),
        'end_date' => now()->addDays(2)->toDateString(),
    ]);

    CalendarEvent::create([
        'tenant_id' => $this->tenantId,
        'title' => 'Agenda Departemen',
        'type' => 'meeting',
        'department_id' => $this->employee->department_id,
        'start_date' => now()->addDays(3)->toDateString(),
        'end_date' => now()->addDays(3)->toDateString(),
    ]);

    CalendarEvent::create([
        'tenant_id' => $this->tenantId,
        'title' => 'Agenda Pribadi',
        'type' => 'deadline',
        'employee_id' => $this->employee->id,
        'start_date' => now()->addDays(4)->toDateString(),
        'end_date' => now()->addDays(4)->toDateString(),
    ]);

    if ($otherDepartment !== null) {
        CalendarEvent::create([
            'tenant_id' => $this->tenantId,
            'title' => 'Agenda Departemen Lain',
            'type' => 'meeting',
            'department_id' => $otherDepartment,
            'start_date' => now()->addDays(5)->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
        ]);
    }

    $this->actingAs($this->user)
        ->get('/avana/saya/kalender')
        ->assertOk()
        ->assertInertia(function ($page) {
            $titles = collect($page->toArray()['props']['upcoming'])->pluck('title');

            expect($titles)
                ->toContain('Agenda Perusahaan', 'Agenda Departemen', 'Agenda Pribadi')
                ->not->toContain('Agenda Departemen Lain');

            return $page;
        });
});

it('lists only the signed-in employee field visit tasks', function (): void {
    FieldVisit::query()->delete();

    $mine = FieldVisit::create([
        'tenant_id' => $this->tenantId,
        'employee_id' => $this->employee->id,
        'visit_date' => now()->subDay()->toDateString(),
        'location' => 'Jakarta',
        'client_name' => 'PT Uji',
        'status' => 'submitted',
    ]);

    $mine->tasks()->createMany([
        ['tenant_id' => $this->tenantId, 'title' => 'Tugas selesai', 'is_done' => true, 'sort_order' => 1],
        ['tenant_id' => $this->tenantId, 'title' => 'Tugas belum', 'is_done' => false, 'sort_order' => 2],
    ]);

    FieldVisit::create([
        'tenant_id' => $this->tenantId,
        'employee_id' => $this->colleague->id,
        'visit_date' => now()->subDay()->toDateString(),
        'location' => 'Bandung',
        'status' => 'submitted',
    ]);

    $this->actingAs($this->user)
        ->get('/avana/saya/tugas')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('avana/saya/tugas')
            ->has('visits', 1)
            ->where('visits.0.client_name', 'PT Uji')
            ->where('visits.0.status_label', 'Dilaporkan')
            ->has('visits.0.tasks', 2)
            ->where('visits.0.percent', 50)
            ->where('summary.open', 1)
        );
});

it('refuses all five screens to an account with no employee record', function (string $path): void {
    $this->actingAs(User::where('email', 'rina.a@nusantara.co.id')->firstOrFail())
        ->get($path)
        ->assertForbidden();
})->with([
    '/avana/saya/kalender',
    '/avana/saya/tugas',
    '/avana/saya/pembelajaran',
    '/avana/saya/benefit',
    '/avana/saya/perjalanan-dinas',
]);
