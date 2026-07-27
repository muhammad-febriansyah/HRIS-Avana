<?php

use App\Models\Tenant;
use App\Support\AvanaNav;
use Illuminate\Database\Migrations\Migration;

/**
 * Publish the "SOP Perusahaan" self-service menu to existing tenants, so an
 * employee can read the company's public SOPs directly instead of having to
 * ask the AI assistant to quote them. Idempotent.
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
