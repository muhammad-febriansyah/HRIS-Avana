<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('benefits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->string('type')->default('allowance'); // insurance|allowance|facility
            $table->decimal('value', 15, 2)->default(0);
            $table->text('description')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
            $table->index(['tenant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('benefits');
    }
};
