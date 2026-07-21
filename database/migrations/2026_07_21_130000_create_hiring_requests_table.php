<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hiring_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('request_number')->nullable();
            $table->foreignId('requester_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('position_title');
            $table->unsignedInteger('vacancy')->default(1);
            $table->text('job_description')->nullable();
            $table->text('qualification')->nullable();
            $table->string('employment_type')->default('tetap'); // tetap|kontrak|magang|harian
            $table->date('target_join_date')->nullable();
            $table->string('status')->default('open'); // open|in_process|closed
            $table->timestamps();
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hiring_requests');
    }
};
