<?php

use App\Models\AuditLog;
use App\Models\MenuItem;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\AvanaNav;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->superAdmin = User::where('email', 'superadmin@avanahr.id')->firstOrFail();
    $this->admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->admin->tenant_id);
});

/**
 * Read a streamed download into a string.
 */
function streamedBody(TestResponse $response): string
{
    ob_start();
    $response->baseResponse->sendContent();

    return (string) ob_get_clean();
}

it('lists the tables and their row counts for a super admin', function (): void {
    actingAs($this->superAdmin)
        ->get(route('avana.backup'))
        ->assertOk()
        ->assertInertia(function (Assert $page): void {
            $props = $page->component('avana/backup/index')->toArray()['props'];

            expect($props['totalTables'])->toBeGreaterThan(0)
                ->and($props['totalRows'])->toBeGreaterThan(0)
                ->and($props['error'])->toBeNull()
                ->and(collect($props['tables'])->pluck('name'))->toContain('users');
        });
});

it('refuses the screen to a tenant admin', function (): void {
    actingAs($this->admin)->get(route('avana.backup'))->assertForbidden();
});

it('refuses the download to a tenant admin', function (): void {
    actingAs($this->admin)->get(route('avana.backup.download'))->assertForbidden();
});

it('refuses both to a plain employee', function (): void {
    $employeeRole = Role::where('tenant_id', $this->tenant->id)->where('code', 'employee')->firstOrFail();
    $staff = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $staff->roles()->sync([$employeeRole->id]);

    actingAs($staff)->get(route('avana.backup'))->assertForbidden();
    actingAs($staff)->get(route('avana.backup.download'))->assertForbidden();
});

it('streams a dump carrying structure and rows', function (): void {
    $response = actingAs($this->superAdmin)
        ->get(route('avana.backup.download', ['tables' => ['users'], 'compress' => 0]))
        ->assertOk();

    $body = streamedBody($response);

    expect($body)->toContain('AvanaHR database export')
        ->and($body)->toContain('DROP TABLE IF EXISTS')
        ->and($body)->toContain('CREATE TABLE')
        ->and($body)->toContain('INSERT INTO')
        ->and($body)->toContain($this->superAdmin->email);
});

it('leaves the rows out when only the structure is asked for', function (): void {
    $response = actingAs($this->superAdmin)
        ->get(route('avana.backup.download', ['tables' => ['users'], 'with_data' => 0, 'compress' => 0]))
        ->assertOk();

    $body = streamedBody($response);

    expect($body)->toContain('CREATE TABLE')
        ->and($body)->not->toContain('INSERT INTO');
});

it('ignores a table name that is not in the database', function (): void {
    $response = actingAs($this->superAdmin)
        ->get(route('avana.backup.download', ['tables' => ['users', 'tabel_karangan'], 'compress' => 0]))
        ->assertOk();

    $body = streamedBody($response);

    expect($body)->toContain('users')
        ->and($body)->not->toContain('tabel_karangan');
});

it('refuses when every requested table is unknown', function (): void {
    actingAs($this->superAdmin)
        ->get(route('avana.backup.download', ['tables' => ['tabel_karangan']]))
        ->assertStatus(422);
});

it('writes each download to the audit trail', function (): void {
    $before = AuditLog::where('action', 'export')->count();

    $response = actingAs($this->superAdmin)
        ->get(route('avana.backup.download', ['tables' => ['users'], 'compress' => 0]))
        ->assertOk();

    streamedBody($response);

    $log = AuditLog::where('action', 'export')->latest('id')->first();

    expect(AuditLog::where('action', 'export')->count())->toBe($before + 1)
        ->and($log->user_id)->toBe($this->superAdmin->id)
        ->and($log->auditable_type)->toBe('database');
});

it('gzips the dump when asked', function (): void {
    $response = actingAs($this->superAdmin)
        ->get(route('avana.backup.download', ['tables' => ['users'], 'compress' => 1]))
        ->assertOk();

    $body = streamedBody($response);

    expect(gzdecode($body))->toContain('AvanaHR database export');
});

it('carries a real menu row rather than a hardcoded link', function (): void {
    $row = MenuItem::whereNull('tenant_id')->where('key', 'backup')->first();

    expect($row)->not->toBeNull()
        ->and($row->href)->toBe('/avana/backup')
        ->and((bool) $row->super_admin_only)->toBeTrue()
        ->and((bool) $row->is_active)->toBeTrue()
        // Platform menu only: a tenant copy would put it in a tenant sidebar.
        ->and(MenuItem::whereNotNull('tenant_id')->where('key', 'backup')->count())->toBe(0);
});

it('drops out of the sidebar when the menu row is switched off', function (): void {
    MenuItem::whereNull('tenant_id')->where('key', 'backup')->update(['is_active' => false]);

    $groups = AvanaNav::forUser($this->superAdmin);

    $keys = collect($groups)
        ->flatMap(fn (array $group): array => $group['items'] ?? [])
        ->pluck('id');

    expect($keys)->not->toContain('backup');
});

it('keeps the platform menu out of a tenant admin\'s builder', function (): void {
    actingAs($this->admin)->get(route('avana.menu-builder'))->assertForbidden();
});
