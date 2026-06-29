<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_workflows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('request_type')->nullable();
            $table->string('approval_mode')->default('sequential');
            $table->boolean('is_active')->default(true);
            $table->json('conditions')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['tenant_id', 'request_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_workflows');
    }
};
