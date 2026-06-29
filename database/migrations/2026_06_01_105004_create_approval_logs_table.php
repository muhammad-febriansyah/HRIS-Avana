<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('approval_request_id')->constrained('approval_requests')->cascadeOnDelete();
            $table->foreignId('approver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->unsignedInteger('step_order')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'approval_request_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_logs');
    }
};
