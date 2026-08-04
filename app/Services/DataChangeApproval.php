<?php

namespace App\Services;

use App\Models\DataChangeRequest;
use App\Models\Employee;
use App\Support\DataChangeFields;
use Illuminate\Support\Facades\DB;

/**
 * Finalizes an approved "Perubahan Data Pribadi" request: every proposed value
 * is written onto the employee's own record, and the request keeps the copy of
 * what it changed so the audit trail survives a later edit.
 */
class DataChangeApproval
{
    /**
     * Apply the request's changes and mark it approved. Shared by the approval
     * screen and the workflow engine's final step, so both land identically.
     */
    public static function finalize(DataChangeRequest $request, ?int $approverUserId = null): void
    {
        DB::transaction(function () use ($request, $approverUserId): void {
            $employee = Employee::forTenant((int) $request->tenant_id)->find($request->employee_id);

            if ($employee !== null) {
                foreach ((array) $request->changes as $field => $change) {
                    if (! is_string($field) || ! in_array($field, DataChangeFields::keys(), true)) {
                        continue;
                    }

                    $value = is_array($change) ? ($change['new'] ?? null) : null;

                    DataChangeFields::apply($employee, $field, $value === null ? null : (string) $value);
                }
            }

            $request->update([
                'status' => 'approved',
                'approver_id' => $approverUserId,
                'decided_at' => now(),
            ]);
        });
    }
}
