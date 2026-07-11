<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Attendance anti-spoofing hardening:
     *  - attendance_policies: per-tenant verification rules (face enrollment,
     *    liveness challenge, and block-vs-flag enforcement for face/integrity).
     *  - attendance_challenges: single-use nonces that make each clock payload
     *    fresh, closing the replay hole on face embeddings.
     *  - attendances: device + integrity verdict + risk flags recorded per punch
     *    so HR can review anything that was flagged instead of blocked.
     */
    public function up(): void
    {
        Schema::create('attendance_policies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained('tenants')->cascadeOnDelete();
            $table->boolean('require_face_enrollment')->default(false);
            $table->boolean('require_liveness_challenge')->default(false);
            // 'block' rejects the clock action; 'flag' records the punch + risk.
            // Both default to 'block' (strict, backward-compatible); a tenant may
            // relax either to 'flag' to record risk without stopping the punch.
            $table->string('face_enforcement')->default('block');
            $table->string('integrity_enforcement')->default('block');
            $table->boolean('block_mock_location')->default(true);
            $table->boolean('block_rooted')->default(true);
            $table->boolean('block_emulator')->default(true);
            $table->timestamps();
        });

        Schema::create('attendance_challenges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('nonce', 64)->unique();
            $table->string('purpose')->default('clock');
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'used_at']);
        });

        Schema::table('attendances', function (Blueprint $table): void {
            $table->string('device_id')->nullable()->after('face_confidence');
            $table->string('integrity_verdict')->nullable()->after('device_id');
            $table->json('risk_flags')->nullable()->after('integrity_verdict');
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table): void {
            $table->dropColumn(['device_id', 'integrity_verdict', 'risk_flags']);
        });
        Schema::dropIfExists('attendance_challenges');
        Schema::dropIfExists('attendance_policies');
    }
};
