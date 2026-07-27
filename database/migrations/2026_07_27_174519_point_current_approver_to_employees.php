<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `current_approver_id` holds an EMPLOYEE id — every writer sets it from
 * `$employee->manager_id`, and the manager inbox matches it against an employee
 * id — but six tables constrained it to `users`. It only ever worked because
 * the two id sequences happened to overlap.
 *
 * The cost was real: an org chart could not put the Direktur Utama above the HR
 * Manager, because the first leave request routed to him would try to store an
 * employee id with no matching user row and fail the insert. That is why the
 * demo seeder left the director as a second, disconnected root — a workaround
 * this removes.
 *
 * `reimbursements` and `settlements` already pointed at `employees`; this makes
 * the rest agree.
 */
return new class extends Migration
{
    /**
     * @var list<string>
     */
    private const TABLES = [
        'leave_requests',
        'overtime_requests',
        'permission_requests',
        'wfh_requests',
        'approval_requests',
        'attendance_corrections',
        'claims',
    ];

    public function up(): void
    {
        // SQLite cannot drop a foreign key by name, and a test database is
        // built from the migrations anyway — those now declare `employees`
        // directly, so there is nothing here to correct.
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        foreach (self::TABLES as $table) {
            if (! Schema::hasColumn($table, 'current_approver_id')) {
                continue;
            }

            // A value that is not a real employee cannot satisfy the new
            // constraint. There are none in practice, but a tenant that ran the
            // old code with mismatched sequences could carry one.
            DB::table($table)
                ->whereNotNull('current_approver_id')
                ->whereNotExists(fn ($query) => $query
                    ->select(DB::raw(1))
                    ->from('employees')
                    ->whereColumn('employees.id', $table.'.current_approver_id'))
                ->update(['current_approver_id' => null]);

            Schema::table($table, function (Blueprint $blueprint) use ($table): void {
                $blueprint->dropForeign($table.'_current_approver_id_foreign');
                $blueprint->foreign('current_approver_id')
                    ->references('id')
                    ->on('employees')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        foreach (self::TABLES as $table) {
            if (! Schema::hasColumn($table, 'current_approver_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table): void {
                $blueprint->dropForeign($table.'_current_approver_id_foreign');
                $blueprint->foreign('current_approver_id')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            });
        }
    }
};
