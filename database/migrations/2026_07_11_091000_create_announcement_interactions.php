<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Interactive announcements: per-employee read confirmations and threaded
     * comments, so the mobile app can show who has seen an announcement and let
     * employees discuss it.
     */
    public function up(): void
    {
        Schema::create('announcement_reads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('announcement_id')->constrained('announcements')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->timestamp('read_at');
            $table->timestamps();

            $table->unique(['announcement_id', 'employee_id']);
        });

        Schema::create('announcement_comments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('announcement_id')->constrained('announcements')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index(['announcement_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement_comments');
        Schema::dropIfExists('announcement_reads');
    }
};
