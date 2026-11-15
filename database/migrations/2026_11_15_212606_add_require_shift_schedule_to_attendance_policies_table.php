<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A tenant that rosters every working day can require a shift before a
     * clock-in: a day with no roster row is then a day off — no clock-in, no
     * "belum absen", no Alpa. Off by default, so an office tenant that never
     * uses the roster keeps its plain Monday-to-Friday week.
     */
    public function up(): void
    {
        Schema::table('attendance_policies', function (Blueprint $table) {
            $table->boolean('require_shift_schedule')->default(false)->after('block_emulator');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_policies', function (Blueprint $table) {
            $table->dropColumn('require_shift_schedule');
        });
    }
};
