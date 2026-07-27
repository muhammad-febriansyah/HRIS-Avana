<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Employee reports on a social post.
 *
 * Posts publish immediately, so moderation is after the fact — this is how the
 * wall tells HR something needs looking at instead of relying on HR reading
 * every post. One report per employee per post; HR resolves them in bulk when
 * they act on the post.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_post_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('social_post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('reason')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->unique(['social_post_id', 'employee_id']);
            $table->index(['tenant_id', 'resolved_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_post_reports');
    }
};
