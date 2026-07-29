<?php

use App\Http\Controllers\Avana\AccessController;
use App\Models\Employee;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();

    Route::middleware('web')->prefix('__access')->group(function (): void {
        Route::get('/', [AccessController::class, 'index']);
        Route::post('/roles', [AccessController::class, 'storeRole']);
        Route::post('/roles/{role}/mobile', [AccessController::class, 'toggleRoleMobile']);
    });

    $this->seed(AvanaDemoSeeder::class);

    $this->admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    $this->employeeRole = Role::where('tenant_id', $this->admin->tenant_id)
        ->where('code', 'employee')
        ->firstOrFail();
});

/** An active account holding the given role, able to sign in to the mobile app. */
function mobileAccount(int $tenantId, Role $role, string $email = 'mobile.role@contoh.co'): User
{
    $user = User::create([
        'tenant_id' => $tenantId,
        'name' => 'Mobile Role Tester',
        'email' => $email,
        'password' => 'secret12345',
        'status' => 'active',
        'email_verified_at' => now(),
    ]);

    Employee::create([
        'tenant_id' => $tenantId,
        'employee_number' => 'MOB-ROLE-'.$user->id,
        'full_name' => 'Mobile Role Tester',
        'email' => $email,
        'employment_status' => 'permanent',
        'status' => 'active',
        'user_id' => $user->id,
    ]);

    $user->roles()->sync([$role->id]);

    return $user;
}

it('defaults every existing role to mobile access', function (): void {
    expect(Role::pluck('can_access_mobile')->every(fn ($allowed): bool => (bool) $allowed))->toBeTrue();
});

it('exposes the mobile flag on each role card', function (): void {
    actingAs($this->admin)
        ->get('/__access')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('roles.0', fn (Assert $role) => $role->has('canAccessMobile')->etc()));
});

it('creates a web-only role when the mobile switch is off', function (): void {
    actingAs($this->admin)
        ->post('/__access/roles', ['name' => 'Staf Kantor', 'can_access_mobile' => false])
        ->assertRedirect();

    expect(Role::where('code', 'staf-kantor')->firstOrFail()->can_access_mobile)->toBeFalse();
});

it('creates a mobile-capable role by default', function (): void {
    actingAs($this->admin)
        ->post('/__access/roles', ['name' => 'Kurir Lapangan'])
        ->assertRedirect();

    expect(Role::where('code', 'kurir-lapangan')->firstOrFail()->can_access_mobile)->toBeTrue();
});

it('turns mobile access off and back on for a role', function (): void {
    actingAs($this->admin)
        ->post('/__access/roles/'.$this->employeeRole->id.'/mobile', ['enabled' => false])
        ->assertRedirect();

    expect($this->employeeRole->fresh()->can_access_mobile)->toBeFalse();

    actingAs($this->admin)
        ->post('/__access/roles/'.$this->employeeRole->id.'/mobile', ['enabled' => true])
        ->assertRedirect();

    expect($this->employeeRole->fresh()->can_access_mobile)->toBeTrue();
});

it('revokes outstanding phone sessions of the holders it locks out', function (): void {
    $member = mobileAccount($this->admin->tenant_id, $this->employeeRole);
    $before = (int) $member->token_version;

    actingAs($this->admin)
        ->post('/__access/roles/'.$this->employeeRole->id.'/mobile', ['enabled' => false])
        ->assertRedirect();

    expect((int) $member->fresh()->token_version)->toBe($before + 1);
});

it('leaves holders alone when another of their roles still allows the app', function (): void {
    $member = mobileAccount($this->admin->tenant_id, $this->employeeRole);
    $manager = Role::where('tenant_id', $this->admin->tenant_id)->where('code', 'manager')->firstOrFail();
    $member->roles()->syncWithoutDetaching([$manager->id]);

    $before = (int) $member->token_version;

    actingAs($this->admin)
        ->post('/__access/roles/'.$this->employeeRole->id.'/mobile', ['enabled' => false])
        ->assertRedirect();

    expect((int) $member->fresh()->token_version)->toBe($before);
});

it('refuses to change the mobile flag of the actor own role', function (): void {
    $ownRole = $this->admin->roles()->firstOrFail();

    actingAs($this->admin)
        ->post('/__access/roles/'.$ownRole->id.'/mobile', ['enabled' => false])
        ->assertForbidden();

    expect($ownRole->fresh()->can_access_mobile)->toBeTrue();
});

it('refuses to change another tenant role', function (): void {
    $other = Tenant::create(['name' => 'PT Lain', 'company_name' => 'PT Lain', 'slug' => 'lain', 'status' => 'active']);

    $foreign = Role::create([
        'tenant_id' => $other->id,
        'code' => 'foreign-role',
        'name' => 'Foreign Role',
        'is_system' => false,
    ]);

    actingAs($this->admin)
        ->post('/__access/roles/'.$foreign->id.'/mobile', ['enabled' => false])
        ->assertNotFound();
});
