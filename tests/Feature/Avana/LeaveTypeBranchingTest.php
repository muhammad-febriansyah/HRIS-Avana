<?php

use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Tenant;
use App\Models\User;
use App\Services\LeaveApproval;
use App\Services\LeaveQuota;
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
 * Build an "annual leave" root with the given sub-types, mirroring what the
 * form posts.
 *
 * @param  array<int, array<string, mixed>>  $children
 */
function branchedType(int $tenantId, int $quota, array $children): LeaveType
{
    $parent = LeaveType::create([
        'tenant_id' => $tenantId,
        'code' => 'BR-TAHUNAN',
        'name' => 'Cuti Tahunan Bercabang',
        'default_quota' => $quota,
        'allow_negative' => false,
        'requires_attachment' => false,
        'status' => 'active',
    ]);

    foreach ($children as $child) {
        LeaveType::create(array_merge([
            'tenant_id' => $tenantId,
            'parent_id' => $parent->id,
            'default_quota' => 0,
            'status' => 'active',
        ], $child));
    }

    return $parent->fresh();
}

it('creates a leave type together with its sub-types', function (): void {
    actingAs($this->admin)
        ->post(route('avana.cuti.jenis.store'), [
            'code' => 'TAHUNAN-2',
            'name' => 'Cuti Tahunan',
            'default_quota' => 12,
            'status' => 'active',
            'allow_negative' => false,
            'requires_attachment' => false,
            'children' => [
                ['code' => 'REGULER', 'name' => 'Reguler', 'sub_limit' => null],
                ['code' => 'BERSAMA', 'name' => 'Cuti Bersama', 'sub_limit' => 3],
                [
                    'code' => 'SAKIT-BAYAR',
                    'name' => 'Sakit Berbayar',
                    'sub_limit' => 2,
                    'requires_attachment' => 'yes',
                ],
            ],
        ])
        ->assertRedirect(route('avana.cuti.jenis'))
        ->assertSessionHas('success');

    $parent = LeaveType::where('code', 'TAHUNAN-2')->firstOrFail();

    expect($parent->children)->toHaveCount(3);
    expect($parent->default_quota)->toBe(12);

    $bersama = $parent->children->firstWhere('code', 'BERSAMA');
    expect($bersama->sub_limit)->toBe(3);
    expect($bersama->default_quota)->toBe(0);
    // Left unset, so it defers to the parent.
    expect($bersama->allow_negative)->toBeNull();
    expect($bersama->effectiveAllowNegative())->toBeFalse();

    $sakit = $parent->children->firstWhere('code', 'SAKIT-BAYAR');
    expect($sakit->requires_attachment)->toBeTrue();
    expect($sakit->effectiveRequiresAttachment())->toBeTrue();
});

it('rejects a sub limit larger than the parent quota', function (): void {
    actingAs($this->admin)
        ->post(route('avana.cuti.jenis.store'), [
            'code' => 'TAHUNAN-3',
            'name' => 'Cuti Tahunan',
            'default_quota' => 12,
            'status' => 'active',
            'children' => [
                ['code' => 'KEBANYAKAN', 'name' => 'Kebanyakan', 'sub_limit' => 20],
            ],
        ])
        ->assertSessionHasErrors('children.0.sub_limit');

    expect(LeaveType::where('code', 'TAHUNAN-3')->exists())->toBeFalse();
});

it('rejects duplicate sub-type codes within the same branch', function (): void {
    actingAs($this->admin)
        ->post(route('avana.cuti.jenis.store'), [
            'code' => 'TAHUNAN-4',
            'name' => 'Cuti Tahunan',
            'default_quota' => 12,
            'status' => 'active',
            'children' => [
                ['code' => 'SAMA', 'name' => 'Satu'],
                ['code' => 'SAMA', 'name' => 'Dua'],
            ],
        ])
        ->assertSessionHasErrors('children.1.code');
});

