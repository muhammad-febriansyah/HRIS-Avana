<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('ticket_no');
            $table->foreignId('requester_id')->constrained('employees')->cascadeOnDelete();
            $table->string('category')->default('other'); // it|hr|payroll|facility|other
            $table->string('subject');
            $table->text('description');
            $table->string('priority')->default('medium'); // low|medium|high|urgent
            $table->string('status')->default('open'); // open|in_progress|resolved|closed
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id']);
            $table->unique(['tenant_id', 'ticket_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
