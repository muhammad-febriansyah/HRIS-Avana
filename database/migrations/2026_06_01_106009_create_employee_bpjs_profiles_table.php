<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_bpjs_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('bpjs_kesehatan_number')->nullable();
            $table->string('bpjs_ketenagakerjaan_number')->nullable();
            $table->decimal('registered_wage', 15, 2)->default(0);
            $table->boolean('jht_enabled')->default(true);
            $table->boolean('jkk_enabled')->default(true);
            $table->boolean('jkm_enabled')->default(true);
            $table->boolean('jp_enabled')->default(true);
            $table->boolean('kesehatan_enabled')->default(true);
            $table->date('effective_start_date')->nullable();
            $table->date('effective_end_date')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_bpjs_profiles');
    }
};
