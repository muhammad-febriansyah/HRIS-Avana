<?php

use App\Models\ApprovalWorkflow;
use App\Models\Department;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(AvanaDemoSeeder::class);
    $this->admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
});

/**
 * Walk the wizard as far as "Assign Approver" with two levels configured, the
 * second one a different approver type so a reorder is visible in the markup.
 */
function approverStepWithTwoLevels(): object
{
    $page = visit('/avana/approval-workflow');

    return $page->click('Buat Workflow Baru')
        ->click('text="Cuti (Leave)"')
        ->click('Lanjut')
        ->click('Lanjut')
        ->assertSee('Konfigurasi Approver')
        ->click('Tambah Step')
        ->select('select[name=approver_type_1]', 'role')
        ->assertValue('select[name=approver_type_0]', 'direct_manager')
        ->assertValue('select[name=approver_type_1]', 'role');
}

it('moves a step down the approval chain', function () {
    // The chain used to be append-only: a flow entered upside down (the VP
    // first, the direct manager last) could only be fixed by deleting every
    // step and retyping it — which is how a live tenant ended up routing leave
    // to the Vice President before the requester's own manager.
    actingAs($this->admin);

    approverStepWithTwoLevels()
        ->click('button[aria-label="Turunkan step 1"]')
        ->assertValue('select[name=approver_type_0]', 'role')
        ->assertValue('select[name=approver_type_1]', 'direct_manager')
        ->assertNoJavascriptErrors();
});

it('moves a step up the approval chain', function () {
    actingAs($this->admin);

    approverStepWithTwoLevels()
        ->click('button[aria-label="Naikkan step 2"]')
        ->assertValue('select[name=approver_type_0]', 'role')
        ->assertValue('select[name=approver_type_1]', 'direct_manager')
        ->assertNoJavascriptErrors();
});

/**
 * The tenant-wide leave flow, the one every division falls back to.
 */
function seedDefaultLeaveFlow(int $tenantId): ApprovalWorkflow
{
    $workflow = ApprovalWorkflow::create([
        'tenant_id' => $tenantId,
        'name' => 'Cuti (Leave)',
        'request_type' => 'leave',
        'department_id' => null,
        'approval_mode' => 'sequential',
        'is_active' => true,
        'conditions' => [],
    ]);

    $workflow->steps()->create([
        'tenant_id' => $tenantId,
        'step_order' => 1,
        'approver_type' => 'direct_manager',
    ]);

    return $workflow;
}

it('warns that re-scoping the default flow strands the other divisions', function () {
    // Picking a division here MOVES the module's only flow instead of adding
    // an exception, and the divisions left behind fall back to single-step
    // manager routing without anything on screen saying so.
    actingAs($this->admin);

    seedDefaultLeaveFlow($this->admin->tenant_id);
    $department = Department::forTenant($this->admin->tenant_id)->firstOrFail();

    $page = visit('/avana/approval-workflow');

    $page->click('Edit')
        ->assertSee('Berlaku untuk Divisi')
        ->assertDontSee('Alur default akan dipindah')
        ->select('select[name=department_id]', (string) $department->id)
        ->assertSee('Alur default akan dipindah, bukan ditambah')
        ->assertSee($department->name)
        ->select('select[name=department_id]', '')
        ->assertDontSee('Alur default akan dipindah')
        ->assertNoJavascriptErrors();
});

it('does not warn while creating a division-scoped flow from scratch', function () {
    // A brand new scoped flow takes nothing away from anyone, so the warning
    // would be noise.
    actingAs($this->admin);

    $department = Department::forTenant($this->admin->tenant_id)->firstOrFail();

    $page = visit('/avana/approval-workflow');

    $page->click('Buat Workflow Baru')
        ->select('select[name=department_id]', (string) $department->id)
        ->assertDontSee('Alur default akan dipindah')
        ->assertNoJavascriptErrors();
});

it('cannot move the first step up or the last step down', function () {
    actingAs($this->admin);

    approverStepWithTwoLevels()
        ->assertDisabled('button[aria-label="Naikkan step 1"]')
        ->assertDisabled('button[aria-label="Turunkan step 2"]')
        ->assertEnabled('button[aria-label="Turunkan step 1"]')
        ->assertEnabled('button[aria-label="Naikkan step 2"]');
});
