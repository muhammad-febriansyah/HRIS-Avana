<?php

use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\OvertimeRequest;
use App\Models\PermissionRequest;
use App\Models\Reimbursement;
use App\Models\Role;
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
});

/**
 * Create a pending leave request for the seeded tenant.
 */
function makeApprovalLeave(int $tenantId, array $overrides = []): LeaveRequest
{
    $employee = Employee::forTenant($tenantId)->firstOrFail();
    $leaveType = LeaveType::forTenant($tenantId)->firstOrFail();

    return LeaveRequest::create(array_merge([
        'tenant_id' => $tenantId,
        'employee_id' => $employee->id,
        'branch_id' => $employee->branch_id,
        'leave_type_id' => $leaveType->id,
        'start_date' => '2026-07-01',
        'end_date' => '2026-07-03',
        'total_days' => 3,
        'reason' => 'Keperluan keluarga',
        'status' => 'pending',
    ], $overrides));
}

/**
 * Create a pending overtime request for the seeded tenant.
 */
function makeApprovalOvertime(int $tenantId, array $overrides = []): OvertimeRequest
{
    $employee = Employee::forTenant($tenantId)->firstOrFail();

    return OvertimeRequest::create(array_merge([
        'tenant_id' => $tenantId,
        'employee_id' => $employee->id,
        'branch_id' => $employee->branch_id,
        'date' => '2026-07-01',
        'hours' => 3,
        'reason' => 'Deadline proyek',
        'status' => 'pending',
    ], $overrides));
}

/**
 * Create a pending permission (izin) request for the seeded tenant.
 */
function makeApprovalPermission(int $tenantId, array $overrides = []): PermissionRequest
{
    $employee = Employee::forTenant($tenantId)->firstOrFail();

    return PermissionRequest::create(array_merge([
        'tenant_id' => $tenantId,
        'employee_id' => $employee->id,
        'start_date' => '2026-07-01',
        'end_date' => '2026-07-01',
        'type' => 'izin_jam',
        'start_time' => '10:00',
        'end_time' => '12:00',
        'reason' => 'Urusan pribadi',
        'status' => 'pending',
    ], $overrides));
}

/**
 * Create a pending reimbursement claim for the seeded tenant.
 */
function makeApprovalClaim(int $tenantId, array $overrides = []): Reimbursement
{
    $employee = Employee::forTenant($tenantId)->firstOrFail();

    return Reimbursement::create(array_merge([
        'tenant_id' => $tenantId,
        'employee_id' => $employee->id,
        'category' => 'operasional',
        'title' => 'Taksi ke klien',
        'amount' => 250000,
        'expense_date' => '2026-07-01',
        'description' => 'Perjalanan ke kantor klien',
        'status' => 'pending',
    ], $overrides));
}

