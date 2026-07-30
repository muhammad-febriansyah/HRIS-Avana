<?php

use App\Models\Feature;
use App\Models\Meeting;
use App\Models\MobileMenuItem;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RoleMenuVisibility;
use App\Models\Tenant;
use App\Models\User;
use App\Support\AvanaNav;
use App\Support\MobileMenu;
use Database\Seeders\AvanaDemoSeeder;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->admin->tenant_id);
});

it('lists Rapat & Transkrip in the Hak Akses matrix with its full action set', function (): void {
    actingAs($this->admin)
        ->get(route('avana.hak-akses'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('modules', function ($modules): bool {
                $row = collect($modules)->firstWhere('key', 'rapat');

                return $row !== null
                    && $row['label'] === 'Rapat & Transkrip'
                    && $row['actionable'] === true
                    && $row['featureEnabled'] === true
                    && $row['feature'] === 'meeting_ai'
                    && in_array('meeting', $row['permissionModules'], true);
            })
            // The six standard actions are offered, same as any other menu.
            ->has('actions', 6));
});

it('offers Rapat as a pickable menu on the Buat Peran screen', function (): void {
    actingAs($this->admin)
        ->get(route('avana.hak-akses.roles.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('modules', fn ($modules): bool => collect($modules)->firstWhere('key', 'rapat') !== null)
            // The phone tile is pickable on the same screen.
            ->where('mobileMenu', fn ($tiles): bool => collect($tiles)->firstWhere('key', 'rapat') !== null));
});

it('creates a tenant role granted only Rapat and lets it reach the screen', function (): void {
    actingAs($this->admin)
        ->post(route('avana.hak-akses.roles.store'), [
            'name' => 'Notulen Rapat',
            'description' => 'Hanya boleh membaca rapat',
            'menus' => [
                ['key' => 'rapat', 'actions' => ['view', 'update']],
            ],
        ])
        ->assertRedirect();

    $role = Role::where('tenant_id', $this->tenant->id)->where('name', 'Notulen Rapat')->firstOrFail();

    expect($role->permissions->pluck('code'))
        ->toContain('meeting.view', 'meeting.update')
        ->not->toContain('meeting.archive');

    // Somebody holding only that role reaches the screen and sees the menu.
    $notulen = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $notulen->roles()->attach($role);

    actingAs($notulen->fresh())->get(route('avana.rapat'))->assertOk();

    $nav = collect(AvanaNav::forUser($notulen->fresh()))
        ->flatMap(fn (array $group): array => $group['items'])
        ->pluck('id');

    expect($nav)->toContain('rapat');
});

it('withholds the delete button from a role granted only view', function (): void {
    actingAs($this->admin)
        ->post(route('avana.hak-akses.roles.store'), [
            'name' => 'Pembaca Rapat',
            'menus' => [['key' => 'rapat', 'actions' => ['view']]],
        ])
        ->assertRedirect();

    $role = Role::where('tenant_id', $this->tenant->id)->where('name', 'Pembaca Rapat')->firstOrFail();
    $reader = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $reader->roles()->attach($role);

    $meeting = Meeting::create([
        'tenant_id' => $this->tenant->id, 'created_by' => $reader->id,
        'title' => 'Rapat', 'status' => Meeting::STATUS_READY, 'started_at' => now(),
    ]);

    // The page tells the UI what to hide...
    actingAs($reader->fresh())
        ->get(route('avana.rapat.show', $meeting->id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('can.update', false)
            ->where('can.archive', false));

    // ...and the server refuses the action regardless of what the UI shows.
    actingAs($reader->fresh())
        ->delete(route('avana.rapat.destroy', $meeting->id))
        ->assertForbidden();
});

it('hides the menu entirely when the company switches the feature off', function (): void {
    $feature = Feature::where('code', 'meeting_ai')->firstOrFail();
    $this->tenant->features()->where('feature_id', $feature->id)->update(['is_enabled' => false]);

    $nav = collect(AvanaNav::forUser($this->admin->fresh()))
        ->flatMap(fn (array $group): array => $group['items'])
        ->pluck('id');

    expect($nav)->not->toContain('rapat');
});

it('ships the AI Recorder tile per tenant so a role can hide it on the phone', function (): void {
    // Tiles are seeded the first time anyone asks for them, which is what every
    // real caller does before reading one.
    MobileMenu::forTenant($this->tenant->id);

    $tile = MobileMenuItem::where('tenant_id', $this->tenant->id)->where('key', 'rapat')->firstOrFail();

    expect($tile->label)->toBe('AI Recorder')
        ->and($tile->route)->toBe('/meeting')
        ->and($tile->is_active)->toBeTrue();

    // Created without the tile in its Menu Cepat: the role hides it on the phone.
    $keep = MobileMenuItem::where('tenant_id', $this->tenant->id)
        ->where('key', '!=', 'rapat')
        ->pluck('key')
        ->all();

    actingAs($this->admin)
        ->post(route('avana.hak-akses.roles.store'), [
            'name' => 'Tanpa Recorder',
            'menus' => [['key' => 'rapat', 'actions' => ['view']]],
            'mobile_menus' => $keep,
        ])
        ->assertRedirect();

    $role = Role::where('tenant_id', $this->tenant->id)->where('name', 'Tanpa Recorder')->firstOrFail();

    expect(
        RoleMenuVisibility::where('role_id', $role->id)->where('is_visible', false)->pluck('menu_key')
    )->toContain($tile->visibilityKey());
});

it('keeps another tenant out of the meeting screens entirely', function (): void {
    $otherTenant = Tenant::create([
        'name' => 'PT Lain', 'company_name' => 'PT Lain', 'slug' => 'lain-matrix', 'status' => 'active',
    ]);

    $meeting = Meeting::create([
        'tenant_id' => $this->tenant->id, 'created_by' => $this->admin->id,
        'title' => 'Rapat Internal', 'status' => Meeting::STATUS_READY, 'started_at' => now(),
    ]);

    $role = Role::create(['tenant_id' => $otherTenant->id, 'code' => 'hr_lain_matrix', 'name' => 'HR Lain']);
    $role->permissions()->attach(
        Permission::whereIn('code', ['meeting.view', 'meeting.archive'])->pluck('id'),
    );

    $outsider = User::factory()->create(['tenant_id' => $otherTenant->id]);
    $outsider->roles()->attach($role);

    // Holds meeting.view, but in a company that was never given the feature —
    // so the shared Avana gate turns them away before the controller is
    // reached. (The controller's own tenant guard is covered separately, in
    // MeetingInsightTest.) Either way nothing of this tenant's is exposed.
    actingAs($outsider->fresh())->get(route('avana.rapat'))->assertForbidden();

    actingAs($outsider->fresh())
        ->get(route('avana.rapat.show', $meeting->id))
        ->assertForbidden();

    expect(
        Meeting::query()->forTenant($otherTenant->id)->pluck('id')
    )->not->toContain($meeting->id);
});
