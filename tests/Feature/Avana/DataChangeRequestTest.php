<?php

use App\Models\ApprovalRequest;
use App\Models\ApprovalStep;
use App\Models\ApprovalWorkflow;
use App\Models\DataChangeRequest;
use App\Models\Employee;
use App\Models\EmployeeBankAccount;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->hr = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->hr->tenant_id);

    $this->staff = Employee::forTenant($this->tenant->id)
        ->where('is_top_approver', false)
        ->whereNotNull('user_id')
        ->whereNotNull('manager_id')
        ->whereHas('manager', fn ($query) => $query->whereNotNull('user_id'))
        ->firstOrFail();

    $this->manager = Employee::forTenant($this->tenant->id)->findOrFail($this->staff->manager_id);
});

it('files a change request without touching the employee record yet', function (): void {
    $phoneBefore = $this->staff->phone;

    actingAs($this->staff->user)
        ->post(route('avana.saya.perubahan-data.store'), [
            'changes' => [
                ['field' => 'phone', 'value' => '081200001111'],
                ['field' => 'address', 'value' => 'Jl. Baru No. 7, Bandung'],
            ],
            'reason' => 'Pindah kos dan ganti nomor',
        ])
        ->assertRedirect(route('avana.saya.perubahan-data'));

    $request = DataChangeRequest::where('employee_id', $this->staff->id)->latest('id')->firstOrFail();

    expect($request->status)->toBe('pending');
    expect($request->changes['phone']['new'])->toBe('081200001111');
    expect($request->changes['phone']['old'])->toBe($phoneBefore);
    expect((int) $request->current_approver_id)->toBe((int) $this->manager->id);

    // Still the old value: the request has not been decided.
    expect($this->staff->fresh()->phone)->toBe($phoneBefore);
});

it('writes the new values onto the employee once approved', function (): void {
    actingAs($this->staff->user)
        ->post(route('avana.saya.perubahan-data.store'), [
            'changes' => [['field' => 'phone', 'value' => '081200002222']],
            'reason' => 'Ganti nomor',
        ])
        ->assertRedirect();

    $request = DataChangeRequest::where('employee_id', $this->staff->id)->latest('id')->firstOrFail();

    actingAs($this->hr)
        ->post(route('avana.approval.approve', ['type' => 'data', 'id' => $request->id]))
        ->assertSessionHas('success');

    expect($this->staff->fresh()->phone)->toBe('081200002222');
    expect($request->fresh()->status)->toBe('approved');
    expect($request->fresh()->decided_at)->not->toBeNull();
});

it('leaves the employee record alone when the request is rejected', function (): void {
    $phoneBefore = $this->staff->phone;

    actingAs($this->staff->user)
        ->post(route('avana.saya.perubahan-data.store'), [
            'changes' => [['field' => 'phone', 'value' => '081200003333']],
        ])
        ->assertRedirect();

    $request = DataChangeRequest::where('employee_id', $this->staff->id)->latest('id')->firstOrFail();

    actingAs($this->hr)
        ->post(route('avana.approval.reject', ['type' => 'data', 'id' => $request->id]))
        ->assertSessionHas('success');

    expect($request->fresh()->status)->toBe('rejected');
    expect($this->staff->fresh()->phone)->toBe($phoneBefore);
});

it('creates the bank account row when an approved change is the first one', function (): void {
    EmployeeBankAccount::where('employee_id', $this->staff->id)->delete();

    actingAs($this->staff->user)
        ->post(route('avana.saya.perubahan-data.store'), [
            'changes' => [
                ['field' => 'bank_name', 'value' => 'BCA'],
                ['field' => 'bank_account_number', 'value' => '1234567890'],
            ],
            'reason' => 'Rekening lama ditutup',
        ])
        ->assertRedirect();

    $request = DataChangeRequest::where('employee_id', $this->staff->id)->latest('id')->firstOrFail();

    actingAs($this->hr)
        ->post(route('avana.approval.approve', ['type' => 'data', 'id' => $request->id]))
        ->assertSessionHas('success');

    $account = EmployeeBankAccount::where('employee_id', $this->staff->id)->firstOrFail();

    expect($account->bank_name)->toBe('BCA');
    expect($account->account_number)->toBe('1234567890');
});