it('adds, updates, and drops sub-types on edit', function (): void {
    $parent = branchedType($this->tenant->id, 12, [
        ['code' => 'A1', 'name' => 'Awal', 'sub_limit' => 2],
        ['code' => 'A2', 'name' => 'Dibuang', 'sub_limit' => 1],
    ]);

    $kept = $parent->children->firstWhere('code', 'A1');

    actingAs($this->admin)
        ->put(route('avana.cuti.jenis.update', $parent), [
            'code' => $parent->code,
            'name' => $parent->name,
            'default_quota' => 12,
            'status' => 'active',
            'children' => [
                ['id' => $kept->id, 'code' => 'A1', 'name' => 'Diubah', 'sub_limit' => 5],
                ['code' => 'A3', 'name' => 'Baru', 'sub_limit' => null],
            ],
        ])
        ->assertRedirect(route('avana.cuti.jenis'))
        ->assertSessionHas('success');

    $parent->refresh()->load('children');
    $codes = $parent->children->pluck('code')->sort()->values()->all();

    expect($codes)->toBe(['A1', 'A3']);
    expect($parent->children->firstWhere('code', 'A1')->name)->toBe('Diubah');
    expect($parent->children->firstWhere('code', 'A1')->sub_limit)->toBe(5);
    expect(LeaveType::where('code', 'A2')->exists())->toBeFalse();
});

it('deletes the sub-types along with their parent', function (): void {
    $parent = branchedType($this->tenant->id, 12, [
        ['code' => 'D1', 'name' => 'Satu'],
        ['code' => 'D2', 'name' => 'Dua'],
    ]);

    actingAs($this->admin)
        ->delete(route('avana.cuti.jenis.destroy', $parent))
        ->assertSessionHas('success');

    expect(LeaveType::find($parent->id))->toBeNull();
    expect(LeaveType::whereIn('code', ['D1', 'D2'])->count())->toBe(0);
});

it('redirects a sub-type edit link to its parent', function (): void {
    $parent = branchedType($this->tenant->id, 12, [
        ['code' => 'R1', 'name' => 'Satu'],
    ]);

    actingAs($this->admin)
        ->get(route('avana.cuti.jenis.edit', $parent->children->first()))
        ->assertRedirect(route('avana.cuti.jenis.edit', $parent->id));
});

it('nests sub-types under their parent on the index page', function (): void {
    branchedType($this->tenant->id, 12, [
        ['code' => 'N1', 'name' => 'Nested'],
    ]);

    actingAs($this->admin)
        ->get(route('avana.cuti.jenis'))
        ->assertOk()
        ->assertInertia(function (Assert $page): void {
            $rows = collect($page->toArray()['props']['leaveTypes']);

            // Sub-types never appear as top-level rows.
            expect($rows->pluck('code'))->not->toContain('N1');

            $parent = $rows->firstWhere('code', 'BR-TAHUNAN');
            expect($parent['children'])->toHaveCount(1);
            expect($parent['children'][0]['code'])->toBe('N1');
        });
});

it('draws a sub-type request down from the parent balance', function (): void {
    $parent = branchedType($this->tenant->id, 12, [
        ['code' => 'S1', 'name' => 'Cuti Bersama', 'sub_limit' => 3],
    ]);
    $sub = $parent->children->first();

    $employee = Employee::forTenant($this->tenant->id)->firstOrFail();

    $balance = LeaveBalance::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $employee->id,
        'leave_type_id' => $parent->id,
        'year' => now()->year,
        'quota' => 12,
        'used' => 0,
        'remaining' => 12,
    ]);

    $leave = LeaveRequest::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $employee->id,
        'branch_id' => $employee->branch_id,
        'leave_type_id' => $sub->id,
        'start_date' => now()->startOfYear()->addMonth()->toDateString(),
        'end_date' => now()->startOfYear()->addMonth()->addDays(1)->toDateString(),
        'total_days' => 2,
        'status' => 'pending',
    ]);

    LeaveApproval::finalize($leave);

    $balance->refresh();

    expect((float) $balance->used)->toBe(2.0);
    expect((float) $balance->remaining)->toBe(10.0);
});

