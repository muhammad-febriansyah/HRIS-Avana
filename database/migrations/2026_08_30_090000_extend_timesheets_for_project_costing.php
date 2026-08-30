<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Turns the timesheet module from a log into a project-costing tool: projects
 * gain a client, a period, a budget and default rates; entries gain an approval
 * state and the billable/cost figures the profitability report reads; and
 * `project_members` records who may log against a project, with the per-person
 * rate overrides.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->foreignId('manager_id')->nullable()->after('tenant_id')->constrained('employees')->nullOnDelete();
            $table->string('client_name')->nullable()->after('code');
            $table->text('description')->nullable()->after('client_name');
            $table->date('start_date')->nullable()->after('description');
            $table->date('end_date')->nullable()->after('start_date');
            $table->decimal('budget_amount', 15, 2)->nullable()->after('end_date');
            $table->decimal('budget_hours', 8, 2)->nullable()->after('budget_amount');
            $table->boolean('is_billable')->default(true)->after('budget_hours');
            $table->decimal('default_bill_rate', 15, 2)->nullable()->after('is_billable');
            $table->decimal('default_cost_rate', 15, 2)->nullable()->after('default_bill_rate');
        });

        Schema::table('timesheets', function (Blueprint $table): void {
            $table->foreignId('branch_id')->nullable()->after('employee_id')->constrained()->nullOnDelete();
            $table->string('status')->default('pending')->after('notes');
            // An EMPLOYEE id, matching every other approvable model in the app
            // (the mobile MSS queue compares it against the manager's employee).
            $table->unsignedBigInteger('current_approver_id')->nullable()->after('status');
            // A USER id, matching the duty-travel/reimbursement screens.
            $table->unsignedBigInteger('approved_by')->nullable()->after('current_approver_id');
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->text('rejection_reason')->nullable()->after('approved_at');
            $table->boolean('is_billable')->default(true)->after('rejection_reason');
            $table->decimal('bill_rate', 15, 2)->nullable()->after('is_billable');
            $table->decimal('cost_rate', 15, 2)->nullable()->after('bill_rate');
            $table->decimal('bill_amount', 15, 2)->nullable()->after('cost_rate');
            $table->decimal('cost_amount', 15, 2)->nullable()->after('bill_amount');
            $table->string('source')->default('web')->after('cost_amount');
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'employee_id', 'date']);
        });

        // Entries filed before approval existed were typed by an HR desk that
        // had already accepted them; leaving them pending would park historical
        // hours in an approval queue nobody asked for.
        DB::table('timesheets')->update(['status' => 'approved', 'approved_at' => now()]);

        Schema::create('project_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->decimal('bill_rate', 15, 2)->nullable();
            $table->decimal('cost_rate', 15, 2)->nullable();
            $table->timestamps();
            $table->unique(['project_id', 'employee_id']);
            $table->index(['tenant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_members');

        Schema::table('timesheets', function (Blueprint $table): void {
            $table->dropForeign(['branch_id']);
            $table->dropIndex(['tenant_id', 'status']);
            $table->dropIndex(['tenant_id', 'employee_id', 'date']);
            $table->dropColumn([
                'branch_id', 'status', 'current_approver_id', 'approved_by', 'approved_at',
                'rejection_reason', 'is_billable', 'bill_rate', 'cost_rate',
                'bill_amount', 'cost_amount', 'source',
            ]);
        });

        Schema::table('projects', function (Blueprint $table): void {
            $table->dropForeign(['manager_id']);
            $table->dropColumn([
                'manager_id', 'client_name', 'description', 'start_date', 'end_date',
                'budget_amount', 'budget_hours', 'is_billable',
                'default_bill_rate', 'default_cost_rate',
            ]);
        });
    }
};