it('refuses a field the employee does not own', function (): void {
    actingAs($this->staff->user)
        ->post(route('avana.saya.perubahan-data.store'), [
            'changes' => [['field' => 'salary_master_id', 'value' => '99']],
        ])
        ->assertSessionHasErrors('changes.0.field');

    expect(DataChangeRequest::where('employee_id', $this->staff->id)->count())->toBe(0);
});

it('refuses a value its own field rules reject', function (): void {
    actingAs($this->staff->user)
        ->post(route('avana.saya.perubahan-data.store'), [
            'changes' => [['field' => 'nik', 'value' => '123']],
        ])
        ->assertSessionHasErrors('nik');

    expect(DataChangeRequest::where('employee_id', $this->staff->id)->count())->toBe(0);
});

it('refuses a marital status outside the fixed list', function (): void {
    actingAs($this->staff->user)
        ->post(route('avana.saya.perubahan-data.store'), [
            'changes' => [['field' => 'marital_status', 'value' => 'kawin siri']],
        ])
        ->assertSessionHasErrors('marital_status');

    expect(DataChangeRequest::where('employee_id', $this->staff->id)->count())->toBe(0);
});

it('refuses a request that changes nothing', function (): void {
    actingAs($this->staff->user)
        ->post(route('avana.saya.perubahan-data.store'), [
            'changes' => [['field' => 'phone', 'value' => $this->staff->phone]],
        ])
        ->assertSessionHasErrors('changes');

    expect(DataChangeRequest::where('employee_id', $this->staff->id)->count())->toBe(0);
});

it('runs the data change through the workflow the wizard offers', function (): void {
    $workflow = ApprovalWorkflow::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Perubahan Data: Atasan lalu HC',
        'request_type' => 'data_change',
        'approval_mode' => 'sequential',
        'is_active' => true,
    ]);

    ApprovalStep::create([
        'tenant_id' => $this->tenant->id,
        'approval_workflow_id' => $workflow->id,
        'step_order' => 1,
        'approver_type' => 'direct_manager',
    ]);

    $approver = Employee::forTenant($this->tenant->id)
        ->whereNotNull('user_id')
        ->where('id', '!=', $this->manager->id)
        ->where('id', '!=', $this->staff->id)
        ->where('is_top_approver', false)
        ->whereDoesntHave('subordinates')
        ->whereDoesntHave('user.roles', fn ($query) => $query->whereIn('code', ['super_admin', 'admin_tenant_hr', 'manager']))
        ->firstOrFail();

    ApprovalStep::create([
        'tenant_id' => $this->tenant->id,
        'approval_workflow_id' => $workflow->id,
        'step_order' => 2,
        'approver_type' => 'specific_user',
        'approver_user_id' => $approver->id,
    ]);

    actingAs($this->staff->user)
        ->post(route('avana.saya.perubahan-data.store'), [
            'changes' => [['field' => 'address', 'value' => 'Jl. Melati No. 12, Depok']],
            'reason' => 'Pindah rumah',
        ])
        ->assertRedirect();

    $request = DataChangeRequest::where('employee_id', $this->staff->id)->latest('id')->firstOrFail();

    expect(ApprovalRequest::where('approvable_type', DataChangeRequest::class)->where('approvable_id', $request->id)->exists())
        ->toBeTrue();

    actingAs($this->manager->user)
        ->post(route('avana.approval.approve', ['type' => 'data', 'id' => $request->id]))
        ->assertRedirect();

    // Step 1 only advances it; the employee record is untouched so far.
    expect($request->fresh()->status)->toBe('pending');
    expect((int) $request->fresh()->current_approver_id)->toBe((int) $approver->id);
    expect($this->staff->fresh()->address)->not->toBe('Jl. Melati No. 12, Depok');

    actingAs($approver->user)
        ->post(route('avana.approval.approve', ['type' => 'data', 'id' => $request->id]))
        ->assertRedirect();

    expect($request->fresh()->status)->toBe('approved');
    expect($this->staff->fresh()->address)->toBe('Jl. Melati No. 12, Depok');
});