it('blocks a sub-type request beyond its own yearly cap', function (): void {
    $parent = branchedType($this->tenant->id, 12, [
        ['code' => 'C1', 'name' => 'Cuti Bersama', 'sub_limit' => 3],
    ]);
    $sub = $parent->children->first()->load('parent');

    $employee = Employee::forTenant($this->tenant->id)->firstOrFail();
    $year = now()->year;

    LeaveBalance::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $employee->id,
        'leave_type_id' => $parent->id,
        'year' => $year,
        'quota' => 12,
        'used' => 0,
        'remaining' => 12,
    ]);

    // Within the cap: allowed even though it eats into the shared pool.
    expect(LeaveQuota::check($employee->id, $sub, 3.0, $year))->toBeNull();

    // Over the cap, while the parent still has 12 days left.
    $message = LeaveQuota::check($employee->id, $sub, 4.0, $year);

    expect($message)->toContain('batas 3 hari');
});

it('counts days already taken against the sub cap', function (): void {
    $parent = branchedType($this->tenant->id, 12, [
        ['code' => 'C2', 'name' => 'Cuti Bersama', 'sub_limit' => 3],
    ]);
    $sub = $parent->children->first()->load('parent');

    $employee = Employee::forTenant($this->tenant->id)->firstOrFail();
    $year = now()->year;

    LeaveRequest::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $employee->id,
        'branch_id' => $employee->branch_id,
        'leave_type_id' => $sub->id,
        'start_date' => now()->startOfYear()->addMonth()->toDateString(),
        'end_date' => now()->startOfYear()->addMonth()->addDays(1)->toDateString(),
        'total_days' => 2,
        'status' => 'approved',
    ]);

    expect(LeaveQuota::subRemaining($employee->id, $sub, $year))->toBe(1.0);
    expect(LeaveQuota::check($employee->id, $sub, 2.0, $year))->toContain('tinggal 1 hari');
    expect(LeaveQuota::check($employee->id, $sub, 1.0, $year))->toBeNull();
});

it('still blocks on the parent balance when the sub has no cap', function (): void {
    $parent = branchedType($this->tenant->id, 12, [
        ['code' => 'C3', 'name' => 'Reguler', 'sub_limit' => null],
    ]);
    $sub = $parent->children->first()->load('parent');

    $employee = Employee::forTenant($this->tenant->id)->firstOrFail();
    $year = now()->year;

    LeaveBalance::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $employee->id,
        'leave_type_id' => $parent->id,
        'year' => $year,
        'quota' => 12,
        'used' => 10,
        'remaining' => 2,
    ]);

    expect(LeaveQuota::check($employee->id, $sub, 2.0, $year))->toBeNull();
    expect(LeaveQuota::check($employee->id, $sub, 3.0, $year))
        ->toContain('Cuti Tahunan Bercabang');
});

it('refuses a request booked against a branched root', function (): void {
    $parent = branchedType($this->tenant->id, 12, [
        ['code' => 'C4', 'name' => 'Reguler'],
    ]);

    $employee = Employee::forTenant($this->tenant->id)->firstOrFail();

    expect(LeaveQuota::check($employee->id, $parent, 1.0, now()->year))
        ->toContain('Pilih sub-jenis');
});

it('offers sub-types instead of their parent in the leave picker', function (): void {
    branchedType($this->tenant->id, 12, [
        ['code' => 'P1', 'name' => 'Cuti Bersama', 'sub_limit' => 3],
    ]);

    $tree = LeaveType::selectableTree($this->tenant->id);

    $branched = collect($tree)->firstWhere('name', 'Cuti Tahunan Bercabang');

    expect($branched['selectable'])->toBeFalse();
    expect($branched['children'])->toHaveCount(1);
    expect($branched['children'][0]['sub_limit'])->toBe(3);

    // A type with no sub-types stays directly selectable.
    $flat = collect($tree)->first(fn (array $row): bool => $row['children'] === []);
    expect($flat['selectable'])->toBeTrue();
});
