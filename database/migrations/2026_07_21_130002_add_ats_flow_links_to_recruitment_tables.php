<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_postings', function (Blueprint $table): void {
            $table->foreignId('recruitment_requisition_id')->nullable()->after('department_id')
                ->constrained('recruitment_requisitions')->nullOnDelete();
        });

        Schema::table('applicants', function (Blueprint $table): void {
            $table->string('tracking_number')->nullable()->after('job_posting_id');
            $table->string('interview_result')->nullable()->after('interview_status'); // passed|failed
            $table->foreignId('employee_id')->nullable()->after('ai_recommendation')
                ->constrained('employees')->nullOnDelete();
            $table->timestamp('onboarded_at')->nullable()->after('employee_id');
        });
    }

    public function down(): void
    {
        Schema::table('job_postings', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('recruitment_requisition_id');
        });

        Schema::table('applicants', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('employee_id');
            $table->dropColumn(['tracking_number', 'interview_result', 'onboarded_at']);
        });
    }
};
