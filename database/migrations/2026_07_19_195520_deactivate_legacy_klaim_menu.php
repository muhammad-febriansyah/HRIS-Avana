<?php

use App\Models\MenuItem;
use Illuminate\Database\Migrations\Migration;

/**
 * "Klaim & Reimbursement" and "Reimbursement" sat side by side in the sidebar
 * doing the same job. The newer Finance → Reimbursement carries the numbering,
 * status guards, rejection reason and payment method the older screen lacks, so
 * the older one is switched off.
 *
 * The row is deactivated rather than deleted: an absent menu row would leave
 * /avana/klaim ungated by the access middleware, and a tenant that still wants
 * the old screen can switch it back on from the Menu Builder.
 */
return new class extends Migration
{
    public function up(): void
    {
        MenuItem::query()
            ->where('href', '/avana/klaim')
            ->update(['is_active' => false]);
    }

    public function down(): void
    {
        MenuItem::query()
            ->where('href', '/avana/klaim')
            ->update(['is_active' => true]);
    }
};
