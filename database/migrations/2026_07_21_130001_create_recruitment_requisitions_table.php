<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recruitment_requisitions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('requisition_number')->nullable();
            $table->foreignId('hiring_request_id')->nullable()->constrained('hiring_requests')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('job_posting_id')->nullable()->constrained('job_postings')->nullOnDelete();
            $table->string('position_title');
            $table->unsignedInteger('vacancy')->default(1);
            $table->text('qualification')->nullable();
            $table->text('job_description')->nullable();
            $table->string('employment_type')->default('tetap');
            $table->string('location')->nullable();
            $table->string('status')->default('draft'); // draft|published|closed
            $table->date('publish_date')->nullable();
            $table->date('closing_date')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recruitment_requisitions');
    }
};
