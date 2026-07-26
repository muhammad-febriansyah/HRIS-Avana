<?php

use App\Models\LeaveType;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;

beforeEach(function (): void {
    $this->seed(AvanaDemoSeeder::class);

    $this->admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
});

it('stores the annual quota when creating a leave type', function (): void {
    $this->actingAs($this->admin)
        ->post(route('avana.cuti.jenis.store'), [
            'code' => 'UNPAID',
            'name' => 'Cuti Tanpa Gaji',
            'default_quota' => 24,
            'allow_negative' => false,
            'requires_attachment' => false,
            'status' => 'active',
        ])
        ->assertRedirect(route('avana.cuti.jenis'));

    $leaveType = LeaveType::forTenant($this->admin->tenant_id)
        ->where('code', 'UNPAID')
        ->firstOrFail();

    expect($leaveType->default_quota)->toBe(24);
});

it('updates the annual quota of an existing leave type', function (): void {
    $leaveType = LeaveType::forTenant($this->admin->tenant_id)
        ->where('code', 'PENTING')
        ->firstOrFail();

    $this->actingAs($this->admin)
        ->put(route('avana.cuti.jenis.update', $leaveType), [
            'code' => $leaveType->code,
            'name' => $leaveType->name,
            'default_quota' => 12,
            'allow_negative' => false,
            'requires_attachment' => false,
            'status' => 'active',
        ])
        ->assertRedirect(route('avana.cuti.jenis'));

    expect($leaveType->refresh()->default_quota)->toBe(12);
});

it('rejects a quota that is not a whole number of days within range', function (mixed $quota): void {
    $this->actingAs($this->admin)
        ->post(route('avana.cuti.jenis.store'), [
            'code' => 'INVALID',
            'name' => 'Cuti Invalid',
            'default_quota' => $quota,
            'allow_negative' => false,
            'requires_attachment' => false,
            'status' => 'active',
        ])
        ->assertSessionHasErrors('default_quota');

    expect(LeaveType::forTenant($this->admin->tenant_id)->where('code', 'INVALID')->exists())->toBeFalse();
})->with([
    'missing' => [null],
    'negative' => [-1],
    'above the yearly maximum' => [366],
    'not numeric' => ['dua belas'],
]);

it('exposes the quota to the leave type list and edit pages', function (): void {
    $leaveType = LeaveType::forTenant($this->admin->tenant_id)
        ->where('code', 'TAHUNAN')
        ->firstOrFail();

    $this->actingAs($this->admin)
        ->get(route('avana.cuti.jenis'))
        ->assertInertia(fn ($page) => $page
            ->component('avana/jenis-cuti/index')
            ->where('leaveTypes.0.default_quota', fn ($quota) => $quota !== null)
        );

    $this->actingAs($this->admin)
        ->get(route('avana.cuti.jenis.edit', $leaveType))
        ->assertInertia(fn ($page) => $page
            ->component('avana/jenis-cuti/edit')
            ->where('leaveType.default_quota', $leaveType->default_quota)
        );
});
