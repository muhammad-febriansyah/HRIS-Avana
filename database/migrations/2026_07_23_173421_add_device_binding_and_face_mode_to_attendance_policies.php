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
        Schema::table('attendance_policies', function (Blueprint $table): void {
            // "1 perangkat 1 akun" — when false, mobile login skips device binding.
            $table->boolean('device_binding_enabled')->default(true)->after('attendance_scope');
            // recognition = 1:1 face match; detection = live-face only (no match);
            // off = no face check at all.
            $table->string('face_mode', 20)->default('recognition')->after('require_face_enrollment');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance_policies', function (Blueprint $table): void {
            $table->dropColumn(['device_binding_enabled', 'face_mode']);
        });
    }
};
