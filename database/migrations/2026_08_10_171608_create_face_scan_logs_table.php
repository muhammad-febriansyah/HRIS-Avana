<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('face_scan_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            // enroll | verify | clock — which flow produced the entry.
            $table->string('context', 20)->index();
            // Enrollment step the entry belongs to (0 = neutral, 1 = smile).
            $table->unsignedTinyInteger('step')->nullable();
            // ok | fail | blocked
            $table->string('outcome', 20);
            // Machine-readable cause, e.g. no_face, multi_face, not_frontal.
            $table->string('reason', 40)->index();
            // Human-readable hint shown to the employee, when there was one.
            $table->string('message')->nullable();
            // Free-form diagnostics: face count, head angles, eye/smile
            // probabilities, frame size, match score.
            $table->json('metrics')->nullable();
            $table->string('platform', 20)->nullable()->index();
            $table->string('os_version', 60)->nullable();
            $table->string('device_model', 120)->nullable();
            $table->string('app_version', 30)->nullable();
            $table->string('device_id', 191)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'created_at']);
            $table->index(['tenant_id', 'employee_id', 'created_at']);
            $table->index(['tenant_id', 'outcome', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('face_scan_logs');
    }
};
