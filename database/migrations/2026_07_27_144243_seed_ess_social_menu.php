<?php

use App\Models\Tenant;
use App\Support\AvanaNav;
use Illuminate\Database\Migrations\Migration;

/**
 * Publish the "Sosmed" self-service menu to existing tenants.
 *
 * Once a tenant has `menu_items` rows the sidebar is served from those (the
 * Menu Builder), not from the AvanaNav definition — so adding the leaf in code
 * alone leaves the page reachable only by typing its URL. Idempotent: seeding
 * skips leaf keys the tenant already has.
 */
return new class extends Migration
{
    public function up(): void
    {
        Tenant::query()->pluck('id')->each(
            fn (int $tenantId) => AvanaNav::seedDefaultsFor($tenantId),
        );
    }

    public function down(): void
    {
        // Non-destructive: leave the menu row in place.
    }
};
