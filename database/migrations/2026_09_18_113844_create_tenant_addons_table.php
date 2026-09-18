<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tenant_addons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_group_id')->constrained('tenant_groups')->cascadeOnDelete();
            $table->string('code');
            $table->unsignedInteger('company_limit')->default(1);
            $table->string('status')->default('active')->index();
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();
            $table->unique(['tenant_group_id', 'code']);
            $table->index(['tenant_group_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_addons');
    }
};
