<?php

use App\Concerns\HasPublicId;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Give a URL key to the records that carry money or a named person's private
 * business, so their links cannot be counted through.
 *
 * Employees already had one. These are the rest of the first tier: a contract
 * states a salary, a payslip line states a take-home, a document is somebody's
 * identity card, an applicant is a stranger's CV. The primary keys stay as they
 * are — every foreign key points at them, and none of that belongs in a URL.
 *
 * @see HasPublicId
 */
return new class extends Migration
{
    /**
     * @var array<int, string>
     */
    private const TABLES = [
        'employee_contracts',
        'payroll_run_items',
        'loans',
        'cash_advances',
        'claims',
        'reimbursements',
        'settlements',
        'employee_documents',
        'applicants',
        'performance_reviews',
        'offboarding_cases',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (Schema::hasColumn($table, 'public_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->string('public_id', 26)->nullable()->after('id');
            });

            // Every existing row needs its own value before the unique index
            // goes on, and there is no expression that produces a ULID in SQL.
            DB::table($table)->orderBy('id')->select('id')->chunk(500, function ($rows) use ($table): void {
                foreach ($rows as $row) {
                    DB::table($table)->where('id', $row->id)->update(['public_id' => (string) Str::ulid()]);
                }
            });

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->unique('public_id');
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasColumn($table, 'public_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropUnique(['public_id']);
                $blueprint->dropColumn('public_id');
            });
        }
    }
};
