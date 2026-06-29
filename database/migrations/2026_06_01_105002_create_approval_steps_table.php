<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('approval_workflow_id')->constrained('approval_workflows')->cascadeOnDelete();
            $table->unsignedInteger('step_order')->default(1);
            $table->string('approver_type')->nullable();
            $table->foreignId('approver_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('approver_role_id')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'approval_workflow_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_steps');
    }
};
