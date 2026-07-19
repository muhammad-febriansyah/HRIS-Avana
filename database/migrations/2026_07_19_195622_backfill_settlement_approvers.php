<?php

use App\Models\Settlement;
use Illuminate\Database\Migrations\Migration;

/**
 * Settlements submitted before approvals were routed carry no approver, so they
 * sit in nobody's queue — invisible on the manager's screen and in the mobile
 * approval list, reachable only by HR. Point each one at its employee's line
 * manager, the way a fresh submission now does.
 *
 * Only pending ones are touched: a settlement already approved, paid or sent
 * back has left the manager's desk, and writing an approver onto it would put
 * it back in a queue it has no business being in.
 */
return new class extends Migration
{
    public function up(): void
    {
        Settlement::query()
            ->where('status', Settlement::STATUS_SUBMITTED)
            ->whereNull('current_approver_id')
            ->with('employee:id,manager_id')
            ->get()
            ->each(function (Settlement $settlement): void {
                $managerId = $settlement->employee?->manager_id;

                if ($managerId === null) {
                    return;
                }

                $settlement->update(['current_approver_id' => $managerId]);
            });
    }

    public function down(): void
    {
        // The column itself is dropped by its own migration; nothing to undo
        // here that would not also erase approvers set after this ran.
    }
};
