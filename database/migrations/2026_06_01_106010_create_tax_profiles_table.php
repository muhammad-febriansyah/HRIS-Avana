<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('nik')->nullable();
            $table->string('npwp')->nullable();
            $table->string('ptkp_status')->nullable();
            $table->string('tax_method')->default('gross');
            $table->string('employment_tax_type')->default('tetap');
            $table->string('tax_category')->nullable();
            $table->date('effective_start_date')->nullable();
            $table->date('effective_end_date')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['tenant_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_profiles');
    }
};
