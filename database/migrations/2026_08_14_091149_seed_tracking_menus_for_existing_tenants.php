<?php

use App\Models\Tenant;
use App\Support\AvanaNav;
use Illuminate\Database\Migrations\Migration;

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
        // Non-destructive: preserve tenant menu customisations.
    }
};
