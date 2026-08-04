<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Perubahan Data Pribadi": an employee asking for their own record to be
 * corrected — a new phone number, a moved address, a changed bank account —
 * instead of editing it unannounced. The proposed values sit in `changes` as
 * `{field: {old, new}}` so one request covers a set of fields and the approver
 * reads exactly what will be written before it is.
 *
 * `current_approver_id` holds an EMPLOYEE id, the unit every other request type
 * and the mobile queue compare against.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_change_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->json('changes');
            $table->text('reason')->nullable();
            $table->string('status')->default('pending')->index();
            $table->foreignId('current_approver_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('approver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_change_requests');
    }
};
