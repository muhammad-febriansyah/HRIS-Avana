<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Employees the company does not expect to clock in — an owner or a
     * director. They are never reminded, never shown as "belum absen" and
     * never marked Alpa, so their pay is never docked for a day off the app.
     */
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->boolean('attendance_exempt')->default(false)->after('attendance_scope');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('attendance_exempt');
        });
    }
};