it('renders the approval center with pending, history and counts props', function (): void {
    makeApprovalLeave($this->tenant->id);

    actingAs($this->admin)
        ->get(route('avana.approval'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/approval/index', false)
            ->has('pending')
            ->has('pending.0', fn (Assert $row) => $row
                ->where('type', 'leave')
                ->has('id')
                ->has('employee.name')
                ->has('employee.initials')
                ->has('employee.avatar_color')
                ->has('title')
                ->has('detail')
                ->has('reason')
                ->has('requested_at')
                ->has('requested_ago')
                ->has('status')
                ->has('status_label'))
            ->has('history')
            ->has('counts'));
});

it('paginates the pending table and reports the page window', function (): void {
    $baseline = actingAs($this->admin)
        ->get(route('avana.approval'))
        ->viewData('page')['props']['pendingMeta']['total'];

    foreach (range(1, 12) as $index) {
        makeApprovalLeave($this->tenant->id, ['reason' => "Pengajuan {$index}"]);
    }

    $total = $baseline + 12;

    $first = actingAs($this->admin)
        ->get(route('avana.approval'))
        ->assertOk()
        ->viewData('page')['props'];

    expect($first['pendingMeta']['total'])->toBe($total);
    expect($first['pendingMeta']['per_page'])->toBe(10);
    expect($first['pendingMeta']['last_page'])->toBe((int) ceil($total / 10));
    expect($first['pending'])->toHaveCount(10);
    expect($first['pendingMeta']['from'])->toBe(1);
    expect($first['pendingMeta']['to'])->toBe(10);

    $second = actingAs($this->admin)
        ->get(route('avana.approval', ['halaman' => 2]))
        ->assertOk()
        ->viewData('page')['props'];

    expect($second['pendingMeta']['current_page'])->toBe(2);
    expect($second['pending'])->toHaveCount(min(10, $total - 10));

    // Page two must not repeat page one — the merge is sorted before slicing.
    $firstKeys = collect($first['pending'])->map(fn (array $row): string => $row['type'].'-'.$row['id']);
    $secondKeys = collect($second['pending'])->map(fn (array $row): string => $row['type'].'-'.$row['id']);

    expect($firstKeys->intersect($secondKeys))->toBeEmpty();
});

it('clamps a page number past the end back to the last page', function (): void {
    makeApprovalLeave($this->tenant->id);

    $props = actingAs($this->admin)
        ->get(route('avana.approval', ['halaman' => 99]))
        ->assertOk()
        ->viewData('page')['props'];

    expect($props['pendingMeta']['current_page'])->toBe($props['pendingMeta']['last_page']);
    expect($props['pending'])->not->toBeEmpty();
});

it('filters the pending table by request type server-side', function (): void {
    makeApprovalLeave($this->tenant->id);
    makeApprovalOvertime($this->tenant->id);

    $props = actingAs($this->admin)
        ->get(route('avana.approval', ['jenis' => 'lembur']))
        ->assertOk()
        ->viewData('page')['props'];

    expect($props['filters']['jenis'])->toBe('lembur');
    expect(collect($props['pending'])->pluck('type')->unique()->all())->toBe(['lembur']);

    // The chips still show every type's count, so the filter is undoable.
    expect($props['counts']['leave'])->toBeGreaterThan(0);
});

it('honours a whitelisted page size and ignores anything else', function (): void {
    foreach (range(1, 12) as $index) {
        makeApprovalLeave($this->tenant->id, ['reason' => "Baris {$index}"]);
    }

    $wide = actingAs($this->admin)
        ->get(route('avana.approval', ['per_page' => 25]))
        ->viewData('page')['props'];

    expect($wide['pendingMeta']['per_page'])->toBe(25);

    $bogus = actingAs($this->admin)
        ->get(route('avana.approval', ['per_page' => 5000]))
        ->viewData('page')['props'];

    expect($bogus['pendingMeta']['per_page'])->toBe(10);
});

it('aggregates pending requests across types with per-type counts', function (): void {
    $baseline = actingAs($this->admin)
        ->get(route('avana.approval'))
        ->assertOk()
        ->viewData('page')['props']['counts'];

    $leave = makeApprovalLeave($this->tenant->id);
    $overtime = makeApprovalOvertime($this->tenant->id);

    actingAs($this->admin)
        ->get(route('avana.approval'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('counts.leave', $baseline['leave'] + 1)
            ->where('counts.lembur', $baseline['lembur'] + 1)
            ->where('counts.total', $baseline['total'] + 2)
            ->where('pending', fn ($pending) => collect($pending)->contains(
                fn ($row) => $row['type'] === 'leave' && $row['id'] === $leave->id,
            ) && collect($pending)->contains(
                fn ($row) => $row['type'] === 'lembur' && $row['id'] === $overtime->id,
            )));
});

it('approves a leave request and decrements the matching balance', function (): void {
    $leave = makeApprovalLeave($this->tenant->id);

    $balance = LeaveBalance::query()
        ->where('employee_id', $leave->employee_id)
        ->where('leave_type_id', $leave->leave_type_id)
        ->where('year', 2026)
        ->firstOrFail();

    $usedBefore = (float) $balance->used;
    $remainingBefore = (float) $balance->remaining;

    actingAs($this->admin)
        ->post(route('avana.approval.approve', ['type' => 'leave', 'id' => $leave->id]))
        ->assertSessionHas('success');

    expect($leave->fresh()->status)->toBe('approved');

    $balance->refresh();
    expect((float) $balance->used)->toBe($usedBefore + 3);
    expect((float) $balance->remaining)->toBe($remainingBefore - 3);
});

it('approves an overtime request', function (): void {
    $overtime = makeApprovalOvertime($this->tenant->id);

    actingAs($this->admin)
        ->post(route('avana.approval.approve', ['type' => 'lembur', 'id' => $overtime->id]))
        ->assertSessionHas('success');

    expect($overtime->fresh()->status)->toBe('approved');
});

it('rejects a permission (izin) request', function (): void {
    $permission = makeApprovalPermission($this->tenant->id);

    actingAs($this->admin)
        ->post(route('avana.approval.reject', ['type' => 'izin', 'id' => $permission->id]))
        ->assertSessionHas('success');

    expect($permission->fresh()->status)->toBe('rejected');
});

it('lists a pending reimbursement claim alongside the other types', function (): void {
    $claim = makeApprovalClaim($this->tenant->id);

    $page = actingAs($this->admin)->get(route('avana.approval'))->assertOk();
    $props = $page->viewData('page')['props'];

    $row = collect($props['pending'])
        ->first(fn (array $item): bool => $item['type'] === 'klaim' && $item['id'] === $claim->id);

    expect($props['counts']['klaim'])->toBe(1);
    expect($row)->not->toBeNull();
    expect($row['title'])->toBe('Taksi ke klien');
    expect($row['detail'])->toBe('01 Jul 2026 · Rp 250.000');
    expect($row['reason'])->toBe('Perjalanan ke kantor klien');
});

it('approves a claim from the approval center and stamps the approver', function (): void {
    $claim = makeApprovalClaim($this->tenant->id);

    actingAs($this->admin)
        ->post(route('avana.approval.approve', ['type' => 'klaim', 'id' => $claim->id]))
        ->assertSessionHas('success');

    $claim->refresh();

    expect($claim->status)->toBe('approved');
    // Finance's four-eyes rule reads this column: the approver cannot also pay.
    expect((int) $claim->approver_id)->toBe((int) $this->admin->id);
    expect($claim->approved_at)->not->toBeNull();
});

it('rejects a claim from the approval center', function (): void {
    $claim = makeApprovalClaim($this->tenant->id);

    actingAs($this->admin)
        ->post(route('avana.approval.reject', ['type' => 'klaim', 'id' => $claim->id]))
        ->assertSessionHas('success');

    expect($claim->fresh()->status)->toBe('rejected');
});

it('keeps a paid claim in history rather than dropping it', function (): void {
    $claim = makeApprovalClaim($this->tenant->id, ['status' => 'paid']);

    $props = actingAs($this->admin)->get(route('avana.approval'))->assertOk()->viewData('page')['props'];
    $row = collect($props['history'])
        ->first(fn (array $item): bool => $item['type'] === 'klaim' && $item['id'] === $claim->id);

    expect($row)->not->toBeNull();
    expect($row['status_label'])->toBe('Dibayar');
});

it('refuses to decide a request that was already processed', function (): void {
    $leave = makeApprovalLeave($this->tenant->id);

    actingAs($this->admin)
        ->post(route('avana.approval.approve', ['type' => 'leave', 'id' => $leave->id]))
        ->assertSessionHas('success');

    $balance = LeaveBalance::query()
        ->where('employee_id', $leave->employee_id)
        ->where('leave_type_id', $leave->leave_type_id)
        ->where('year', 2026)
        ->firstOrFail();

    $usedAfterFirst = (float) $balance->used;

    // A second click on a stale page used to draw the days off twice.
    actingAs($this->admin)
        ->post(route('avana.approval.approve', ['type' => 'leave', 'id' => $leave->id]))
        ->assertStatus(422);

    expect((float) $balance->fresh()->used)->toBe($usedAfterFirst);
});

it('returns 404 for an unknown approval type', function (): void {
    $leave = makeApprovalLeave($this->tenant->id);

    actingAs($this->admin)
        ->post(route('avana.approval.approve', ['type' => 'unknown', 'id' => $leave->id]))
        ->assertNotFound();

    expect($leave->fresh()->status)->toBe('pending');
});

it('returns 404 when approving a request from another tenant', function (): void {
    $otherTenant = Tenant::create(['name' => 'PT Asing', 'slug' => 'pt-asing-approval']);
    $foreignEmployee = Employee::create([
        'tenant_id' => $otherTenant->id,
        'employee_number' => 'EMP-AP-1',
        'full_name' => 'Foreign Worker',
        'employment_status' => 'permanent',
        'status' => 'active',
    ]);
    $foreign = LeaveRequest::create([
        'tenant_id' => $otherTenant->id,
        'employee_id' => $foreignEmployee->id,
        'start_date' => '2026-07-01',
        'end_date' => '2026-07-02',
        'total_days' => 2,
        'status' => 'pending',
    ]);

    actingAs($this->admin)
        ->post(route('avana.approval.approve', ['type' => 'leave', 'id' => $foreign->id]))
        ->assertNotFound();

    expect($foreign->fresh()->status)->toBe('pending');
});

it('forbids an employee-role user from viewing the approval center', function (): void {
    $employeeRole = Role::where('tenant_id', $this->tenant->id)->where('code', 'employee')->firstOrFail();

    $staff = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $staff->roles()->sync([$employeeRole->id]);

    actingAs($staff)
        ->get(route('avana.approval'))
        ->assertForbidden();
});
