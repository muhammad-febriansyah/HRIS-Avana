<?php

use App\Models\Employee;
use Illuminate\Database\Migrations\Migration;

/**
 * Reconnect the org chart.
 *
 * Every employee without a manager renders as its own root, so a tenant with a
 * director, an HR manager and one half-filled record showed three disconnected
 * islands and read as a broken chart. Now that `current_approver_id` points at
 * `employees`, they can all hang off the tenant's top approver.
 *
 * Skipped for a tenant with no top approver, or more than one — there is no
 * single obvious head to attach them to, and guessing would rewrite a hierarchy
 * somebody may have set deliberately.
 */
return new class extends Migration
{
    public function up(): void
    {
        $byTenant = Employee::query()
            ->where('is_top_approver', true)
            ->get(['id', 'tenant_id'])
            ->groupBy('tenant_id');

        foreach ($byTenant as $tenantId => $candidates) {
            if ($candidates->count() !== 1) {
                continue;
            }

            $top = $candidates->first();

            Employee::query()
                ->where('tenant_id', $tenantId)
                ->whereNull('manager_id')
                ->whereKeyNot($top->id)
                ->update(['manager_id' => $top->id]);
        }
    }

    public function down(): void
    {
        // Irreversible by design: which employees were deliberately left
        // without a manager is recorded nowhere, so undoing this would guess.
    }
};
