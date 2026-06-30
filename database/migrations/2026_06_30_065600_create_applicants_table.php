<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applicants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_posting_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('source')->nullable();
            $table->string('stage')->default('applied'); // applied|screening|interview|offer|hired|rejected
            $table->date('applied_date');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'job_posting_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applicants');
    }
};
