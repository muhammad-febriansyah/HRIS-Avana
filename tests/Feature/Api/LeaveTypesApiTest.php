<?php

use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;

/**
 * Contract for `/api/v1/me/leave-types`, which the Flutter picker parses into
 * its grouped dropdown. The tree shape and the `selectable` flag are what stop
 * the app from submitting against a branched root.
 */
beforeEach(function (): void {
    $this->seed(AvanaDemoSeeder::class);

    $this->token = $this->postJson('/api/v1/auth/login', [
        'email' => 'bagus.p@nusantara.co.id',
        'password' => 'password',
    ])->json('access_token');

    $this->auth = function () {
        $this->app['auth']->forgetGuards();

        return $this->withHeader('Authorization', 'Bearer '.$this->token);
    };

    $this->me = User::where('email', 'bagus.p@nusantara.co.id')->firstOrFail()->employee;
    $this->tenantId = $this->me->tenant_id;
});

/**
 * An annual-leave root with one capped and one uncapped sub-type.
 */
function apiBranchedType(int $tenantId): LeaveType
{
    $parent = LeaveType::create([
        'tenant_id' => $tenantId,
        'code' => 'API-TAHUNAN',
        'name' => 'Cuti Tahunan API',
        'default_quota' => 12,
        'allow_negative' => false,
        'requires_attachment' => false,
        'status' => 'active',
    ]);

    LeaveType::create([
        'tenant_id' => $tenantId,
        'parent_id' => $parent->id,
        'code' => 'API-BERSAMA',
        'name' => 'Cuti Bersama',
        'default_quota' => 0,
        'sub_limit' => 3,
        'status' => 'active',
    ]);

    LeaveType::create([
        'tenant_id' => $tenantId,
        'parent_id' => $parent->id,
        'code' => 'API-REGULER',
        'name' => 'Reguler',
        'default_quota' => 0,
        'sub_limit' => null,
        'status' => 'active',
    ]);

    return $parent->fresh();
}

it('returns leave types as a tree with the fields the app reads', function (): void {
    apiBranchedType($this->tenantId);

    $response = ($this->auth)()->getJson('/api/v1/me/leave-types')->assertOk();

    $rows = collect($response->json('data'));
    $branched = $rows->firstWhere('code', 'API-TAHUNAN');

    expect($branched)->not->toBeNull();
    expect($branched['selectable'])->toBeFalse();
    expect($branched['default_quota'])->toBe(12);
    expect($branched['children'])->toHaveCount(2);

    $bersama = collect($branched['children'])->firstWhere('code', 'API-BERSAMA');
    expect($bersama['sub_limit'])->toBe(3);
    expect($bersama['name'])->toBe('Cuti Bersama');

    $reguler = collect($branched['children'])->firstWhere('code', 'API-REGULER');
    expect($reguler['sub_limit'])->toBeNull();
});

it('keeps an unbranched type selectable with no children', function (): void {
    $response = ($this->auth)()->getJson('/api/v1/me/leave-types')->assertOk();

    $flat = collect($response->json('data'))
        ->first(fn (array $row): bool => $row['children'] === []);

    expect($flat['selectable'])->toBeTrue();
    expect($flat['code'])->not->toBeNull();
});

it('never lists a sub-type as a top-level entry', function (): void {
    apiBranchedType($this->tenantId);

    $codes = collect(($this->auth)()->getJson('/api/v1/me/leave-types')->json('data'))
        ->pluck('code');

    expect($codes)->not->toContain('API-BERSAMA');
    expect($codes)->not->toContain('API-REGULER');
});

it('submits a sub-type request and draws it from the parent balance', function (): void {
    $parent = apiBranchedType($this->tenantId);
    $sub = $parent->children->firstWhere('code', 'API-BERSAMA');

    LeaveBalance::create([
        'tenant_id' => $this->tenantId,
        'employee_id' => $this->me->id,
        'leave_type_id' => $parent->id,
        'year' => now()->year,
        'quota' => 12,
        'used' => 0,
        'remaining' => 12,
    ]);

    ($this->auth)()->postJson('/api/v1/me/leave-requests', [
        'leave_type_id' => $sub->id,
        'start_date' => now()->addMonth()->toDateString(),
        'end_date' => now()->addMonth()->addDays(1)->toDateString(),
        'reason' => 'Libur bersama',
    ])->assertCreated();

    $this->assertDatabaseHas('leave_requests', [
        'employee_id' => $this->me->id,
        'leave_type_id' => $sub->id,
        'total_days' => 2,
    ]);
});

it('rejects a sub-type request beyond its cap with a readable message', function (): void {
    $parent = apiBranchedType($this->tenantId);
    $sub = $parent->children->firstWhere('code', 'API-BERSAMA');

    LeaveBalance::create([
        'tenant_id' => $this->tenantId,
        'employee_id' => $this->me->id,
        'leave_type_id' => $parent->id,
        'year' => now()->year,
        'quota' => 12,
        'used' => 0,
        'remaining' => 12,
    ]);

    // Four days against a three-day cap, while the parent still has all 12.
    ($this->auth)()->postJson('/api/v1/me/leave-requests', [
        'leave_type_id' => $sub->id,
        'start_date' => now()->addMonth()->toDateString(),
        'end_date' => now()->addMonth()->addDays(3)->toDateString(),
    ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'Jatah Cuti Bersama tinggal 3 hari dari batas 3 hari per tahun.');
});

it('refuses a request booked straight against a branched root', function (): void {
    $parent = apiBranchedType($this->tenantId);

    ($this->auth)()->postJson('/api/v1/me/leave-requests', [
        'leave_type_id' => $parent->id,
        'start_date' => now()->addMonth()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
    ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'Pilih sub-jenis dari Cuti Tahunan API.');

    $this->assertDatabaseMissing('leave_requests', [
        'employee_id' => $this->me->id,
        'leave_type_id' => $parent->id,
    ]);
});
