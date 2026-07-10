<?php

use App\Models\LeaveType;
use App\Models\SalaryMaster;
use App\Models\SalesOrder;
use App\Models\Shift;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->admin->tenant_id);

    $this->master = SalaryMaster::create([
        'tenant_id' => $this->tenant->id, 'code' => 'MG-SO', 'category' => 'Organik', 'is_active' => true,
    ]);
    $this->shift = Shift::forTenant($this->tenant->id)->first();
    $this->leave = LeaveType::forTenant($this->tenant->id)->first();

    $this->order = SalesOrder::create([
        'tenant_id' => $this->tenant->id,
        'code' => 'SO-TEST-1',
        'client_name' => 'PT Klien Uji',
        'position_name' => 'Teller',
        'headcount' => 4,
        'status' => 'new',
    ]);
});

it('renders the sales order list with mapping options', function (): void {
    actingAs($this->admin)
        ->get(route('avana.payroll.sales-order'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/payroll-sales-order/index', false)
            ->has('orders', 1)
            ->has('masterOptions')
            ->has('shiftOptions')
            ->has('leaveOptions'));
});

it('maps a master gaji, shift and leave type onto a sales order', function (): void {
    actingAs($this->admin)
        ->post(route('avana.payroll.sales-order.map', $this->order), [
            'salary_master_id' => $this->master->id,
            'shift_id' => $this->shift?->id,
            'leave_type_id' => $this->leave?->id,
        ])
        ->assertSessionHas('success');

    $order = $this->order->fresh();
    expect($order->status)->toBe('mapped');
    expect((int) $order->salary_master_id)->toBe($this->master->id);
    expect($order->mapped_at)->not->toBeNull();
});

it('requires a master gaji to map', function (): void {
    actingAs($this->admin)
        ->post(route('avana.payroll.sales-order.map', $this->order), [])
        ->assertSessionHasErrors('salary_master_id');

    expect($this->order->fresh()->status)->toBe('new');
});

it('rejects mapping a master gaji from another tenant', function (): void {
    $other = Tenant::create(['name' => 'PT Lain', 'slug' => 'pt-lain-so']);
    $foreignMaster = SalaryMaster::create(['tenant_id' => $other->id, 'code' => 'MG-X', 'category' => 'Organik']);

    actingAs($this->admin)
        ->post(route('avana.payroll.sales-order.map', $this->order), [
            'salary_master_id' => $foreignMaster->id,
        ])
        ->assertSessionHasErrors('salary_master_id');
});

it('filters sales orders by status', function (): void {
    SalesOrder::create([
        'tenant_id' => $this->tenant->id, 'code' => 'SO-MAPPED', 'client_name' => 'PT Sudah',
        'position_name' => 'CS', 'headcount' => 2, 'status' => 'mapped', 'salary_master_id' => $this->master->id,
    ]);

    actingAs($this->admin)
        ->get(route('avana.payroll.sales-order', ['status' => 'new']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('orders', 1));
});
