<?php

use App\Models\MenuItem;
use App\Models\User;
use App\Support\AvanaNav;
use Database\Seeders\AvanaDemoSeeder;

beforeEach(function (): void {
    $this->seed(AvanaDemoSeeder::class);
});

/**
 * Flatten a nav tree down to the hrefs it offers, children included.
 *
 * @param  array<int, array<string, mixed>>  $groups
 * @return array<int, string>
 */
function navHrefs(array $groups): array
{
    $hrefs = [];

    foreach ($groups as $group) {
        foreach ($group['items'] as $item) {
            foreach ($item['children'] ?? [$item] as $leaf) {
                if (isset($leaf['href'])) {
                    $hrefs[] = $leaf['href'];
                }
            }
        }
    }

    return $hrefs;
}

it('offers account security to a plain employee', function (): void {
    $karyawan = User::where('email', 'budi.s@nusantara.co.id')->firstOrFail();

    expect(navHrefs(AvanaNav::forUser($karyawan)))->toContain('/settings/security');
});

it('offers account security to an HR admin', function (): void {
    $admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();

    expect(navHrefs(AvanaNav::forUser($admin)))->toContain('/settings/security');
});

it('offers account security to a super admin on the platform menu', function (): void {
    $superAdmin = User::where('email', 'superadmin@avanahr.id')->firstOrFail();

    expect(navHrefs(AvanaNav::forUser($superAdmin, platform: true)))->toContain('/settings/security');
});

it('carries no feature or permission gate, so no tenant toggle can hide it', function (): void {
    $leaf = collect(AvanaNav::allLeaves())->firstWhere('href', '/settings/security');

    expect($leaf)->not->toBeNull()
        ->and($leaf['feature'])->toBeNull()
        ->and($leaf['modules'])->toBe([])
        ->and($leaf['adminOnly'])->toBeFalse();
});

it('backfills the menu into a tenant that was seeded before it existed', function (): void {
    $tenantId = (int) User::where('email', 'rina.a@nusantara.co.id')->firstOrFail()->tenant_id;

    MenuItem::forTenant($tenantId)->where('key', 'akun-keamanan')->delete();

    expect(MenuItem::forTenant($tenantId)->where('key', 'akun-keamanan')->exists())->toBeFalse();

    $this->artisan('avana:sync-menu-defaults')->assertSuccessful();

    expect(MenuItem::forTenant($tenantId)->where('key', 'akun-keamanan')->exists())->toBeTrue();
});

it('leaves a customised menu row alone when it backfills', function (): void {
    $tenantId = (int) User::where('email', 'rina.a@nusantara.co.id')->firstOrFail()->tenant_id;

    MenuItem::forTenant($tenantId)
        ->where('key', 'akun-keamanan')
        ->update(['label' => 'Keamanan', 'is_active' => false]);

    $this->artisan('avana:sync-menu-defaults')->assertSuccessful();

    $row = MenuItem::forTenant($tenantId)->where('key', 'akun-keamanan')->sole();

    expect($row->label)->toBe('Keamanan')
        ->and($row->is_active)->toBeFalse();
});
