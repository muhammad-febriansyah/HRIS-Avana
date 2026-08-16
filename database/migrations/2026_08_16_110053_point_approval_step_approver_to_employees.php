<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `approval_steps.approver_user_id` has always held an EMPLOYEE id — the wizard
 * offers employees, the store validation is `exists:employees,id` and
 * ApprovalEngine resolves it with `Employee::find()` — but the column was
 * constrained to `users.id`. Two things went wrong because of that: the preview
 * modal labelled the step with `User::find()`, naming a different person when
 * the ids happened to differ, and picking an employee whose id is not also a
 * user id would have been refused by the foreign key.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->realignStrayApprovers();

        Schema::table('approval_steps', function (Blueprint $table): void {
            $table->dropForeign(['approver_user_id']);
            $table->foreign('approver_user_id')
                ->references('id')
                ->on('employees')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('approval_steps', function (Blueprint $table): void {
            $table->dropForeign(['approver_user_id']);
            $table->foreign('approver_user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    /**
     * Rows written before this migration may hold a user id (older seeders did
     * that). Translate those to the matching employee of the same tenant, and
     * blank out anything that resolves to neither — a step with no approver
     * falls back to the requester's manager, which beats blocking the whole
     * migration on one stale row.
     */
    private function realignStrayApprovers(): void
    {
        $rows = DB::table('approval_steps')
            ->whereNotNull('approver_user_id')
            ->get(['id', 'tenant_id', 'approver_user_id']);

        foreach ($rows as $row) {
            $isEmployeeId = DB::table('employees')
                ->where('id', $row->approver_user_id)
                ->where('tenant_id', $row->tenant_id)
                ->exists();

            if ($isEmployeeId) {
                continue;
            }

            $employeeId = DB::table('employees')
                ->where('user_id', $row->approver_user_id)
                ->where('tenant_id', $row->tenant_id)
                ->value('id');

            DB::table('approval_steps')
                ->where('id', $row->id)
                ->update(['approver_user_id' => $employeeId]);
        }
    }
};
