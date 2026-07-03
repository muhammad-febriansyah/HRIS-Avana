<?php

use App\Models\Employee;
use App\Models\MoodCheckin;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
});

it('renders the mood page for HR with a breakdown and employee rows', function (): void {
    $employee = Employee::forTenant($this->admin->tenant_id)->firstOrFail();
    MoodCheckin::create([
        'tenant_id' => $this->admin->tenant_id,
        'employee_id' => $employee->id,
        'mood' => 'baik',
        'date' => now()->toDateString(),
    ]);

    actingAs($this->admin)->get('/avana/mood')
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('avana/mood/index')
            ->where('summary.total', 1)
            ->where('summary.index', 80)
            ->has('summary.breakdown', 5)
            ->has('employees', 1)
            ->where('employees.0.mood', 'baik')
            ->has('trend', 7));
});

it('forbids a non-HR employee from the mood page', function (): void {
    $karyawan = User::where('email', 'bagus.p@nusantara.co.id')->firstOrFail();

    actingAs($karyawan)->get('/avana/mood')->assertForbidden();
});
