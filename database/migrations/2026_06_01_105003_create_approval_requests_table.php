<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('approvable_type');
            $table->unsignedBigInteger('approvable_id');
            $table->foreignId('requester_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('current_approver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approval_workflow_id')->nullable()->constrained('approval_workflows')->nullOnDelete();
            $table->unsignedInteger('current_step')->default(1);
            $table->string('status')->default('pending')->index();
            $table->timestamps();
            $table->index(['approvable_type', 'approvable_id']);
            $table->index(['tenant_id', 'approvable_type', 'approvable_id']);
            $table->index(['tenant_id', 'requester_id', 'status']);
            $table->index(['tenant_id', 'current_approver_id', 'status']);
            $table->index(['tenant_id', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_requests');
    }
};
